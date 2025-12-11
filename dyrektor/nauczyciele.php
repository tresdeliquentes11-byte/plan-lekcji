<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie nauczyciela
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $imie = trim($_POST['imie']);
        $nazwisko = trim($_POST['nazwisko']);
        $email = trim($_POST['email']);
        $login = trim($_POST['login']);
        $haslo = password_hash($_POST['haslo'], PASSWORD_DEFAULT);
        $przedmioty = $_POST['przedmioty'] ?? [];

        // Input validation
        if (empty($imie) || empty($nazwisko) || empty($email) || empty($login)) {
            $message = 'Wszystkie pola są wymagane';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Nieprawidłowy adres email';
            $message_type = 'error';
        } else {
            $conn->begin_transaction();

            try {
                // Dodaj użytkownika
                $stmt = $conn->prepare("INSERT INTO uzytkownicy (login, haslo, typ, imie, nazwisko, email) VALUES (?, ?, 'nauczyciel', ?, ?, ?)");
                $stmt->bind_param("sssss", $login, $haslo, $imie, $nazwisko, $email);
                $stmt->execute();
                $uzytkownik_id = $conn->insert_id;

                // Dodaj nauczyciela
                $stmt = $conn->prepare("INSERT INTO nauczyciele (uzytkownik_id) VALUES (?)");
                $stmt->bind_param("i", $uzytkownik_id);
                $stmt->execute();
                $nauczyciel_id = $conn->insert_id;

                // Przypisz przedmioty
                foreach ($przedmioty as $przedmiot_id) {
                    $przedmiot_id = intval($przedmiot_id);
                    $stmt = $conn->prepare("INSERT INTO nauczyciel_przedmioty (nauczyciel_id, przedmiot_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $nauczyciel_id, $przedmiot_id);
                    $stmt->execute();
                }

                $conn->commit();
                $message = 'Nauczyciel został dodany pomyślnie';
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Błąd dodawania nauczyciela: " . $e->getMessage());
                $message = 'Błąd podczas dodawania nauczyciela';
                $message_type = 'error';
            }
        }
    }
}

// Usuwanie nauczyciela (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usun_nauczyciela'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $id = intval($_POST['usun_nauczyciela']);

        // Use subquery with prepared statement
        $stmt = $conn->prepare("DELETE FROM uzytkownicy WHERE id IN (SELECT uzytkownik_id FROM nauczyciele WHERE id = ?)");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = 'Nauczyciel został usunięty';
            $message_type = 'success';
        } else {
            error_log("Błąd usuwania nauczyciela: " . $stmt->error);
            $message = 'Błąd podczas usuwania nauczyciela';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Pobierz nauczycieli
$nauczyciele = $conn->query("
    SELECT n.id, u.imie, u.nazwisko, u.email, u.login,
           GROUP_CONCAT(p.nazwa SEPARATOR ', ') as przedmioty
    FROM nauczyciele n
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    LEFT JOIN nauczyciel_przedmioty np ON n.id = np.nauczyciel_id
    LEFT JOIN przedmioty p ON np.przedmiot_id = p.id
    GROUP BY n.id
    ORDER BY u.nazwisko, u.imie
");

// Pobierz przedmioty
$przedmioty = $conn->query("SELECT * FROM przedmioty ORDER BY nazwa");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Nauczycielami</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Zarządzanie Nauczycielami</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Dodaj nowego nauczyciela</h3>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Imię</label>
                            <input type="text" name="imie" required>
                        </div>
                        <div class="form-group">
                            <label>Nazwisko</label>
                            <input type="text" name="nazwisko" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Login</label>
                            <input type="text" name="login" required>
                        </div>
                        <div class="form-group">
                            <label>Hasło</label>
                            <input type="password" name="haslo" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Przedmioty które może uczyć (wybierz kilka)</label>
                        <div style="max-height: 200px; overflow-y: auto; border: 2px solid #e9ecef; padding: 15px; border-radius: 8px;">
                            <?php while ($p = $przedmioty->fetch_assoc()): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="przedmioty[]" value="<?php echo $p['id']; ?>">
                                    <?php echo e($p['nazwa']); ?>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="dodaj" class="btn btn-primary">Dodaj nauczyciela</button>
                </form>
            </div>
            
            <div class="card">
                <h3 class="card-title">Lista nauczycieli</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Imię i nazwisko</th>
                            <th>Email</th>
                            <th>Login</th>
                            <th>Przedmioty</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($n = $nauczyciele->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?></td>
                                <td><?php echo e($n['email']); ?></td>
                                <td><?php echo e($n['login']); ?></td>
                                <td><?php echo e($n['przedmioty'] ?? 'Brak'); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Czy na pewno?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="usun_nauczyciela" value="<?php echo $n['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                            Usuń
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
            </div>
        </div>
    </div>
</body>
</html>
