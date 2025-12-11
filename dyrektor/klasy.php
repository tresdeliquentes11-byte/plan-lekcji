<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Aktualizacja klasy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktualizuj_klase'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $klasa_id = intval($_POST['klasa_id']);
        $wychowawca_id = !empty($_POST['wychowawca_id']) ? intval($_POST['wychowawca_id']) : null;
        $rozszerzenie_1 = $_POST['rozszerzenie_1'];
        $rozszerzenie_2 = $_POST['rozszerzenie_2'];
        $ilosc_godzin = intval($_POST['ilosc_godzin_dziennie']);

        // Input validation
        if ($ilosc_godzin < 5 || $ilosc_godzin > 8) {
            $ilosc_godzin = 7; // Default value
        }

        $stmt = $conn->prepare("UPDATE klasy SET wychowawca_id = ?, rozszerzenie_1 = ?, rozszerzenie_2 = ?, ilosc_godzin_dziennie = ? WHERE id = ?");
        $stmt->bind_param("issii", $wychowawca_id, $rozszerzenie_1, $rozszerzenie_2, $ilosc_godzin, $klasa_id);

        if ($stmt->execute()) {
            $message = 'Dane klasy zostały zaktualizowane';
            $message_type = 'success';
        } else {
            error_log("Błąd aktualizacji klasy: " . $stmt->error);
            $message = 'Wystąpił błąd podczas aktualizacji klasy';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Przypisywanie przedmiotów
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['przypisz_przedmioty'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $klasa_id = intval($_POST['klasa_id']);

        // Usuń stare przypisania (using prepared statement)
        $stmt_delete = $conn->prepare("DELETE FROM klasa_przedmioty WHERE klasa_id = ?");
        $stmt_delete->bind_param("i", $klasa_id);
        $stmt_delete->execute();
        $stmt_delete->close();

        // Dodaj nowe przypisania
        $liczba_przypisanych = 0;
        if (isset($_POST['przedmioty']) && is_array($_POST['przedmioty'])) {
            foreach ($_POST['przedmioty'] as $przedmiot_id => $dane) {
                // Sprawdź czy nauczyciel jest wybrany I liczba godzin jest większa od 0
                if (!empty($dane['nauczyciel_id']) && isset($dane['godziny']) && $dane['godziny'] > 0) {
                    $przedmiot_id = intval($przedmiot_id);
                    $nauczyciel_id = intval($dane['nauczyciel_id']);
                    $godziny = intval($dane['godziny']);

                    // Validate hours
                    if ($godziny < 0 || $godziny > 10) {
                        continue; // Skip invalid entries
                    }

                    $stmt = $conn->prepare("INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiii", $klasa_id, $przedmiot_id, $nauczyciel_id, $godziny);
                    if ($stmt->execute()) {
                        $liczba_przypisanych++;
                    } else {
                        error_log("Błąd przypisywania przedmiotu: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }

        if ($liczba_przypisanych > 0) {
            $message = "Zapisano {$liczba_przypisanych} przedmiotów dla klasy";
            $message_type = 'success';
        } else {
            $message = 'Nie przypisano żadnych przedmiotów. Upewnij się że wybrałeś nauczycieli i ustawiłeś liczbę godzin > 0';
            $message_type = 'warning';
        }
    }
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
    $klasa_id = intval($_GET['klasa_id']);
    $stmt = $conn->prepare("SELECT * FROM klasy WHERE id = ?");
    $stmt->bind_param("i", $klasa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_klasa = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Klasami</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Zarządzanie Klasami</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        
        <div class="admin-content">
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
                        <?php echo csrf_field(); ?>
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
                    
                    <?php
                    // Sprawdź czy są przedmioty bez nauczycieli
                    $przedmioty_bez_nauczycieli = [];
                    $przedmioty->data_seek(0);
                    $stmt_count = $conn->prepare("SELECT COUNT(*) as cnt FROM nauczyciel_przedmioty WHERE przedmiot_id = ?");
                    while ($p = $przedmioty->fetch_assoc()) {
                        $przedmiot_id = intval($p['id']);
                        $stmt_count->bind_param("i", $przedmiot_id);
                        $stmt_count->execute();
                        $count_result = $stmt_count->get_result();
                        $count = $count_result->fetch_assoc()['cnt'];

                        if ($count == 0) {
                            $przedmioty_bez_nauczycieli[] = $p['nazwa'];
                        }
                    }
                    $stmt_count->close();
                    
                    if (count($przedmioty_bez_nauczycieli) > 0):
                    ?>
                        <div class="alert alert-warning">
                            <strong>⚠ Uwaga!</strong> Następujące przedmioty nie mają przypisanych nauczycieli:
                            <ul style="margin: 10px 0;">
                                <?php foreach ($przedmioty_bez_nauczycieli as $przedmiot): ?>
                                    <li><?php echo e($przedmiot); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p>Przejdź do zakładki <a href="nauczyciele.php" style="color: #856404; font-weight: bold;">Nauczyciele</a> aby przypisać nauczycieli do tych przedmiotów.</p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?php echo csrf_field(); ?>
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

                                // Prepare statements outside the loop for better performance
                                $stmt_przypisanie = $conn->prepare("SELECT * FROM klasa_przedmioty WHERE klasa_id = ? AND przedmiot_id = ?");
                                $stmt_liczba_naucz = $conn->prepare("SELECT COUNT(*) as cnt FROM nauczyciel_przedmioty WHERE przedmiot_id = ?");
                                $stmt_nauczyciele = $conn->prepare("
                                    SELECT n.id, u.imie, u.nazwisko
                                    FROM nauczyciele n
                                    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
                                    JOIN nauczyciel_przedmioty np ON n.id = np.nauczyciel_id
                                    WHERE np.przedmiot_id = ?
                                    ORDER BY u.nazwisko, u.imie
                                ");

                                while ($p = $przedmioty->fetch_assoc()):
                                    $przedmiot_id = intval($p['id']);
                                    $klasa_id_int = intval($selected_klasa['id']);

                                    // Pobierz obecne przypisanie
                                    $stmt_przypisanie->bind_param("ii", $klasa_id_int, $przedmiot_id);
                                    $stmt_przypisanie->execute();
                                    $przypisanie_result = $stmt_przypisanie->get_result();
                                    $przypisanie = $przypisanie_result->fetch_assoc();
                                ?>
                                    <tr>
                                        <td>
                                            <?php echo e($p['nazwa']); ?>
                                            <?php
                                            // Policz ilu nauczycieli może uczyć tego przedmiotu
                                            $stmt_liczba_naucz->bind_param("i", $przedmiot_id);
                                            $stmt_liczba_naucz->execute();
                                            $liczba_result = $stmt_liczba_naucz->get_result();
                                            $liczba_nauczycieli = $liczba_result->fetch_assoc()['cnt'];

                                            if ($liczba_nauczycieli > 0):
                                            ?>
                                                <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 10px;">
                                                    <?php echo $liczba_nauczycieli; ?> nauczycieli
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 10px;">
                                                    0 nauczycieli
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="przedmioty[<?php echo $p['id']; ?>][nauczyciel_id]">
                                                <option value="">Brak</option>
                                                <?php
                                                // Pobierz TYLKO nauczycieli którzy uczą tego przedmiotu
                                                $stmt_nauczyciele->bind_param("i", $przedmiot_id);
                                                $stmt_nauczyciele->execute();
                                                $nauczyciele_przedmiotu = $stmt_nauczyciele->get_result();

                                                if ($nauczyciele_przedmiotu->num_rows > 0):
                                                    while ($n = $nauczyciele_przedmiotu->fetch_assoc()):
                                                ?>
                                                    <option value="<?php echo $n['id']; ?>"
                                                        <?php echo ($przypisanie && $n['id'] == $przypisanie['nauczyciel_id']) ? 'selected' : ''; ?>>
                                                        <?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?>
                                                    </option>
                                                <?php
                                                    endwhile;
                                                else:
                                                ?>
                                                    <option value="" disabled style="color: red;">Brak nauczycieli tego przedmiotu</option>
                                                <?php endif; ?>
                                            </select>
                                            <?php if ($nauczyciele_przedmiotu->num_rows == 0): ?>
                                                <small style="color: red; display: block; margin-top: 5px;">
                                                    ⚠ Najpierw przypisz nauczycieli do tego przedmiotu w zakładce "Nauczyciele"
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="przedmioty[<?php echo $p['id']; ?>][godziny]"
                                                   value="<?php echo $przypisanie ? $przypisanie['ilosc_godzin_tydzien'] : $p['domyslna_ilosc_godzin']; ?>"
                                                   min="0" max="10"
                                                   style="width: 80px;">
                                        </td>
                                    </tr>
                                <?php endwhile;

                                // Close prepared statements
                                $stmt_przypisanie->close();
                                $stmt_liczba_naucz->close();
                                $stmt_nauczyciele->close();
                                ?>
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
    </div>
</body>
</html>
