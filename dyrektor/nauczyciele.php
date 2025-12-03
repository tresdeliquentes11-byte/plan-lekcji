<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie nauczyciela
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj'])) {
    $imie = $_POST['imie'];
    $nazwisko = $_POST['nazwisko'];
    $email = $_POST['email'];
    $login = $_POST['login'];
    $haslo = password_hash($_POST['haslo'], PASSWORD_DEFAULT);
    $przedmioty = $_POST['przedmioty'] ?? [];
    
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
            $stmt = $conn->prepare("INSERT INTO nauczyciel_przedmioty (nauczyciel_id, przedmiot_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $nauczyciel_id, $przedmiot_id);
            $stmt->execute();
        }
        
        $conn->commit();
        $message = 'Nauczyciel został dodany pomyślnie';
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Błąd: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Usuwanie nauczyciela
if (isset($_GET['usun'])) {
    $id = $_GET['usun'];
    $conn->query("DELETE FROM uzytkownicy WHERE id IN (SELECT uzytkownik_id FROM nauczyciele WHERE id = $id)");
    $message = 'Nauczyciel został usunięty';
    $message_type = 'success';
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
</head>
<body>
    <div class="container">
        <header>
            <h1>System Planu Lekcji - Panel Dyrektora</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="plan_generuj.php">Generuj Plan</a></li>
                <li><a href="zastepstwa.php">Zastępstwa</a></li>
                <li><a href="nauczyciele.php" class="active">Nauczyciele</a></li>
                <li><a href="uczniowie.php">Uczniowie</a></li>
                <li><a href="klasy.php">Klasy</a></li>
                <li><a href="przedmioty.php">Przedmioty</a></li>
                <li><a href="sale.php">Sale</a></li>
                <li><a href="kalendarz.php">Kalendarz</a></li>
                <li><a href="plan_podglad.php">Podgląd Planu</a></li>
                <li><a href="dostepnosc.php">Dostępność</a></li>
                <li><a href="ustawienia.php">Ustawienia</a></li>
            </ul>
        </nav>
        
        <div class="content">
            <h2 class="page-title">Zarządzanie Nauczycielami</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Dodaj nowego nauczyciela</h3>
                <form method="POST">
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
                                    <a href="?usun=<?php echo $n['id']; ?>" 
                                       class="btn btn-danger" 
                                       style="padding: 5px 10px; font-size: 12px;"
                                       onclick="return confirm('Czy na pewno?')">Usuń</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
