<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('uczen');

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
    <title>Zmiana hasła - Panel Ucznia</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .student-layout {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .student-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .student-header {
            background: white;
            border-radius: 15px;
            padding: 25px 35px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .class-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            color: #2c3e50;
            font-weight: 500;
        }

        .btn-logout {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .btn-back {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .password-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .password-card h2 {
            margin: 0 0 25px 0;
            color: #2c3e50;
            font-size: 24px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .password-form {
            max-width: 400px;
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
            box-sizing: border-box;
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

        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 25px;
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

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .student-header {
                flex-direction: column;
                gap: 15px;
                padding: 20px;
            }

            .student-header h1 {
                font-size: 20px;
                flex-direction: column;
                text-align: center;
            }

            .user-info {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="student-layout">
        <div class="student-container">
            <header class="student-header">
                <h1>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Zmiana hasła
                    <span class="class-badge">Uczeń</span>
                </h1>
                <div class="user-info">
                    <span><?php echo e($_SESSION['user_name']); ?></span>
                    <a href="dashboard.php" class="btn-back">Powrót</a>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="password-card">
                <h2>Zmień swoje hasło</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
                <?php endif; ?>

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
</body>
</html>
