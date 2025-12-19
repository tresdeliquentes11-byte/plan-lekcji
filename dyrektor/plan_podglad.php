<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

// Funkcja do pobierania ustawienia
function pobierz_ustawienie($nazwa, $domyslna = '') {
    global $conn;

    // Sprawdź czy tabela istnieje
    $check_table = $conn->query("SHOW TABLES LIKE 'ustawienia_planu'");
    if ($check_table->num_rows == 0) {
        return $domyslna; // Zwróć wartość domyślną jeśli tabela nie istnieje
    }

    $stmt = $conn->prepare("SELECT wartosc FROM ustawienia_planu WHERE nazwa = ?");
    $stmt->bind_param("s", $nazwa);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['wartosc'];
    }
    return $domyslna;
}

// Pobierz ustawienia
$dlugosc_lekcji = intval(pobierz_ustawienie('dlugosc_lekcji', '45'));
$godzina_rozpoczecia = pobierz_ustawienie('godzina_rozpoczecia', '08:00');
$liczba_lekcji = intval(pobierz_ustawienie('liczba_lekcji', '8'));

// Pobierz długości wszystkich przerw
$przerwy = [];
for ($i = 1; $i < $liczba_lekcji; $i++) {
    $przerwy[$i] = intval(pobierz_ustawienie("przerwa_po_$i", $i == 3 ? '15' : '10'));
}

// Pobierz wybraną klasę i tydzień
$klasa_id = $_GET['klasa_id'] ?? null;
$tydzien_offset = $_GET['tydzien'] ?? 0;

// Oblicz daty tygodnia - względem bieżącej daty użytkownika
$current_date = date('Y-m-d');
$current_week_monday = date('Y-m-d', strtotime('monday this week'));

// Ogranicz nawigację tylko do poprzedniego i następnego tygodnia względem bieżącego
if ($tydzien_offset < -1) $tydzien_offset = -1;
if ($tydzien_offset > 1) $tydzien_offset = 1;

$poczatek_tygodnia = date('Y-m-d', strtotime($current_week_monday . ' +' . ($tydzien_offset * 7) . ' days'));
$koniec_tygodnia = date('Y-m-d', strtotime($poczatek_tygodnia . ' +4 days')); // Poniedziałek + 4 dni = Piątek

// Pobierz klasy
$klasy = $conn->query("SELECT * FROM klasy ORDER BY nazwa");

$plan = [];
if ($klasa_id) {
    // Pobierz plan dla wybranej klasy
    $plan_query = $conn->query("
        SELECT pd.*, p.nazwa as przedmiot, p.skrot, 
               u.imie, u.nazwisko, s.numer as sala,
               pd.czy_zastepstwo, u2.imie as oryg_imie, u2.nazwisko as oryg_nazwisko
        FROM plan_dzienny pd
        JOIN przedmioty p ON pd.przedmiot_id = p.id
        JOIN nauczyciele n ON pd.nauczyciel_id = n.id
        JOIN uzytkownicy u ON n.uzytkownik_id = u.id
        LEFT JOIN sale s ON pd.sala_id = s.id
        LEFT JOIN nauczyciele n2 ON pd.oryginalny_nauczyciel_id = n2.id
        LEFT JOIN uzytkownicy u2 ON n2.uzytkownik_id = u2.id
        WHERE pd.klasa_id = $klasa_id
        AND pd.data >= '$poczatek_tygodnia'
        AND pd.data <= '$koniec_tygodnia'
        ORDER BY pd.data, pd.numer_lekcji
    ");
    
    while ($lekcja = $plan_query->fetch_assoc()) {
        $data = $lekcja['data'];
        $numer = $lekcja['numer_lekcji'];
        $plan[$data][$numer] = $lekcja;
    }
}

$dni_tygodnia = [
    $poczatek_tygodnia => 'Poniedziałek',
    date('Y-m-d', strtotime($poczatek_tygodnia . ' +1 day')) => 'Wtorek',
    date('Y-m-d', strtotime($poczatek_tygodnia . ' +2 days')) => 'Środa',
    date('Y-m-d', strtotime($poczatek_tygodnia . ' +3 days')) => 'Czwartek',
    date('Y-m-d', strtotime($poczatek_tygodnia . ' +4 days')) => 'Piątek',
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podgląd Planu</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Podgląd Planu Lekcji</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        
        <div class="admin-content">
            <h2 class="page-title">Podgląd Planu Lekcji</h2>
            
            <div class="plan-header">
                <div>
                    <label style="margin-right: 10px;">Wybierz klasę:</label>
                    <select onchange="location.href='?klasa_id=' + this.value + '&tydzien=<?php echo $tydzien_offset; ?>'" style="padding: 8px; border-radius: 6px; border: 2px solid #e9ecef;">
                        <option value="">-- Wybierz --</option>
                        <?php while ($k = $klasy->fetch_assoc()): ?>
                            <option value="<?php echo $k['id']; ?>" <?php echo ($klasa_id == $k['id']) ? 'selected' : ''; ?>>
                                <?php echo e($k['nazwa']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <?php if ($klasa_id): ?>
                    <div class="week-navigation">
                        <?php if ($tydzien_offset > -1): ?>
                            <a href="?klasa_id=<?php echo $klasa_id; ?>&tydzien=<?php echo $tydzien_offset - 1; ?>" class="btn btn-secondary">← Poprzedni tydzień</a>
                        <?php else: ?>
                            <span class="btn btn-secondary disabled">← Poprzedni tydzień</span>
                        <?php endif; ?>
                        
                        <span class="current-week">
                            <?php
                            if ($tydzien_offset == 0) {
                                echo 'Bieżący tydzień';
                            } elseif ($tydzien_offset == -1) {
                                echo 'Poprzedni tydzień';
                            } elseif ($tydzien_offset == 1) {
                                echo 'Następny tydzień';
                            }
                            ?><br>
                            <small><?php echo formatuj_date($poczatek_tygodnia); ?> - <?php echo formatuj_date($koniec_tygodnia); ?></small>
                        </span>
                        
                        <?php if ($tydzien_offset < 1): ?>
                            <a href="?klasa_id=<?php echo $klasa_id; ?>&tydzien=<?php echo $tydzien_offset + 1; ?>" class="btn btn-secondary">Następny tydzień →</a>
                        <?php else: ?>
                            <span class="btn btn-secondary disabled">Następny tydzień →</span>
                        <?php endif; ?>
                    </div>
                    <script>
                        console.log('Plan podglad - klasa_id: <?php echo $klasa_id; ?>, tydzien_offset: <?php echo $tydzien_offset; ?>');
                        console.log('Początek tygodnia: <?php echo $poczatek_tygodnia; ?>, Koniec tygodnia: <?php echo $koniec_tygodnia; ?>');
                        console.log('Current monday: <?php echo $current_monday; ?>, Current date: <?php echo $current_date; ?>');
                    </script>
                <?php endif; ?>
            </div>
            
            <?php if ($klasa_id): ?>
                <div class="timetable">
                    <table>
                        <thead>
                            <tr>
                                <th class="time-cell">Godzina</th>
                                <?php foreach ($dni_tygodnia as $data => $dzien): ?>
                                    <th><?php echo $dzien; ?><br><small><?php echo formatuj_date($data); ?></small></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($lekcja_nr = 1; $lekcja_nr <= $liczba_lekcji; $lekcja_nr++): ?>
                                <?php
                                // Oblicz czas rozpoczęcia lekcji na podstawie ustawień
                                $start_time = strtotime($godzina_rozpoczecia);

                                // Dodaj czas poprzednich lekcji i przerw
                                for ($i = 1; $i < $lekcja_nr; $i++) {
                                    $start_time += $dlugosc_lekcji * 60; // Dodaj długość lekcji

                                    // Dodaj przerwę po danej lekcji (jeśli istnieje)
                                    if (isset($przerwy[$i])) {
                                        $start_time += $przerwy[$i] * 60;
                                    }
                                }

                                $end_time = $start_time + ($dlugosc_lekcji * 60);
                                ?>
                                <tr>
                                    <td class="time-cell">
                                        <strong><?php echo $lekcja_nr; ?>.</strong><br>
                                        <small><?php echo date('H:i', $start_time); ?> - <?php echo date('H:i', $end_time); ?></small>
                                    </td>
                                    <?php foreach ($dni_tygodnia as $data => $dzien): ?>
                                        <td class="lesson-cell">
                                            <?php if (isset($plan[$data][$lekcja_nr])): ?>
                                                <?php $l = $plan[$data][$lekcja_nr]; ?>
                                                <div class="lesson-card <?php echo $l['czy_zastepstwo'] ? 'zastepstwo' : ''; ?>">
                                                    <div class="lesson-subject"><?php echo e($l['skrot']); ?></div>
                                                    <div class="lesson-teacher">
                                                        <?php echo e($l['imie'] . ' ' . $l['nazwisko']); ?>
                                                        <?php if ($l['czy_zastepstwo']): ?>
                                                            <br><small>(zastępstwo za: <?php echo e($l['oryg_imie'] . ' ' . $l['oryg_nazwisko']); ?>)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($l['sala']): ?>
                                                        <div class="lesson-room">Sala: <?php echo e($l['sala']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Wybierz klasę z listy powyżej, aby zobaczyć plan lekcji</div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
