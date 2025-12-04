<?php
require_once '../includes/config.php';
require_once '../includes/generator_planu.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generuj'])) {
    $generator = new GeneratorPlanu($conn);
    $uzytkownik_id = $_SESSION['user_id'] ?? 1;

    $wynik = $generator->generujPlan($uzytkownik_id);

    if ($wynik['success']) {
        $message = "Plan lekcji został pomyślnie wygenerowany!<br>";
        $message .= "Wygenerowano {$wynik['ilosc_lekcji']} lekcji w {$wynik['czas_trwania']} sekund.";

        if ($wynik['unassigned'] > 0) {
            $message .= "<br><strong>Uwaga:</strong> Nie udało się przypisać {$wynik['unassigned']} slotów.";
            $message .= " Naprawiono {$wynik['repaired_slots']} slotów.";
            $message_type = 'warning';
        } else {
            $message_type = 'success';
        }
    } else {
        if (isset($wynik['etap']) && $wynik['etap'] === 'walidacja') {
            $message = '<strong>Błędy walidacji:</strong><br>';
            foreach ($wynik['errors'] as $error) {
                $message .= "• " . htmlspecialchars($error) . "<br>";
            }
        } else {
            $message = 'Wystąpił błąd podczas generowania planu: ' . ($wynik['error'] ?? 'Nieznany błąd');
        }
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
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Generowanie Planu Lekcji</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        
        <div class="admin-content">
            <h2 class="page-title">Generowanie Planu Lekcji</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
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
                <p>Nowy algorytm generatora planu działa w 6 krokach:</p>
                <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                    <li><strong>Walidacja</strong> - sprawdza czy wszystkie klasy mają przedmioty, nauczycieli i sale</li>
                    <li><strong>Załadowanie danych</strong> - wczytuje wszystkie dane do pamięci (nauczyciele, sale, godziny pracy)</li>
                    <li><strong>Heurystyka</strong> - sortuje przedmioty według ilości godzin i dostępności nauczycieli</li>
                    <li><strong>Przypisywanie lekcji</strong> - inteligentnie rozmieszcza lekcje z uwzględnieniem limitów dziennych</li>
                    <li><strong>Repair</strong> - naprawia nieprzypisane sloty przez swap i przesunięcia (do 200 prób na slot)</li>
                    <li><strong>Zapis</strong> - zapisuje plan do bazy w transakcji wraz ze statystykami</li>
                </ul>

                <p style="margin-top: 15px;">Generator automatycznie:</p>
                <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                    <li>Respektuje godziny pracy nauczycieli</li>
                    <li>Przydziela sale według priorytetów (przedmiot+nauczyciel → przedmiot → nauczyciel → dowolna)</li>
                    <li>Limituje wystąpienia przedmiotów rozszerzonych do 2 dziennie</li>
                    <li>Generuje plan na cały rok szkolny (wrzesień - czerwiec)</li>
                    <li>Uwzględnia dni wolne i święta z kalendarza</li>
                </ul>

                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>Uwaga!</strong> Przed wygenerowaniem planu upewnij się, że:
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>Wszystkie klasy mają przypisane przedmioty z ilością godzin tygodniowo</li>
                        <li>Każdy przedmiot ma przypisanego nauczyciela</li>
                        <li>Nauczyciele mają ustawione godziny pracy</li>
                        <li>Istnieją sale przypisane do przedmiotów lub nauczycieli</li>
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
    </div>
</body>
</html>
