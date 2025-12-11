<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie dnia wolnego
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_dzien'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $data = $_POST['data'];
        $opis = trim($_POST['opis']);

        // Input validation
        if (empty($data) || empty($opis)) {
            $message = 'Data i opis są wymagane';
            $message_type = 'error';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $message = 'Nieprawidłowy format daty';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO dni_wolne (data, opis) VALUES (?, ?)");
            $stmt->bind_param("ss", $data, $opis);

            if ($stmt->execute()) {
                $message = 'Dzień wolny został dodany';
                $message_type = 'success';
            } else {
                error_log("Błąd dodawania dnia wolnego: " . $stmt->error);
                $message = 'Ten dzień jest już w kalendarzu';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Usuwanie dnia wolnego (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usun_dzien'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } else {
        $id = intval($_POST['usun_dzien']);
        $stmt = $conn->prepare("DELETE FROM dni_wolne WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = 'Dzień wolny został usunięty';
            $message_type = 'success';
        } else {
            error_log("Błąd usuwania dnia wolnego: " . $stmt->error);
            $message = 'Błąd podczas usuwania dnia wolnego';
            $message_type = 'error';
        }
        $stmt->close();
    }
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
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
        <header class="admin-header">
            <h1>Kalendarz Szkolny</h1>
            <div class="user-info">
                <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                <a href="../logout.php" class="btn-logout">Wyloguj</a>
            </div>
        </header>
        
        
        <div class="admin-content">
            <h2 class="page-title">Zarządzanie Kalendarzem</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Dodaj dzień wolny / święto</h3>
                <form method="POST">
                    <?php echo csrf_field(); ?>
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
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Czy na pewno?')">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="usun_dzien" value="<?php echo $d['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                                Usuń
                                            </button>
                                        </form>
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
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
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
