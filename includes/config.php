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

// CSRF Protection Functions
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Rate Limiting Functions
function check_rate_limit($key, $max_attempts = 5, $timeout_minutes = 15) {
    $attempts_key = 'rate_limit_' . $key;
    $timeout_key = 'rate_limit_timeout_' . $key;

    // Check if timeout is still active
    if (isset($_SESSION[$timeout_key]) && $_SESSION[$timeout_key] > time()) {
        $remaining = ceil(($_SESSION[$timeout_key] - time()) / 60);
        return ['allowed' => false, 'message' => "Zbyt wiele prób. Spróbuj ponownie za $remaining minut."];
    }

    // Reset counter if timeout expired
    if (isset($_SESSION[$timeout_key]) && $_SESSION[$timeout_key] <= time()) {
        unset($_SESSION[$attempts_key]);
        unset($_SESSION[$timeout_key]);
    }

    // Initialize or increment attempts
    if (!isset($_SESSION[$attempts_key])) {
        $_SESSION[$attempts_key] = 0;
    }

    $_SESSION[$attempts_key]++;

    // Check if limit exceeded
    if ($_SESSION[$attempts_key] > $max_attempts) {
        $_SESSION[$timeout_key] = time() + ($timeout_minutes * 60);
        return ['allowed' => false, 'message' => "Zbyt wiele prób. Spróbuj ponownie za $timeout_minutes minut."];
    }

    return ['allowed' => true, 'remaining' => $max_attempts - $_SESSION[$attempts_key]];
}

function reset_rate_limit($key) {
    $attempts_key = 'rate_limit_' . $key;
    $timeout_key = 'rate_limit_timeout_' . $key;
    unset($_SESSION[$attempts_key]);
    unset($_SESSION[$timeout_key]);
}

// Funkcja do formatowania daty
function formatuj_date($data) {
    if (empty($data)) {
        error_log("formatuj_date: Przekazano pustą datę");
        return '';
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        error_log("formatuj_date: Nie udało się sparsować daty: " . $data);
        return $data; // Zwróć oryginalną wartość jako fallback
    }

    return date('d.m.Y', $timestamp);
}

// Funkcja do formatowania czasu
function formatuj_czas($czas) {
    if (empty($czas)) {
        error_log("formatuj_czas: Przekazano pusty czas");
        return '';
    }

    $timestamp = strtotime($czas);

    if ($timestamp === false) {
        error_log("formatuj_czas: Nie udało się sparsować czasu: " . $czas);
        return $czas; // Zwróć oryginalną wartość jako fallback
    }

    return date('H:i', $timestamp);
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
    if (empty($data)) {
        error_log("pobierz_numer_tygodnia: Przekazano pustą datę");
        return date('W'); // Zwróć bieżący tydzień
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        error_log("pobierz_numer_tygodnia: Nie udało się sparsować daty: " . $data);
        return date('W'); // Zwróć bieżący tydzień jako fallback
    }

    return date('W', $timestamp);
}

// Funkcja do pobierania początku tygodnia
function pobierz_poczatek_tygodnia($data) {
    if (empty($data)) {
        error_log("pobierz_poczatek_tygodnia: Przekazano pustą datę");
        $data = date('Y-m-d'); // Użyj bieżącej daty
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        error_log("pobierz_poczatek_tygodnia: Nie udało się sparsować daty: " . $data);
        $timestamp = time(); // Użyj bieżącego czasu jako fallback
    }

    $dzien_tygodnia = date('N', $timestamp);
    $dni_wstecz = $dzien_tygodnia - 1;

    $poczatek = strtotime("-$dni_wstecz days", $timestamp);

    if ($poczatek === false) {
        error_log("pobierz_poczatek_tygodnia: Błąd obliczania początku tygodnia");
        return date('Y-m-d', $timestamp); // Zwróć oryginalną datę
    }

    return date('Y-m-d', $poczatek);
}

// Funkcja do pobierania końca tygodnia
function pobierz_koniec_tygodnia($data) {
    if (empty($data)) {
        error_log("pobierz_koniec_tygodnia: Przekazano pustą datę");
        $data = date('Y-m-d'); // Użyj bieżącej daty
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        error_log("pobierz_koniec_tygodnia: Nie udało się sparsować daty: " . $data);
        $timestamp = time(); // Użyj bieżącego czasu jako fallback
    }

    $dzien_tygodnia = date('N', $timestamp);
    $dni_do_piatku = 5 - $dzien_tygodnia;

    $koniec = strtotime("+$dni_do_piatku days", $timestamp);

    if ($koniec === false) {
        error_log("pobierz_koniec_tygodnia: Błąd obliczania końca tygodnia");
        return date('Y-m-d', $timestamp); // Zwróć oryginalną datę
    }

    return date('Y-m-d', $koniec);
}
?>
