<?php
require_once '../includes/config.php';
require_once '../includes/oceny_functions.php';
sprawdz_uprawnienia('dyrektor');

// Pobierz wszystkie klasy
$klasy = $conn->query("SELECT * FROM klasy ORDER BY nazwa");

// Wybrana klasa
$wybrana_klasa_id = isset($_GET['klasa']) ? intval($_GET['klasa']) : null;

// Statystyki ogólne
$stats = [];

// Średnie dla wszystkich klas
$srednie_klas = [];
$klasy_result = $conn->query("SELECT id, nazwa FROM klasy ORDER BY nazwa");

while ($klasa = $klasy_result->fetch_assoc()) {
    $klasa_id = $klasa['id'];

    // Oblicz średnią klasy
    $srednia_result = $conn->query("
        SELECT AVG(srednia) as srednia_klasy FROM (
            SELECT uc.id, SUM(o.ocena * ko.waga) / SUM(ko.waga) as srednia
            FROM uczniowie uc
            JOIN oceny o ON uc.id = o.uczen_id
            JOIN kategorie_ocen ko ON o.kategoria_id = ko.id
            WHERE uc.klasa_id = $klasa_id AND o.czy_poprawiona = 0
            GROUP BY uc.id
        ) as s
    ");
    $srednia = $srednia_result->fetch_assoc()['srednia_klasy'];

    // Policz oceny
    $liczba_result = $conn->query("
        SELECT COUNT(DISTINCT o.id) as liczba FROM oceny o 
        JOIN uczniowie uc ON o.uczen_id = uc.id 
        WHERE uc.klasa_id = $klasa_id
    ");
    $liczba = $liczba_result->fetch_assoc()['liczba'];

    $srednie_klas[] = [
        'id' => $klasa['id'],
        'nazwa' => $klasa['nazwa'],
        'srednia_klasy' => $srednia,
        'liczba_ocen' => $liczba
    ];
}

// Najlepsza i najsłabsza klasa
$najlepsza = null;
$najslabsza = null;
foreach ($srednie_klas as $k) {
    if ($k['srednia_klasy'] !== null) {
        if ($najlepsza === null || $k['srednia_klasy'] > $najlepsza['srednia_klasy']) {
            $najlepsza = $k;
        }
        if ($najslabsza === null || $k['srednia_klasy'] < $najslabsza['srednia_klasy']) {
            $najslabsza = $k;
        }
    }
}

// Średnia szkolna
$result = $conn->query("
    SELECT AVG(srednia) as srednia_szkoly FROM (
        SELECT uc.id, SUM(o.ocena * ko.waga) / SUM(ko.waga) as srednia
        FROM uczniowie uc
        JOIN oceny o ON uc.id = o.uczen_id
        JOIN kategorie_ocen ko ON o.kategoria_id = ko.id
        WHERE o.czy_poprawiona = 0
        GROUP BY uc.id
    ) as s
");
$srednia_szkoly = $result->fetch_assoc()['srednia_szkoly'];

// Rozkład ocen dla wybranej klasy
$rozklad = null;
$uczniowie_zagrozone = [];
if ($wybrana_klasa_id) {
    $rozklad = pobierz_rozklad_ocen($conn, $wybrana_klasa_id);
    $uczniowie_zagrozone = pobierz_uczniow_zagrozonych($conn, $wybrana_klasa_id);
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statystyki Ocen - Panel Dyrektora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }

        .stat-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
        }

        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .ranking-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ranking-table th,
        .ranking-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .ranking-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .ranking-table tr:hover {
            background: #f8f9fa;
        }

        .srednia-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            color: white;
        }

        .chart-container {
            max-width: 400px;
            margin: 20px auto;
        }

        .zagrozone-list {
            list-style: none;
            padding: 0;
        }

        .zagrozone-list li {
            padding: 12px 15px;
            background: #fff8f8;
            border-left: 4px solid #dc3545;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }

        .zagrozone-srednia {
            float: right;
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>📊 Statystyki Ocen</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo $srednia_szkoly ? round($srednia_szkoly, 2) : '-'; ?></div>
                        <div class="label">Średnia szkoły</div>
                    </div>
                    <?php if ($najlepsza): ?>
                        <div class="stat-card success">
                            <div class="number"><?php echo round($najlepsza['srednia_klasy'], 2); ?></div>
                            <div class="label">Najlepsza: <?php echo e($najlepsza['nazwa']); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($najslabsza): ?>
                        <div class="stat-card danger">
                            <div class="number"><?php echo round($najslabsza['srednia_klasy'], 2); ?></div>
                            <div class="label">Najsłabsza: <?php echo e($najslabsza['nazwa']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3 class="card-title">🏆 Ranking klas według średniej</h3>
                    <table class="ranking-table">
                        <thead>
                            <tr>
                                <th>Pozycja</th>
                                <th>Klasa</th>
                                <th>Średnia</th>
                                <th>Liczba ocen</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            usort($srednie_klas, function ($a, $b) {
                                if ($a['srednia_klasy'] === null)
                                    return 1;
                                if ($b['srednia_klasy'] === null)
                                    return -1;
                                return $b['srednia_klasy'] <=> $a['srednia_klasy'];
                            });
                            $pozycja = 1;
                            foreach ($srednie_klas as $k):
                                ?>
                                <tr>
                                    <td><strong><?php echo $k['srednia_klasy'] ? $pozycja++ : '-'; ?></strong></td>
                                    <td><?php echo e($k['nazwa']); ?></td>
                                    <td>
                                        <?php if ($k['srednia_klasy']): ?>
                                            <span class="srednia-badge"
                                                style="background: <?php echo kolor_oceny($k['srednia_klasy']); ?>;">
                                                <?php echo round($k['srednia_klasy'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">Brak ocen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $k['liczba_ocen']; ?></td>
                                    <td>
                                        <a href="?klasa=<?php echo $k['id']; ?>" class="btn btn-secondary"
                                            style="padding: 6px 12px; font-size: 13px;">
                                            Szczegóły
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($wybrana_klasa_id && $rozklad): ?>
                    <div class="card" style="margin-top: 25px;">
                        <h3 class="card-title">
                            📈 Rozkład ocen - Klasa <?php
                            foreach ($srednie_klas as $k) {
                                if ($k['id'] == $wybrana_klasa_id)
                                    echo e($k['nazwa']);
                            }
                            ?>
                        </h3>
                        <div class="chart-container">
                            <canvas id="rozkladChart"></canvas>
                        </div>
                        <script>
                            new Chart(document.getElementById('rozkladChart'), {
                                type: 'bar',
                                data: {
                                    labels: ['1', '2', '3', '4', '5', '6'],
                                    datasets: [{
                                        label: 'Liczba ocen',
                                        data: [
                                            <?php echo $rozklad[1]; ?>,
                                            <?php echo $rozklad[2]; ?>,
                                            <?php echo $rozklad[3]; ?>,
                                            <?php echo $rozklad[4]; ?>,
                                            <?php echo $rozklad[5]; ?>,
                                            <?php echo $rozklad[6]; ?>
                                        ],
                                        backgroundColor: [
                                            '#c9302c',
                                            '#d9534f',
                                            '#f0ad4e',
                                            '#5cb85c',
                                            '#28a745',
                                            '#1e7e34'
                                        ]
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: { display: false }
                                    },
                                    scales: {
                                        y: { beginAtZero: true }
                                    }
                                }
                            });
                        </script>
                    </div>

                    <?php if (!empty($uczniowie_zagrozone)): ?>
                        <div class="card" style="margin-top: 25px;">
                            <h3 class="card-title">⚠️ Uczniowie zagrożeni (średnia &lt; 2.0)</h3>
                            <ul class="zagrozone-list">
                                <?php foreach ($uczniowie_zagrozone as $u): ?>
                                    <li>
                                        <span class="zagrozone-srednia"><?php echo $u['srednia']; ?></span>
                                        <?php echo e($u['imie'] . ' ' . $u['nazwisko']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>