<?php
/*
 * © 2025 TresDeliquentes. All rights reserved.
 * LibreLessons jest licencjonowane na zasadach TEUL – do użytku edukacyjnego.
 * Zakazana jest dystrybucja, publikacja i komercyjne wykorzystanie bez zgody autora.
 * Korzystając z kodu, akceptujesz warunki licencji (LICENSE.md).
 */

// Custom error handler - zwraca błędy jako JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $errstr,
        'error_details' => [
            'file' => $errfile,
            'line' => $errline,
            'type' => $errno
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Custom exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Uncaught Exception: ' . $exception->getMessage(),
        'error_details' => [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/edytor_planu.php';

// Sprawdź uprawnienia dyrektora
sprawdz_uprawnienia('dyrektor');

header('Content-Type: application/json; charset=utf-8');

$uzytkownik_id = $_SESSION['user_id'];
$edytor = new EdytorPlanu($conn);

// Parsuj dane wejściowe
$input = null;
$raw_input = file_get_contents('php://input');

if (!empty($raw_input)) {
    $input = json_decode($raw_input, true);
}

// Pobierz akcję z parametrów (GET ma priorytet, potem JSON body, potem POST)
$action = $_GET['action'] ?? ($input['action'] ?? $_POST['action'] ?? null);

try {
    switch ($action) {

        case 'dodaj_lekcje':
            // Dodaj nową lekcję
            if (!$input) {
                $input = $_POST;
            }

            $dane = [
                'klasa_id' => $input['klasa_id'] ?? null,
                'data' => $input['data'] ?? null,
                'numer_lekcji' => $input['numer_lekcji'] ?? null,
                'przedmiot_id' => $input['przedmiot_id'] ?? null,
                'nauczyciel_id' => $input['nauczyciel_id'] ?? null,
                'sala_id' => $input['sala_id'] ?? null
            ];

            $result = $edytor->dodajLekcje($dane, $uzytkownik_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'edytuj_lekcje':
            // Edytuj istniejącą lekcję
            if (!$input) {
                $input = $_POST;
            }

            $plan_dzienny_id = $input['plan_dzienny_id'] ?? null;

            if (!$plan_dzienny_id) {
                echo json_encode(['success' => false, 'message' => 'Brak ID lekcji'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $dane = [
                'przedmiot_id' => $input['przedmiot_id'] ?? null,
                'nauczyciel_id' => $input['nauczyciel_id'] ?? null,
                'sala_id' => $input['sala_id'] ?? null
            ];

            $result = $edytor->edytujLekcje($plan_dzienny_id, $dane, $uzytkownik_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'usun_lekcje':
            // Usuń lekcję
            if (!$input) {
                $input = $_POST;
            }

            $plan_dzienny_id = $input['plan_dzienny_id'] ?? $_GET['plan_dzienny_id'] ?? null;

            if (!$plan_dzienny_id) {
                echo json_encode(['success' => false, 'message' => 'Brak ID lekcji'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $edytor->usunLekcje($plan_dzienny_id, $uzytkownik_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'przenies_lekcje':
            // Przenieś lekcję (drag & drop)
            if (!$input) {
                $input = $_POST;
            }

            $plan_dzienny_id = $input['plan_dzienny_id'] ?? null;

            if (!$plan_dzienny_id) {
                echo json_encode(['success' => false, 'message' => 'Brak ID lekcji'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $nowe_dane = [
                'nowa_data' => $input['nowa_data'] ?? null,
                'nowy_numer_lekcji' => $input['nowy_numer_lekcji'] ?? null
            ];

            $result = $edytor->przeniesLekcje($plan_dzienny_id, $nowe_dane, $uzytkownik_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'sprawdz_konflikty':
            // Sprawdź konflikty dla danej lekcji
            if (!$input) {
                $input = $_POST;
            }

            $dane = [
                'klasa_id' => $input['klasa_id'] ?? null,
                'data' => $input['data'] ?? null,
                'numer_lekcji' => $input['numer_lekcji'] ?? null,
                'przedmiot_id' => $input['przedmiot_id'] ?? null,
                'nauczyciel_id' => $input['nauczyciel_id'] ?? null,
                'sala_id' => $input['sala_id'] ?? null
            ];

            $exclude_id = $input['exclude_id'] ?? null;

            $konflikty = $edytor->sprawdzKonflikty($dane, $exclude_id);
            echo json_encode($konflikty, JSON_UNESCAPED_UNICODE);
            break;

        case 'pobierz_plan':
            // Pobierz plan do edycji
            $klasa_id = $_GET['klasa_id'] ?? null;
            $data_od = $_GET['data_od'] ?? null;
            $data_do = $_GET['data_do'] ?? null;

            if (!$klasa_id || !$data_od || !$data_do) {
                echo json_encode(['success' => false, 'message' => 'Brak wymaganych parametrów'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Pobierz plan
            $stmt = $conn->prepare("
                SELECT pd.*,
                       p.nazwa as przedmiot_nazwa,
                       p.skrot as przedmiot_skrot,
                       CONCAT(u.imie, ' ', u.nazwisko) as nauczyciel_nazwa,
                       s.numer as sala_numer
                FROM plan_dzienny pd
                LEFT JOIN przedmioty p ON pd.przedmiot_id = p.id
                LEFT JOIN nauczyciele n ON pd.nauczyciel_id = n.id
                LEFT JOIN uzytkownicy u ON n.uzytkownik_id = u.id
                LEFT JOIN sale s ON pd.sala_id = s.id
                WHERE pd.klasa_id = ?
                AND pd.data BETWEEN ? AND ?
                ORDER BY pd.data, pd.numer_lekcji
            ");

            $stmt->bind_param("iss", $klasa_id, $data_od, $data_do);
            $stmt->execute();
            $result = $stmt->get_result();

            $plan = [];
            while ($row = $result->fetch_assoc()) {
                $plan[] = $row;
            }

            echo json_encode(['success' => true, 'plan' => $plan], JSON_UNESCAPED_UNICODE);
            break;

        case 'pobierz_historie':
            // Pobierz historię zmian dla lekcji
            $plan_dzienny_id = $_GET['plan_dzienny_id'] ?? null;

            if (!$plan_dzienny_id) {
                echo json_encode(['success' => false, 'message' => 'Brak ID lekcji'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $historia = $edytor->pobierzHistorie($plan_dzienny_id);
            echo json_encode(['success' => true, 'historia' => $historia], JSON_UNESCAPED_UNICODE);
            break;

        case 'cofnij_zmiane':
            // Cofnij ostatnią zmianę (undo)
            $result = $edytor->cofnijOstatniaZmiane($uzytkownik_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'pobierz_konflikty':
            // Pobierz wszystkie konflikty
            $tylko_nierozwiazane = isset($_GET['tylko_nierozwiazane']) ? (bool)$_GET['tylko_nierozwiazane'] : true;

            $konflikty = $edytor->pobierzKonflikty($tylko_nierozwiazane);
            echo json_encode(['success' => true, 'konflikty' => $konflikty], JSON_UNESCAPED_UNICODE);
            break;

        case 'rozwiaz_konflikt':
            // Oznacz konflikt jako rozwiązany
            $konflikt_id = $_POST['konflikt_id'] ?? $_GET['konflikt_id'] ?? null;

            if (!$konflikt_id) {
                echo json_encode(['success' => false, 'message' => 'Brak ID konfliktu'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $success = $edytor->rozwiazKonflikt($konflikt_id);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Konflikt oznaczony jako rozwiązany' : 'Błąd przy rozwiązywaniu konfliktu'
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'pobierz_dane_formularza':
            // Pobierz dane do formularza (klasy, przedmioty, nauczyciele, sale)

            // Pobierz klasy
            $klasy = [];
            $result = $conn->query("SELECT id, nazwa FROM klasy ORDER BY nazwa");
            while ($row = $result->fetch_assoc()) {
                $klasy[] = $row;
            }

            // Pobierz przedmioty
            $przedmioty = [];
            $result = $conn->query("SELECT id, nazwa, skrot FROM przedmioty ORDER BY nazwa");
            while ($row = $result->fetch_assoc()) {
                $przedmioty[] = $row;
            }

            // Pobierz nauczycieli
            $nauczyciele = [];
            $result = $conn->query("
                SELECT n.id, CONCAT(u.imie, ' ', u.nazwisko) as nazwa
                FROM nauczyciele n
                LEFT JOIN uzytkownicy u ON n.uzytkownik_id = u.id
                ORDER BY u.nazwisko, u.imie
            ");
            while ($row = $result->fetch_assoc()) {
                $nauczyciele[] = $row;
            }

            // Pobierz sale
            $sale = [];
            $result = $conn->query("SELECT id, numer, nazwa FROM sale ORDER BY numer");
            while ($row = $result->fetch_assoc()) {
                $sale[] = $row;
            }

            echo json_encode([
                'success' => true,
                'klasy' => $klasy,
                'przedmioty' => $przedmioty,
                'nauczyciele' => $nauczyciele,
                'sale' => $sale
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Nieznana akcja: ' . ($action ?? 'brak')
            ], JSON_UNESCAPED_UNICODE);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd serwera: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
