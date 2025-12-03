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

// Oblicz daty tygodnia
$current_date = date('Y-m-d');
$poczatek_tygodnia = date('Y-m-d', strtotime("monday this week +" . ($tydzien_offset * 7) . " days"));
$koniec_tygodnia = date('Y-m-d', strtotime("friday this week +" . ($tydzien_offset * 7) . " days"));

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
                <li><a href="uczniowie.php">Uczniowie</a></li>
                <li><a href="klasy.php">Klasy</a></li>
                <li><a href="przedmioty.php">Przedmioty</a></li>
                <li><a href="sale.php">Sale</a></li>
                <li><a href="kalendarz.php">Kalendarz</a></li>
                <li><a href="plan_podglad.php" class="active">Podgląd Planu</a></li>
                <li><a href="dostepnosc.php">Dostępność</a></li>
                <li><a href="ustawienia.php">Ustawienia</a></li>
            </ul>
        </nav>
        
        <div class="content">
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
                        <button onclick="location.href='?klasa_id=<?php echo $klasa_id; ?>&tydzien=<?php echo $tydzien_offset - 1; ?>'">← Poprzedni tydzień</button>
                        <span class="current-week">
                            <?php echo formatuj_date($poczatek_tygodnia); ?> - <?php echo formatuj_date($koniec_tygodnia); ?>
                        </span>
                        <button onclick="location.href='?klasa_id=<?php echo $klasa_id; ?>&tydzien=<?php echo $tydzien_offset + 1; ?>'">Następny tydzień →</button>
                    </div>
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
</body>
</html>
