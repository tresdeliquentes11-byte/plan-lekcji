<?php
require_once '../includes/config.php';
require_once '../includes/generator_zastepstw.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie nieobecności
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_nieobecnosc'])) {
    $nauczyciel_id = $_POST['nauczyciel_id'];
    $data_od = $_POST['data_od'];
    $data_do = $_POST['data_do'];
    $powod = $_POST['powod'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO nieobecnosci (nauczyciel_id, data_od, data_do, powod) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $nauczyciel_id, $data_od, $data_do, $powod);
    
    if ($stmt->execute()) {
        $nieobecnosc_id = $conn->insert_id;
        
        // Generujemy zastępstwa
        $generator = new GeneratorZastepstw($conn);
        $wynik = $generator->generujZastepstwa($nieobecnosc_id);
        
        $message = "Nieobecność została dodana. Utworzono {$wynik['utworzone']} zastępstw.";
        if (count($wynik['pominiete']) > 0) {
            $message .= " Pominięto " . count($wynik['pominiete']) . " lekcji (początkowe/końcowe godziny klasy).";
        }
        if (count($wynik['niemozliwe']) > 0) {
            $message .= " Nie udało się utworzyć zastępstw dla " . count($wynik['niemozliwe']) . " lekcji (brak dostępnych nauczycieli).";
        }
        $message_type = 'success';
    } else {
        $message = 'Błąd podczas dodawania nieobecności';
        $message_type = 'error';
    }
}

// Usuwanie nieobecności
if (isset($_GET['usun'])) {
    $id = $_GET['usun'];
    
    $generator = new GeneratorZastepstw($conn);
    $generator->usunZastepstwa($id);
    
    $conn->query("DELETE FROM nieobecnosci WHERE id = $id");
    $message = 'Nieobecność została usunięta wraz z zastępstwami';
    $message_type = 'success';
}

// Pobieramy listę nauczycieli
$nauczyciele = $conn->query("
    SELECT n.id, u.imie, u.nazwisko
    FROM nauczyciele n
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    ORDER BY u.nazwisko, u.imie
");

// Pobieramy nieobecności
$nieobecnosci = $conn->query("
    SELECT n.*, u.imie, u.nazwisko,
           (SELECT COUNT(*) FROM zastepstwa z WHERE z.nieobecnosc_id = n.id) as liczba_zastepstw
    FROM nieobecnosci n
    JOIN nauczyciele na ON n.nauczyciel_id = na.id
    JOIN uzytkownicy u ON na.uzytkownik_id = u.id
    ORDER BY n.data_od DESC
");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Zastępstwami - Panel Dyrektora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Zarządzanie Zastępstwami</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        
        <div class="admin-content">
            <h2 class="page-title">Zarządzanie Zastępstwami</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Dodaj nieobecność nauczyciela</h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="nauczyciel_id">Nauczyciel</label>
                        <select id="nauczyciel_id" name="nauczyciel_id" required>
                            <option value="">Wybierz nauczyciela</option>
                            <?php while ($n = $nauczyciele->fetch_assoc()): ?>
                                <option value="<?php echo $n['id']; ?>">
                                    <?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_od">Data od</label>
                        <input type="date" id="data_od" name="data_od" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_do">Data do</label>
                        <input type="date" id="data_do" name="data_do" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="powod">Powód (opcjonalnie)</label>
                        <input type="text" id="powod" name="powod" placeholder="np. choroba, urlop">
                    </div>
                    
                    <button type="submit" name="dodaj_nieobecnosc" class="btn btn-primary">
                        Dodaj nieobecność i wygeneruj zastępstwa
                    </button>
                </form>
                
                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>Informacja:</strong> System automatycznie wygeneruje zastępstwa dla wszystkich lekcji nieobecnego nauczyciela według następujących zasad:
                    <ul style="margin-top: 10px; margin-bottom: 0;">
                        <li><strong>Priorytet 1:</strong> Nauczyciel tego samego przedmiotu</li>
                        <li><strong>Priorytet 2:</strong> Nauczyciel innego przedmiotu, którego uczy się dana klasa</li>
                        <li><strong>Pomijane (tylko gdy brak nauczyciela):</strong> Lekcje na pierwszej lub ostatniej godzinie dnia klasy mogą zostać pominięte, jeśli nie ma dostępnego nauczyciela (uczniowie mogą przyjść później/wyjść wcześniej)</li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Lista nieobecności</h3>
                
                <?php
                $nieobecnosci->data_seek(0); // Reset wskaźnika
                if ($nieobecnosci->num_rows > 0):
                ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nauczyciel</th>
                                <th>Data od</th>
                                <th>Data do</th>
                                <th>Powód</th>
                                <th>Zastępstw</th>
                                <th>Data zgłoszenia</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($n = $nieobecnosci->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?></td>
                                    <td><?php echo formatuj_date($n['data_od']); ?></td>
                                    <td><?php echo formatuj_date($n['data_do']); ?></td>
                                    <td><?php echo e($n['powod'] ?? '-'); ?></td>
                                    <td><?php echo $n['liczba_zastepstw']; ?></td>
                                    <td><?php echo formatuj_date($n['data_zgloszenia']); ?></td>
                                    <td>
                                        <a href="?usun=<?php echo $n['id']; ?>" 
                                           class="btn btn-danger" 
                                           style="padding: 5px 10px; font-size: 12px;"
                                           onclick="return confirm('Czy na pewno chcesz usunąć tę nieobecność? Wszystkie zastępstwa zostaną anulowane.')">
                                            Usuń
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Brak zgłoszonych nieobecności</div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
</body>
</html>
