<?php
require_once '../includes/config.php';
require_once '../includes/wiadomosci_functions.php';
sprawdz_uprawnienia('dyrektor');

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Automatyczne czyszczenie starych załączników (>30 dni)
wyczysc_stare_zalaczniki($conn, 30);

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
        $czy_wazne = isset($_POST['czy_wazne']);

        if (empty($odbiorcy) || empty($temat) || empty($tresc)) {
            $error = 'Wypełnij wszystkie pola';
        } else {
            $wiadomosc_id = wyslij_wiadomosc($conn, $user_id, $odbiorcy, $temat, $tresc, $czy_wazne);

            if ($wiadomosc_id) {
                // Obsługa załączników - sprawdź czy plik został faktycznie wysłany
                if (isset($_FILES['zalacznik']) && $_FILES['zalacznik']['error'] === UPLOAD_ERR_OK && $_FILES['zalacznik']['size'] > 0) {
                    $upload = upload_zalacznik($_FILES['zalacznik'], $wiadomosc_id);
                    if (!isset($upload['error'])) {
                        dodaj_zalacznik(
                            $conn,
                            $wiadomosc_id,
                            $upload['nazwa_pliku'],
                            $upload['sciezka'],
                            $upload['rozmiar'],
                            $upload['typ_mime']
                        );
                    }
                }
                $message = 'Wiadomość wysłana';
            } else {
                $error = 'Błąd podczas wysyłania';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['usun'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error = 'Błąd tokenu CSRF';
        } else {
            if (usun_wiadomosc($conn, intval($_POST['usun']), $user_id)) {
                $message = 'Wiadomość usunięta';
            }
        }
    }

    if (isset($_POST['archiwizuj'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error = 'Błąd tokenu CSRF';
        } else {
            if (archiwizuj_wiadomosc($conn, intval($_POST['archiwizuj']), $user_id)) {
                $message = 'Wiadomość zarchiwizowana';
            }
        }
    }

    // Ręczne czyszczenie wszystkich załączników
    if (isset($_POST['czysc_wszystkie'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error = 'Błąd tokenu CSRF';
        } else {
            $ilosc = wyczysc_wszystkie_zalaczniki($conn);
            $message = "Wyczyszczono wszystkie załączniki z serwera ($ilosc plików).";
        }
    }
}

$folder = $_GET['folder'] ?? 'odebrane';
if (!in_array($folder, ['odebrane', 'wyslane', 'archiwum']))
    $folder = 'odebrane';

$wiadomosci = pobierz_skrzynke($conn, $user_id, $folder);
$nieprzeczytane = liczba_nieprzeczytanych($conn, $user_id);

$szczegoly = null;
if (isset($_GET['id'])) {
    $szczegoly = pobierz_wiadomosc($conn, intval($_GET['id']), $user_id);
    if ($szczegoly && !$szczegoly['czy_przeczytana']) {
        oznacz_przeczytana($conn, intval($_GET['id']), $user_id);
        $nieprzeczytane = max(0, $nieprzeczytane - 1);
    }
}

$lista_odbiorcow = pobierz_liste_odbiorcow($conn, $user_id, $user_type);
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiadomości - Panel Dyrektora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .mail-layout {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 25px;
            margin-top: 25px;
        }

        .mail-sidebar {
            background: white;
            border-radius: 10px;
            padding: 20px;
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
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        }

        .mail-content {
            flex: 1;
            min-width: 0;
        }

        .mail-subject {
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
            margin: 0 0 15px 0;
        }

        .mail-view-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
        }

        .mail-view-body {
            line-height: 1.8;
        }

        .mail-attachments {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .attachment-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-right: 10px;
            text-decoration: none;
            color: #2c3e50;
        }

        .btn-compose {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            padding: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .empty-mail {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .badge-msg {
            background: #dc3545;
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>✉️ Wiadomości <?php if ($nieprzeczytane > 0): ?><span
                            class="badge-msg"><?php echo $nieprzeczytane; ?></span><?php endif; ?></h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
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

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e9ecef;">

                        <form method="post"
                            onsubmit="return confirm('⚠️ Czy na pewno usunąć WSZYSTKIE załączniki z serwera?\nTej operacji nie można cofnąć!');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="czysc_wszystkie" value="1">
                            <button type="submit" class="btn btn-danger"
                                style="width: 100%; font-size: 13px; padding: 8px;">
                                🗑️ Usuń wszystkie załączniki
                            </button>
                        </form>
                    </div>

                    <div class="mail-main">
                        <?php if ($szczegoly): ?>
                            <div class="mail-header">
                                <a href="?folder=<?php echo $folder; ?>" class="btn btn-secondary">← Powrót</a>
                                <div>
                                    <form method="post" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="archiwizuj" value="<?php echo $szczegoly['id']; ?>">
                                        <button type="submit" class="btn btn-secondary">📁 Archiwizuj</button>
                                    </form>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Usunąć?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="usun" value="<?php echo $szczegoly['id']; ?>">
                                        <button type="submit" class="btn btn-danger">🗑️ Usuń</button>
                                    </form>
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
                                <?php if (!empty($szczegoly['zalaczniki'])): ?>
                                    <div class="mail-attachments">
                                        <strong>📎 Załączniki:</strong><br><br>
                                        <?php foreach ($szczegoly['zalaczniki'] as $z): ?>
                                            <a href="../<?php echo e($z['sciezka']); ?>" class="attachment-item" download>
                                                📄 <?php echo e($z['nazwa_pliku']); ?>
                                                <small>(<?php echo formatuj_rozmiar($z['rozmiar']); ?>)</small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
                                        <p>Ten folder jest pusty.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($wiadomosci as $w): ?>
                                        <div class="mail-item <?php echo !$w['czy_przeczytana'] ? 'unread' : ''; ?>"
                                            onclick="location.href='?folder=<?php echo $folder; ?>&id=<?php echo $w['id']; ?>'">
                                            <div class="mail-sender">
                                                <?php echo e($w['nadawca_imie'] . ' ' . $w['nadawca_nazwisko']); ?>
                                            </div>
                                            <div class="mail-content">
                                                <div class="mail-subject">
                                                    <?php if ($w['czy_wazne']): ?>❗<?php endif; ?><?php if ($w['liczba_zalacznikow'] > 0): ?>📎<?php endif; ?>
                                                    <?php echo e($w['temat']); ?>
                                                </div>
                                                <div class="mail-preview"><?php echo e(substr($w['tresc'], 0, 80)); ?>...</div>
                                            </div>
                                            <div class="mail-date"><?php echo formatuj_date_wiadomosci($w['data_wyslania']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="composeModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Nowa wiadomość</h3>
                <button class="modal-close"
                    onclick="document.getElementById('composeModal').classList.remove('active')">&times;</button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="wyslij" value="1">
                <div class="form-group">
                    <label>Odbiorcy</label>
                    <select name="odbiorcy[]" multiple required style="height: 150px;">
                        <?php foreach ($lista_odbiorcow as $grupa => $osoby): ?>
                            <optgroup label="<?php echo e($grupa); ?>">
                                <?php foreach ($osoby as $os): ?>
                                    <option value="<?php echo $os['id']; ?>">
                                        <?php echo e($os['imie'] . ' ' . $os['nazwisko']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6c757d;">Przytrzymaj Ctrl aby wybrać wielu</small>
                </div>
                <div class="form-group"><label>Temat</label><input type="text" name="temat" required></div>
                <div class="form-group"><label>Treść</label><textarea name="tresc" rows="6" required></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="czy_wazne"> Oznacz jako ważne</label></div>
                <div class="form-group"><label>Załącznik</label><input type="file" name="zalacznik"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div>
                <button type="submit" class="btn btn-primary btn-full">📨 Wyślij</button>
            </form>
        </div>
    </div>
    <script>document.getElementById('composeModal').addEventListener('click', function (e) { if (e.target === this) this.classList.remove('active'); });</script>
</body>

</html>