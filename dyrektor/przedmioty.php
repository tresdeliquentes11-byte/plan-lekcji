<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie przedmiotu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $nazwa = trim($_POST['nazwa']);
        $skrot = trim($_POST['skrot']);
        $czy_rozszerzony = isset($_POST['czy_rozszerzony']) ? 1 : 0;
        $domyslna_ilosc = intval($_POST['domyslna_ilosc_godzin']);

        // Input validation
        if (empty($nazwa) || empty($skrot)) {
            $message = 'Nazwa i skrót są wymagane';
            $message_type = 'error';
        } elseif ($domyslna_ilosc < 0 || $domyslna_ilosc > 10) {
            $message = 'Liczba godzin musi być w zakresie 0-10';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO przedmioty (nazwa, skrot, czy_rozszerzony, domyslna_ilosc_godzin) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $nazwa, $skrot, $czy_rozszerzony, $domyslna_ilosc);

            if ($stmt->execute()) {
                $message = 'Przedmiot został dodany';
                $message_type = 'success';
            } else {
                error_log("Błąd dodawania przedmiotu: " . $stmt->error);
                $message = 'Błąd podczas dodawania przedmiotu';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Usuwanie przedmiotu (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usun_przedmiot'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $id = intval($_POST['usun_przedmiot']);
        $stmt = $conn->prepare("DELETE FROM przedmioty WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = 'Przedmiot został usunięty';
            $message_type = 'success';
        } else {
            error_log("Błąd usuwania przedmiotu: " . $stmt->error);
            $message = 'Błąd podczas usuwania przedmiotu';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Pobierz przedmioty
$przedmioty = $conn->query("SELECT * FROM przedmioty ORDER BY nazwa");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Przedmiotami</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Zarządzanie Przedmiotami</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        
        <div class="admin-content">
            <h2 class="page-title">Zarządzanie Przedmiotami</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Dodaj nowy przedmiot</h3>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                        <div class="form-group">
                            <label>Nazwa przedmiotu</label>
                            <input type="text" name="nazwa" required>
                        </div>
                        <div class="form-group">
                            <label>Skrót</label>
                            <input type="text" name="skrot" maxlength="20" required>
                        </div>
                        <div class="form-group">
                            <label>Domyślna liczba godzin</label>
                            <input type="number" name="domyslna_ilosc_godzin" min="0" max="10" value="2" required>
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0;">
                                <input type="checkbox" name="czy_rozszerzony">
                                Przedmiot rozszerzony
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" name="dodaj" class="btn btn-primary" style="margin-top: 10px;">Dodaj przedmiot</button>
                </form>
            </div>
            
            <div class="card">
                <h3 class="card-title">Lista przedmiotów</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nazwa</th>
                            <th>Skrót</th>
                            <th>Domyślna liczba godzin</th>
                            <th>Typ</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($p = $przedmioty->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo e($p['nazwa']); ?></td>
                                <td><?php echo e($p['skrot']); ?></td>
                                <td><?php echo $p['domyslna_ilosc_godzin']; ?></td>
                                <td><?php echo $p['czy_rozszerzony'] ? 'Rozszerzony' : 'Podstawowy'; ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Czy na pewno? Usunięcie przedmiotu może wpłynąć na istniejący plan.')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="usun_przedmiot" value="<?php echo $p['id']; ?>">
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
</body>
</html>
