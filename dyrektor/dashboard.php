<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

// Pobieranie statystyk
$stats = [];

// Liczba klas
$result = $conn->query("SELECT COUNT(*) as count FROM klasy");
$stats['klasy'] = $result->fetch_assoc()['count'];

// Liczba nauczycieli
$result = $conn->query("SELECT COUNT(*) as count FROM nauczyciele");
$stats['nauczyciele'] = $result->fetch_assoc()['count'];

// Liczba uczniów
$result = $conn->query("SELECT COUNT(*) as count FROM uczniowie");
$stats['uczniowie'] = $result->fetch_assoc()['count'];

// Liczba nieobecności w tym tygodniu
$poczatek_tygodnia = pobierz_poczatek_tygodnia(date('Y-m-d'));
$koniec_tygodnia = pobierz_koniec_tygodnia(date('Y-m-d'));
$result = $conn->query("SELECT COUNT(*) as count FROM nieobecnosci WHERE data_od <= '$koniec_tygodnia' AND data_do >= '$poczatek_tygodnia'");
$stats['nieobecnosci'] = $result->fetch_assoc()['count'];

// Najnowsze nieobecności
$nieobecnosci = $conn->query("
    SELECT n.*, u.imie, u.nazwisko, u.login
    FROM nieobecnosci n
    JOIN nauczyciele na ON n.nauczyciel_id = na.id
    JOIN uzytkownicy u ON na.uzytkownik_id = u.id
    ORDER BY n.data_zgloszenia DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Dyrektora - Dashboard</title>
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
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="plan_generuj.php">Generuj Plan</a></li>
                <li><a href="zastepstwa.php">Zastępstwa</a></li>
                <li><a href="nauczyciele.php">Nauczyciele</a></li>
                <li><a href="uczniowie.php">Uczniowie</a></li>
                <li><a href="klasy.php">Klasy</a></li>
                <li><a href="przedmioty.php">Przedmioty</a></li>
                <li><a href="sale.php">Sale</a></li>
                <li><a href="kalendarz.php">Kalendarz</a></li>
                <li><a href="plan_podglad.php">Podgląd Planu</a></li>
                <li><a href="dostepnosc.php">Dostępność</a></li>
                <li><a href="ustawienia.php">Ustawienia</a></li>
            </ul>
        </nav>
        
        <div class="content">
            <h2 class="page-title">Dashboard</h2>
            
            <div class="cards-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['klasy']; ?></div>
                    <div class="stat-label">Klas w szkole</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['nauczyciele']; ?></div>
                    <div class="stat-label">Nauczycieli</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['uczniowie']; ?></div>
                    <div class="stat-label">Uczniów</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['nieobecnosci']; ?></div>
                    <div class="stat-label">Nieobecności w tym tygodniu</div>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Najnowsze zgłoszenia nieobecności</h3>
                
                <?php if ($nieobecnosci->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nauczyciel</th>
                                <th>Data od</th>
                                <th>Data do</th>
                                <th>Powód</th>
                                <th>Data zgłoszenia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($n = $nieobecnosci->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?></td>
                                    <td><?php echo formatuj_date($n['data_od']); ?></td>
                                    <td><?php echo formatuj_date($n['data_do']); ?></td>
                                    <td><?php echo e($n['powod'] ?? '-'); ?></td>
                                    <td><?php echo formatuj_date($n['data_zgloszenia']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Brak zgłoszonych nieobecności</div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3 class="card-title">Szybkie akcje</h3>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="plan_generuj.php" class="btn btn-primary">Wygeneruj nowy plan</a>
                    <a href="zastepstwa.php" class="btn btn-success">Zarządzaj zastępstwami</a>
                    <a href="nauczyciele.php" class="btn btn-secondary">Dodaj nauczyciela</a>
                    <a href="kalendarz.php" class="btn btn-secondary">Zarządzaj kalendarzem</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
