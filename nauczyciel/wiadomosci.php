<?php
require_once '../includes/config.php';
require_once '../includes/wiadomosci_functions.php';
sprawdz_uprawnienia('nauczyciel');

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

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

        if (empty($odbiorcy)) {
            $error = 'Wybierz przynajmniej jednego odbiorcę';
        } elseif (empty($temat)) {
            $error = 'Podaj temat wiadomości';
        } elseif (empty($tresc)) {
            $error = 'Wpisz treść wiadomości';
        } else {
            $wiadomosc_id = wyslij_wiadomosc($conn, $user_id, $odbiorcy, $temat, $tresc, $czy_wazne);

            if ($wiadomosc_id) {
                // Obsługa załączników
                if (!empty($_FILES['zalacznik']['name'])) {
                    $upload = upload_zalacznik($_FILES['zalacznik'], $wiadomosc_id);
                    if (isset($upload['error'])) {
                        $error = 'Wiadomość wysłana, ale: ' . $upload['error'];
                    } else {
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

                if (!$error) {
                    $message = 'Wiadomość została wysłana pomyślnie';
                }
            } else {
                $error = 'Wystąpił błąd podczas wysyłania wiadomości';
            }
        }
    }
}

// Oznaczanie jako przeczytane
if (isset($_GET['przeczytaj'])) {
    $wiadomosc_id = intval($_GET['przeczytaj']);
    oznacz_przeczytana($conn, $wiadomosc_id, $user_id);
}

// Usuwanie wiadomości
if (isset($_GET['usun'])) {
    $wiadomosc_id = intval($_GET['usun']);
    if (usun_wiadomosc($conn, $wiadomosc_id, $user_id)) {
        $message = 'Wiadomość została usunięta';
    }
}

// Archiwizacja
if (isset($_GET['archiwizuj'])) {
    $wiadomosc_id = intval($_GET['archiwizuj']);
    if (archiwizuj_wiadomosc($conn, $wiadomosc_id, $user_id)) {
        $message = 'Wiadomość została zarchiwizowana';
    }
}

// Aktualny folder
$folder = $_GET['folder'] ?? 'odebrane';
if (!in_array($folder, ['odebrane', 'wyslane', 'archiwum'])) {
    $folder = 'odebrane';
}

// Pobierz wiadomości
$wiadomosci = pobierz_skrzynke($conn, $user_id, $folder);

// Liczba nieprzeczytanych
$nieprzeczytane = liczba_nieprzeczytanych($conn, $user_id);

// Pobierz szczegóły wiadomości
$szczegoly = null;
if (isset($_GET['id'])) {
    $szczegoly = pobierz_wiadomosc($conn, intval($_GET['id']), $user_id);
    if ($szczegoly && !$szczegoly['czy_przeczytana']) {
        oznacz_przeczytana($conn, intval($_GET['id']), $user_id);
        $nieprzeczytane = max(0, $nieprzeczytane - 1);
    }
}

// Lista odbiorców do nowej wiadomości
$lista_odbiorcow = pobierz_liste_odbiorcow($conn, $user_id, $user_type);
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiadomości - Panel Nauczyciela</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .teacher-layout {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .teacher-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .teacher-header {
            background: white;
            border-radius: 15px;
            padding: 25px 35px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .teacher-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
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
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
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
            transition: all 0.3s;
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
            font-size: 20px;
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

        .mail-item.unread:hover {
            background: #e3eaff;
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
            font-size: 14px;
        }

        .mail-view-body {
            line-height: 1.8;
            color: #2c3e50;
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
            color: #2c3e50;
            text-decoration: none;
        }

        .attachment-item:hover {
            background: #e9ecef;
        }

        .btn-compose {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-compose:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
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
            font-weight: 500;
        }

        .empty-mail {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-mail svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .mail-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="teacher-layout">
        <div class="teacher-container">
            <header class="teacher-header">
                <h1>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    Wiadomości
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
                    <?php if ($nieprzeczytane > 0): ?>
                        <span class="badge"><?php echo $nieprzeczytane; ?></span>
                    <?php endif; ?>
                </a>
                <a href="zmiana_hasla.php">🔐 Konto</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo e($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="mail-layout">
                <div class="mail-sidebar">
                    <button class="btn-compose"
                        onclick="document.getElementById('composeModal').classList.add('active')"
                        style="width: 100%; margin-bottom: 20px;">
                        ✏️ Nowa wiadomość
                    </button>

                    <a href="?folder=odebrane" class="<?php echo $folder === 'odebrane' ? 'active' : ''; ?>">
                        📥 Odebrane
                        <?php if ($nieprzeczytane > 0): ?>
                            <span class="count"><?php echo $nieprzeczytane; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?folder=wyslane" class="<?php echo $folder === 'wyslane' ? 'active' : ''; ?>">
                        📤 Wysłane
                    </a>
                    <a href="?folder=archiwum" class="<?php echo $folder === 'archiwum' ? 'active' : ''; ?>">
                        📁 Archiwum
                    </a>
                </div>

                <div class="mail-main">
                    <?php if ($szczegoly): ?>
                        <div class="mail-header">
                            <a href="?folder=<?php echo $folder; ?>" class="btn btn-secondary" style="padding: 8px 16px;">←
                                Powrót</a>
                            <div>
                                <a href="?folder=<?php echo $folder; ?>&archiwizuj=<?php echo $szczegoly['id']; ?>"
                                    class="btn btn-secondary" style="padding: 8px 16px;">📁 Archiwizuj</a>
                                <a href="?folder=<?php echo $folder; ?>&usun=<?php echo $szczegoly['id']; ?>"
                                    class="btn btn-danger" style="padding: 8px 16px;"
                                    onclick="return confirm('Usunąć wiadomość?')">🗑️ Usuń</a>
                            </div>
                        </div>
                        <div class="mail-view">
                            <div class="mail-view-header">
                                <h2 class="mail-view-subject">
                                    <?php if ($szczegoly['czy_wazne']): ?>
                                        <span class="mail-important">❗</span>
                                    <?php endif; ?>
                                    <?php echo e($szczegoly['temat']); ?>
                                </h2>
                                <div class="mail-view-meta">
                                    <span>Od:
                                        <strong><?php echo e($szczegoly['nadawca_imie'] . ' ' . $szczegoly['nadawca_nazwisko']); ?></strong>
                                        (<?php echo e($szczegoly['nadawca_typ']); ?>)</span>
                                    <span><?php echo formatuj_date_wiadomosci($szczegoly['data_wyslania']); ?></span>
                                </div>
                            </div>
                            <div class="mail-view-body">
                                <?php echo nl2br(e($szczegoly['tresc'])); ?>
                            </div>
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
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                        </path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
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
                                                <?php if ($w['czy_wazne']): ?>
                                                    <span class="mail-important">❗</span>
                                                <?php endif; ?>
                                                <?php if ($w['liczba_zalacznikow'] > 0): ?>
                                                    📎
                                                <?php endif; ?>
                                                <?php echo e($w['temat']); ?>
                                            </div>
                                            <div class="mail-preview"><?php echo e(substr($w['tresc'], 0, 80)); ?>...</div>
                                        </div>
                                        <div class="mail-date">
                                            <?php echo formatuj_date_wiadomosci($w['data_wyslania']); ?>
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

    <!-- Modal tworzenia wiadomości -->
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
                                        <?php if (isset($os['klasa'])): ?> (<?php echo e($os['klasa']); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6c757d;">Przytrzymaj Ctrl aby wybrać wielu odbiorców</small>
                </div>

                <div class="form-group">
                    <label>Temat</label>
                    <input type="text" name="temat" required placeholder="Temat wiadomości">
                </div>

                <div class="form-group">
                    <label>Treść</label>
                    <textarea name="tresc" rows="6" required placeholder="Wpisz treść wiadomości..."></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="czy_wazne"> Oznacz jako ważne
                    </label>
                </div>

                <div class="form-group">
                    <label>Załącznik (opcjonalnie)</label>
                    <input type="file" name="zalacznik" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small style="color: #6c757d;">Dozwolone: PDF, DOC, JPG, PNG (max 10MB)</small>
                </div>

                <button type="submit" class="btn btn-primary btn-full">📨 Wyślij wiadomość</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('composeModal').addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('active');
        });
    </script>
</body>

</html>