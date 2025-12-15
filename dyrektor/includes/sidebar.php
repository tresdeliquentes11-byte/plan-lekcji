<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <h2>Dyrektor</h2>
        <p>Panel Zarządzania</p>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Przegląd</span>
        </a>

        <div class="nav-separator">Zarządzanie Planem</div>

        <a href="plan_generuj.php"
            class="nav-item <?php echo ($current_page == 'plan_generuj.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="15" y2="15"></line>
            </svg>
            <span>Generuj Plan</span>
        </a>

        <a href="plan_edycja_ui.php"
            class="nav-item <?php echo ($current_page == 'plan_edycja_ui.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            <span>Edycja Planu</span>
        </a>

        <a href="plan_podglad.php"
            class="nav-item <?php echo ($current_page == 'plan_podglad.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <span>Podgląd Planu</span>
        </a>

        <a href="zastepstwa.php" class="nav-item <?php echo ($current_page == 'zastepstwa.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <polyline points="17 11 19 13 23 9"></polyline>
            </svg>
            <span>Zastępstwa</span>
        </a>

        <a href="kalendarz.php" class="nav-item <?php echo ($current_page == 'kalendarz.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Kalendarz</span>
        </a>

        <a href="ustawienia.php" class="nav-item <?php echo ($current_page == 'ustawienia.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>Dzwonki</span>
        </a>

        <div class="nav-separator">Nauczyciele i Dostępność</div>

        <a href="nauczyciele.php" class="nav-item <?php echo ($current_page == 'nauczyciele.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span>Nauczyciele</span>
        </a>

        <a href="dostepnosc.php" class="nav-item <?php echo ($current_page == 'dostepnosc.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>Dostępność</span>
        </a>

        <div class="nav-separator">Dane Szkolne</div>

        <a href="uczniowie.php" class="nav-item <?php echo ($current_page == 'uczniowie.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Uczniowie</span>
        </a>

        <a href="klasy.php" class="nav-item <?php echo ($current_page == 'klasy.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Klasy</span>
        </a>

        <a href="przedmioty.php" class="nav-item <?php echo ($current_page == 'przedmioty.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span>Przedmioty</span>
        </a>

        <a href="sale.php" class="nav-item <?php echo ($current_page == 'sale.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
                <polyline points="17 2 12 7 7 2"></polyline>
            </svg>
            <span>Sale</span>
        </a>

        <div class="nav-separator">System Szkolny</div>

        <a href="oceny.php" class="nav-item <?php echo ($current_page == 'oceny.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            <span>Statystyki Ocen</span>
        </a>

        <?php
        require_once dirname(__DIR__) . '/../includes/wiadomosci_functions.php';
        $nieprzeczytane_wiad = liczba_nieprzeczytanych($conn, $_SESSION['user_id']);
        ?>
        <a href="wiadomosci.php" class="nav-item <?php echo ($current_page == 'wiadomosci.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            <span>Wiadomości</span>
            <?php if ($nieprzeczytane_wiad > 0): ?>
                <span
                    style="background: #dc3545; color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; margin-left: 5px;"><?php echo $nieprzeczytane_wiad; ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-separator">Konto</div>

        <a href="zmiana_hasla.php"
            class="nav-item <?php echo ($current_page == 'zmiana_hasla.php') ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>Zmiana hasła</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="btn-logout-sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Wyloguj</span>
        </a>
    </div>
</aside>