<?php
require_once 'includes/config.php';
require_once 'includes/admin_functions.php';

// Loguj wylogowanie przed zniszczeniem sesji
if (isset($_SESSION['user_id'])) {
    $uzytkownik_id = $_SESSION['user_id'];
    $login = $_SESSION['user_login'] ?? 'nieznany';

    // Zarządzaj sesją i loguj aktywność
    zarzadzaj_sesja($uzytkownik_id, 'logout');
    loguj_aktywnosc($uzytkownik_id, 'wylogowanie', "Użytkownik $login wylogował się z systemu");
}

session_destroy();
header('Location: index.php');
exit();
?>
