<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('uczen');

// Pobierz klasę ucznia
$uczen = $conn->query("
    SELECT k.* FROM uczniowie u
    JOIN klasy k ON u.klasa_id = k.id
    WHERE u.uzytkownik_id = {$_SESSION['user_id']}
")->fetch_assoc();

if (!$uczen) {
    die("Błąd: Uczeń nie jest przypisany do żadnej klasy");
}

$klasa_id = $uczen['id'];
$klasa_nazwa = $uczen['nazwa'];

// Pobierz tydzień
$tydzien_offset = intval($_GET['tydzien'] ?? 0);

// Oblicz daty tygodnia
$dzisiaj = new DateTime();
$dzien_tygodnia = $dzisiaj->format('N'); // 1 = poniedziałek, 7 = niedziela

// Oblicz poniedziałek bieżącego tygodnia
$poniedzialek_tego_tygodnia = clone $dzisiaj;
$poniedzialek_tego_tygodnia->modify('-' . ($dzien_tygodnia - 1) . ' days');

// Dodaj offset tygodni
$poniedzialek_docelowy = clone $poniedzialek_tego_tygodnia;
$poniedzialek_docelowy->modify(($tydzien_offset > 0 ? '+' : '') . $tydzien_offset . ' weeks');

// Oblicz piątek tego samego tygodnia
$piatek_docelowy = clone $poniedzialek_docelowy;
$piatek_docelowy->modify('+4 days');

$poczatek_tygodnia = $poniedzialek_docelowy->format('Y-m-d');
$koniec_tygodnia = $piatek_docelowy->format('Y-m-d');

// Pobierz plan
$plan_query = $conn->query("
    SELECT pd.*, p.nazwa as przedmiot, p.skrot,
           u.imie, u.nazwisko, s.numer as sala,
           pd.czy_zastepstwo
    FROM plan_dzienny pd
    JOIN przedmioty p ON pd.przedmiot_id = p.id
    JOIN nauczyciele n ON pd.nauczyciel_id = n.id
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    LEFT JOIN sale s ON pd.sala_id = s.id
    WHERE pd.klasa_id = $klasa_id
    AND pd.data >= '$poczatek_tygodnia'
    AND pd.data <= '$koniec_tygodnia'
    ORDER BY pd.data, pd.numer_lekcji
");

$plan = [];
while ($lekcja = $plan_query->fetch_assoc()) {
    $data = $lekcja['data'];
    $numer = $lekcja['numer_lekcji'];
    $plan[$data][$numer] = $lekcja;
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
    <title>Plan Lekcji - Klasa <?php echo e($klasa_nazwa); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .student-layout {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .student-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .student-header {
            background: white;
            border-radius: 15px;
            padding: 25px 35px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .class-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
            color: #2c3e50;
            font-weight: 500;
        }

        .btn-logout {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .btn-settings {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-settings:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .plan-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .week-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .week-navigation button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .week-navigation button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .current-week {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .timetable {
            overflow-x: auto;
        }

        .timetable table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .timetable th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .timetable th:first-child {
            border-top-left-radius: 10px;
        }

        .timetable th:last-child {
            border-top-right-radius: 10px;
        }

        .timetable th small {
            display: block;
            margin-top: 5px;
            opacity: 0.9;
            font-size: 12px;
        }

        .time-cell {
            background: #f8f9fa !important;
            font-weight: 600;
            color: #2c3e50 !important;
            text-align: center;
            padding: 15px 10px !important;
            border-right: 2px solid #e9ecef;
            width: 100px;
        }

        .time-cell strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .time-cell small {
            color: #6c757d;
            font-size: 11px;
        }

        .lesson-cell {
            padding: 8px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            vertical-align: top;
            min-height: 80px;
        }

        .lesson-card {
            background: white;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 12px;
            min-height: 75px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .lesson-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .lesson-card.zastepstwo {
            border-left-color: #f39c12;
            background: #fff8e1;
        }

        .lesson-subject {
            font-weight: 700;
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .lesson-teacher {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
        }

        .lesson-teacher small {
            color: #f39c12;
            font-weight: 600;
        }

        .lesson-room {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 6px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .student-header {
                flex-direction: column;
                gap: 15px;
                padding: 20px;
            }

            .student-header h1 {
                font-size: 20px;
                flex-direction: column;
                text-align: center;
            }

            .week-navigation {
                flex-direction: column;
                gap: 15px;
            }

            .week-navigation button {
                width: 100%;
            }

            .timetable th,
            .timetable td {
                font-size: 12px;
                padding: 8px;
            }

            .time-cell {
                width: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="student-layout">
        <div class="student-container">
            <header class="student-header">
                <h1>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    Plan Lekcji
                    <span class="class-badge">Klasa <?php echo e($klasa_nazwa); ?></span>
                </h1>
                <div class="user-info">
                    <span><?php echo e($_SESSION['user_name']); ?></span>
                    <a href="zmiana_hasla.php" class="btn-settings">Zmiana hasła</a>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="plan-card">
                <div class="week-navigation">
                    <button onclick="location.href='?tydzien=<?php echo $tydzien_offset - 1; ?>'">
                        ← Poprzedni tydzień
                    </button>
                    <span class="current-week">
                        📅 <?php echo formatuj_date($poczatek_tygodnia); ?> - <?php echo formatuj_date($koniec_tygodnia); ?>
                    </span>
                    <button onclick="location.href='?tydzien=<?php echo $tydzien_offset + 1; ?>'">
                        Następny tydzień →
                    </button>
                </div>

                <div class="timetable">
                    <table>
                        <thead>
                            <tr>
                                <th class="time-cell">Godzina</th>
                                <?php foreach ($dni_tygodnia as $data => $dzien): ?>
                                    <th>
                                        <?php echo $dzien; ?>
                                        <small><?php echo formatuj_date($data); ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($lekcja_nr = 1; $lekcja_nr <= 8; $lekcja_nr++): ?>
                                <?php
                                $start_time = strtotime('08:00') + (($lekcja_nr - 1) * 55 * 60);
                                $end_time = $start_time + (45 * 60);
                                ?>
                                <tr>
                                    <td class="time-cell">
                                        <strong><?php echo $lekcja_nr; ?>.</strong>
                                        <small><?php echo date('H:i', $start_time); ?> - <?php echo date('H:i', $end_time); ?></small>
                                    </td>
                                    <?php foreach ($dni_tygodnia as $data => $dzien): ?>
                                        <td class="lesson-cell">
                                            <?php if (isset($plan[$data][$lekcja_nr])): ?>
                                                <?php $l = $plan[$data][$lekcja_nr]; ?>
                                                <div class="lesson-card <?php echo $l['czy_zastepstwo'] ? 'zastepstwo' : ''; ?>">
                                                    <div class="lesson-subject"><?php echo e($l['przedmiot']); ?></div>
                                                    <div class="lesson-teacher">
                                                        <?php echo e($l['imie'] . ' ' . $l['nazwisko']); ?>
                                                        <?php if ($l['czy_zastepstwo']): ?>
                                                            <br><small>⚠️ ZASTĘPSTWO</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($l['sala']): ?>
                                                        <div class="lesson-room">📍 Sala: <?php echo e($l['sala']); ?></div>
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
            </div>
        </div>
    </div>
</body>
</html>
