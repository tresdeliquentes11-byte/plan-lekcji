<?php
require_once '../includes/config.php';
require_once '../includes/oceny_functions.php';
sprawdz_uprawnienia('uczen');

// Pobierz ID i klasę ucznia
$stmt = $conn->prepare("
    SELECT uc.id as uczen_id, k.id as klasa_id, k.nazwa as klasa_nazwa
    FROM uczniowie uc
    JOIN klasy k ON uc.klasa_id = k.id
    WHERE uc.uzytkownik_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$uczen = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$uczen) {
    die("Błąd: Nie znaleziono danych ucznia");
}

$uczen_id = $uczen['uczen_id'];

// Pobierz wszystkie oceny ucznia
$oceny = pobierz_wszystkie_oceny_ucznia($conn, $uczen_id);

// Oblicz średnią ogólną
$srednia_ogolna = oblicz_srednia_ogolna($conn, $uczen_id);

// Oblicz średnie dla każdego przedmiotu
$srednie_przedmioty = [];
foreach ($oceny as $przedmiot => $lista) {
    $przedmiot_id = $lista[0]['przedmiot_id'];
    $srednie_przedmioty[$przedmiot] = oblicz_srednia_wazona($conn, $uczen_id, $przedmiot_id);
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moje Oceny - Panel Ucznia</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .student-layout {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .student-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .student-header {
            background: white;
            border-radius: 15px;
            padding: 25px 35px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .class-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .nav-tabs a {
            background: white;
            color: #2c3e50;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs a:hover,
        .nav-tabs a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 25px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-card .number {
            font-size: 42px;
            font-weight: 700;
        }

        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .przedmiot-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .przedmiot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .przedmiot-nazwa {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .przedmiot-srednia {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            color: white;
            font-size: 16px;
        }

        .oceny-lista {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .ocena-item {
            background: white;
            border-radius: 8px;
            padding: 10px 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 80px;
        }

        .ocena-wartosc {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .ocena-kategoria {
            font-size: 11px;
            color: #6c757d;
            text-align: center;
        }

        .ocena-data {
            font-size: 10px;
            color: #adb5bd;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
            color: #2c3e50;
            font-weight: 500;
        }

        .btn-logout {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }

        .no-grades {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .no-grades svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>

<body>
    <div class="student-layout">
        <div class="student-container">
            <header class="student-header">
                <h1>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                    Moje Oceny
                    <span class="class-badge">Klasa <?php echo e($uczen['klasa_nazwa']); ?></span>
                </h1>
                <div class="user-info">
                    <span><?php echo e($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="nav-tabs">
                <a href="dashboard.php">📅 Plan Lekcji</a>
                <a href="oceny.php" class="active">📊 Oceny</a>
                <a href="wiadomosci.php">✉️ Wiadomości</a>
                <a href="zmiana_hasla.php">🔐 Konto</a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $srednia_ogolna ?? '-'; ?></div>
                    <div class="label">Średnia ogólna</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo count($oceny); ?></div>
                    <div class="label">Przedmiotów z ocenami</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo array_sum(array_map('count', $oceny)); ?></div>
                    <div class="label">Wszystkich ocen</div>
                </div>
            </div>

            <div class="content-card">
                <h2
                    style="margin: 0 0 25px 0; color: #2c3e50; font-size: 22px; border-bottom: 2px solid #e9ecef; padding-bottom: 15px;">
                    📚 Oceny z przedmiotów
                </h2>

                <?php if (empty($oceny)): ?>
                    <div class="no-grades">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="12" y1="18" x2="12" y2="12"></line>
                            <line x1="9" y1="15" x2="15" y2="15"></line>
                        </svg>
                        <h3>Brak ocen</h3>
                        <p>Nie masz jeszcze żadnych ocen w systemie.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($oceny as $przedmiot => $lista): ?>
                        <div class="przedmiot-card">
                            <div class="przedmiot-header">
                                <span class="przedmiot-nazwa">📖 <?php echo e($przedmiot); ?></span>
                                <span class="przedmiot-srednia"
                                    style="background: <?php echo kolor_oceny($srednie_przedmioty[$przedmiot] ?? 3); ?>;">
                                    Średnia: <?php echo $srednie_przedmioty[$przedmiot] ?? '-'; ?>
                                </span>
                            </div>
                            <div class="oceny-lista">
                                <?php foreach ($lista as $o): ?>
                                    <?php if (!$o['czy_poprawiona']): ?>
                                        <div class="ocena-item">
                                            <span class="ocena-wartosc" style="color: <?php echo kolor_oceny($o['ocena']); ?>;">
                                                <?php echo formatuj_ocene($o['ocena']); ?>
                                            </span>
                                            <span class="ocena-kategoria"><?php echo e($o['kategoria']); ?></span>
                                            <span class="ocena-data"><?php echo formatuj_date($o['data_wystawienia']); ?></span>
                                            <?php if ($o['komentarz']): ?>
                                                <span class="ocena-kategoria" title="<?php echo e($o['komentarz']); ?>">💬</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>