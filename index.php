<?php
require_once 'includes/config.php';
require_once 'includes/admin_functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $haslo = $_POST['haslo'] ?? '';

    if ($login && $haslo) {
        $stmt = $conn->prepare("SELECT id, login, haslo, typ, imie, nazwisko FROM uzytkownicy WHERE login = ? AND aktywny = 1");
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Weryfikacja hasła
            if (password_verify($haslo, $user['haslo'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_login'] = $user['login'];
                $_SESSION['user_type'] = $user['typ'];
                $_SESSION['user_name'] = $user['imie'] . ' ' . $user['nazwisko'];

                // Zarządzaj sesją i loguj aktywność
                zarzadzaj_sesja($user['id'], 'login');
                loguj_aktywnosc($user['id'], 'logowanie', "Użytkownik {$user['login']} zalogował się do systemu");

                // Przekierowanie w zależności od typu użytkownika
                switch ($user['typ']) {
                    case 'dyrektor':
                        header('Location: dyrektor/dashboard.php');
                        break;
                    case 'administrator':
                        header('Location: administrator/dashboard.php');
                        break;
                    case 'nauczyciel':
                        header('Location: nauczyciel/dashboard.php');
                        break;
                    case 'uczen':
                        header('Location: uczen/dashboard.php');
                        break;
                }
                exit();
            } else {
                $error = 'Nieprawidłowy login lub hasło';
                // Loguj nieudaną próbę logowania
                loguj_aktywnosc(null, 'nieudane_logowanie', "Nieudana próba logowania dla loginu: $login");
            }
        } else {
            // Sprawdź czy użytkownik istnieje ale jest zablokowany
            $stmt2 = $conn->prepare("SELECT id FROM uzytkownicy WHERE login = ? AND aktywny = 0");
            $stmt2->bind_param("s", $login);
            $stmt2->execute();
            $result2 = $stmt2->get_result();

            if ($result2->num_rows === 1) {
                $error = 'Konto zostało zablokowane. Skontaktuj się z administratorem.';
            } else {
                $error = 'Nieprawidłowy login lub hasło';
            }
            $stmt2->close();
        }
        $stmt->close();
    } else {
        $error = 'Proszę wypełnić wszystkie pola';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - System Planu Lekcji</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <h2>System Planu Lekcji</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="login">Login</label>
                <input type="text" id="login" name="login" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="haslo">Hasło</label>
                <input type="password" id="haslo" name="haslo" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-full">Zaloguj się</button>
        </form>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef; font-size: 13px; color: #6c757d;">
            <strong>Domyślne konta testowe:</strong><br>
            Dyrektor: dyrektor / dyrektor123<br>
            Administrator: admin / admin123
        </div>
    </div>
</body>
</html>
