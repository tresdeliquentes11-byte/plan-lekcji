<?php
require_once '../includes/config.php';
require_once '../includes/oceny_functions.php';
sprawdz_uprawnienia('nauczyciel');

// Pobierz ID nauczyciela
$stmt = $conn->prepare("
    SELECT n.id FROM nauczyciele n WHERE n.uzytkownik_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$nauczyciel = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$nauczyciel) {
    die("Błąd: Nie znaleziono danych nauczyciela");
}
$nauczyciel_id = $nauczyciel['id'];

// Pobierz klasy i przedmioty, których uczy nauczyciel
$klasy_przedmioty = $conn->query("
    SELECT DISTINCT k.id as klasa_id, k.nazwa as klasa_nazwa, 
           p.id as przedmiot_id, p.nazwa as przedmiot_nazwa
    FROM klasa_przedmioty kp
    JOIN klasy k ON kp.klasa_id = k.id
    JOIN przedmioty p ON kp.przedmiot_id = p.id
    WHERE kp.nauczyciel_id = $nauczyciel_id
    ORDER BY k.nazwa, p.nazwa
");

$dane_nauczyciela = [];
while ($row = $klasy_przedmioty->fetch_assoc()) {
    $klasa = $row['klasa_nazwa'];
    if (!isset($dane_nauczyciela[$klasa])) {
        $dane_nauczyciela[$klasa] = ['id' => $row['klasa_id'], 'przedmioty' => []];
    }
    $dane_nauczyciela[$klasa]['przedmioty'][] = [
        'id' => $row['przedmiot_id'],
        'nazwa' => $row['przedmiot_nazwa']
    ];
}

// Wybrana klasa i przedmiot
$wybrana_klasa_id = isset($_GET['klasa']) ? intval($_GET['klasa']) : null;
$wybrany_przedmiot_id = isset($_GET['przedmiot']) ? intval($_GET['przedmiot']) : null;

// Pobierz kategorie ocen
$kategorie = pobierz_kategorie_ocen($conn);

// Obsługa dodawania oceny
$message = '';
$error = '';

// Dodawanie zwykłej oceny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_ocene'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Błąd weryfikacji tokenu CSRF';
    } else {
        $uczen_id = intval($_POST['uczen_id']);
        $przedmiot_id = intval($_POST['przedmiot_id']);
        $kategoria_id = intval($_POST['kategoria_id']);
        $ocena_text = $_POST['ocena'];
        $komentarz = trim($_POST['komentarz'] ?? '');

        // Obsługa niestandardowej kategorii
        if ($kategoria_id == 0 && !empty($_POST['custom_kategoria_nazwa']) && !empty($_POST['custom_kategoria_waga'])) {
            $custom_nazwa = trim($_POST['custom_kategoria_nazwa']);
            $custom_waga = floatval($_POST['custom_kategoria_waga']);
            if ($custom_waga > 0 && $custom_waga <= 5) {
                $kategoria_id = dodaj_kategorie_niestandardowa($conn, $custom_nazwa, $custom_waga);
                $kategorie = pobierz_kategorie_ocen($conn); // odśwież listę
            } else {
                $error = 'Waga musi być między 0.1 a 5.0';
            }
        }

        if (!$error) {
            $ocena = parsuj_ocene($ocena_text);
            if ($ocena === null) {
                $error = 'Nieprawidłowy format oceny. Użyj: 1, 2, 3, 4, 5, 6 lub z +/- (np. 4+, 5-)';
            } else {
                if (dodaj_ocene($conn, $uczen_id, $przedmiot_id, $nauczyciel_id, $kategoria_id, $ocena, $komentarz)) {
                    $message = 'Ocena została dodana pomyślnie';
                } else {
                    $error = 'Wystąpił błąd podczas dodawania oceny';
                }
            }
        }
    }
}

// Edycja oceny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edytuj_ocene'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Błąd weryfikacji tokenu CSRF';
    } else {
        $ocena_id = intval($_POST['ocena_id']);
        $kategoria_id = intval($_POST['kategoria_id']);
        $ocena_text = $_POST['ocena'];
        $komentarz = trim($_POST['komentarz'] ?? '');

        $ocena = parsuj_ocene($ocena_text);
        if ($ocena === null) {
            $error = 'Nieprawidłowy format oceny';
        } else {
            if (edytuj_ocene($conn, $ocena_id, $ocena, $kategoria_id, $komentarz)) {
                $message = 'Ocena została zaktualizowana';
            } else {
                $error = 'Błąd podczas edycji oceny';
            }
        }
    }
}

// Usuwanie oceny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usun_ocene'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Błąd weryfikacji tokenu CSRF';
    } else {
        $ocena_id = intval($_POST['ocena_id']);
        if (usun_ocene($conn, $ocena_id, $nauczyciel_id)) {
            $message = 'Ocena została usunięta';
        } else {
            $error = 'Nie można usunąć tej oceny (brak uprawnień lub ocena nie istnieje)';
        }
    }
}

// Poprawianie oceny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['popraw_ocene'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Błąd weryfikacji tokenu CSRF';
    } else {
        $stara_ocena_id = intval($_POST['stara_ocena_id']);
        $nowa_ocena_text = $_POST['nowa_ocena'];
        $komentarz = trim($_POST['komentarz'] ?? 'Poprawka');

        $nowa_ocena = parsuj_ocene($nowa_ocena_text);
        if ($nowa_ocena === null) {
            $error = 'Nieprawidłowy format oceny poprawkowej';
        } else {
            if (popraw_ocene($conn, $stara_ocena_id, $nowa_ocena, $nauczyciel_id, $komentarz)) {
                $message = 'Ocena poprawkowa została dodana';
            } else {
                $error = 'Błąd podczas dodawania poprawki';
            }
        }
    }
}

// Pobierz uczniów z wybranej klasy
$uczniowie = [];
if ($wybrana_klasa_id) {
    $stmt = $conn->prepare("
        SELECT uc.id as uczen_id, u.imie, u.nazwisko
        FROM uczniowie uc
        JOIN uzytkownicy u ON uc.uzytkownik_id = u.id
        WHERE uc.klasa_id = ? AND u.aktywny = 1
        ORDER BY u.nazwisko, u.imie
    ");
    $stmt->bind_param("i", $wybrana_klasa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $uczniowie[] = $row;
    }
    $stmt->close();
}

// Pobierz oceny klasy jeśli wybrano przedmiot
$oceny_klasy = [];
$srednia_klasy = null;
if ($wybrana_klasa_id && $wybrany_przedmiot_id) {
    $oceny_klasy = pobierz_oceny_klasy($conn, $wybrana_klasa_id, $wybrany_przedmiot_id);
    $srednia_klasy = oblicz_srednia_klasy($conn, $wybrana_klasa_id, $wybrany_przedmiot_id);
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oceny - Panel Nauczyciela</title>
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

        .teacher-badge {
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

        .content-card h2 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 22px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
        }

        .filter-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }

        .filter-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .oceny-table {
            width: 100%;
            border-collapse: collapse;
        }

        .oceny-table th,
        .oceny-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .oceny-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .oceny-table tr:hover {
            background: #f8f9fa;
        }

        .ocena-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
            margin: 2px;
            font-size: 13px;
        }

        .srednia-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 700;
            color: white;
            font-size: 16px;
        }

        .btn-add-grade {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-add-grade:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 22px;
            color: #2c3e50;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6c757d;
        }

        .stats-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }

        .stats-box .number {
            font-size: 36px;
            font-weight: 700;
        }

        .stats-box .label {
            font-size: 14px;
            opacity: 0.9;
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
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .oceny-lista {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
    </style>
</head>

<body>
    <div class="teacher-layout">
        <div class="teacher-container">
            <header class="teacher-header">
                <h1>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                    System Ocen
                    <span class="teacher-badge">Nauczyciel</span>
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

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo e($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="content-card">
                <h2>📝 Wystawianie Ocen</h2>

                <form method="get" class="filter-row">
                    <div class="filter-group">
                        <label>Wybierz klasę</label>
                        <select name="klasa" onchange="this.form.submit()">
                            <option value="">-- Wybierz klasę --</option>
                            <?php foreach ($dane_nauczyciela as $klasa => $info): ?>
                                <option value="<?php echo $info['id']; ?>" <?php echo $wybrana_klasa_id == $info['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($klasa); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($wybrana_klasa_id): ?>
                        <div class="filter-group">
                            <label>Wybierz przedmiot</label>
                            <select name="przedmiot" onchange="this.form.submit()">
                                <option value="">-- Wybierz przedmiot --</option>
                                <?php
                                foreach ($dane_nauczyciela as $klasa => $info) {
                                    if ($info['id'] == $wybrana_klasa_id) {
                                        foreach ($info['przedmioty'] as $przedmiot) {
                                            $selected = $wybrany_przedmiot_id == $przedmiot['id'] ? 'selected' : '';
                                            echo '<option value="' . $przedmiot['id'] . '" ' . $selected . '>' . e($przedmiot['nazwa']) . '</option>';
                                        }
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($wybrana_klasa_id && $wybrany_przedmiot_id && $srednia_klasy): ?>
                    <div class="stats-box">
                        <div class="number"><?php echo $srednia_klasy; ?></div>
                        <div class="label">Średnia klasy z tego przedmiotu</div>
                    </div>
                <?php endif; ?>

                <?php if ($wybrana_klasa_id && $wybrany_przedmiot_id && !empty($uczniowie)): ?>
                    <table class="oceny-table">
                        <thead>
                            <tr>
                                <th>Uczeń</th>
                                <th>Oceny</th>
                                <th>Średnia</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uczniowie as $uczen): ?>
                                <?php
                                $oceny_ucznia = pobierz_oceny_ucznia_przedmiot($conn, $uczen['uczen_id'], $wybrany_przedmiot_id);
                                $srednia = oblicz_srednia_wazona($conn, $uczen['uczen_id'], $wybrany_przedmiot_id);
                                ?>
                                <tr>
                                    <td><strong><?php echo e($uczen['nazwisko'] . ' ' . $uczen['imie']); ?></strong></td>
                                    <td>
                                        <div class="oceny-lista">
                                            <?php foreach ($oceny_ucznia as $o): ?>
                                                <?php
                                                $is_poprawka = $o['poprawia_ocene_id'] !== null;
                                                $opacity = $o['czy_poprawiona'] ? '0.5' : '1';
                                                $komentarz_safe = htmlspecialchars($o['komentarz'] ?? '', ENT_QUOTES);
                                                ?>
                                                <span class="ocena-badge ocena-clickable"
                                                    style="background: <?php echo kolor_oceny($o['ocena']); ?>; opacity: <?php echo $opacity; ?>"
                                                    title="<?php echo e($o['kategoria'] . ' - ' . formatuj_date($o['data_wystawienia'])); ?>"
                                                    data-id="<?php echo $o['id']; ?>" data-ocena="<?php echo $o['ocena']; ?>"
                                                    data-kategoria="<?php echo $o['kategoria_id']; ?>"
                                                    data-komentarz="<?php echo $komentarz_safe; ?>"
                                                    data-poprawiona="<?php echo $o['czy_poprawiona'] ? '1' : '0'; ?>"
                                                    data-uczen="<?php echo $uczen['uczen_id']; ?>">
                                                    <?php if ($is_poprawka): ?><span style="font-size:10px">★</span><?php endif; ?>
                                                    <?php echo formatuj_ocene($o['ocena']); ?>
                                                    <?php if ($o['czy_poprawiona']): ?><span
                                                            style="font-size:9px">✗</span><?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($srednia): ?>
                                            <span class="srednia-badge" style="background: <?php echo kolor_oceny($srednia); ?>;">
                                                <?php echo $srednia; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-add-grade"
                                            onclick="openModal(<?php echo $uczen['uczen_id']; ?>, '<?php echo e($uczen['imie'] . ' ' . $uczen['nazwisko']); ?>')">
                                            + Dodaj ocenę
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($wybrana_klasa_id && !$wybrany_przedmiot_id): ?>
                    <div class="alert alert-info">Wybierz przedmiot, aby wyświetlić oceny uczniów.</div>
                <?php elseif (!$wybrana_klasa_id): ?>
                    <div class="alert alert-info">Wybierz klasę, aby rozpocząć wystawianie ocen.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal dodawania oceny -->
    <div class="modal" id="gradeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Dodaj ocenę</h3>
                <button class="modal-close" onclick="closeModal('gradeModal')">&times;</button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="dodaj_ocene" value="1">
                <input type="hidden" name="uczen_id" id="modal_uczen_id">
                <input type="hidden" name="przedmiot_id" value="<?php echo $wybrany_przedmiot_id; ?>">

                <div class="form-group">
                    <label>Uczeń</label>
                    <input type="text" id="modal_uczen_nazwa" readonly class="form-control">
                </div>

                <div class="form-group">
                    <label>Kategoria oceny</label>
                    <select name="kategoria_id" id="add_kategoria" onchange="toggleCustomCategory(this)">
                        <?php foreach ($kategorie as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>">
                                <?php echo e($kat['nazwa']); ?> (waga: <?php echo $kat['waga']; ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="0">➕ Niestandardowa kategoria...</option>
                    </select>
                </div>

                <div class="form-group" id="customCategoryGroup"
                    style="display:none; background:#f8f9fa; padding:15px; border-radius:8px;">
                    <label>Nazwa kategorii</label>
                    <input type="text" name="custom_kategoria_nazwa" placeholder="np. Konkurs, Referat">
                    <label style="margin-top:10px;">Waga (0.1-5.0)</label>
                    <input type="number" name="custom_kategoria_waga" step="0.1" min="0.1" max="5"
                        placeholder="np. 2.0">
                </div>

                <div class="form-group">
                    <label>Ocena (np. 4, 4+, 5-)</label>
                    <input type="text" name="ocena" required placeholder="np. 4, 4+, 5-">
                </div>

                <div class="form-group">
                    <label>Komentarz (opcjonalnie)</label>
                    <textarea name="komentarz" rows="2" placeholder="Np. temat sprawdzianu, uwagi..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Zapisz ocenę</button>
            </form>
        </div>
    </div>

    <!-- Modal akcji na ocenie -->
    <!-- Modal akcji na ocenie -->
    <div class="modal" id="actionsModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <h3 class="modal-title">Akcje dla oceny</h3>
                <button class="modal-close" onclick="closeModal('actionsModal')">&times;</button>
            </div>
            <p id="action_info" style="margin-bottom:20px;"></p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <button class="btn btn-secondary" onclick="openEditModal()" id="btn_edit">✏️ Edytuj ocenę</button>
                <button class="btn btn-primary" onclick="openCorrectionModal()" id="btn_correct">📝 Wstaw
                    poprawkę</button>

                <form method="post" id="form_delete" onsubmit="return confirm('Na pewno usunąć tę ocenę?');"
                    style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="usun_ocene" value="1">
                    <input type="hidden" name="ocena_id" id="delete_ocena_id">
                    <button type="submit" class="btn btn-danger" id="btn_delete" style="width:100%">🗑️ Usuń
                        ocenę</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal edycji oceny -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edytuj ocenę</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="edytuj_ocene" value="1">
                <input type="hidden" name="ocena_id" id="edit_ocena_id">

                <div class="form-group">
                    <label>Kategoria</label>
                    <select name="kategoria_id" id="edit_kategoria">
                        <?php foreach ($kategorie as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>"><?php echo e($kat['nazwa']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ocena</label>
                    <input type="text" name="ocena" id="edit_ocena" required>
                </div>

                <div class="form-group">
                    <label>Komentarz</label>
                    <textarea name="komentarz" id="edit_komentarz" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Zapisz zmiany</button>
            </form>
        </div>
    </div>

    <!-- Modal poprawiania oceny -->
    <div class="modal" id="correctionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Wstaw poprawkę</h3>
                <button class="modal-close" onclick="closeModal('correctionModal')">&times;</button>
            </div>
            <p style="background:#fff3cd; padding:10px; border-radius:6px; margin-bottom:15px;">
                ⚠️ Stara ocena zostanie oznaczona jako poprawiona (nie będzie liczyć się do średniej).
                Nowa ocena pojawi się z gwiazdką ★.
            </p>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="popraw_ocene" value="1">
                <input type="hidden" name="stara_ocena_id" id="correction_ocena_id">

                <div class="form-group">
                    <label>Nowa ocena (poprawka)</label>
                    <input type="text" name="nowa_ocena" required placeholder="np. 4, 4+, 5-">
                </div>

                <div class="form-group">
                    <label>Komentarz</label>
                    <textarea name="komentarz" rows="2" placeholder="np. Poprawka sprawdzianu">Poprawka oceny</textarea>
                </div>

                <button type="submit" class="btn btn-success btn-full">Zapisz poprawkę</button>
            </form>
        </div>
    </div>

    <style>
        .ocena-clickable {
            cursor: pointer;
            transition: transform 0.2s;
        }

        .ocena-clickable:hover {
            transform: scale(1.15);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            text-align: center;
        }
    </style>

    <script>
        let currentGrade = {};

        function openModal(uczenId, uczenNazwa) {
            document.getElementById('modal_uczen_id').value = uczenId;
            document.getElementById('modal_uczen_nazwa').value = uczenNazwa;
            document.getElementById('gradeModal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function toggleCustomCategory(select) {
            document.getElementById('customCategoryGroup').style.display = select.value === '0' ? 'block' : 'none';
        }

        function showGradeActions(el) {
            currentGrade = {
                id: el.dataset.id,
                ocena: el.dataset.ocena,
                kategoriaId: el.dataset.kategoria,
                komentarz: el.dataset.komentarz || '',
                czyPoprawiona: el.dataset.poprawiona === '1',
                uczenId: el.dataset.uczen
            };

            document.getElementById('action_info').innerHTML =
                '<strong>Ocena:</strong> ' + currentGrade.ocena + '<br><small>' + (currentGrade.komentarz || 'Brak komentarza') + '</small>';

            document.getElementById('action_info').innerHTML =
                '<strong>Ocena:</strong> ' + currentGrade.ocena + '<br><small>' + (currentGrade.komentarz || 'Brak komentarza') + '</small>';

            // Ukryj przyciski dla już poprawionych ocen
            const display = currentGrade.czyPoprawiona ? 'none' : 'block';
            document.getElementById('btn_edit').style.display = display;
            document.getElementById('btn_correct').style.display = display;
            document.getElementById('form_delete').style.display = display;

            document.getElementById('delete_ocena_id').value = currentGrade.id;

            document.getElementById('actionsModal').classList.add('active');
        }

        function openEditModal() {
            closeModal('actionsModal');
            document.getElementById('edit_ocena_id').value = currentGrade.id;
            document.getElementById('edit_ocena').value = currentGrade.ocena;
            document.getElementById('edit_kategoria').value = currentGrade.kategoriaId;
            document.getElementById('edit_komentarz').value = currentGrade.komentarz;
            document.getElementById('editModal').classList.add('active');
        }

        function openCorrectionModal() {
            closeModal('actionsModal');
            document.getElementById('correction_ocena_id').value = currentGrade.id;
            document.getElementById('correctionModal').classList.add('active');
        }

        // Event listener dla badge ocen
        document.querySelectorAll('.ocena-clickable').forEach(badge => {
            badge.addEventListener('click', function () {
                showGradeActions(this);
            });
        });

        // Zamknij modal po kliknięciu poza nim
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>
</body>

</html>