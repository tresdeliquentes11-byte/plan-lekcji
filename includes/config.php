<?php
// Environment configuration
define('ENVIRONMENT', 'development'); // Change to 'production' in production environment

// Konfiguracja połączenia z bazą danych
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'plan_lekcji');

// Połączenie z bazą danych - ENHANCED ERROR HANDLING
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // Don't expose detailed errors in production
        $error_message = (defined('ENVIRONMENT') && ENVIRONMENT === 'development')
            ? "Błąd połączenia z bazą danych: " . $conn->connect_error
            : "Błąd połączenia z bazą danych. Skontaktuj się z administratorem.";
        
        error_log("Database connection error: " . $conn->connect_error);
        die($error_message);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    $error_message = (defined('ENVIRONMENT') && ENVIRONMENT === 'development')
        ? "Błąd połączenia: " . $e->getMessage()
        : "Błąd połączenia z bazą danych. Skontaktuj się z administratorem.";
    
    error_log("Database connection exception: " . $e->getMessage());
    die($error_message);
}

// Rozpoczęcie sesji - ENHANCED SECURITY
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    session_set_cookie_params([
        'lifetime' => 3600, // 1 hour
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
    
    // Regenerate session ID to prevent session fixation
    if (!isset($_SESSION['session_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated'] = true;
    }
}

// Funkcja sprawdzająca czy użytkownik jest zalogowany - ENHANCED SECURITY
function sprawdz_zalogowanie() {
    if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
    
    // Additional session validation
    if (!isset($_SESSION['session_regenerated']) || !isset($_SESSION['ip_address'])) {
        session_destroy();
        header('Location: index.php');
        exit();
    }
    
    // Validate IP address (optional - can cause issues with dynamic IPs)
    if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        // Log potential session hijacking attempt
        error_log("Session IP mismatch: " . $_SESSION['ip_address'] . " vs " . $_SERVER['REMOTE_ADDR']);
        session_destroy();
        header('Location: index.php');
        exit();
    }
}

// Funkcja sprawdzająca uprawnienia użytkownika - ENHANCED VALIDATION
function sprawdz_uprawnienia($wymagany_typ) {
    sprawdz_zalogowanie();
    
    // Validate user type format
    $valid_types = ['dyrektor', 'administrator', 'nauczyciel', 'uczen'];
    if (!in_array($wymagany_typ, $valid_types)) {
        error_log("Invalid user type requested: $wymagany_typ");
        die("Nieprawidłowe żądanie uprawnień.");
    }
    
    if ($_SESSION['user_type'] !== $wymagany_typ) {
        header('Location: brak_uprawnien.php');
        exit();
    }
}

// Input validation function
function validate_input($data, $type = 'string', $required = true) {
    if ($required && (empty($data) && $data !== '0')) {
        throw new InvalidArgumentException("Pole jest wymagane");
    }
    
    if (empty($data) && !$required) {
        return null;
    }
    
    switch ($type) {
        case 'int':
            if (!is_numeric($data) || (int)$data != $data) {
                throw new InvalidArgumentException("Wartość musi być liczbą całkowitą");
            }
            return (int)$data;
            
        case 'float':
            if (!is_numeric($data)) {
                throw new InvalidArgumentException("Wartość musi być liczbą");
            }
            return (float)$data;
            
        case 'email':
            if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Nieprawidłowy format email");
            }
            return $data;
            
        case 'string':
            if (!is_string($data)) {
                throw new InvalidArgumentException("Wartość musi być tekstem");
            }
            return trim($data);
            
        case 'date':
            $date = DateTime::createFromFormat('Y-m-d', $data);
            if (!$date || $date->format('Y-m-d') !== $data) {
                throw new InvalidArgumentException("Nieprawidłowy format daty (YYYY-MM-DD)");
            }
            return $data;
            
        case 'alpha':
            if (!preg_match('/^[a-zA-ZąęóąśłżźćńĄĘÓĄŚŁŻŹĆŃ]+$/', $data)) {
                throw new InvalidArgumentException("Wartość może zawierać tylko litery");
            }
            return $data;
            
        case 'alphanum':
            if (!preg_match('/^[a-zA-Z0-9ąęóąśłżźćńĄĘÓĄŚŁŻŹĆŃ ]+$/', $data)) {
                throw new InvalidArgumentException("Wartość może zawierać tylko litery, cyfry i spacje");
            }
            return $data;
            
        default:
            throw new InvalidArgumentException("Nieznany typ walidacji: $type");
    }
}

// Funkcja do bezpiecznego wyświetlania danych
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Enhanced CSRF Protection Functions
function csrf_token() {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Token expires after 1 hour
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    
    // One-time use - clear token after validation
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
    
    return $valid;
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

// Funkcja do pobierania końca tygodnia (piątek)
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

    $dzien_tygodnia = date('N', $timestamp); // 1=Poniedziałek, 7=Niedziela

    // NAPRAWA: Obsługa weekendów (sobota=6, niedziela=7)
    // Dla weekendów zwracamy piątek TEGO samego tygodnia kalendarzowego
    if ($dzien_tygodnia >= 6) {
        // Sobota (6) -> cofnij o 1 dzień do piątku
        // Niedziela (7) -> cofnij o 2 dni do piątku
        $dni_wstecz = $dzien_tygodnia - 5;
        $koniec = strtotime("-$dni_wstecz days", $timestamp);
    } else {
        // Poniedziałek-Piątek: oblicz normalnie
        $dni_do_piatku = 5 - $dzien_tygodnia;
        $koniec = strtotime("+$dni_do_piatku days", $timestamp);
    }

    if ($koniec === false) {
        error_log("pobierz_koniec_tygodnia: Błąd obliczania końca tygodnia");
        return date('Y-m-d', $timestamp); // Zwróć oryginalną datę
    }

    return date('Y-m-d', $koniec);
}
?>
