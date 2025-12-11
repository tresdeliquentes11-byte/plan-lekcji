<?php
/*
 * © 2025 TresDeliquentes. All rights reserved.
 * LibreLessons jest licencjonowane na zasadach TEUL – do użytku edukacyjnego.
 * Zakazana jest dystrybucja, publikacja i komercyjne wykorzystanie bez zgody autora.
 * Korzystając z kodu, akceptujesz warunki licencji (LICENSE.md).
 */

require_once 'config.php';

// Loguje aktywność użytkownika

function loguj_aktywnosc($uzytkownik_id, $typ_akcji, $opis, $dodatkowe_dane = null) {
    global $conn;

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $dodatkowe_json = $dodatkowe_dane ? json_encode($dodatkowe_dane) : null;

    $stmt = $conn->prepare("INSERT INTO logi_aktywnosci (uzytkownik_id, typ_akcji, opis, ip_address, user_agent, dodatkowe_dane) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $uzytkownik_id, $typ_akcji, $opis, $ip_address, $user_agent, $dodatkowe_json);
    $stmt->execute();
    $stmt->close();
}

/**
 * Tworzy lub aktualizuje sesję użytkownika
 */
function zarzadzaj_sesja($uzytkownik_id, $akcja = 'login') {
    global $conn;

    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if ($akcja === 'login') {
        // Dezaktywuj stare sesje użytkownika
        $stmt = $conn->prepare("UPDATE sesje_uzytkownikow SET aktywna = 0 WHERE uzytkownik_id = ?");
        $stmt->bind_param("i", $uzytkownik_id);
        $stmt->execute();
        $stmt->close();

        // Utwórz nową sesję
        $stmt = $conn->prepare("INSERT INTO sesje_uzytkownikow (uzytkownik_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $uzytkownik_id, $session_id, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    } elseif ($akcja === 'logout') {
        // Dezaktywuj sesję
        $stmt = $conn->prepare("UPDATE sesje_uzytkownikow SET aktywna = 0 WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($akcja === 'activity') {
        // Aktualizuj ostatnią aktywność
        $stmt = $conn->prepare("UPDATE sesje_uzytkownikow SET ostatnia_aktywnosc = CURRENT_TIMESTAMP WHERE session_id = ? AND aktywna = 1");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Czyści nieaktywne sesje (starsze niż 30 minut)
 */
function wyczysc_nieaktywne_sesje() {
    global $conn;
    $conn->query("UPDATE sesje_uzytkownikow SET aktywna = 0 WHERE ostatnia_aktywnosc < DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND aktywna = 1");
}

// ============================================
// FUNKCJE ZARZĄDZANIA UŻYTKOWNIKAMI
// ============================================

/**
 * Pobiera wszystkich użytkowników danego typu
 */
function pobierz_uzytkownikow($typ = null, $tylko_aktywni = false) {
    global $conn;

    $sql = "SELECT * FROM uzytkownicy WHERE 1=1";

    if ($typ) {
        $sql .= " AND typ = ?";
    }
    if ($tylko_aktywni) {
        $sql .= " AND aktywny = 1";
    }

    $sql .= " ORDER BY nazwisko, imie";

    $stmt = $conn->prepare($sql);
    if ($typ) {
        $stmt->bind_param("s", $typ);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $uzytkownicy = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $uzytkownicy;
}

/**
 * Pobiera dane pojedynczego użytkownika
 */
function pobierz_uzytkownika($id) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM uzytkownicy WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $uzytkownik = $result->fetch_assoc();
    $stmt->close();

    return $uzytkownik;
}

/**
 * Dodaje nowego użytkownika
 */
function dodaj_uzytkownika($dane) {
    global $conn;

    // Sprawdź czy login jest unikalny
    $stmt = $conn->prepare("SELECT id FROM uzytkownicy WHERE login = ?");
    $stmt->bind_param("s", $dane['login']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Login jest już zajęty'];
    }
    $stmt->close();

    // Hashuj hasło
    $haslo_hash = password_hash($dane['haslo'], PASSWORD_DEFAULT);

    // Dodaj użytkownika
    $stmt = $conn->prepare("INSERT INTO uzytkownicy (login, haslo, typ, imie, nazwisko, email, aktywny) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $aktywny = isset($dane['aktywny']) ? $dane['aktywny'] : 1;
    $stmt->bind_param("ssssssi", $dane['login'], $haslo_hash, $dane['typ'], $dane['imie'], $dane['nazwisko'], $dane['email'], $aktywny);

    if ($stmt->execute()) {
        $uzytkownik_id = $conn->insert_id;
        $stmt->close();

        // Jeśli to nauczyciel, dodaj wpis do tabeli nauczyciele
        if ($dane['typ'] === 'nauczyciel') {
            $stmt = $conn->prepare("INSERT INTO nauczyciele (uzytkownik_id) VALUES (?)");
            $stmt->bind_param("i", $uzytkownik_id);
            $stmt->execute();
            $stmt->close();
        }

        // Jeśli to uczeń, dodaj wpis do tabeli uczniowie (jeśli podano klasę)
        if ($dane['typ'] === 'uczen' && isset($dane['klasa_id'])) {
            $stmt = $conn->prepare("INSERT INTO uczniowie (uzytkownik_id, klasa_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $uzytkownik_id, $dane['klasa_id']);
            $stmt->execute();
            $stmt->close();
        }

        // Loguj aktywność
        loguj_aktywnosc($_SESSION['user_id'], 'dodanie_uzytkownika', "Dodano użytkownika: {$dane['login']} ({$dane['typ']})", ['uzytkownik_id' => $uzytkownik_id]);

        return ['success' => true, 'message' => 'Użytkownik został dodany', 'id' => $uzytkownik_id];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Błąd dodawania użytkownika: ' . $error];
    }
}

/**
 * Aktualizuje dane użytkownika
 */
function aktualizuj_uzytkownika($id, $dane) {
    global $conn;

    // Sprawdź czy login jest unikalny (oprócz aktualnego użytkownika)
    if (isset($dane['login'])) {
        $stmt = $conn->prepare("SELECT id FROM uzytkownicy WHERE login = ? AND id != ?");
        $stmt->bind_param("si", $dane['login'], $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Login jest już zajęty'];
        }
        $stmt->close();
    }

    // Przygotuj zapytanie UPDATE
    $pola = [];
    $typy = '';
    $wartosci = [];

    if (isset($dane['login'])) {
        $pola[] = 'login = ?';
        $typy .= 's';
        $wartosci[] = $dane['login'];
    }
    if (isset($dane['haslo']) && !empty($dane['haslo'])) {
        $pola[] = 'haslo = ?';
        $typy .= 's';
        $wartosci[] = password_hash($dane['haslo'], PASSWORD_DEFAULT);
    }
    if (isset($dane['imie'])) {
        $pola[] = 'imie = ?';
        $typy .= 's';
        $wartosci[] = $dane['imie'];
    }
    if (isset($dane['nazwisko'])) {
        $pola[] = 'nazwisko = ?';
        $typy .= 's';
        $wartosci[] = $dane['nazwisko'];
    }
    if (isset($dane['email'])) {
        $pola[] = 'email = ?';
        $typy .= 's';
        $wartosci[] = $dane['email'];
    }
    if (isset($dane['aktywny'])) {
        $pola[] = 'aktywny = ?';
        $typy .= 'i';
        $wartosci[] = $dane['aktywny'];
    }

    if (empty($pola)) {
        return ['success' => false, 'message' => 'Brak danych do aktualizacji'];
    }

    $sql = "UPDATE uzytkownicy SET " . implode(', ', $pola) . " WHERE id = ?";
    $typy .= 'i';
    $wartosci[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($typy, ...$wartosci);

    if ($stmt->execute()) {
        $stmt->close();

        // Jeśli zmieniono status aktywności, wyloguj użytkownika - używamy prepared statement
        if (isset($dane['aktywny']) && $dane['aktywny'] == 0) {
            $stmt_logout = $conn->prepare("UPDATE sesje_uzytkownikow SET aktywna = 0 WHERE uzytkownik_id = ?");
            if ($stmt_logout) {
                $stmt_logout->bind_param("i", $id);
                $stmt_logout->execute();
                $stmt_logout->close();
            }
        }

        // Loguj aktywność
        loguj_aktywnosc($_SESSION['user_id'], 'edycja_uzytkownika', "Zaktualizowano użytkownika ID: $id", ['uzytkownik_id' => $id, 'zmiany' => array_keys($dane)]);

        return ['success' => true, 'message' => 'Użytkownik został zaktualizowany'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        error_log("Błąd aktualizacji użytkownika ID $id: " . $error);
        return ['success' => false, 'message' => 'Błąd aktualizacji użytkownika'];
    }
}

/**
 * Usuwa użytkownika
 */
function usun_uzytkownika($id) {
    global $conn;

    // Sprawdź czy użytkownik istnieje
    $uzytkownik = pobierz_uzytkownika($id);
    if (!$uzytkownik) {
        return ['success' => false, 'message' => 'Użytkownik nie istnieje'];
    }

    // Nie pozwól usunąć samego siebie
    if ($id == $_SESSION['user_id']) {
        return ['success' => false, 'message' => 'Nie możesz usunąć samego siebie'];
    }

    $stmt = $conn->prepare("DELETE FROM uzytkownicy WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();

        // Loguj aktywność
        loguj_aktywnosc($_SESSION['user_id'], 'usuniecie_uzytkownika', "Usunięto użytkownika: {$uzytkownik['login']} ({$uzytkownik['typ']})", ['uzytkownik_id' => $id]);

        return ['success' => true, 'message' => 'Użytkownik został usunięty'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Błąd usuwania: ' . $error];
    }
}

/**
 * Blokuje lub odblokuje użytkownika
 */
function zmien_status_uzytkownika($id, $aktywny) {
    global $conn;

    // Nie pozwól zablokować samego siebie
    if ($id == $_SESSION['user_id']) {
        return ['success' => false, 'message' => 'Nie możesz zmienić statusu samego siebie'];
    }

    $stmt = $conn->prepare("UPDATE uzytkownicy SET aktywny = ? WHERE id = ?");
    $stmt->bind_param("ii", $aktywny, $id);

    if ($stmt->execute()) {
        $stmt->close();

        // Jeśli blokujemy, wyloguj użytkownika - używamy prepared statement
        if ($aktywny == 0) {
            $stmt_logout = $conn->prepare("UPDATE sesje_uzytkownikow SET aktywna = 0 WHERE uzytkownik_id = ?");
            if ($stmt_logout) {
                $stmt_logout->bind_param("i", $id);
                $stmt_logout->execute();
                $stmt_logout->close();
            }
        }

        $akcja = $aktywny ? 'odblokowanie' : 'blokada';
        loguj_aktywnosc($_SESSION['user_id'], $akcja . '_uzytkownika', "Zmieniono status użytkownika ID: $id na " . ($aktywny ? 'aktywny' : 'nieaktywny'), ['uzytkownik_id' => $id]);

        return ['success' => true, 'message' => 'Status użytkownika został zmieniony'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        error_log("Błąd zmiany statusu użytkownika ID $id: " . $error);
        return ['success' => false, 'message' => 'Błąd zmiany statusu użytkownika'];
    }
}

/**
 * Pobiera wszystkie klasy
 */
function pobierz_klasy() {
    global $conn;

    $result = $conn->query("SELECT * FROM klasy ORDER BY nazwa");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Aktualizuje klasę ucznia
 */
function aktualizuj_klase_ucznia($uzytkownik_id, $klasa_id) {
    global $conn;

    // Sprawdź czy uczeń już ma wpis w tabeli uczniowie
    $stmt = $conn->prepare("SELECT id FROM uczniowie WHERE uzytkownik_id = ?");
    $stmt->bind_param("i", $uzytkownik_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Aktualizuj istniejący wpis
        $stmt->close();
        $stmt = $conn->prepare("UPDATE uczniowie SET klasa_id = ? WHERE uzytkownik_id = ?");
        $stmt->bind_param("ii", $klasa_id, $uzytkownik_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Dodaj nowy wpis
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO uczniowie (uzytkownik_id, klasa_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $uzytkownik_id, $klasa_id);
        $stmt->execute();
        $stmt->close();
    }
}

// ============================================
// FUNKCJE STATYSTYK
// ============================================

/**
 * Pobiera liczbę aktualnie zalogowanych użytkowników
 */
function pobierz_aktywnych_uzytkownikow() {
    global $conn;

    wyczysc_nieaktywne_sesje();

    $result = $conn->query("
        SELECT COUNT(DISTINCT su.uzytkownik_id) as total,
               COUNT(DISTINCT CASE WHEN u.typ = 'uczen' THEN su.uzytkownik_id END) as uczniowie,
               COUNT(DISTINCT CASE WHEN u.typ = 'nauczyciel' THEN su.uzytkownik_id END) as nauczyciele,
               COUNT(DISTINCT CASE WHEN u.typ = 'dyrektor' THEN su.uzytkownik_id END) as dyrektor,
               COUNT(DISTINCT CASE WHEN u.typ = 'administrator' THEN su.uzytkownik_id END) as administratorzy
        FROM sesje_uzytkownikow su
        JOIN uzytkownicy u ON su.uzytkownik_id = u.id
        WHERE su.aktywna = 1
    ");

    return $result->fetch_assoc();
}

/**
 * Pobiera listę aktualnie zalogowanych użytkowników
 */
function pobierz_liste_aktywnych_uzytkownikow() {
    global $conn;

    wyczysc_nieaktywne_sesje();

    $result = $conn->query("
        SELECT u.id, u.login, u.imie, u.nazwisko, u.typ,
               su.ip_address, su.ostatnia_aktywnosc, su.data_logowania
        FROM sesje_uzytkownikow su
        JOIN uzytkownicy u ON su.uzytkownik_id = u.id
        WHERE su.aktywna = 1
        ORDER BY su.ostatnia_aktywnosc DESC
    ");

    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Pobiera statystyki generowania planu
 */
function pobierz_statystyki_generowania($dni = 30) {
    global $conn;

    // Statystyki dzienne
    $stmt = $conn->prepare("
        SELECT DATE(data_generowania) as dzien,
               COUNT(*) as ilosc,
               SUM(CASE WHEN status = 'sukces' THEN 1 ELSE 0 END) as sukces,
               SUM(CASE WHEN status = 'blad' THEN 1 ELSE 0 END) as blad
        FROM statystyki_generowania
        WHERE data_generowania >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(data_generowania)
        ORDER BY dzien DESC
    ");
    $stmt->bind_param("i", $dni);
    $stmt->execute();
    $result = $stmt->get_result();
    $statystyki_dzienne = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Statystyki ogólne
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN status = 'sukces' THEN 1 ELSE 0 END) as sukces,
               SUM(CASE WHEN status = 'blad' THEN 1 ELSE 0 END) as blad,
               AVG(czas_trwania_sekundy) as sredni_czas
        FROM statystyki_generowania
        WHERE data_generowania >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->bind_param("i", $dni);
    $stmt->execute();
    $result = $stmt->get_result();
    $statystyki_ogolne = $result->fetch_assoc();
    $stmt->close();

    return [
        'dzienne' => $statystyki_dzienne,
        'ogolne' => $statystyki_ogolne
    ];
}

/**
 * Pobiera statystyki użytkowników
 */
function pobierz_statystyki_uzytkownikow() {
    global $conn;

    $result = $conn->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN typ = 'uczen' THEN 1 ELSE 0 END) as uczniowie,
            SUM(CASE WHEN typ = 'nauczyciel' THEN 1 ELSE 0 END) as nauczyciele,
            SUM(CASE WHEN typ = 'dyrektor' THEN 1 ELSE 0 END) as dyrektor,
            SUM(CASE WHEN typ = 'administrator' THEN 1 ELSE 0 END) as administratorzy,
            SUM(CASE WHEN aktywny = 1 THEN 1 ELSE 0 END) as aktywni,
            SUM(CASE WHEN aktywny = 0 THEN 1 ELSE 0 END) as zablokowani
        FROM uzytkownicy
    ");

    return $result->fetch_assoc();
}

/**
 * Pobiera ostatnie akcje użytkowników
 */
function pobierz_ostatnie_akcje($limit = 50) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT la.*, u.login, u.imie, u.nazwisko, u.typ
        FROM logi_aktywnosci la
        LEFT JOIN uzytkownicy u ON la.uzytkownik_id = u.id
        ORDER BY la.data_akcji DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $akcje = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $akcje;
}

/**
 * Pobiera statystyki zarządzania użytkownikami
 */
function pobierz_statystyki_zarzadzania($dni = 30) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT DATE(data_operacji) as dzien,
               typ_operacji,
               COUNT(*) as ilosc
        FROM statystyki_uzytkownikow
        WHERE data_operacji >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(data_operacji), typ_operacji
        ORDER BY dzien DESC
    ");
    $stmt->bind_param("i", $dni);
    $stmt->execute();
    $result = $stmt->get_result();
    $statystyki = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $statystyki;
}

/**
 * Loguje operację zarządzania użytkownikami
 */
function loguj_operacje_uzytkownika($typ_operacji, $uzytkownik_docelowy_id, $opis_zmian = null) {
    global $conn;

    $administrator_id = $_SESSION['user_id'];

    // Pobierz typ użytkownika docelowego
    $typ_uzytkownika = null;
    if ($uzytkownik_docelowy_id) {
        $stmt = $conn->prepare("SELECT typ FROM uzytkownicy WHERE id = ?");
        $stmt->bind_param("i", $uzytkownik_docelowy_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $typ_uzytkownika = $row['typ'];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO statystyki_uzytkownikow (administrator_id, typ_operacji, uzytkownik_docelowy_id, typ_uzytkownika_docelowego, opis_zmian) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $administrator_id, $typ_operacji, $uzytkownik_docelowy_id, $typ_uzytkownika, $opis_zmian);
    $stmt->execute();
    $stmt->close();
}

?>
