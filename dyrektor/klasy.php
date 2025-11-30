<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Aktualizacja klasy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktualizuj_klase'])) {
    $klasa_id = $_POST['klasa_id'];
    $wychowawca_id = $_POST['wychowawca_id'] ?: null;
    $rozszerzenie_1 = $_POST['rozszerzenie_1'];
    $rozszerzenie_2 = $_POST['rozszerzenie_2'];
    $ilosc_godzin = $_POST['ilosc_godzin_dziennie'];
    
    $stmt = $conn->prepare("UPDATE klasy SET wychowawca_id = ?, rozszerzenie_1 = ?, rozszerzenie_2 = ?, ilosc_godzin_dziennie = ? WHERE id = ?");
    $stmt->bind_param("issii", $wychowawca_id, $rozszerzenie_1, $rozszerzenie_2, $ilosc_godzin, $klasa_id);
    
    if ($stmt->execute()) {
        $message = 'Dane klasy zostały zaktualizowane';
        $message_type = 'success';
    }
}

// Przypisywanie przedmiotów
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['przypisz_przedmioty'])) {
    $klasa_id = $_POST['klasa_id'];
    
    // Usuń stare przypisania
    $conn->query("DELETE FROM klasa_przedmioty WHERE klasa_id = $klasa_id");
    
    // Dodaj nowe przypisania
    foreach ($_POST['przedmioty'] as $przedmiot_id => $dane) {
        if (!empty($dane['nauczyciel_id']) && !empty($dane['godziny'])) {
            $nauczyciel_id = $dane['nauczyciel_id'];
            $godziny = $dane['godziny'];
            
            $stmt = $conn->prepare("INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $klasa_id, $przedmiot_id, $nauczyciel_id, $godziny);
            $stmt->execute();
        }
    }
    
    $message = 'Przedmioty zostały przypisane do klasy';
    $message_type = 'success';
}

// Pobierz klasy
$klasy = $conn->query("
    SELECT k.*, u.imie, u.nazwisko
    FROM klasy k
    LEFT JOIN nauczyciele n ON k.wychowawca_id = n.id
    LEFT JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    ORDER BY k.nazwa
");

// Pobierz nauczycieli
$nauczyciele = $conn->query("
    SELECT n.id, u.imie, u.nazwisko
    FROM nauczyciele n
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    ORDER BY u.nazwisko, u.imie
");

// Pobierz przedmioty
$przedmioty = $conn->query("SELECT * FROM przedmioty ORDER BY nazwa");

$selected_klasa = null;
if (isset($_GET['klasa_id'])) {
    $klasa_id = $_GET['klasa_id'];
    $selected_klasa = $conn->query("SELECT * FROM klasy WHERE id = $klasa_id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Klasami</title>
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
                <li><a href="nauczyciele.php">Nauczyciele</a></li>
                <li><a href="klasy.php" class="active">Klasy</a></li>
                <li><a href="przedmioty.php">Przedmioty</a></li>
                <li><a href="kalendarz.php">Kalendarz</a></li>
                <li><a href="plan_podglad.php">Podgląd Planu</a></li>
            </ul>
        </nav>
        
        <div class="content">
            <h2 class="page-title">Zarządzanie Klasami</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Wybierz klasę do edycji</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                    <?php 
                    $klasy->data_seek(0);
                    while ($k = $klasy->fetch_assoc()): 
                    ?>
                        <a href="?klasa_id=<?php echo $k['id']; ?>" 
                           class="btn <?php echo (isset($_GET['klasa_id']) && $_GET['klasa_id'] == $k['id']) ? 'btn-primary' : 'btn-secondary'; ?>">
                            <?php echo e($k['nazwa']); ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <?php if ($selected_klasa): ?>
                <div class="card">
                    <h3 class="card-title">Edycja klasy <?php echo e($selected_klasa['nazwa']); ?></h3>
                    <form method="POST">
                        <input type="hidden" name="klasa_id" value="<?php echo $selected_klasa['id']; ?>">
                        
                        <div class="form-group">
                            <label>Wychowawca</label>
                            <select name="wychowawca_id">
                                <option value="">Brak</option>
                                <?php 
                                $nauczyciele->data_seek(0);
                                while ($n = $nauczyciele->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $n['id']; ?>" 
                                        <?php echo ($n['id'] == $selected_klasa['wychowawca_id']) ? 'selected' : ''; ?>>
                                        <?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Rozszerzenie 1</label>
                            <select name="rozszerzenie_1" required>
                                <option value="">Wybierz</option>
                                <option value="Matematyka rozszerzona" <?php echo ($selected_klasa['rozszerzenie_1'] == 'Matematyka rozszerzona') ? 'selected' : ''; ?>>Matematyka rozszerzona</option>
                                <option value="Fizyka rozszerzona" <?php echo ($selected_klasa['rozszerzenie_1'] == 'Fizyka rozszerzona') ? 'selected' : ''; ?>>Fizyka rozszerzona</option>
                                <option value="Język angielski rozszerzony" <?php echo ($selected_klasa['rozszerzenie_1'] == 'Język angielski rozszerzony') ? 'selected' : ''; ?>>Język angielski rozszerzony</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Rozszerzenie 2</label>
                            <select name="rozszerzenie_2" required>
                                <option value="">Wybierz</option>
                                <option value="Matematyka rozszerzona" <?php echo ($selected_klasa['rozszerzenie_2'] == 'Matematyka rozszerzona') ? 'selected' : ''; ?>>Matematyka rozszerzona</option>
                                <option value="Fizyka rozszerzona" <?php echo ($selected_klasa['rozszerzenie_2'] == 'Fizyka rozszerzona') ? 'selected' : ''; ?>>Fizyka rozszerzona</option>
                                <option value="Język angielski rozszerzony" <?php echo ($selected_klasa['rozszerzenie_2'] == 'Język angielski rozszerzony') ? 'selected' : ''; ?>>Język angielski rozszerzony</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Maksymalna liczba godzin dziennie</label>
                            <input type="number" name="ilosc_godzin_dziennie" min="5" max="8" value="<?php echo $selected_klasa['ilosc_godzin_dziennie']; ?>" required>
                        </div>
                        
                        <button type="submit" name="aktualizuj_klase" class="btn btn-primary">Zapisz</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3 class="card-title">Przypisz przedmioty i nauczycieli dla klasy <?php echo e($selected_klasa['nazwa']); ?></h3>
                    <form method="POST">
                        <input type="hidden" name="klasa_id" value="<?php echo $selected_klasa['id']; ?>">
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Przedmiot</th>
                                    <th>Nauczyciel</th>
                                    <th>Godzin/tydzień</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $przedmioty->data_seek(0);
                                while ($p = $przedmioty->fetch_assoc()):
                                    // Pobierz obecne przypisanie
                                    $przypisanie = $conn->query("
                                        SELECT * FROM klasa_przedmioty 
                                        WHERE klasa_id = {$selected_klasa['id']} 
                                        AND przedmiot_id = {$p['id']}
                                    ")->fetch_assoc();
                                ?>
                                    <tr>
                                        <td><?php echo e($p['nazwa']); ?></td>
                                        <td>
                                            <select name="przedmioty[<?php echo $p['id']; ?>][nauczyciel_id]">
                                                <option value="">Brak</option>
                                                <?php 
                                                $nauczyciele->data_seek(0);
                                                while ($n = $nauczyciele->fetch_assoc()): 
                                                ?>
                                                    <option value="<?php echo $n['id']; ?>" 
                                                        <?php echo ($przypisanie && $n['id'] == $przypisanie['nauczyciel_id']) ? 'selected' : ''; ?>>
                                                        <?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="przedmioty[<?php echo $p['id']; ?>][godziny]" 
                                                   value="<?php echo $przypisanie ? $przypisanie['ilosc_godzin_tydzien'] : $p['domyslna_ilosc_godzin']; ?>" 
                                                   min="0" max="10" 
                                                   style="width: 80px;">
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <button type="submit" name="przypisz_przedmioty" class="btn btn-success" style="margin-top: 20px;">
                            Zapisz przypisania przedmiotów
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Wybierz klasę z listy powyżej, aby ją edytować</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
