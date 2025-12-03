<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

// Automatyczne tworzenie tabeli jeśli nie istnieje i dodanie domyślnych przerw
$check_table = $conn->query("SHOW TABLES LIKE 'ustawienia_planu'");
if ($check_table->num_rows == 0) {
    // Utwórz tabelę
    $conn->query("
        CREATE TABLE `ustawienia_planu` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nazwa` varchar(100) NOT NULL,
          `wartosc` varchar(255) NOT NULL,
          `opis` text,
          `data_modyfikacji` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `nazwa` (`nazwa`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Dodaj domyślne ustawienia z indywidualnymi przerwami
    $conn->query("
        INSERT INTO `ustawienia_planu` (`nazwa`, `wartosc`, `opis`) VALUES
        ('dlugosc_lekcji', '45', 'Długość jednej lekcji w minutach'),
        ('godzina_rozpoczecia', '08:00', 'Godzina rozpoczęcia pierwszej lekcji'),
        ('liczba_lekcji', '8', 'Maksymalna liczba lekcji w dniu'),
        ('przerwa_po_1', '10', 'Długość przerwy po 1 lekcji (w minutach)'),
        ('przerwa_po_2', '10', 'Długość przerwy po 2 lekcji (w minutach)'),
        ('przerwa_po_3', '15', 'Długość przerwy po 3 lekcji (w minutach)'),
        ('przerwa_po_4', '10', 'Długość przerwy po 4 lekcji (w minutach)'),
        ('przerwa_po_5', '10', 'Długość przerwy po 5 lekcji (w minutach)'),
        ('przerwa_po_6', '10', 'Długość przerwy po 6 lekcji (w minutach)'),
        ('przerwa_po_7', '10', 'Długość przerwy po 7 lekcji (w minutach)'),
        ('przerwa_po_8', '10', 'Długość przerwy po 8 lekcji (w minutach)'),
        ('przerwa_po_9', '10', 'Długość przerwy po 9 lekcji (w minutach)')
    ");
}

$message = '';
$message_type = '';

// Funkcja do pobierania ustawienia
function pobierz_ustawienie($nazwa, $domyslna = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT wartosc FROM ustawienia_planu WHERE nazwa = ?");
    $stmt->bind_param("s", $nazwa);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['wartosc'];
    }
    return $domyslna;
}

// Funkcja do zapisywania ustawienia
function zapisz_ustawienie($nazwa, $wartosc, $opis = '') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO ustawienia_planu (nazwa, wartosc, opis) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE wartosc = ?, data_modyfikacji = CURRENT_TIMESTAMP");
    $stmt->bind_param("ssss", $nazwa, $wartosc, $opis, $wartosc);
    return $stmt->execute();
}

// Zapisywanie ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zapisz'])) {
    try {
        $liczba_lekcji = intval($_POST['liczba_lekcji']);

        zapisz_ustawienie('dlugosc_lekcji', $_POST['dlugosc_lekcji'], 'Długość jednej lekcji w minutach');
        zapisz_ustawienie('godzina_rozpoczecia', $_POST['godzina_rozpoczecia'], 'Godzina rozpoczęcia pierwszej lekcji');
        zapisz_ustawienie('liczba_lekcji', $liczba_lekcji, 'Maksymalna liczba lekcji w dniu');

        // Zapisz indywidualne przerwy (do max 10 lekcji)
        for ($i = 1; $i < $liczba_lekcji; $i++) {
            if (isset($_POST["przerwa_po_$i"])) {
                zapisz_ustawienie("przerwa_po_$i", $_POST["przerwa_po_$i"], "Długość przerwy po $i lekcji (w minutach)");
            }
        }

        $message = 'Ustawienia zostały zapisane pomyślnie';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Błąd podczas zapisywania ustawień: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Pobierz aktualne ustawienia
$dlugosc_lekcji = pobierz_ustawienie('dlugosc_lekcji', '45');
$godzina_rozpoczecia = pobierz_ustawienie('godzina_rozpoczecia', '08:00');
$liczba_lekcji = intval(pobierz_ustawienie('liczba_lekcji', '8'));

// Pobierz długości wszystkich przerw
$przerwy = [];
for ($i = 1; $i < $liczba_lekcji; $i++) {
    $przerwy[$i] = intval(pobierz_ustawienie("przerwa_po_$i", $i == 3 ? '15' : '10'));
}

// Oblicz przykładowe godziny lekcji
function oblicz_godziny_lekcji($start, $dlugosc_lekcji, $przerwy, $liczba_lekcji) {
    $godziny = [];
    $czas = strtotime($start);

    for ($i = 1; $i <= $liczba_lekcji; $i++) {
        $start_lekcji = date('H:i', $czas);
        $czas += $dlugosc_lekcji * 60;
        $koniec_lekcji = date('H:i', $czas);

        $godziny[$i] = "$start_lekcji - $koniec_lekcji";

        // Dodaj przerwę (jeśli nie jest to ostatnia lekcja)
        if ($i < $liczba_lekcji && isset($przerwy[$i])) {
            $czas += $przerwy[$i] * 60;
        }
    }

    return $godziny;
}

$przykladowe_godziny = oblicz_godziny_lekcji($godzina_rozpoczecia, $dlugosc_lekcji, $przerwy, $liczba_lekcji);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia Planu Lekcji</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .settings-preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .preview-table {
            width: 100%;
            max-width: 500px;
            margin-top: 15px;
        }

        .preview-table td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }

        .preview-table td:first-child {
            font-weight: 600;
            width: 120px;
        }

        .preview-table td:last-child {
            color: #6c757d;
            font-size: 14px;
        }

        .form-group-inline {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group-inline input,
        .form-group-inline select {
            flex: 0 0 150px;
        }

        .help-text {
            color: #6c757d;
            font-size: 14px;
            margin-top: 5px;
        }

        .przerwy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .przerwa-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }

        .przerwa-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .przerwa-item input {
            width: 100%;
        }

        .section-divider {
            border-top: 2px solid #e9ecef;
            margin: 30px 0;
            padding-top: 20px;
        }
    </style>
    <script>
        function aktualizujPrzerwy() {
            const liczbaLekcji = parseInt(document.getElementById('liczba_lekcji').value);
            const container = document.getElementById('przerwy_container');

            // Wyczyść kontener
            container.innerHTML = '';

            // Wygeneruj pola dla przerw (liczba przerw = liczba lekcji - 1)
            for (let i = 1; i < liczbaLekcji; i++) {
                const div = document.createElement('div');
                div.className = 'przerwa-item';

                const label = document.createElement('label');
                label.textContent = 'Po ' + i + ' lekcji';

                const input = document.createElement('input');
                input.type = 'number';
                input.name = 'przerwa_po_' + i;
                input.value = <?php echo json_encode($przerwy); ?>[i] || (i == 3 ? 15 : 10);
                input.min = 5;
                input.max = 30;
                input.required = true;
                input.style.textAlign = 'center';
                input.style.fontWeight = '600';

                div.appendChild(label);
                div.appendChild(input);
                container.appendChild(div);
            }
        }

        // Wywołaj na załadowanie strony
        window.addEventListener('DOMContentLoaded', function() {
            aktualizujPrzerwy();
        });
    </script>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Ustawienia Systemu</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>


        <div class="admin-content">
            <h2 class="page-title">Ustawienia Planu Lekcji</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 class="card-title">Konfiguracja godzin lekcyjnych</h3>

                <form method="POST" id="settingsForm">
                    <div class="form-group">
                        <label>Liczba lekcji w dniu *</label>
                        <div class="form-group-inline">
                            <input type="number"
                                   id="liczba_lekcji"
                                   name="liczba_lekcji"
                                   value="<?php echo e($liczba_lekcji); ?>"
                                   min="6"
                                   max="10"
                                   required
                                   onchange="aktualizujPrzerwy()">
                            <span class="help-text">Ile maksymalnie lekcji w ciągu dnia (6-10)</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Godzina rozpoczęcia zajęć *</label>
                        <div class="form-group-inline">
                            <input type="time" name="godzina_rozpoczecia" value="<?php echo e($godzina_rozpoczecia); ?>" required>
                            <span class="help-text">O której godzinie rozpoczyna się pierwsza lekcja</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Długość lekcji (w minutach) *</label>
                        <div class="form-group-inline">
                            <input type="number" name="dlugosc_lekcji" value="<?php echo e($dlugosc_lekcji); ?>" min="30" max="60" required>
                            <span class="help-text">Standardowo 45 minut</span>
                        </div>
                    </div>

                    <div class="section-divider">
                        <h4 style="margin-bottom: 15px; color: #333;">Długość przerw po poszczególnych lekcjach (w minutach)</h4>
                        <p class="help-text" style="margin-bottom: 15px;">
                            Ustaw długość każdej przerwy osobno. Pola przerw dostosowują się automatycznie do liczby lekcji.
                        </p>
                        <div class="przerwy-grid" id="przerwy_container">
                            <!-- Pola przerw generowane dynamicznie przez JavaScript -->
                        </div>
                    </div>

                    <button type="submit" name="zapisz" class="btn btn-primary" style="margin-top: 20px;">
                        Zapisz ustawienia
                    </button>
                </form>
            </div>

            <div class="card">
                <h3 class="card-title">Podgląd rozkładu godzin</h3>
                <div class="settings-preview">
                    <p><strong>Na podstawie aktualnych ustawień, godziny lekcji będą wyglądać następująco:</strong></p>
                    <table class="preview-table">
                        <?php foreach ($przykladowe_godziny as $numer => $godzina): ?>
                            <tr>
                                <td><?php echo $numer; ?> lekcja:</td>
                                <td><strong><?php echo $godzina; ?></strong></td>
                                <td>
                                    <?php if ($numer < $liczba_lekcji && isset($przerwy[$numer])): ?>
                                        potem przerwa <?php echo $przerwy[$numer]; ?> min
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <p style="margin-top: 15px; color: #6c757d; font-size: 14px;">
                        <strong>Uwaga:</strong> Zmiany w ustawieniach będą widoczne w wyświetlanych planach lekcji.
                    </p>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Informacje dodatkowe</h3>
                <ul style="line-height: 2; color: #495057;">
                    <li><strong>Liczba lekcji:</strong> Określa maksymalną liczbę lekcji w ciągu dnia (6-10). System automatycznie dostosuje pola przerw.</li>
                    <li><strong>Długość lekcji:</strong> Określa ile minut trwa jedna lekcja. W Polsce standardowo jest to 45 minut.</li>
                    <li><strong>Godzina rozpoczęcia:</strong> Pierwsza lekcja rozpoczyna się o wskazanej godzinie.</li>
                    <li><strong>Indywidualne przerwy:</strong> Możesz ustawić długość każdej przerwy osobno, co pozwala na elastyczne planowanie.</li>
                    <li><strong>Typowe wartości:</strong> Krótka przerwa 5-10 minut, przerwa obiadowa 15-20 minut.</li>
                </ul>
            </div>
            </div>
        </div>
    </div>
</body>
</html>
