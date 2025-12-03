<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie przedmiotu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj'])) {
    $nazwa = $_POST['nazwa'];
    $skrot = $_POST['skrot'];
    $czy_rozszerzony = isset($_POST['czy_rozszerzony']) ? 1 : 0;
    $domyslna_ilosc = $_POST['domyslna_ilosc_godzin'];
    
    $stmt = $conn->prepare("INSERT INTO przedmioty (nazwa, skrot, czy_rozszerzony, domyslna_ilosc_godzin) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $nazwa, $skrot, $czy_rozszerzony, $domyslna_ilosc);
    
    if ($stmt->execute()) {
        $message = 'Przedmiot został dodany';
        $message_type = 'success';
    } else {
        $message = 'Błąd podczas dodawania przedmiotu';
        $message_type = 'error';
    }
}

// Usuwanie przedmiotu
if (isset($_GET['usun'])) {
    $id = $_GET['usun'];
    if ($conn->query("DELETE FROM przedmioty WHERE id = $id")) {
        $message = 'Przedmiot został usunięty';
        $message_type = 'success';
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
                                    <a href="?usun=<?php echo $p['id']; ?>" 
                                       class="btn btn-danger" 
                                       style="padding: 5px 10px; font-size: 12px;"
                                       onclick="return confirm('Czy na pewno? Usunięcie przedmiotu może wpłynąć na istniejący plan.')">Usuń</a>
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
