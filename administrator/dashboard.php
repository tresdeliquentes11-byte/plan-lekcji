<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('administrator');

// Aktualizuj aktywność sesji
zarzadzaj_sesja($_SESSION['user_id'], 'activity');

// Pobierz statystyki
$stats_uzytkownikow = pobierz_statystyki_uzytkownikow();
$aktywni_uzytkownicy = pobierz_aktywnych_uzytkownikow();
$ostatnie_akcje = pobierz_ostatnie_akcje(10);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Panel Administratora</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <h2 class="page-title">Przegląd Systemu</h2>

                <!-- Karty ze statystykami -->
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
                            <h3><?php echo $stats_uzytkownikow['total']; ?></h3>
                            <p>Wszystkich użytkowników</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <polyline points="17 11 19 13 23 9"></polyline>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $aktywni_uzytkownicy['total']; ?></h3>
                            <p>Zalogowanych teraz</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats_uzytkownikow['uczniowie']; ?></h3>
                            <p>Uczniów</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats_uzytkownikow['nauczyciele']; ?></h3>
                            <p>Nauczycieli</p>
                        </div>
                    </div>
                </div>

                <!-- Szczegółowe statystyki -->
                <div class="cards-grid">
                    <div class="card">
                        <h3 class="card-title">Użytkownicy według typu</h3>
                        <div class="stat-list">
                            <div class="stat-item">
                                <span class="stat-label">Uczniowie</span>
                                <span class="stat-value"><?php echo $stats_uzytkownikow['uczniowie']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Nauczyciele</span>
                                <span class="stat-value"><?php echo $stats_uzytkownikow['nauczyciele']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Dyrektor</span>
                                <span class="stat-value"><?php echo $stats_uzytkownikow['dyrektor']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Administratorzy</span>
                                <span class="stat-value"><?php echo $stats_uzytkownikow['administratorzy']; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h3 class="card-title">Status kont</h3>
                        <div class="stat-list">
                            <div class="stat-item">
                                <span class="stat-label">Aktywne konta</span>
                                <span class="stat-value stat-success"><?php echo $stats_uzytkownikow['aktywni']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Zablokowane konta</span>
                                <span class="stat-value stat-danger"><?php echo $stats_uzytkownikow['zablokowani']; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h3 class="card-title">Aktualnie zalogowani</h3>
                        <div class="stat-list">
                            <div class="stat-item">
                                <span class="stat-label">Uczniowie</span>
                                <span class="stat-value"><?php echo $aktywni_uzytkownicy['uczniowie']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Nauczyciele</span>
                                <span class="stat-value"><?php echo $aktywni_uzytkownicy['nauczyciele']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Dyrektor</span>
                                <span class="stat-value"><?php echo $aktywni_uzytkownicy['dyrektor']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Administratorzy</span>
                                <span class="stat-value"><?php echo $aktywni_uzytkownicy['administratorzy']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ostatnie akcje -->
                <div class="card">
                    <h3 class="card-title">Ostatnie akcje w systemie</h3>
                    <?php if (!empty($ostatnie_akcje)): ?>
                        <div class="activity-list">
                            <?php foreach ($ostatnie_akcje as $akcja): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $akcja['typ_akcji']; ?>">
                                        <?php if (strpos($akcja['typ_akcji'], 'logowanie') !== false): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                                <polyline points="10 17 15 12 10 7"></polyline>
                                                <line x1="15" y1="12" x2="3" y2="12"></line>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12 6 12 12 16 14"></polyline>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-user">
                                            <?php if ($akcja['login']): ?>
                                                <strong><?php echo e($akcja['imie'] . ' ' . $akcja['nazwisko']); ?></strong>
                                                <span class="user-badge"><?php echo e($akcja['typ']); ?></span>
                                            <?php else: ?>
                                                <strong>Nieznany użytkownik</strong>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-desc"><?php echo e($akcja['opis']); ?></div>
                                        <div class="activity-time"><?php echo date('d.m.Y H:i:s', strtotime($akcja['data_akcji'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Brak aktywności do wyświetlenia</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
