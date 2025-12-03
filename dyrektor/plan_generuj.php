<?php
require_once '../includes/config.php';
require_once '../includes/generator_planu.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generuj'])) {
    $generator = new GeneratorPlanu($conn);
    
    if ($generator->generujPlan()) {
        $message = 'Plan lekcji został pomyślnie wygenerowany!';
        $message_type = 'success';
    } else {
        $message = 'Wystąpił błąd podczas generowania planu. Sprawdź czy wszystkie klasy mają przypisane przedmioty i nauczycieli.';
        $message_type = 'error';
    }
}

// Sprawdzamy czy plan istnieje
$plan_exists = $conn->query("SELECT COUNT(*) as count FROM plan_lekcji")->fetch_assoc()['count'] > 0;

// Pobieramy statystyki
$stats = [];
$stats['klasy'] = $conn->query("SELECT COUNT(*) as count FROM klasy")->fetch_assoc()['count'];
$stats['nauczyciele'] = $conn->query("SELECT COUNT(*) as count FROM nauczyciele")->fetch_assoc()['count'];
$stats['przedmioty_przypisane'] = $conn->query("SELECT COUNT(*) as count FROM klasa_przedmioty")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generowanie Planu - Panel Dyrektora</title>
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
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="plan_generuj.php" class="active">Generuj Plan</a></li>
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
            <h2 class="page-title">Generowanie Planu Lekcji</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Status systemu</h3>
                
                <table>
                    <tr>
                        <td><strong>Liczba klas:</strong></td>
                        <td><?php echo $stats['klasy']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Liczba nauczycieli:</strong></td>
                        <td><?php echo $stats['nauczyciele']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Przypisanych przedmiotów do klas:</strong></td>
                        <td><?php echo $stats['przedmioty_przypisane']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status planu:</strong></td>
                        <td><?php echo $plan_exists ? '<span style="color: green;">✓ Plan istnieje</span>' : '<span style="color: red;">✗ Plan nie został wygenerowany</span>'; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h3 class="card-title">Informacje o generowaniu</h3>
                <p>Generator planu automatycznie:</p>
                <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                    <li>Równomiernie rozkłada przedmioty w ciągu tygodnia</li>
                    <li>Unika nakładania się sal (każda sala może być używana tylko przez jedną klasę w danym czasie)</li>
                    <li>Eliminuje okienka - lekcje są układane bez przerw</li>
                    <li>Sprawdza dostępność nauczycieli (nauczyciel nie może uczyć dwóch klas jednocześnie)</li>
                    <li>Generuje plan na cały rok szkolny (wrzesień - czerwiec)</li>
                    <li>Uwzględnia dni wolne i święta z kalendarza</li>
                </ul>
                
                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>Uwaga!</strong> Przed wygenerowaniem planu upewnij się, że:
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>Wszystkie klasy mają przypisanych nauczycieli do przedmiotów</li>
                        <li>Każda klasa ma wybrane 2 rozszerzenia</li>
                        <li>Nauczyciele mają przypisane przedmioty, które mogą uczyć</li>
                        <li>W systemie są zdefiniowane sale lekcyjne</li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Generowanie planu</h3>
                
                <form method="POST" onsubmit="return confirm('Czy na pewno chcesz wygenerować nowy plan? Obecny plan zostanie usunięty.');">
                    <p style="margin-bottom: 20px;">
                        <?php if ($plan_exists): ?>
                            <strong style="color: #dc3545;">Uwaga:</strong> Wygenerowanie nowego planu spowoduje usunięcie obecnego planu i wszystkich powiązanych danych!
                        <?php else: ?>
                            Kliknij poniższy przycisk, aby wygenerować plan lekcji.
                        <?php endif; ?>
                    </p>
                    
                    <button type="submit" name="generuj" class="btn btn-primary">
                        <?php echo $plan_exists ? 'Wygeneruj nowy plan (nadpisz obecny)' : 'Wygeneruj plan lekcji'; ?>
                    </button>
                    
                    <?php if ($plan_exists): ?>
                        <a href="plan_podglad.php" class="btn btn-secondary" style="margin-left: 10px;">Podgląd obecnego planu</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
