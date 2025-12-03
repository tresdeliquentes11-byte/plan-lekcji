<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Sprawdź czy tabela istnieje, jeśli nie - utwórz ją
$check_table = $conn->query("SHOW TABLES LIKE 'nauczyciel_godziny_pracy'");
if ($check_table->num_rows == 0) {
    // Usuń starą tabelę jeśli istnieje
    $conn->query("DROP TABLE IF EXISTS nauczyciel_dostepnosc");

    // Utwórz nową tabelę
    $conn->query("
        CREATE TABLE nauczyciel_godziny_pracy (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nauczyciel_id INT NOT NULL,
            dzien_tygodnia INT NOT NULL COMMENT '1-5',
            godzina_od TIME NOT NULL,
            godzina_do TIME NOT NULL,
            utworzono TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            zaktualizowano TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (nauczyciel_id) REFERENCES nauczyciele(id) ON DELETE CASCADE,
            UNIQUE KEY unique_nauczyciel_dzien (nauczyciel_id, dzien_tygodnia),
            INDEX idx_nauczyciel (nauczyciel_id),
            INDEX idx_dzien (dzien_tygodnia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Zapisywanie godzin pracy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zapisz'])) {
    $nauczyciel_id = intval($_POST['nauczyciel_id']);

    try {
        $conn->begin_transaction();

        // Usuń stare godziny
        $stmt = $conn->prepare("DELETE FROM nauczyciel_godziny_pracy WHERE nauczyciel_id = ?");
        $stmt->bind_param("i", $nauczyciel_id);
        $stmt->execute();

        // Dodaj nowe godziny dla każdego dnia
        for ($dzien = 1; $dzien <= 5; $dzien++) {
            $pracuje = isset($_POST["pracuje_$dzien"]);

            if ($pracuje) {
                $godzina_od = $_POST["godzina_od_$dzien"];
                $godzina_do = $_POST["godzina_do_$dzien"];

                // Walidacja - sprawdź czy godziny są wypełnione
                if (!empty($godzina_od) && !empty($godzina_do)) {
                    // Walidacja - sprawdź czy godzina rozpoczęcia jest wcześniejsza niż zakończenia
                    if ($godzina_od >= $godzina_do) {
                        throw new Exception("Godzina rozpoczęcia musi być wcześniejsza niż godzina zakończenia (dzień: " . $dni_tygodnia[$dzien] . ")");
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO nauczyciel_godziny_pracy (nauczyciel_id, dzien_tygodnia, godzina_od, godzina_do)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiss", $nauczyciel_id, $dzien, $godzina_od, $godzina_do);
                    $stmt->execute();
                }
            }
        }

        $conn->commit();
        $message = 'Godziny pracy zostały zapisane pomyślnie';
        $message_type = 'success';
        loguj_aktywnosc($_SESSION['user_id'], 'edycja', "Zaktualizowano godziny pracy nauczyciela ID: $nauczyciel_id");
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Błąd podczas zapisywania: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Pobierz wybranego nauczyciela
$wybrany_nauczyciel = isset($_GET['nauczyciel']) ? intval($_GET['nauczyciel']) : null;

// Pobierz wszystkich nauczycieli z ich godzinami pracy dla podglądu
$harmonogram = [];
$wszyscy_nauczyciele = $conn->query("
    SELECT n.id, u.imie, u.nazwisko
    FROM nauczyciele n
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    ORDER BY u.nazwisko, u.imie
");

while ($n = $wszyscy_nauczyciele->fetch_assoc()) {
    $nauczyciel_id = $n['id'];
    $harmonogram[$nauczyciel_id] = [
        'imie' => $n['imie'],
        'nazwisko' => $n['nazwisko'],
        'godziny' => []
    ];

    // Pobierz godziny dla tego nauczyciela
    $godziny_query = $conn->prepare("
        SELECT dzien_tygodnia, godzina_od, godzina_do
        FROM nauczyciel_godziny_pracy
        WHERE nauczyciel_id = ?
        ORDER BY dzien_tygodnia
    ");
    $godziny_query->bind_param("i", $nauczyciel_id);
    $godziny_query->execute();
    $result = $godziny_query->get_result();

    while ($g = $result->fetch_assoc()) {
        $harmonogram[$nauczyciel_id]['godziny'][$g['dzien_tygodnia']] = [
            'od' => substr($g['godzina_od'], 0, 5),
            'do' => substr($g['godzina_do'], 0, 5)
        ];
    }
}

// Pobierz listę nauczycieli dla dropdowna
$nauczyciele = $conn->query("
    SELECT n.id, u.imie, u.nazwisko
    FROM nauczyciele n
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    ORDER BY u.nazwisko, u.imie
");

// Pobierz godziny pracy dla wybranego nauczyciela
$godziny_pracy = [];
if ($wybrany_nauczyciel) {
    $stmt = $conn->prepare("
        SELECT dzien_tygodnia, godzina_od, godzina_do
        FROM nauczyciel_godziny_pracy
        WHERE nauczyciel_id = ?
    ");
    $stmt->bind_param("i", $wybrany_nauczyciel);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $godziny_pracy[$row['dzien_tygodnia']] = [
            'od' => substr($row['godzina_od'], 0, 5),
            'do' => substr($row['godzina_do'], 0, 5)
        ];
    }
}

$dni_tygodnia = [
    1 => 'Poniedziałek',
    2 => 'Wtorek',
    3 => 'Środa',
    4 => 'Czwartek',
    5 => 'Piątek'
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Godziny Pracy Nauczycieli - Panel Dyrektora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .hours-table {
            width: 100%;
            margin-top: 20px;
        }
        .hours-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
        }
        .hours-table td {
            padding: 10px;
            vertical-align: middle;
        }
        .hours-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .hours-inputs input[type="time"] {
            padding: 6px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
        }
        .hours-inputs input[type="time"]:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .checkbox-cell {
            width: 100px;
            text-align: center;
        }
        .day-name {
            font-weight: 500;
            width: 150px;
        }
        .schedule-overview {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .schedule-overview th {
            background-color: #495057;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #dee2e6;
        }
        .schedule-overview td {
            padding: 10px;
            border: 1px solid #dee2e6;
            text-align: center;
        }
        .schedule-overview tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .schedule-overview tr:hover {
            background-color: #e9ecef;
        }
        .teacher-name {
            text-align: left;
            font-weight: 500;
            padding-left: 15px !important;
        }
        .hours-cell {
            font-size: 14px;
            color: #28a745;
            font-weight: 500;
        }
        .no-hours {
            color: #dc3545;
            font-weight: 500;
        }
    </style>
    <script>
        function toggleDay(dzien) {
            const checkbox = document.getElementById('pracuje_' + dzien);
            const odInput = document.getElementById('godzina_od_' + dzien);
            const doInput = document.getElementById('godzina_do_' + dzien);

            if (checkbox.checked) {
                odInput.disabled = false;
                doInput.disabled = false;
                if (!odInput.value) odInput.value = '08:00';
                if (!doInput.value) doInput.value = '16:00';
            } else {
                odInput.disabled = true;
                doInput.disabled = true;
            }
        }

        function zaznaczWszystkie() {
            for (let i = 1; i <= 5; i++) {
                const checkbox = document.getElementById('pracuje_' + i);
                if (!checkbox.checked) {
                    checkbox.checked = true;
                    toggleDay(i);
                }
            }
        }

        function odznaczWszystkie() {
            for (let i = 1; i <= 5; i++) {
                const checkbox = document.getElementById('pracuje_' + i);
                checkbox.checked = false;
                toggleDay(i);
            }
        }

        function walidujFormularz() {
            const dniNazwy = {
                1: 'Poniedziałek',
                2: 'Wtorek',
                3: 'Środa',
                4: 'Czwartek',
                5: 'Piątek'
            };

            for (let dzien = 1; dzien <= 5; dzien++) {
                const checkbox = document.getElementById('pracuje_' + dzien);

                if (checkbox.checked) {
                    const odInput = document.getElementById('godzina_od_' + dzien);
                    const doInput = document.getElementById('godzina_do_' + dzien);
                    const od = odInput.value;
                    const do_val = doInput.value;

                    // Sprawdź czy pola są wypełnione
                    if (!od || !do_val) {
                        alert('Proszę wypełnić godziny dla dnia: ' + dniNazwy[dzien]);
                        odInput.focus();
                        return false;
                    }

                    // Sprawdź czy godzina rozpoczęcia jest wcześniejsza niż zakończenia
                    if (od >= do_val) {
                        alert('Godzina rozpoczęcia musi być wcześniejsza niż godzina zakończenia!\nDzień: ' + dniNazwy[dzien] + '\nOd: ' + od + ', Do: ' + do_val);
                        odInput.focus();
                        return false;
                    }
                }
            }

            return true;
        }
    </script>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Dostępność Nauczycieli</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>


        <div class="admin-content">
            <h2 class="page-title">Godziny Pracy Nauczycieli</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 class="card-title">Harmonogram Pracy Nauczycieli</h3>

                <?php if (count($harmonogram) > 0): ?>
                    <table class="schedule-overview">
                        <thead>
                            <tr>
                                <th style="width: 200px;">Nauczyciel</th>
                                <th>Poniedziałek</th>
                                <th>Wtorek</th>
                                <th>Środa</th>
                                <th>Czwartek</th>
                                <th>Piątek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($harmonogram as $nauczyciel_id => $dane): ?>
                                <tr>
                                    <td class="teacher-name">
                                        <a href="?nauczyciel=<?php echo $nauczyciel_id; ?>" style="color: #007bff; text-decoration: none;">
                                            <?php echo e($dane['imie'] . ' ' . $dane['nazwisko']); ?>
                                        </a>
                                    </td>
                                    <?php for ($dzien = 1; $dzien <= 5; $dzien++): ?>
                                        <td>
                                            <?php if (isset($dane['godziny'][$dzien])): ?>
                                                <span class="hours-cell">
                                                    <?php echo $dane['godziny'][$dzien]['od']; ?> - <?php echo $dane['godziny'][$dzien]['do']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="no-hours">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Brak nauczycieli w systemie</div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 class="card-title">Edytuj Godziny Pracy</h3>
                <form method="GET">
                    <div class="form-group">
                        <label for="nauczyciel">Nauczyciel</label>
                        <select id="nauczyciel" name="nauczyciel" onchange="this.form.submit()" style="width: 100%; max-width: 400px;">
                            <option value="">-- Wybierz nauczyciela --</option>
                            <?php while ($n = $nauczyciele->fetch_assoc()): ?>
                                <option value="<?php echo $n['id']; ?>" <?php echo ($wybrany_nauczyciel == $n['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($wybrany_nauczyciel): ?>
                <div class="card">
                    <h3 class="card-title">Godziny Pracy</h3>

                    <div class="alert alert-info">
                        <strong>Informacja:</strong> Zaznacz dni, w które nauczyciel pracuje i ustaw godziny pracy.
                        Jeśli dzień nie jest zaznaczony, nauczyciel będzie niedostępny i nie będzie mógł prowadzić lekcji tego dnia.
                    </div>

                    <form method="POST" onsubmit="return walidujFormularz()">
                        <input type="hidden" name="nauczyciel_id" value="<?php echo $wybrany_nauczyciel; ?>">

                        <div style="margin-bottom: 15px;">
                            <button type="button" onclick="zaznaczWszystkie()" class="btn btn-secondary" style="margin-right: 10px;">
                                Zaznacz wszystkie
                            </button>
                            <button type="button" onclick="odznaczWszystkie()" class="btn btn-secondary">
                                Odznacz wszystkie
                            </button>
                        </div>

                        <table class="hours-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">Pracuje</th>
                                    <th class="day-name">Dzień tygodnia</th>
                                    <th>Godzina od</th>
                                    <th>Godzina do</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dni_tygodnia as $nr => $nazwa): ?>
                                    <?php
                                    $pracuje = isset($godziny_pracy[$nr]);
                                    $godzina_od = $pracuje ? $godziny_pracy[$nr]['od'] : '08:00';
                                    $godzina_do = $pracuje ? $godziny_pracy[$nr]['do'] : '16:00';
                                    ?>
                                    <tr>
                                        <td class="checkbox-cell">
                                            <input type="checkbox"
                                                   id="pracuje_<?php echo $nr; ?>"
                                                   name="pracuje_<?php echo $nr; ?>"
                                                   onchange="toggleDay(<?php echo $nr; ?>)"
                                                   <?php echo $pracuje ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="day-name">
                                            <label for="pracuje_<?php echo $nr; ?>" style="cursor: pointer; margin: 0;">
                                                <?php echo $nazwa; ?>
                                            </label>
                                        </td>
                                        <td>
                                            <input type="time"
                                                   id="godzina_od_<?php echo $nr; ?>"
                                                   name="godzina_od_<?php echo $nr; ?>"
                                                   value="<?php echo $godzina_od; ?>"
                                                   <?php echo !$pracuje ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="time"
                                                   id="godzina_do_<?php echo $nr; ?>"
                                                   name="godzina_do_<?php echo $nr; ?>"
                                                   value="<?php echo $godzina_do; ?>"
                                                   <?php echo !$pracuje ? 'disabled' : ''; ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="margin-top: 20px;">
                            <button type="submit" name="zapisz" class="btn btn-primary">Zapisz godziny pracy</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Wybierz nauczyciela z listy powyżej, aby ustawić godziny pracy</div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
