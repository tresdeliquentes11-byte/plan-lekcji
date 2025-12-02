<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('administrator');

// Aktualizuj aktywność sesji
zarzadzaj_sesja($_SESSION['user_id'], 'activity');

$message = '';
$message_type = '';

// Obsługa akcji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['dodaj'])) {
        // Dodawanie ucznia
        $dane = [
            'login' => $_POST['login'],
            'haslo' => $_POST['haslo'],
            'typ' => 'uczen',
            'imie' => $_POST['imie'],
            'nazwisko' => $_POST['nazwisko'],
            'email' => $_POST['email'] ?? null,
            'klasa_id' => $_POST['klasa_id']
        ];

        $result = dodaj_uzytkownika($dane);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';

        if ($result['success']) {
            loguj_operacje_uzytkownika('dodanie', $result['id'], "Dodano ucznia: {$dane['login']}");
        }
    } elseif (isset($_POST['edytuj'])) {
        // Edycja ucznia
        $id = $_POST['id'];
        $dane = [
            'login' => $_POST['login'],
            'imie' => $_POST['imie'],
            'nazwisko' => $_POST['nazwisko'],
            'email' => $_POST['email'] ?? null
        ];

        // Dodaj hasło tylko jeśli zostało podane
        if (!empty($_POST['haslo'])) {
            $dane['haslo'] = $_POST['haslo'];
        }

        $result = aktualizuj_uzytkownika($id, $dane);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';

        // Aktualizuj klasę
        if ($result['success'] && isset($_POST['klasa_id'])) {
            aktualizuj_klase_ucznia($id, $_POST['klasa_id']);
            loguj_operacje_uzytkownika('edycja', $id, "Zaktualizowano ucznia: {$dane['login']}");
        }
    }
}

// Obsługa akcji GET
if (isset($_GET['akcja'])) {
    $id = $_GET['id'] ?? 0;

    switch ($_GET['akcja']) {
        case 'blokuj':
            $result = zmien_status_uzytkownika($id, 0);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            if ($result['success']) {
                loguj_operacje_uzytkownika('blokada', $id, "Zablokowano ucznia ID: $id");
            }
            break;

        case 'odblokuj':
            $result = zmien_status_uzytkownika($id, 1);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            if ($result['success']) {
                loguj_operacje_uzytkownika('odblokowanie', $id, "Odblokowano ucznia ID: $id");
            }
            break;

        case 'usun':
            $result = usun_uzytkownika($id);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            if ($result['success']) {
                loguj_operacje_uzytkownika('usuniecie', $id, "Usunięto ucznia ID: $id");
            }
            break;
    }
}

// Pobierz dane
$uczniowie_query = $conn->query("
    SELECT u.*, uc.klasa_id, k.nazwa as klasa_nazwa
    FROM uzytkownicy u
    LEFT JOIN uczniowie uc ON u.id = uc.uzytkownik_id
    LEFT JOIN klasy k ON uc.klasa_id = k.id
    WHERE u.typ = 'uczen'
    ORDER BY k.nazwa, u.nazwisko, u.imie
");

$klasy = pobierz_klasy();

// Dane do edycji
$edytowany_uzytkownik = null;
if (isset($_GET['edytuj'])) {
    $edytowany_uzytkownik = pobierz_uzytkownika($_GET['edytuj']);
    if ($edytowany_uzytkownik) {
        // Pobierz klasę ucznia
        $stmt = $conn->prepare("SELECT klasa_id FROM uczniowie WHERE uzytkownik_id = ?");
        $stmt->bind_param("i", $edytowany_uzytkownik['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $edytowany_uzytkownik['klasa_id'] = $row['klasa_id'];
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Uczniami - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Zarządzanie Uczniami</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
                <?php endif; ?>

                <!-- Formularz dodawania/edycji -->
                <div class="card">
                    <h3 class="card-title">
                        <?php echo $edytowany_uzytkownik ? 'Edytuj ucznia' : 'Dodaj nowego ucznia'; ?>
                    </h3>
                    <form method="POST" action="uczniowie.php">
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

                            <div class="form-group">
                                <label>Klasa *</label>
                                <select name="klasa_id" required>
                                    <option value="">Wybierz klasę</option>
                                    <?php foreach ($klasy as $klasa): ?>
                                        <option value="<?php echo $klasa['id']; ?>"
                                            <?php echo (isset($edytowany_uzytkownik['klasa_id']) && $edytowany_uzytkownik['klasa_id'] == $klasa['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($klasa['nazwa']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $edytowany_uzytkownik ? 'edytuj' : 'dodaj'; ?>" class="btn btn-primary">
                                <?php echo $edytowany_uzytkownik ? 'Zapisz zmiany' : 'Dodaj ucznia'; ?>
                            </button>
                            <?php if ($edytowany_uzytkownik): ?>
                                <a href="uczniowie.php" class="btn btn-secondary">Anuluj</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Lista uczniów -->
                <div class="card">
                    <h3 class="card-title">Lista uczniów (<?php echo $uczniowie_query->num_rows; ?>)</h3>

                    <?php if ($uczniowie_query->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Imię i nazwisko</th>
                                        <th>Login</th>
                                        <th>Email</th>
                                        <th>Klasa</th>
                                        <th>Status</th>
                                        <th>Data utworzenia</th>
                                        <th>Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($u = $uczniowie_query->fetch_assoc()): ?>
                                        <tr class="<?php echo $u['aktywny'] ? '' : 'row-blocked'; ?>">
                                            <td><?php echo $u['id']; ?></td>
                                            <td>
                                                <strong><?php echo e($u['imie'] . ' ' . $u['nazwisko']); ?></strong>
                                            </td>
                                            <td><?php echo e($u['login']); ?></td>
                                            <td><?php echo e($u['email'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($u['klasa_nazwa']): ?>
                                                    <span class="badge badge-info"><?php echo e($u['klasa_nazwa']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Brak</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($u['aktywny']): ?>
                                                    <span class="badge badge-success">Aktywny</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Zablokowany</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($u['data_utworzenia'])); ?></td>
                                            <td class="table-actions">
                                                <a href="uczniowie.php?edytuj=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary" title="Edytuj">
                                                    Edytuj
                                                </a>
                                                <?php if ($u['aktywny']): ?>
                                                    <a href="uczniowie.php?akcja=blokuj&id=<?php echo $u['id']; ?>"
                                                       class="btn btn-sm btn-warning"
                                                       onclick="return confirm('Czy na pewno chcesz zablokować tego ucznia?')"
                                                       title="Zablokuj">
                                                        Blokuj
                                                    </a>
                                                <?php else: ?>
                                                    <a href="uczniowie.php?akcja=odblokuj&id=<?php echo $u['id']; ?>"
                                                       class="btn btn-sm btn-success"
                                                       title="Odblokuj">
                                                        Odblokuj
                                                    </a>
                                                <?php endif; ?>
                                                <a href="uczniowie.php?akcja=usun&id=<?php echo $u['id']; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Czy na pewno chcesz usunąć tego ucznia? Ta operacja jest nieodwracalna!')"
                                                   title="Usuń">
                                                    Usuń
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Brak uczniów w systemie</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
