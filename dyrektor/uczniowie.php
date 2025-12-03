<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie ucznia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj'])) {
    $imie = trim($_POST['imie']);
    $nazwisko = trim($_POST['nazwisko']);
    $email = trim($_POST['email']);
    $login = trim($_POST['login']);
    $haslo = password_hash($_POST['haslo'], PASSWORD_DEFAULT);
    $klasa_id = intval($_POST['klasa_id']);

    $conn->begin_transaction();

    try {
        // Sprawdź czy login jest unikalny
        $check = $conn->prepare("SELECT id FROM uzytkownicy WHERE login = ?");
        $check->bind_param("s", $login);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            throw new Exception("Login '$login' jest już zajęty");
        }

        // Dodaj użytkownika
        $stmt = $conn->prepare("INSERT INTO uzytkownicy (login, haslo, typ, imie, nazwisko, email) VALUES (?, ?, 'uczen', ?, ?, ?)");
        $stmt->bind_param("sssss", $login, $haslo, $imie, $nazwisko, $email);
        $stmt->execute();
        $uzytkownik_id = $conn->insert_id;

        // Dodaj ucznia
        $stmt = $conn->prepare("INSERT INTO uczniowie (uzytkownik_id, klasa_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $uzytkownik_id, $klasa_id);
        $stmt->execute();

        $conn->commit();
        $message = 'Uczeń został dodany pomyślnie';
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Błąd: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Edycja ucznia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edytuj'])) {
    $id = intval($_POST['id']);
    $imie = trim($_POST['imie']);
    $nazwisko = trim($_POST['nazwisko']);
    $email = trim($_POST['email']);
    $login = trim($_POST['login']);
    $klasa_id = intval($_POST['klasa_id']);

    $conn->begin_transaction();

    try {
        // Sprawdź czy login jest unikalny (oprócz tego użytkownika)
        $check = $conn->prepare("SELECT id FROM uzytkownicy WHERE login = ? AND id != ?");
        $check->bind_param("si", $login, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            throw new Exception("Login '$login' jest już zajęty");
        }

        // Aktualizuj użytkownika
        if (!empty($_POST['haslo'])) {
            $haslo = password_hash($_POST['haslo'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE uzytkownicy SET login = ?, haslo = ?, imie = ?, nazwisko = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $login, $haslo, $imie, $nazwisko, $email, $id);
        } else {
            $stmt = $conn->prepare("UPDATE uzytkownicy SET login = ?, imie = ?, nazwisko = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $login, $imie, $nazwisko, $email, $id);
        }
        $stmt->execute();

        // Aktualizuj klasę ucznia
        $stmt = $conn->prepare("UPDATE uczniowie SET klasa_id = ? WHERE uzytkownik_id = ?");
        $stmt->bind_param("ii", $klasa_id, $id);
        $stmt->execute();

        $conn->commit();
        $message = 'Dane ucznia zostały zaktualizowane';
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Błąd: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Blokowanie/Odblokowanie ucznia
if (isset($_GET['akcja']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    if ($_GET['akcja'] === 'blokuj') {
        $conn->query("UPDATE uzytkownicy SET aktywny = 0 WHERE id = $id");
        $message = 'Uczeń został zablokowany';
        $message_type = 'success';
    } elseif ($_GET['akcja'] === 'odblokuj') {
        $conn->query("UPDATE uzytkownicy SET aktywny = 1 WHERE id = $id");
        $message = 'Uczeń został odblokowany';
        $message_type = 'success';
    } elseif ($_GET['akcja'] === 'usun') {
        // Sprawdź czy można usunąć
        $conn->begin_transaction();
        try {
            // Usuń powiązane dane
            $conn->query("DELETE FROM uczniowie WHERE uzytkownik_id = $id");
            $conn->query("DELETE FROM uzytkownicy WHERE id = $id");
            $conn->commit();
            $message = 'Uczeń został usunięty';
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Błąd podczas usuwania: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Pobierz dane
$uczniowie = $conn->query("
    SELECT u.*, uc.klasa_id, k.nazwa as klasa_nazwa
    FROM uzytkownicy u
    LEFT JOIN uczniowie uc ON u.id = uc.uzytkownik_id
    LEFT JOIN klasy k ON uc.klasa_id = k.id
    WHERE u.typ = 'uczen'
    ORDER BY k.nazwa, u.nazwisko, u.imie
");

$klasy = $conn->query("SELECT * FROM klasy ORDER BY nazwa");

// Dane do edycji
$edytowany_uzytkownik = null;
if (isset($_GET['edytuj'])) {
    $id = intval($_GET['edytuj']);
    $result = $conn->query("
        SELECT u.*, uc.klasa_id
        FROM uzytkownicy u
        LEFT JOIN uczniowie uc ON u.id = uc.uzytkownik_id
        WHERE u.id = $id AND u.typ = 'uczen'
    ");
    $edytowany_uzytkownik = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Uczniami</title>
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
                <li><a href="uczniowie.php" class="active">Uczniowie</a></li>
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
            <h2 class="page-title">Zarządzanie Uczniami</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>

            <!-- Formularz dodawania/edycji -->
            <div class="card">
                <h3 class="card-title">
                    <?php echo $edytowany_uzytkownik ? 'Edytuj ucznia' : 'Dodaj nowego ucznia'; ?>
                </h3>
                <form method="POST">
                    <?php if ($edytowany_uzytkownik): ?>
                        <input type="hidden" name="id" value="<?php echo $edytowany_uzytkownik['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Imię *</label>
                            <input type="text" name="imie" value="<?php echo e($edytowany_uzytkownik['imie'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Nazwisko *</label>
                            <input type="text" name="nazwisko" value="<?php echo e($edytowany_uzytkownik['nazwisko'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Login *</label>
                            <input type="text" name="login" value="<?php echo e($edytowany_uzytkownik['login'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Hasło <?php echo $edytowany_uzytkownik ? '(zostaw puste, aby nie zmieniać)' : '*'; ?></label>
                            <input type="password" name="haslo" <?php echo !$edytowany_uzytkownik ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo e($edytowany_uzytkownik['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Klasa *</label>
                            <select name="klasa_id" required>
                                <option value="">Wybierz klasę</option>
                                <?php
                                $klasy->data_seek(0);
                                while ($klasa = $klasy->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $klasa['id']; ?>"
                                        <?php echo (isset($edytowany_uzytkownik['klasa_id']) && $edytowany_uzytkownik['klasa_id'] == $klasa['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($klasa['nazwa']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
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
                <h3 class="card-title">Lista uczniów (<?php echo $uczniowie->num_rows; ?>)</h3>

                <?php if ($uczniowie->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Imię i nazwisko</th>
                                    <th>Login</th>
                                    <th>Email</th>
                                    <th>Klasa</th>
                                    <th>Status</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($u = $uczniowie->fetch_assoc()): ?>
                                    <tr style="<?php echo !$u['aktywny'] ? 'opacity: 0.6; background: #f8f8f8;' : ''; ?>">
                                        <td><?php echo $u['id']; ?></td>
                                        <td>
                                            <strong><?php echo e($u['imie'] . ' ' . $u['nazwisko']); ?></strong>
                                        </td>
                                        <td><?php echo e($u['login']); ?></td>
                                        <td><?php echo e($u['email'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($u['klasa_nazwa']): ?>
                                                <span style="background: #4CAF50; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                                    <?php echo e($u['klasa_nazwa']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #ff9800; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                                    Brak
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['aktywny']): ?>
                                                <span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                                    Aktywny
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                                    Zablokowany
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="uczniowie.php?edytuj=<?php echo $u['id']; ?>" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">
                                                Edytuj
                                            </a>
                                            <?php if ($u['aktywny']): ?>
                                                <a href="uczniowie.php?akcja=blokuj&id=<?php echo $u['id']; ?>"
                                                   class="btn btn-warning"
                                                   style="font-size: 12px; padding: 5px 10px;"
                                                   onclick="return confirm('Czy na pewno chcesz zablokować tego ucznia?')">
                                                    Blokuj
                                                </a>
                                            <?php else: ?>
                                                <a href="uczniowie.php?akcja=odblokuj&id=<?php echo $u['id']; ?>"
                                                   class="btn btn-success"
                                                   style="font-size: 12px; padding: 5px 10px;">
                                                    Odblokuj
                                                </a>
                                            <?php endif; ?>
                                            <a href="uczniowie.php?akcja=usun&id=<?php echo $u['id']; ?>"
                                               class="btn btn-danger"
                                               style="font-size: 12px; padding: 5px 10px;"
                                               onclick="return confirm('Czy na pewno chcesz usunąć tego ucznia? Ta operacja jest nieodwracalna!')">
                                                Usuń
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: #666; padding: 20px;">Brak uczniów w systemie</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
