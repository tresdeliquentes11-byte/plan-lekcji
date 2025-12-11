<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
sprawdz_uprawnienia('administrator');

// Aktualizuj aktywność sesji
zarzadzaj_sesja($_SESSION['user_id'], 'activity');

$message = '';
$message_type = '';

// Dodawanie nowej klasy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_klase'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'danger';
    } else {
        $nazwa = trim($_POST['nazwa']);
        $ilosc_godzin_dziennie = intval($_POST['ilosc_godzin_dziennie']);
        $rozszerzenie_1 = $_POST['rozszerzenie_1'];
        $rozszerzenie_2 = $_POST['rozszerzenie_2'];
        $wychowawca_id = !empty($_POST['wychowawca_id']) ? intval($_POST['wychowawca_id']) : null;

        // Walidacja
        if (empty($nazwa)) {
            $message = 'Nazwa klasy jest wymagana';
            $message_type = 'danger';
        } elseif (strlen($nazwa) > 10) {
            $message = 'Nazwa klasy może mieć maksymalnie 10 znaków';
            $message_type = 'danger';
        } else {
            // Sprawdź czy klasa o takiej nazwie już istnieje
            $check = $conn->prepare("SELECT id FROM klasy WHERE nazwa = ?");
            $check->bind_param("s", $nazwa);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $message = 'Klasa o nazwie "' . e($nazwa) . '" już istnieje';
                $message_type = 'danger';
            } else {
                // Dodaj klasę
                $stmt = $conn->prepare("INSERT INTO klasy (nazwa, wychowawca_id, ilosc_godzin_dziennie, rozszerzenie_1, rozszerzenie_2) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("siiss", $nazwa, $wychowawca_id, $ilosc_godzin_dziennie, $rozszerzenie_1, $rozszerzenie_2);

                if ($stmt->execute()) {
                    $message = 'Klasa "' . e($nazwa) . '" została dodana pomyślnie';
                    $message_type = 'success';

                    // Logowanie akcji
                    loguj_aktywnosc($_SESSION['user_id'], 'dodanie_klasy', 'Dodano klasę: ' . $nazwa);
                } else {
                    error_log("Błąd dodawania klasy: " . $conn->error);
                    $message = 'Błąd podczas dodawania klasy';
                    $message_type = 'danger';
                }
                $stmt->close();
            }
            $check->close();
        }
    }
}

// Usuwanie klasy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usun_klase'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.';
        $message_type = 'danger';
    } else {
        $klasa_id = intval($_POST['klasa_id']);

        // Pobierz nazwę klasy przed usunięciem
        $stmt_get_klasa = $conn->prepare("SELECT nazwa FROM klasy WHERE id = ?");
        $stmt_get_klasa->bind_param("i", $klasa_id);
        $stmt_get_klasa->execute();
        $klasa_result = $stmt_get_klasa->get_result();
        $klasa = $klasa_result->fetch_assoc();
        $stmt_get_klasa->close();

        if ($klasa) {
            // Sprawdź czy są powiązane dane
            $powiazania = [];

            // Sprawdź uczniów
            $stmt_uczniowie = $conn->prepare("SELECT COUNT(*) as cnt FROM uczniowie WHERE klasa_id = ?");
            $stmt_uczniowie->bind_param("i", $klasa_id);
            $stmt_uczniowie->execute();
            $uczniowie_count = $stmt_uczniowie->get_result()->fetch_assoc()['cnt'];
            $stmt_uczniowie->close();

            if ($uczniowie_count > 0) {
                $powiazania[] = "$uczniowie_count uczniów";
            }

            // Sprawdź przypisane przedmioty
            $stmt_przedmioty = $conn->prepare("SELECT COUNT(*) as cnt FROM klasa_przedmioty WHERE klasa_id = ?");
            $stmt_przedmioty->bind_param("i", $klasa_id);
            $stmt_przedmioty->execute();
            $przedmioty_count = $stmt_przedmioty->get_result()->fetch_assoc()['cnt'];
            $stmt_przedmioty->close();

            if ($przedmioty_count > 0) {
                $powiazania[] = "$przedmioty_count przypisanych przedmiotów";
            }

            // Sprawdź plan lekcji
            $stmt_plan = $conn->prepare("SELECT COUNT(*) as cnt FROM plan_lekcji WHERE klasa_id = ?");
            $stmt_plan->bind_param("i", $klasa_id);
            $stmt_plan->execute();
            $plan_count = $stmt_plan->get_result()->fetch_assoc()['cnt'];
            $stmt_plan->close();

            if ($plan_count > 0) {
                $powiazania[] = "$plan_count lekcji w planie";
            }

            if (!empty($powiazania)) {
                $message = 'Nie można usunąć klasy "' . e($klasa['nazwa']) . '". Klasa ma powiązane dane: ' . implode(', ', $powiazania) . '. Najpierw usuń lub przenieś powiązane dane.';
                $message_type = 'danger';
            } else {
                // Usuń klasę
                $stmt_delete = $conn->prepare("DELETE FROM klasy WHERE id = ?");
                $stmt_delete->bind_param("i", $klasa_id);

                if ($stmt_delete->execute()) {
                    $message = 'Klasa "' . e($klasa['nazwa']) . '" została usunięta pomyślnie';
                    $message_type = 'success';

                    // Logowanie akcji
                    loguj_aktywnosc($_SESSION['user_id'], 'usuniecie_klasy', 'Usunięto klasę: ' . $klasa['nazwa']);
                } else {
                    error_log("Błąd usuwania klasy: " . $conn->error);
                    $message = 'Błąd podczas usuwania klasy';
                    $message_type = 'danger';
                }
                $stmt_delete->close();
            }
        }
    }
}

// Pobierz nauczycieli, którzy NIE są wychowawcami żadnej klasy
$nauczyciele_bez_klasy = $conn->query("
    SELECT n.id, u.imie, u.nazwisko
    FROM nauczyciele n
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    WHERE n.id NOT IN (SELECT wychowawca_id FROM klasy WHERE wychowawca_id IS NOT NULL)
    ORDER BY u.nazwisko, u.imie
");

// Pobierz wszystkie klasy z dodatkowymi informacjami
$klasy = $conn->query("
    SELECT
        k.*,
        u.imie,
        u.nazwisko,
        (SELECT COUNT(*) FROM uczniowie WHERE klasa_id = k.id) as liczba_uczniow,
        (SELECT COUNT(*) FROM klasa_przedmioty WHERE klasa_id = k.id) as liczba_przedmiotow
    FROM klasy k
    LEFT JOIN nauczyciele n ON k.wychowawca_id = n.id
    LEFT JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    ORDER BY k.nazwa
");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Klasami - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .klasy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .klasa-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .klasa-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .klasa-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .klasa-nazwa {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        .klasa-info {
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }

        .klasa-info-label {
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }

        .klasa-stats {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }

        .stat-badge {
            flex: 1;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }

        .klasa-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .btn-delete {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: opacity 0.2s;
            flex: 1;
        }

        .btn-delete:hover {
            opacity: 0.9;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #dc3545;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .form-grid .form-group:first-child {
            grid-column: 1 / 2;
        }

        .form-grid .form-group:nth-child(2) {
            grid-column: 2 / 3;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Zarządzanie Klasami</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <!-- Formularz dodawania klasy -->
                <div class="card">
                    <h3 class="card-title">Dodaj nową klasę</h3>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nazwa klasy *</label>
                                <input type="text" name="nazwa" placeholder="np. 1A, 2B, 3C" required maxlength="10">
                                <small>Maksymalnie 10 znaków</small>
                            </div>

                            <div class="form-group">
                                <label>Wychowawca</label>
                                <select name="wychowawca_id">
                                    <option value="">Brak (przypisz później)</option>
                                    <?php if ($nauczyciele_bez_klasy->num_rows > 0): ?>
                                        <?php while ($nauczyciel = $nauczyciele_bez_klasy->fetch_assoc()): ?>
                                            <option value="<?php echo $nauczyciel['id']; ?>">
                                                <?php echo e($nauczyciel['imie'] . ' ' . $nauczyciel['nazwisko']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Wszyscy nauczyciele są już wychowawcami</option>
                                    <?php endif; ?>
                                </select>
                                <small>Tylko nauczyciele bez przypisanej klasy</small>
                            </div>

                            <div class="form-group">
                                <label>Maksymalna liczba godzin dziennie *</label>
                                <input type="number" name="ilosc_godzin_dziennie" value="8" min="5" max="10" required>
                            </div>

                            <div class="form-group">
                                <label>Rozszerzenie 1 *</label>
                                <select name="rozszerzenie_1" required>
                                    <option value="">Wybierz</option>
                                    <option value="Matematyka rozszerzona">Matematyka rozszerzona</option>
                                    <option value="Fizyka rozszerzona">Fizyka rozszerzona</option>
                                    <option value="Język angielski rozszerzony">Język angielski rozszerzony</option>
                                    <option value="Informatyka rozszerzona">Informatyka rozszerzona</option>
                                    <option value="Biologia rozszerzona">Biologia rozszerzona</option>
                                    <option value="Chemia rozszerzona">Chemia rozszerzona</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Rozszerzenie 2 *</label>
                                <select name="rozszerzenie_2" required>
                                    <option value="">Wybierz</option>
                                    <option value="Matematyka rozszerzona">Matematyka rozszerzona</option>
                                    <option value="Fizyka rozszerzona">Fizyka rozszerzona</option>
                                    <option value="Język angielski rozszerzony">Język angielski rozszerzony</option>
                                    <option value="Informatyka rozszerzona">Informatyka rozszerzona</option>
                                    <option value="Biologia rozszerzona">Biologia rozszerzona</option>
                                    <option value="Chemia rozszerzona">Chemia rozszerzona</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="dodaj_klase" class="btn btn-primary" style="margin-top: 15px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Dodaj klasę
                        </button>
                    </form>
                </div>

                <!-- Lista klas -->
                <div class="card">
                    <h3 class="card-title">Lista klas (<?php echo $klasy->num_rows; ?>)</h3>

                    <?php if ($klasy->num_rows > 0): ?>
                        <div class="klasy-grid">
                            <?php while ($klasa = $klasy->fetch_assoc()): ?>
                                <div class="klasa-card">
                                    <div class="klasa-card-header">
                                        <span class="klasa-nazwa"><?php echo e($klasa['nazwa']); ?></span>
                                    </div>

                                    <div class="klasa-stats">
                                        <div class="stat-badge">
                                            <div class="stat-value"><?php echo $klasa['liczba_uczniow']; ?></div>
                                            <div class="stat-label">Uczniów</div>
                                        </div>
                                        <div class="stat-badge">
                                            <div class="stat-value"><?php echo $klasa['liczba_przedmiotow']; ?></div>
                                            <div class="stat-label">Przedmiotów</div>
                                        </div>
                                    </div>

                                    <div class="klasa-info">
                                        <span class="klasa-info-label">Wychowawca:</span>
                                        <?php if ($klasa['imie'] && $klasa['nazwisko']): ?>
                                            <?php echo e($klasa['imie'] . ' ' . $klasa['nazwisko']); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">Nie przypisano</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="klasa-info">
                                        <span class="klasa-info-label">Godzin dziennie:</span>
                                        <?php echo $klasa['ilosc_godzin_dziennie']; ?>
                                    </div>

                                    <div class="klasa-info">
                                        <span class="klasa-info-label">Rozszerzenia:</span>
                                        <small>
                                            <?php echo e($klasa['rozszerzenie_1']); ?><br>
                                            <?php echo e($klasa['rozszerzenie_2']); ?>
                                        </small>
                                    </div>

                                    <div class="klasa-actions">
                                        <button type="button" class="btn-delete" onclick="confirmDelete(<?php echo $klasa['id']; ?>, '<?php echo e($klasa['nazwa']); ?>', <?php echo $klasa['liczba_uczniow']; ?>, <?php echo $klasa['liczba_przedmiotow']; ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                            Usuń klasę
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Brak klas w systemie. Dodaj pierwszą klasę używając formularza powyżej.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal potwierdzenia usunięcia -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Potwierdź usunięcie klasy</div>
            <p id="deleteMessage"></p>
            <form method="POST" id="deleteForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="klasa_id" id="deleteKlasaId">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Anuluj</button>
                    <button type="submit" name="usun_klase" class="btn btn-danger">Usuń klasę</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(klasaId, klasaNazwa, liczbaUczniow, liczbaPrzedmiotow) {
            document.getElementById('deleteKlasaId').value = klasaId;

            let message = 'Czy na pewno chcesz usunąć klasę <strong>' + klasaNazwa + '</strong>?';

            if (liczbaUczniow > 0 || liczbaPrzedmiotow > 0) {
                message += '<br><br><div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin-top: 10px;">';
                message += '<strong>⚠ Uwaga:</strong> Ta klasa ma powiązane dane:<ul style="margin: 10px 0; text-align: left;">';

                if (liczbaUczniow > 0) {
                    message += '<li>' + liczbaUczniow + ' uczniów</li>';
                }
                if (liczbaPrzedmiotow > 0) {
                    message += '<li>' + liczbaPrzedmiotow + ' przypisanych przedmiotów</li>';
                }

                message += '</ul>Musisz najpierw usunąć lub przenieść te dane.</div>';
            }

            document.getElementById('deleteMessage').innerHTML = message;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Zamknij modal przy kliknięciu poza nim
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
