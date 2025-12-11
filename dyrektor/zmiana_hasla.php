<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Weryfikacja CSRF
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $stare_haslo = $_POST['stare_haslo'] ?? '';
        $nowe_haslo = $_POST['nowe_haslo'] ?? '';
        $potwierdzenie = $_POST['potwierdzenie'] ?? '';

        // Walidacja
        if (empty($stare_haslo) || empty($nowe_haslo) || empty($potwierdzenie)) {
            $message = 'Wszystkie pola są wymagane.';
            $message_type = 'error';
        } elseif ($nowe_haslo !== $potwierdzenie) {
            $message = 'Nowe hasło i potwierdzenie nie są identyczne.';
            $message_type = 'error';
        } elseif (strlen($nowe_haslo) < 6) {
            $message = 'Nowe hasło musi mieć co najmniej 6 znaków.';
            $message_type = 'error';
        } elseif ($stare_haslo === $nowe_haslo) {
            $message = 'Nowe hasło musi być inne niż obecne.';
            $message_type = 'error';
        } else {
            // Pobierz aktualne hasło użytkownika
            $stmt = $conn->prepare("SELECT haslo FROM uzytkownicy WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user || !password_verify($stare_haslo, $user['haslo'])) {
                $message = 'Aktualne hasło jest nieprawidłowe.';
                $message_type = 'error';
            } else {
                // Zmień hasło
                $nowe_haslo_hash = password_hash($nowe_haslo, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE uzytkownicy SET haslo = ? WHERE id = ?");
                $stmt->bind_param("si", $nowe_haslo_hash, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    $message = 'Hasło zostało pomyślnie zmienione.';
                    $message_type = 'success';

                    // Loguj aktywność
                    loguj_aktywnosc($_SESSION['user_id'], 'zmiana_hasla', 'Użytkownik zmienił swoje hasło');
                } else {
                    $message = 'Wystąpił błąd podczas zmiany hasła. Spróbuj ponownie.';
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zmiana hasła - Panel Dyrektora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .password-form {
            max-width: 500px;
        }

        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
        }

        .password-requirements h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #666;
            font-size: 13px;
        }

        .password-requirements li {
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Zmiana hasła</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <h2 class="page-title">Zmień swoje hasło</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h3 class="card-title">Formularz zmiany hasła</h3>

                    <form method="POST" class="password-form">
                        <?php echo csrf_field(); ?>

                        <div class="form-group">
                            <label for="stare_haslo">Aktualne hasło *</label>
                            <input type="password" id="stare_haslo" name="stare_haslo" required autocomplete="current-password">
                        </div>

                        <div class="form-group">
                            <label for="nowe_haslo">Nowe hasło *</label>
                            <input type="password" id="nowe_haslo" name="nowe_haslo" required minlength="6" autocomplete="new-password">
                        </div>

                        <div class="form-group">
                            <label for="potwierdzenie">Powtórz nowe hasło *</label>
                            <input type="password" id="potwierdzenie" name="potwierdzenie" required minlength="6" autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn-submit">Zmień hasło</button>
                    </form>

                    <div class="password-requirements">
                        <h4>Wymagania dotyczące hasła:</h4>
                        <ul>
                            <li>Minimum 6 znaków</li>
                            <li>Nowe hasło musi być inne niż obecne</li>
                            <li>Zalecane: użyj kombinacji liter, cyfr i znaków specjalnych</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
