<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('administrator');

zarzadzaj_sesja($_SESSION['user_id'], 'activity');

// Pobierz listę aktywnych użytkowników
$aktywne_sesje = pobierz_liste_aktywnych_uzytkownikow();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktywne Sesje - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Aktywne Sesje</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 class="card-title">Zalogowani użytkownicy (<?php echo count($aktywne_sesje); ?>)</h3>
                        <div class="badge badge-info" style="font-size: 14px; padding: 8px 16px;">
                            Automatyczne odświeżanie co 30s
                        </div>
                    </div>

                    <?php if (!empty($aktywne_sesje)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Użytkownik</th>
                                        <th>Login</th>
                                        <th>Typ</th>
                                        <th>Adres IP</th>
                                        <th>Czas logowania</th>
                                        <th>Ostatnia aktywność</th>
                                        <th>Czas sesji</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($aktywne_sesje as $sesja): ?>
                                        <tr>
                                            <td><?php echo $sesja['id']; ?></td>
                                            <td>
                                                <strong><?php echo e($sesja['imie'] . ' ' . $sesja['nazwisko']); ?></strong>
                                            </td>
                                            <td><?php echo e($sesja['login']); ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = 'badge-info';
                                                $typLabel = '';
                                                switch ($sesja['typ']) {
                                                    case 'uczen':
                                                        $badgeClass = 'badge-primary';
                                                        $typLabel = 'Uczeń';
                                                        break;
                                                    case 'nauczyciel':
                                                        $badgeClass = 'badge-success';
                                                        $typLabel = 'Nauczyciel';
                                                        break;
                                                    case 'dyrektor':
                                                        $badgeClass = 'badge-warning';
                                                        $typLabel = 'Dyrektor';
                                                        break;
                                                    case 'administrator':
                                                        $badgeClass = 'badge-danger';
                                                        $typLabel = 'Administrator';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $typLabel; ?></span>
                                            </td>
                                            <td>
                                                <code><?php echo e($sesja['ip_address']); ?></code>
                                            </td>
                                            <td>
                                                <?php echo date('d.m.Y H:i:s', strtotime($sesja['data_logowania'])); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $ostatnia = strtotime($sesja['ostatnia_aktywnosc']);
                                                $teraz = time();
                                                $roznica = $teraz - $ostatnia;

                                                if ($roznica < 60) {
                                                    $czasTekst = 'teraz';
                                                    $statusClass = 'badge-success';
                                                } elseif ($roznica < 300) { // 5 minut
                                                    $czasTekst = floor($roznica / 60) . ' min temu';
                                                    $statusClass = 'badge-info';
                                                } else {
                                                    $czasTekst = floor($roznica / 60) . ' min temu';
                                                    $statusClass = 'badge-warning';
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $czasTekst; ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $czasSesji = $teraz - strtotime($sesja['data_logowania']);
                                                $godziny = floor($czasSesji / 3600);
                                                $minuty = floor(($czasSesji % 3600) / 60);

                                                if ($godziny > 0) {
                                                    echo $godziny . 'h ' . $minuty . 'min';
                                                } else {
                                                    echo $minuty . ' min';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Podsumowanie według typu użytkownika -->
                        <div style="margin-top: 30px;">
                            <h4 style="margin-bottom: 15px;">Podsumowanie według typu</h4>
                            <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
                                <?php
                                $podsumowanie = [
                                    'uczen' => ['nazwa' => 'Uczniowie', 'liczba' => 0],
                                    'nauczyciel' => ['nazwa' => 'Nauczyciele', 'liczba' => 0],
                                    'dyrektor' => ['nazwa' => 'Dyrektor', 'liczba' => 0],
                                    'administrator' => ['nazwa' => 'Administratorzy', 'liczba' => 0]
                                ];

                                foreach ($aktywne_sesje as $sesja) {
                                    $podsumowanie[$sesja['typ']]['liczba']++;
                                }

                                $kolory = [
                                    'uczen' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                                    'nauczyciel' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                                    'dyrektor' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                                    'administrator' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
                                ];

                                foreach ($podsumowanie as $typ => $dane):
                                ?>
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: <?php echo $kolory[$typ]; ?>;">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                        </div>
                                        <div class="stat-info">
                                            <h3><?php echo $dane['liczba']; ?></h3>
                                            <p><?php echo $dane['nazwa']; ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Brak aktywnych sesji (oprócz Twojej)</p>
                    <?php endif; ?>
                </div>

                <!-- Informacje o automatycznym czyszczeniu sesji -->
                <div class="card">
                    <h3 class="card-title">Informacje o sesjach</h3>
                    <div class="alert alert-info">
                        <strong>Automatyczne czyszczenie sesji:</strong><br>
                        Sesje użytkowników są automatycznie oznaczane jako nieaktywne po 30 minutach braku aktywności.
                        Ta strona odświeża się automatycznie co 30 sekund, aby pokazywać aktualne dane.
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
