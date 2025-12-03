<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie dnia wolnego
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_dzien'])) {
    $data = $_POST['data'];
    $opis = $_POST['opis'];
    
    $stmt = $conn->prepare("INSERT INTO dni_wolne (data, opis) VALUES (?, ?)");
    $stmt->bind_param("ss", $data, $opis);
    
    if ($stmt->execute()) {
        $message = 'Dzień wolny został dodany';
        $message_type = 'success';
    } else {
        $message = 'Ten dzień jest już w kalendarzu';
        $message_type = 'error';
    }
}

// Usuwanie dnia wolnego
if (isset($_GET['usun'])) {
    $id = $_GET['usun'];
    $conn->query("DELETE FROM dni_wolne WHERE id = $id");
    $message = 'Dzień wolny został usunięty';
    $message_type = 'success';
}

// Pobierz dni wolne
$dni_wolne = $conn->query("SELECT * FROM dni_wolne ORDER BY data");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalendarz - Panel Dyrektora</title>
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
                <li><a href="plan_generuj.php">Generuj Plan</a></li>
                <li><a href="zastepstwa.php">Zastępstwa</a></li>
                <li><a href="nauczyciele.php">Nauczyciele</a></li>
                <li><a href="uczniowie.php">Uczniowie</a></li>
                <li><a href="klasy.php">Klasy</a></li>
                <li><a href="przedmioty.php">Przedmioty</a></li>
                <li><a href="sale.php">Sale</a></li>
                <li><a href="kalendarz.php" class="active">Kalendarz</a></li>
                <li><a href="plan_podglad.php">Podgląd Planu</a></li>
                <li><a href="dostepnosc.php">Dostępność</a></li>
                <li><a href="ustawienia.php">Ustawienia</a></li>
            </ul>
        </nav>
        
        <div class="content">
            <h2 class="page-title">Zarządzanie Kalendarzem</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Dodaj dzień wolny / święto</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 200px 1fr auto; gap: 15px; align-items: end;">
                        <div class="form-group">
                            <label>Data</label>
                            <input type="date" name="data" required>
                        </div>
                        <div class="form-group">
                            <label>Opis</label>
                            <input type="text" name="opis" placeholder="np. Boże Narodzenie" required>
                        </div>
                        <button type="submit" name="dodaj_dzien" class="btn btn-primary">Dodaj</button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h3 class="card-title">Lista dni wolnych</h3>
                
                <?php if ($dni_wolne->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Dzień tygodnia</th>
                                <th>Opis</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($d = $dni_wolne->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo formatuj_date($d['data']); ?></td>
                                    <td><?php echo date('l', strtotime($d['data'])); ?></td>
                                    <td><?php echo e($d['opis']); ?></td>
                                    <td>
                                        <a href="?usun=<?php echo $d['id']; ?>" 
                                           class="btn btn-danger" 
                                           style="padding: 5px 10px; font-size: 12px;"
                                           onclick="return confirm('Czy na pewno?')">Usuń</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Brak zdefiniowanych dni wolnych</div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3 class="card-title">Szybkie dodawanie świąt</h3>
                <p>Typowe święta w roku szkolnym 2024/2025:</p>
                <div style="margin-top: 15px;">
                    <button onclick="dodajSwieto('2024-11-01', 'Wszystkich Świętych')" class="btn btn-secondary" style="margin: 5px;">01.11 - Wszystkich Świętych</button>
                    <button onclick="dodajSwieto('2024-11-11', 'Święto Niepodległości')" class="btn btn-secondary" style="margin: 5px;">11.11 - Święto Niepodległości</button>
                    <button onclick="dodajSwieto('2024-12-25', 'Boże Narodzenie')" class="btn btn-secondary" style="margin: 5px;">25.12 - Boże Narodzenie</button>
                    <button onclick="dodajSwieto('2024-12-26', 'Drugi dzień Świąt')" class="btn btn-secondary" style="margin: 5px;">26.12 - Drugi dzień Świąt</button>
                    <button onclick="dodajSwieto('2025-01-01', 'Nowy Rok')" class="btn btn-secondary" style="margin: 5px;">01.01 - Nowy Rok</button>
                    <button onclick="dodajSwieto('2025-01-06', 'Trzech Króli')" class="btn btn-secondary" style="margin: 5px;">06.01 - Trzech Króli</button>
                    <button onclick="dodajSwieto('2025-04-20', 'Wielkanoc')" class="btn btn-secondary" style="margin: 5px;">20.04 - Wielkanoc</button>
                    <button onclick="dodajSwieto('2025-04-21', 'Poniedziałek Wielkanocny')" class="btn btn-secondary" style="margin: 5px;">21.04 - Poniedziałek Wielkanocny</button>
                    <button onclick="dodajSwieto('2025-05-01', 'Święto Pracy')" class="btn btn-secondary" style="margin: 5px;">01.05 - Święto Pracy</button>
                    <button onclick="dodajSwieto('2025-05-03', 'Święto Konstytucji')" class="btn btn-secondary" style="margin: 5px;">03.05 - Święto Konstytucji 3 Maja</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function dodajSwieto(data, opis) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="data" value="${data}">
            <input type="hidden" name="opis" value="${opis}">
            <input type="hidden" name="dodaj_dzien" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
    </script>
</body>
</html>
