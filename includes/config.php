<?php
// Konfiguracja połączenia z bazą danych
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'plan_lekcji');

// Połączenie z bazą danych
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Błąd połączenia: " . $e->getMessage());
}

// Rozpoczęcie sesji
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funkcja sprawdzająca czy użytkownik jest zalogowany
function sprawdz_zalogowanie() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

// Funkcja sprawdzająca uprawnienia użytkownika
function sprawdz_uprawnienia($wymagany_typ) {
    sprawdz_zalogowanie();
    if ($_SESSION['user_type'] !== $wymagany_typ) {
        header('Location: brak_uprawnien.php');
        exit();
    }
}

// Funkcja do bezpiecznego wyświetlania danych
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Funkcja do formatowania daty
function formatuj_date($data) {
    $timestamp = strtotime($data);
    return date('d.m.Y', $timestamp);
}

// Funkcja do formatowania czasu
function formatuj_czas($czas) {
    return date('H:i', strtotime($czas));
}

// Funkcja do tłumaczenia dni tygodnia
function tlumacz_dzien($dzien) {
    $dni = [
        'poniedzialek' => 'Poniedziałek',
        'wtorek' => 'Wtorek',
        'sroda' => 'Środa',
        'czwartek' => 'Czwartek',
        'piatek' => 'Piątek'
    ];
    return $dni[$dzien] ?? $dzien;
}

// Funkcja do pobierania numeru tygodnia
function pobierz_numer_tygodnia($data) {
    return date('W', strtotime($data));
}

// Funkcja do pobierania początku tygodnia
function pobierz_poczatek_tygodnia($data) {
    $timestamp = strtotime($data);
    $dzien_tygodnia = date('N', $timestamp);
    $poczatek = strtotime("-" . ($dzien_tygodnia - 1) . " days", $timestamp);
    return date('Y-m-d', $poczatek);
}

// Funkcja do pobierania końca tygodnia
function pobierz_koniec_tygodnia($data) {
    $timestamp = strtotime($data);
    $dzien_tygodnia = date('N', $timestamp);
    $koniec = strtotime("+" . (5 - $dzien_tygodnia) . " days", $timestamp);
    return date('Y-m-d', $koniec);
}
?>
