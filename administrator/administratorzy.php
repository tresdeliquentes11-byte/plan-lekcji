<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('administrator');

zarzadzaj_sesja($_SESSION['user_id'], 'activity');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'error';
    } elseif (isset($_POST['dodaj'])) {
        $dane = [
            'login' => $_POST['login'],
            'haslo' => $_POST['haslo'],
            'typ' => 'administrator',
            'imie' => $_POST['imie'],
            'nazwisko' => $_POST['nazwisko'],
            'email' => $_POST['email'] ?? null
        ];

        $result = dodaj_uzytkownika($dane);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';

        if ($result['success']) {
            loguj_operacje_uzytkownika('dodanie', $result['id'], "Dodano administratora: {$dane['login']}");
        }
    } elseif (isset($_POST['edytuj'])) {
        $id = intval($_POST['id']);
        $dane = [
            'login' => $_POST['login'],
            'imie' => $_POST['imie'],
            'nazwisko' => $_POST['nazwisko'],
            'email' => $_POST['email'] ?? null
        ];

        if (!empty($_POST['haslo'])) {
            $dane['haslo'] = $_POST['haslo'];
        }

        $result = aktualizuj_uzytkownika($id, $dane);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';

        if ($result['success']) {
            loguj_operacje_uzytkownika('edycja', $id, "Zaktualizowano administratora: {$dane['login']}");
        }
    } elseif (isset($_POST['akcja']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $akcja = $_POST['akcja'];

        switch ($akcja) {
            case 'blokuj':
                $result = zmien_status_uzytkownika($id, 0);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    loguj_operacje_uzytkownika('blokada', $id, "Zablokowano administratora ID: $id");
                }
                break;

            case 'odblokuj':
                $result = zmien_status_uzytkownika($id, 1);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    loguj_operacje_uzytkownika('odblokowanie', $id, "Odblokowano administratora ID: $id");
                }
                break;

            case 'usun':
                // NAJPIERW loguj (przed usunięciem!)
                loguj_operacje_uzytkownika('usuniecie', $id, "Usunięto administratora ID: $id");

                // POTEM usuń
                $result = usun_uzytkownika($id);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

$administratorzy = pobierz_uzytkownikow('administrator');

$edytowany_uzytkownik = null;
if (isset($_GET['edytuj'])) {
    $edytowany_uzytkownik = pobierz_uzytkownika($_GET['edytuj']);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Administratorami - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Zarządzanie Administratorami</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h3 class="card-title">
                        <?php echo $edytowany_uzytkownik ? 'Edytuj administratora' : 'Dodaj nowego administratora'; ?>
                    </h3>
                    <form method="POST" action="administratorzy.php">
                        <?php if ($edytowany_uzytkownik): ?>
                            <input type="hidden" name="id" value="<?php echo $edytowany_uzytkownik['id']; ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Imię *</label>
                                <input type="text" name="imie" value="<?php echo e($edytowany_uzytkownik['imie'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Nazwisko *</label>
                                <input type="text" name="nazwisko" value="<?php echo e($edytowany_uzytkownik['nazwisko'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Login *</label>
                                <input type="text" name="login" value="<?php echo e($edytowany_uzytkownik['login'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Hasło <?php echo $edytowany_uzytkownik ? '(zostaw puste, aby nie zmieniać)' : '*'; ?></label>
                                <input type="password" name="haslo" <?php echo !$edytowany_uzytkownik ? 'required' : ''; ?>>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo e($edytowany_uzytkownik['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $edytowany_uzytkownik ? 'edytuj' : 'dodaj'; ?>" class="btn btn-primary">
                                <?php echo $edytowany_uzytkownik ? 'Zapisz zmiany' : 'Dodaj administratora'; ?>
                            </button>
                            <?php if ($edytowany_uzytkownik): ?>
                                <a href="administratorzy.php" class="btn btn-secondary">Anuluj</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h3 class="card-title">Lista administratorów (<?php echo count($administratorzy); ?>)</h3>

                    <?php if (!empty($administratorzy)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Imię i nazwisko</th>
                                        <th>Login</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Data utworzenia</th>
                                        <th>Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($administratorzy as $u): ?>
                                        <tr class="<?php echo $u['aktywny'] ? '' : 'row-blocked'; ?>">
                                            <td><?php echo $u['id']; ?></td>
                                            <td><strong><?php echo e($u['imie'] . ' ' . $u['nazwisko']); ?></strong></td>
                                            <td><?php echo e($u['login']); ?></td>
                                            <td><?php echo e($u['email'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($u['aktywny']): ?>
                                                    <span class="badge badge-success">Aktywny</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Zablokowany</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($u['data_utworzenia'])); ?></td>
                                            <td class="table-actions">
                                                <a href="administratorzy.php?edytuj=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary">Edytuj</a>
                                                <?php if ($u['aktywny']): ?>
                                                    <a href="administratorzy.php?akcja=blokuj&id=<?php echo $u['id']; ?>"
                                                       class="btn btn-sm btn-warning"
                                                       onclick="return confirm('Czy na pewno chcesz zablokować tego administratora?')">
                                                        Blokuj
                                                    </a>
                                                <?php else: ?>
                                                    <a href="administratorzy.php?akcja=odblokuj&id=<?php echo $u['id']; ?>"
                                                       class="btn btn-sm btn-success">Odblokuj</a>
                                                <?php endif; ?>
                                                <a href="administratorzy.php?akcja=usun&id=<?php echo $u['id']; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Czy na pewno chcesz usunąć tego administratora? Ta operacja jest nieodwracalna!')">
                                                    Usuń
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Brak administratorów w systemie</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
