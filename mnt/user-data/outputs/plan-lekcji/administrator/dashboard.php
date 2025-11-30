<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('administrator');

$message = '';
$message_type = '';

// Dodawanie ucznia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj'])) {
    $imie = $_POST['imie'];
    $nazwisko = $_POST['nazwisko'];
    $klasa_id = $_POST['klasa_id'];
    $login = $_POST['login'];
    $haslo = password_hash($_POST['haslo'], PASSWORD_DEFAULT);
    
    $conn->begin_transaction();
    
    try {
        // Dodaj użytkownika
        $stmt = $conn->prepare("INSERT INTO uzytkownicy (login, haslo, typ, imie, nazwisko) VALUES (?, ?, 'uczen', ?, ?)");
        $stmt->bind_param("ssss", $login, $haslo, $imie, $nazwisko);
        $stmt->execute();
        $uzytkownik_id = $conn->insert_id;
        
        // Dodaj ucznia
        $stmt = $conn->prepare("INSERT INTO uczniowie (uzytkownik_id, klasa_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $uzytkownik_id, $klasa_id);
        $stmt->execute();
        
        $conn->commit();
        $message = 'Uczeń został dodany pomyślnie';
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Błąd: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Usuwanie ucznia
if (isset($_GET['usun'])) {
    $id = $_GET['usun'];
    $conn->query("DELETE FROM uzytkownicy WHERE id IN (SELECT uzytkownik_id FROM uczniowie WHERE id = $id)");
    $message = 'Uczeń został usunięty';
    $message_type = 'success';
}

// Pobierz uczniów
$uczniowie = $conn->query("
    SELECT uc.id, u.imie, u.nazwisko, u.login, k.nazwa as klasa
    FROM uczniowie uc
    JOIN uzytkownicy u ON uc.uzytkownik_id = u.id
    JOIN klasy k ON uc.klasa_id = k.id
    ORDER BY k.nazwa, u.nazwisko, u.imie
");

// Pobierz klasy
$klasy = $conn->query("SELECT * FROM klasy ORDER BY nazwa");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>System Planu Lekcji - Panel Administratora</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        <div class="content">
            <h2 class="page-title">Zarządzanie Kontami Uczniów</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Dodaj nowego ucznia</h3>
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
                            <label>Klasa</label>
                            <select name="klasa_id" required>
                                <option value="">Wybierz klasę</option>
                                <?php while ($k = $klasy->fetch_assoc()): ?>
                                    <option value="<?php echo $k['id']; ?>"><?php echo e($k['nazwa']); ?></option>
                                <?php endwhile; ?>
                            </select>
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
                    
                    <button type="submit" name="dodaj" class="btn btn-primary">Dodaj ucznia</button>
                </form>
            </div>
            
            <div class="card">
                <h3 class="card-title">Lista uczniów</h3>
                
                <?php if ($uczniowie->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Klasa</th>
                                <th>Imię i nazwisko</th>
                                <th>Login</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $uczniowie->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($u['klasa']); ?></td>
                                    <td><?php echo e($u['imie'] . ' ' . $u['nazwisko']); ?></td>
                                    <td><?php echo e($u['login']); ?></td>
                                    <td>
                                        <a href="?usun=<?php echo $u['id']; ?>" 
                                           class="btn btn-danger" 
                                           style="padding: 5px 10px; font-size: 12px;"
                                           onclick="return confirm('Czy na pewno?')">Usuń</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Brak uczniów w systemie</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
