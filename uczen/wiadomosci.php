<?php
require_once '../includes/config.php';
require_once '../includes/wiadomosci_functions.php';
sprawdz_uprawnienia('uczen');

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Pobierz klasę ucznia dla widoku
$stmt = $conn->prepare("
    SELECT k.nazwa as klasa_nazwa
    FROM uczniowie uc
    JOIN klasy k ON uc.klasa_id = k.id
    WHERE uc.uzytkownik_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$uczen = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obsługa akcji
$message = '';
$error = '';

// Wysyłanie wiadomości
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wyslij'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Błąd weryfikacji tokenu CSRF';
    } else {
        $odbiorcy = isset($_POST['odbiorcy']) ? array_map('intval', $_POST['odbiorcy']) : [];
        $temat = trim($_POST['temat'] ?? '');
        $tresc = trim($_POST['tresc'] ?? '');

        if (empty($odbiorcy) || empty($temat) || empty($tresc)) {
            $error = 'Wypełnij wszystkie pola';
        } else {
            if (wyslij_wiadomosc($conn, $user_id, $odbiorcy, $temat, $tresc, false)) {
                $message = 'Wiadomość została wysłana';
            } else {
                $error = 'Błąd podczas wysyłania';
            }
        }
    }
}

// Oznaczanie jako przeczytane
if (isset($_GET['przeczytaj'])) {
    oznacz_przeczytana($conn, intval($_GET['przeczytaj']), $user_id);
}

// Usuwanie
if (isset($_GET['usun'])) {
    if (usun_wiadomosc($conn, intval($_GET['usun']), $user_id)) {
        $message = 'Wiadomość usunięta';
    }
}

// Archiwizacja
if (isset($_GET['archiwizuj'])) {
    if (archiwizuj_wiadomosc($conn, intval($_GET['archiwizuj']), $user_id)) {
        $message = 'Wiadomość zarchiwizowana';
    }
}

// Aktualny folder
$folder = $_GET['folder'] ?? 'odebrane';
if (!in_array($folder, ['odebrane', 'wyslane', 'archiwum'])) {
    $folder = 'odebrane';
}

// Pobierz wiadomości
$wiadomosci = pobierz_skrzynke($conn, $user_id, $folder);
$nieprzeczytane = liczba_nieprzeczytanych($conn, $user_id);

// Szczegóły wiadomości
$szczegoly = null;
if (isset($_GET['id'])) {
    $szczegoly = pobierz_wiadomosc($conn, intval($_GET['id']), $user_id);
    if ($szczegoly && !$szczegoly['czy_przeczytana']) {
        oznacz_przeczytana($conn, intval($_GET['id']), $user_id);
        $nieprzeczytane = max(0, $nieprzeczytane - 1);
    }
}

// Lista nauczycieli do kontaktu
$nauczyciele = $conn->query("
    SELECT u.id, u.imie, u.nazwisko, p.nazwa as przedmiot
    FROM uzytkownicy u
    JOIN nauczyciele n ON u.id = n.uzytkownik_id
    JOIN nauczyciel_przedmioty np ON n.id = np.nauczyciel_id
    JOIN przedmioty p ON np.przedmiot_id = p.id
    WHERE u.aktywny = 1 AND u.id != $user_id
    ORDER BY u.nazwisko, u.imie
");
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiadomości - Panel Ucznia</title>
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
            transition: all 0.3s;
            position: relative;
        }

        .nav-tabs a:hover,
        .nav-tabs a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .mail-layout {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 25px;
        }

        .mail-sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .mail-sidebar a {
            display: block;
            padding: 12px 15px;
            color: #495057;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .mail-sidebar a:hover,
        .mail-sidebar a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .mail-sidebar .count {
            float: right;
            background: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .mail-sidebar a.active .count {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .mail-main {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .mail-header {
            padding: 20px 25px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mail-header h2 {
            margin: 0;
            color: #2c3e50;
        }

        .mail-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .mail-item {
            padding: 15px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .mail-item:hover {
            background: #f8f9fa;
        }

        .mail-item.unread {
            background: #f0f4ff;
            font-weight: 600;
        }

        .mail-sender {
            width: 150px;
            flex-shrink: 0;
            color: #2c3e50;
        }

        .mail-content {
            flex: 1;
            min-width: 0;
        }

        .mail-subject {
            color: #2c3e50;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mail-preview {
            color: #6c757d;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: normal;
        }

        .mail-date {
            color: #6c757d;
            font-size: 13px;
            flex-shrink: 0;
        }

        .mail-important {
            color: #dc3545;
        }

        .mail-view {
            padding: 25px;
        }

        .mail-view-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .mail-view-subject {
            font-size: 24px;
            color: #2c3e50;
            margin: 0 0 15px 0;
        }

        .mail-view-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
        }

        .mail-view-body {
            line-height: 1.8;
            color: #2c3e50;
        }

        .btn-compose {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            width: 100%;
            margin-bottom: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-logout {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
        }

        .empty-mail {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .mail-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="student-layout">
        <div class="student-container">
            <header class="student-header">
                <h1>
                    ✉️ Wiadomości
                    <span class="class-badge">Klasa <?php echo e($uczen['klasa_nazwa']); ?></span>
                </h1>
                <div class="user-info">
                    <span><?php echo e($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="nav-tabs">
                <a href="dashboard.php">📅 Plan Lekcji</a>
                <a href="oceny.php">📊 Oceny</a>
                <a href="wiadomosci.php" class="active">
                    ✉️ Wiadomości
                    <?php if ($nieprzeczytane > 0): ?><span
                            class="badge"><?php echo $nieprzeczytane; ?></span><?php endif; ?>
                </a>
                <a href="zmiana_hasla.php">🔐 Konto</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

            <div class="mail-layout">
                <div class="mail-sidebar">
                    <button class="btn-compose"
                        onclick="document.getElementById('composeModal').classList.add('active')">
                        ✏️ Nowa wiadomość
                    </button>
                    <a href="?folder=odebrane" class="<?php echo $folder === 'odebrane' ? 'active' : ''; ?>">
                        📥 Odebrane <?php if ($nieprzeczytane > 0): ?><span
                                class="count"><?php echo $nieprzeczytane; ?></span><?php endif; ?>
                    </a>
                    <a href="?folder=wyslane" class="<?php echo $folder === 'wyslane' ? 'active' : ''; ?>">📤
                        Wysłane</a>
                    <a href="?folder=archiwum" class="<?php echo $folder === 'archiwum' ? 'active' : ''; ?>">📁
                        Archiwum</a>
                </div>

                <div class="mail-main">
                    <?php if ($szczegoly): ?>
                        <div class="mail-header">
                            <a href="?folder=<?php echo $folder; ?>" class="btn btn-secondary">← Powrót</a>
                            <div>
                                <a href="?folder=<?php echo $folder; ?>&archiwizuj=<?php echo $szczegoly['id']; ?>"
                                    class="btn btn-secondary">📁 Archiwizuj</a>
                                <a href="?folder=<?php echo $folder; ?>&usun=<?php echo $szczegoly['id']; ?>"
                                    class="btn btn-danger" onclick="return confirm('Usunąć?')">🗑️ Usuń</a>
                            </div>
                        </div>
                        <div class="mail-view">
                            <div class="mail-view-header">
                                <h2 class="mail-view-subject">
                                    <?php if ($szczegoly['czy_wazne']): ?><span
                                            class="mail-important">❗</span><?php endif; ?>
                                    <?php echo e($szczegoly['temat']); ?>
                                </h2>
                                <div class="mail-view-meta">
                                    <span>Od:
                                        <strong><?php echo e($szczegoly['nadawca_imie'] . ' ' . $szczegoly['nadawca_nazwisko']); ?></strong></span>
                                    <span><?php echo formatuj_date_wiadomosci($szczegoly['data_wyslania']); ?></span>
                                </div>
                            </div>
                            <div class="mail-view-body"><?php echo nl2br(e($szczegoly['tresc'])); ?></div>
                        </div>
                    <?php else: ?>
                        <div class="mail-header">
                            <h2><?php echo $folder === 'odebrane' ? '📥 Odebrane' : ($folder === 'wyslane' ? '📤 Wysłane' : '📁 Archiwum'); ?>
                            </h2>
                        </div>
                        <div class="mail-list">
                            <?php if (empty($wiadomosci)): ?>
                                <div class="empty-mail">
                                    <h3>Brak wiadomości</h3>
                                </div>
                            <?php else: ?>
                                <?php foreach ($wiadomosci as $w): ?>
                                    <div class="mail-item <?php echo !$w['czy_przeczytana'] ? 'unread' : ''; ?>"
                                        onclick="location.href='?folder=<?php echo $folder; ?>&id=<?php echo $w['id']; ?>'">
                                        <div class="mail-sender"><?php echo e($w['nadawca_imie'] . ' ' . $w['nadawca_nazwisko']); ?>
                                        </div>
                                        <div class="mail-content">
                                            <div class="mail-subject"><?php if ($w['czy_wazne']): ?>❗<?php endif; ?>
                                                <?php echo e($w['temat']); ?></div>
                                            <div class="mail-preview"><?php echo e(substr($w['tresc'], 0, 60)); ?>...</div>
                                        </div>
                                        <div class="mail-date"><?php echo formatuj_date_wiadomosci($w['data_wyslania']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="composeModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Nowa wiadomość</h3>
                <button class="modal-close"
                    onclick="document.getElementById('composeModal').classList.remove('active')">&times;</button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="wyslij" value="1">
                <div class="form-group">
                    <label>Odbiorca (nauczyciel)</label>
                    <select name="odbiorcy[]" required>
                        <option value="">-- Wybierz --</option>
                        <?php while ($n = $nauczyciele->fetch_assoc()): ?>
                            <option value="<?php echo $n['id']; ?>"><?php echo e($n['nazwisko'] . ' ' . $n['imie']); ?>
                                (<?php echo e($n['przedmiot']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Temat</label>
                    <input type="text" name="temat" required>
                </div>
                <div class="form-group">
                    <label>Treść</label>
                    <textarea name="tresc" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-full">📨 Wyślij</button>
            </form>
        </div>
    </div>
    <script>document.getElementById('composeModal').addEventListener('click', function (e) { if (e.target === this) this.classList.remove('active'); });</script>
</body>

</html>