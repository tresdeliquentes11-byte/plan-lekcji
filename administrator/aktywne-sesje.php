<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('administrator');

// Diagnostyka - zapisz czas serwera i bazy danych
$server_time = date('Y-m-d H:i:s');
error_log("[DIAGNOSTYKA] Czas serwera PHP: " . $server_time);

// Sprawdź czas bazy danych
global $conn;
try {
    $db_time_result = $conn->query("SELECT NOW() as db_time, UTC_TIMESTAMP() as utc_time");
    $db_time = $db_time_result->fetch_assoc();
    error_log("[DIAGNOSTYKA] Czas bazy danych: " . $db_time['db_time'] . ", UTC: " . $db_time['utc_time']);
} catch (Exception $e) {
    error_log("[DIAGNOSTYKA] Błąd zapytania czasowego: " . $e->getMessage());
    // Fallback - użyj tylko NOW() jeśli UTC_TIMESTAMP nie działa
    $db_time_result = $conn->query("SELECT NOW() as db_time");
    $db_time = $db_time_result->fetch_assoc();
    error_log("[DIAGNOSTYKA] Czas bazy danych (fallback): " . $db_time['db_time']);
}

zarzadzaj_sesja($_SESSION['user_id'], 'activity');

// Diagnostyka - sprawdź sesję po aktualizacji
$session_check = $conn->prepare("SELECT session_id, uzytkownik_id, ostatnia_aktywnosc, data_logowania, aktywna FROM sesje_uzytkownikow WHERE session_id = ?");
$session_id = session_id();
$session_check->bind_param("s", $session_id);
$session_check->execute();
$current_session = $session_check->get_result()->fetch_assoc();
error_log("[DIAGNOSTYKA] Sesja po aktualizacji: " . print_r($current_session, true));

// Pobierz listę aktywnych użytkowników
$aktywne_sesje = pobierz_liste_aktywnych_uzytkownikow();

// Diagnostyka - zapisz liczbę sesji przed i po czyszczeniu
$all_sessions_result = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN aktywna = 1 THEN 1 ELSE 0 END) as active FROM sesje_uzytkownikow");
$all_sessions = $all_sessions_result->fetch_assoc();
error_log("[DIAGNOSTYKA] Wszystkie sesje: " . $all_sessions['total'] . ", Aktywne: " . $all_sessions['active']);
error_log("[DIAGNOSTYKA] Zwrócone aktywne sesje: " . count($aktywne_sesje));
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktywne Sesje - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <!-- Poprawione odświeżanie - dodanie cache control i JavaScript -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="refresh" content="30">
    <script>
        // Dodatkowe odświeżanie JavaScript jako fallback
        let refreshInterval = setInterval(function() {
            // Dodaj losowy parametr aby uniknąć cache
            window.location.href = window.location.pathname + '?t=' + new Date().getTime();
        }, 30000);
        
        // Zatrzymaj odświeżanie jeśli użytkownik interaktywnie korzysta ze strony
        let userActivity = false;
        document.addEventListener('mousemove', function() { userActivity = true; });
        document.addEventListener('keypress', function() { userActivity = true; });
        
        // Resetuj interwał odświeżania po aktywności użytkownika
        setInterval(function() {
            if (userActivity) {
                clearInterval(refreshInterval);
                refreshInterval = setInterval(function() {
                    window.location.href = window.location.pathname + '?t=' + new Date().getTime();
                }, 30000);
                userActivity = false;
            }
        }, 5000);
    </script>
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
                                                <?php
                                                // Poprawione: baza używa czasu lokalnego (Europe/Warsaw), nie UTC
                                                $dataLogowania = new DateTime($sesja['data_logowania']);
                                                $dataLogowania->setTimezone(new DateTimeZone('Europe/Warsaw'));
                                                echo $dataLogowania->format('d.m.Y H:i:s');
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                // Poprawione: baza używa czasu lokalnego, nie UTC
                                                $ostatnia = new DateTime($sesja['ostatnia_aktywnosc']);
                                                $ostatnia->setTimezone(new DateTimeZone('Europe/Warsaw'));
                                                $teraz = new DateTime('now', new DateTimeZone('Europe/Warsaw'));
                                                $roznica = $teraz->getTimestamp() - $ostatnia->getTimestamp();

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
                                                
                                                // Dodaj dokładny czas dla diagnostyki
                                                $dokladnyCzas = $ostatnia->format('H:i:s');
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>" title="Ostatnia aktywność: <?php echo $dokladnyCzas; ?>"><?php echo $czasTekst; ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                // Poprawione: baza używa czasu lokalnego, nie UTC
                                                $dataLogowania = new DateTime($sesja['data_logowania']);
                                                $dataLogowania->setTimezone(new DateTimeZone('Europe/Warsaw'));
                                                $czasSesji = $teraz->getTimestamp() - $dataLogowania->getTimestamp();
                                                $godziny = floor($czasSesji / 3600);
                                                $minuty = floor(($czasSesji % 3600) / 60);

                                                // Zapewnij nieujemne wartości
                                                if ($czasSesji < 0) {
                                                    $czasSesji = 0;
                                                    $godziny = 0;
                                                    $minuty = 0;
                                                }

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
