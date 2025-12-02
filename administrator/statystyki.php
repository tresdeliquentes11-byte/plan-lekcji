<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('administrator');

zarzadzaj_sesja($_SESSION['user_id'], 'activity');

// Pobierz zakres dat (domyślnie ostatnie 30 dni)
$dni = isset($_GET['dni']) ? (int)$_GET['dni'] : 30;

// Pobierz statystyki
$stats_generowania = pobierz_statystyki_generowania($dni);
$stats_zarzadzania = pobierz_statystyki_zarzadzania($dni);
$stats_uzytkownikow = pobierz_statystyki_uzytkownikow();
$aktywni_uzytkownicy = pobierz_aktywnych_uzytkownikow();

// Przygotuj dane dla wykresów
$dni_labels = [];
$generowania_dane = [];
$generowania_bledy = [];

// Wypełnij dane dla wykresów generowania planu
foreach ($stats_generowania['dzienne'] as $stat) {
    $dni_labels[] = date('d.m', strtotime($stat['dzien']));
    $generowania_dane[] = $stat['sukces'];
    $generowania_bledy[] = $stat['blad'];
}

// Odwróć tablice żeby pokazać chronologicznie
$dni_labels = array_reverse($dni_labels);
$generowania_dane = array_reverse($generowania_dane);
$generowania_bledy = array_reverse($generowania_bledy);

// Przygotuj dane dla operacji zarządzania
$operacje_labels = ['Dodanie', 'Edycja', 'Usunięcie', 'Blokada', 'Odblokowanie'];
$operacje_dane = [0, 0, 0, 0, 0];
$operacje_mapa = [
    'dodanie' => 0,
    'edycja' => 1,
    'usuniecie' => 2,
    'blokada' => 3,
    'odblokowanie' => 4
];

foreach ($stats_zarzadzania as $stat) {
    $index = $operacje_mapa[$stat['typ_operacji']] ?? null;
    if ($index !== null) {
        $operacje_dane[$index] += $stat['ilosc'];
    }
}

// Pobierz statystyki logowań z ostatnich 30 dni
$logowania_query = $conn->query("
    SELECT DATE(data_akcji) as dzien, COUNT(*) as ilosc
    FROM logi_aktywnosci
    WHERE typ_akcji = 'logowanie'
    AND data_akcji >= DATE_SUB(NOW(), INTERVAL $dni DAY)
    GROUP BY DATE(data_akcji)
    ORDER BY dzien ASC
");

$logowania_labels = [];
$logowania_dane = [];
while ($row = $logowania_query->fetch_assoc()) {
    $logowania_labels[] = date('d.m', strtotime($row['dzien']));
    $logowania_dane[] = $row['ilosc'];
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statystyki - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Statystyki Systemu</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filtr zakresu dat -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">Zakres danych</h3>
                        <div>
                            <a href="statystyki.php?dni=7" class="btn btn-sm <?php echo $dni == 7 ? 'btn-primary' : 'btn-secondary'; ?>">7 dni</a>
                            <a href="statystyki.php?dni=30" class="btn btn-sm <?php echo $dni == 30 ? 'btn-primary' : 'btn-secondary'; ?>">30 dni</a>
                            <a href="statystyki.php?dni=90" class="btn btn-sm <?php echo $dni == 90 ? 'btn-primary' : 'btn-secondary'; ?>">90 dni</a>
                        </div>
                    </div>
                </div>

                <!-- Podsumowanie -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $aktywni_uzytkownicy['total']; ?></h3>
                            <p>Aktywnych użytkowników</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats_generowania['ogolne']['total'] ?? 0; ?></h3>
                            <p>Generowań planu (<?php echo $dni; ?> dni)</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($logowania_dane) > 0 ? array_sum($logowania_dane) : 0; ?></h3>
                            <p>Logowań (<?php echo $dni; ?> dni)</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                <path d="M2 17l10 5 10-5"></path>
                                <path d="M2 12l10 5 10-5"></path>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo array_sum($operacje_dane); ?></h3>
                            <p>Operacji administracyjnych</p>
                        </div>
                    </div>
                </div>

                <!-- Wykresy -->
                <div class="cards-grid">
                    <!-- Wykres logowań -->
                    <div class="card">
                        <h3 class="card-title">Logowania użytkowników (ostatnie <?php echo $dni; ?> dni)</h3>
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="logowaniaChart"></canvas>
                        </div>
                    </div>

                    <!-- Wykres operacji zarządzania -->
                    <div class="card">
                        <h3 class="card-title">Operacje zarządzania użytkownikami</h3>
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="operacjeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Wykres generowania planu -->
                <?php if (!empty($dni_labels)): ?>
                <div class="card">
                    <h3 class="card-title">Generowanie planu lekcji (ostatnie <?php echo $dni; ?> dni)</h3>
                    <div class="chart-container" style="position: relative; height: 350px;">
                        <canvas id="generowaniaChart"></canvas>
                    </div>
                    <div class="stats-summary" style="margin-top: 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo $stats_generowania['ogolne']['sukces'] ?? 0; ?></div>
                            <div style="color: #6c757d; margin-top: 5px;">Sukces</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #dc3545;"><?php echo $stats_generowania['ogolne']['blad'] ?? 0; ?></div>
                            <div style="color: #6c757d; margin-top: 5px;">Błędy</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #007bff;"><?php echo round($stats_generowania['ogolne']['sredni_czas'] ?? 0, 2); ?>s</div>
                            <div style="color: #6c757d; margin-top: 5px;">Średni czas</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <h3 class="card-title">Generowanie planu lekcji</h3>
                    <p class="text-muted">Brak danych o generowaniu planu w wybranym okresie</p>
                </div>
                <?php endif; ?>

                <!-- Szczegółowe statystyki użytkowników -->
                <div class="card">
                    <h3 class="card-title">Szczegółowe statystyki użytkowników</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Typ użytkownika</th>
                                <th>Liczba</th>
                                <th>Aktywni</th>
                                <th>Zablokowani</th>
                                <th>Zalogowani teraz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Uczniowie</strong></td>
                                <td><?php echo $stats_uzytkownikow['uczniowie']; ?></td>
                                <td><?php
                                    $aktywni_uczniowie = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='uczen' AND aktywny=1")->fetch_assoc();
                                    echo $aktywni_uczniowie['c'];
                                ?></td>
                                <td><?php
                                    $zablokowani_uczniowie = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='uczen' AND aktywny=0")->fetch_assoc();
                                    echo $zablokowani_uczniowie['c'];
                                ?></td>
                                <td><span class="badge badge-success"><?php echo $aktywni_uzytkownicy['uczniowie']; ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Nauczyciele</strong></td>
                                <td><?php echo $stats_uzytkownikow['nauczyciele']; ?></td>
                                <td><?php
                                    $aktywni_nauczyciele = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='nauczyciel' AND aktywny=1")->fetch_assoc();
                                    echo $aktywni_nauczyciele['c'];
                                ?></td>
                                <td><?php
                                    $zablokowani_nauczyciele = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='nauczyciel' AND aktywny=0")->fetch_assoc();
                                    echo $zablokowani_nauczyciele['c'];
                                ?></td>
                                <td><span class="badge badge-success"><?php echo $aktywni_uzytkownicy['nauczyciele']; ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Dyrektor</strong></td>
                                <td><?php echo $stats_uzytkownikow['dyrektor']; ?></td>
                                <td><?php
                                    $aktywni_dyrektor = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='dyrektor' AND aktywny=1")->fetch_assoc();
                                    echo $aktywni_dyrektor['c'];
                                ?></td>
                                <td><?php
                                    $zablokowani_dyrektor = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='dyrektor' AND aktywny=0")->fetch_assoc();
                                    echo $zablokowani_dyrektor['c'];
                                ?></td>
                                <td><span class="badge badge-success"><?php echo $aktywni_uzytkownicy['dyrektor']; ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Administratorzy</strong></td>
                                <td><?php echo $stats_uzytkownikow['administratorzy']; ?></td>
                                <td><?php
                                    $aktywni_admin = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='administrator' AND aktywny=1")->fetch_assoc();
                                    echo $aktywni_admin['c'];
                                ?></td>
                                <td><?php
                                    $zablokowani_admin = $conn->query("SELECT COUNT(*) as c FROM uzytkownicy WHERE typ='administrator' AND aktywny=0")->fetch_assoc();
                                    echo $zablokowani_admin['c'];
                                ?></td>
                                <td><span class="badge badge-success"><?php echo $aktywni_uzytkownicy['administratorzy']; ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Wykres logowań
        const logowaniaCtx = document.getElementById('logowaniaChart').getContext('2d');
        new Chart(logowaniaCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($logowania_labels); ?>,
                datasets: [{
                    label: 'Liczba logowań',
                    data: <?php echo json_encode($logowania_dane); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Wykres operacji zarządzania
        const operacjeCtx = document.getElementById('operacjeChart').getContext('2d');
        new Chart(operacjeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($operacje_labels); ?>,
                datasets: [{
                    label: 'Liczba operacji',
                    data: <?php echo json_encode($operacje_dane); ?>,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        <?php if (!empty($dni_labels)): ?>
        // Wykres generowania planu
        const generowaniaCtx = document.getElementById('generowaniaChart').getContext('2d');
        new Chart(generowaniaCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dni_labels); ?>,
                datasets: [{
                    label: 'Sukces',
                    data: <?php echo json_encode($generowania_dane); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.8)'
                }, {
                    label: 'Błędy',
                    data: <?php echo json_encode($generowania_bledy); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
