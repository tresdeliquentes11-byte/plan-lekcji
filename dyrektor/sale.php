<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$message = '';
$message_type = '';

// Dodawanie sali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_sale'])) {
    $numer = $_POST['numer'];
    $nazwa = $_POST['nazwa'] ?? '';
    $typ = $_POST['typ'] ?? 'standardowa';
    $pojemnosc = $_POST['pojemnosc'] ?? 30;
    
    $stmt = $conn->prepare("INSERT INTO sale (numer, nazwa, typ, pojemnosc) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $numer, $nazwa, $typ, $pojemnosc);
    
    if ($stmt->execute()) {
        $message = 'Sala została dodana pomyślnie';
        $message_type = 'success';
    } else {
        $message = 'Błąd: Sala o tym numerze już istnieje';
        $message_type = 'error';
    }
}

// Edycja sali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edytuj_sale'])) {
    $sala_id = $_POST['sala_id'];
    $numer = $_POST['numer'];
    $nazwa = $_POST['nazwa'] ?? '';
    $typ = $_POST['typ'] ?? 'standardowa';
    $pojemnosc = $_POST['pojemnosc'] ?? 30;
    
    $stmt = $conn->prepare("UPDATE sale SET numer = ?, nazwa = ?, typ = ?, pojemnosc = ? WHERE id = ?");
    $stmt->bind_param("sssii", $numer, $nazwa, $typ, $pojemnosc, $sala_id);
    
    if ($stmt->execute()) {
        $message = 'Sala została zaktualizowana';
        $message_type = 'success';
    } else {
        $message = 'Błąd podczas aktualizacji sali';
        $message_type = 'error';
    }
}

// Przypisywanie przedmiotów do sali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['przypisz_przedmioty'])) {
    $sala_id = $_POST['sala_id'];
    $przedmioty = $_POST['przedmioty'] ?? [];
    
    // Usuń stare przypisania
    $conn->query("DELETE FROM sala_przedmioty WHERE sala_id = $sala_id");
    
    // Dodaj nowe przypisania
    foreach ($przedmioty as $przedmiot_id) {
        $stmt = $conn->prepare("INSERT INTO sala_przedmioty (sala_id, przedmiot_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $sala_id, $przedmiot_id);
        $stmt->execute();
    }
    
    $message = 'Przedmioty zostały przypisane do sali';
    $message_type = 'success';
}

// Przypisywanie nauczycieli do sali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['przypisz_nauczycieli'])) {
    $sala_id = $_POST['sala_id'];
    $nauczyciele = $_POST['nauczyciele'] ?? [];
    
    // Usuń stare przypisania
    $conn->query("DELETE FROM sala_nauczyciele WHERE sala_id = $sala_id");
    
    // Dodaj nowe przypisania
    foreach ($nauczyciele as $nauczyciel_id) {
        $stmt = $conn->prepare("INSERT INTO sala_nauczyciele (sala_id, nauczyciel_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $sala_id, $nauczyciel_id);
        $stmt->execute();
    }
    
    $message = 'Nauczyciele zostali przypisani do sali';
    $message_type = 'success';
}

// Usuwanie sali
if (isset($_GET['usun'])) {
    $id = $_GET['usun'];
    if ($conn->query("DELETE FROM sale WHERE id = $id")) {
        $message = 'Sala została usunięta';
        $message_type = 'success';
    } else {
        $message = 'Nie można usunąć sali (może być używana w planie)';
        $message_type = 'error';
    }
}

// Pobierz sale
$sale = $conn->query("
    SELECT s.*, 
           COUNT(DISTINCT sp.przedmiot_id) as liczba_przedmiotow,
           COUNT(DISTINCT sn.nauczyciel_id) as liczba_nauczycieli
    FROM sale s
    LEFT JOIN sala_przedmioty sp ON s.id = sp.sala_id
    LEFT JOIN sala_nauczyciele sn ON s.id = sn.sala_id
    GROUP BY s.id
    ORDER BY s.numer
");

// Pobierz przedmioty
$przedmioty = $conn->query("SELECT * FROM przedmioty ORDER BY nazwa");

// Pobierz nauczycieli
$nauczyciele = $conn->query("
    SELECT n.id, u.imie, u.nazwisko
    FROM nauczyciele n
    JOIN uzytkownicy u ON n.uzytkownik_id = u.id
    ORDER BY u.nazwisko, u.imie
");

// Wybrana sala do edycji
$selected_sala = null;
$selected_przedmioty = [];
$selected_nauczyciele = [];

if (isset($_GET['edytuj'])) {
    $sala_id = $_GET['edytuj'];
    $selected_sala = $conn->query("SELECT * FROM sale WHERE id = $sala_id")->fetch_assoc();
    
    // Pobierz przypisane przedmioty
    $result = $conn->query("SELECT przedmiot_id FROM sala_przedmioty WHERE sala_id = $sala_id");
    while ($row = $result->fetch_assoc()) {
        $selected_przedmioty[] = $row['przedmiot_id'];
    }
    
    // Pobierz przypisanych nauczycieli
    $result = $conn->query("SELECT nauczyciel_id FROM sala_nauczyciele WHERE sala_id = $sala_id");
    while ($row = $result->fetch_assoc()) {
        $selected_nauczyciele[] = $row['nauczyciel_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Salami - Panel Dyrektora</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sala-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .sala-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        .sala-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .sala-numer {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        .sala-nazwa {
            color: #666;
            margin-bottom: 5px;
        }
        .sala-info {
            font-size: 13px;
            color: #999;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .sala-actions {
            display: flex;
            gap: 5px;
            margin-top: 15px;
        }
        .typ-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .typ-standardowa { background: #e3f2fd; color: #1976d2; }
        .typ-pracownia { background: #f3e5f5; color: #7b1fa2; }
        .typ-sportowa { background: #e8f5e9; color: #388e3c; }
        .typ-specjalna { background: #fff3e0; color: #f57c00; }
        .checkbox-list {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid #e9ecef;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .checkbox-list label {
            display: block;
            padding: 8px;
            margin-bottom: 5px;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .checkbox-list label:hover {
            background: #e9ecef;
        }
        .checkbox-list input[type="checkbox"] {
            margin-right: 10px;
        }
    </style>
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
                <li><a href="uczniowie.php">Uczniowie</a></li>
                <li><a href="klasy.php">Klasy</a></li>
                <li><a href="przedmioty.php">Przedmioty</a></li>
                <li><a href="sale.php" class="active">Sale</a></li>
                <li><a href="kalendarz.php">Kalendarz</a></li>
                <li><a href="plan_podglad.php">Podgląd Planu</a></li>
                <li><a href="dostepnosc.php">Dostępność</a></li>
                <li><a href="ustawienia.php">Ustawienia</a></li>
            </ul>
        </nav>
        
        <div class="content">
            <h2 class="page-title">Zarządzanie Salami Lekcyjnymi</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <?php if (!$selected_sala): ?>
                <!-- Formularz dodawania nowej sali -->
                <div class="card">
                    <h3 class="card-title">Dodaj nową salę</h3>
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 2fr 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Numer sali *</label>
                                <input type="text" name="numer" placeholder="np. 101" required>
                            </div>
                            <div class="form-group">
                                <label>Nazwa sali</label>
                                <input type="text" name="nazwa" placeholder="np. Sala matematyczna">
                            </div>
                            <div class="form-group">
                                <label>Typ sali</label>
                                <select name="typ">
                                    <option value="standardowa">Standardowa</option>
                                    <option value="pracownia">Pracownia</option>
                                    <option value="sportowa">Sportowa</option>
                                    <option value="specjalna">Specjalna</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pojemność</label>
                                <input type="number" name="pojemnosc" value="30" min="1" max="100">
                            </div>
                        </div>
                        <button type="submit" name="dodaj_sale" class="btn btn-primary">Dodaj salę</button>
                    </form>
                </div>
                
                <!-- Lista sal -->
                <div class="card">
                    <h3 class="card-title">Lista sal lekcyjnych (<?php echo $sale->num_rows; ?>)</h3>
                    
                    <?php if ($sale->num_rows > 0): ?>
                        <div class="sala-grid">
                            <?php 
                            $sale->data_seek(0);
                            while ($s = $sale->fetch_assoc()): 
                            ?>
                                <div class="sala-card">
                                    <span class="typ-badge typ-<?php echo e($s['typ'] ?? 'standardowa'); ?>">
                                        <?php echo ucfirst(e($s['typ'] ?? 'standardowa')); ?>
                                    </span>
                                    <div class="sala-numer">Sala <?php echo e($s['numer']); ?></div>
                                    <div class="sala-nazwa"><?php echo e($s['nazwa'] ?? 'Brak nazwy'); ?></div>
                                    <div class="sala-info">
                                        👥 Pojemność: <?php echo $s['pojemnosc'] ?? 30; ?> osób<br>
                                        📚 Przedmiotów: <?php echo $s['liczba_przedmiotow']; ?><br>
                                        👨‍🏫 Nauczycieli: <?php echo $s['liczba_nauczycieli']; ?>
                                    </div>
                                    <div class="sala-actions">
                                        <a href="?edytuj=<?php echo $s['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                                            Edytuj
                                        </a>
                                        <a href="?usun=<?php echo $s['id']; ?>" 
                                           class="btn btn-danger" 
                                           style="padding: 5px 10px; font-size: 12px;"
                                           onclick="return confirm('Czy na pewno usunąć tę salę?')">
                                            Usuń
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Brak sal w systemie. Dodaj pierwszą salę używając formularza powyżej.</div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Edycja wybranej sali -->
                <div class="card">
                    <h3 class="card-title">Edycja sali: <?php echo e($selected_sala['numer']); ?></h3>
                    <a href="sale.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Powrót do listy sal</a>
                    
                    <form method="POST">
                        <input type="hidden" name="sala_id" value="<?php echo $selected_sala['id']; ?>">
                        
                        <div style="display: grid; grid-template-columns: 1fr 2fr 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Numer sali *</label>
                                <input type="text" name="numer" value="<?php echo e($selected_sala['numer']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Nazwa sali</label>
                                <input type="text" name="nazwa" value="<?php echo e($selected_sala['nazwa'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Typ sali</label>
                                <select name="typ">
                                    <option value="standardowa" <?php echo ($selected_sala['typ'] ?? 'standardowa') == 'standardowa' ? 'selected' : ''; ?>>Standardowa</option>
                                    <option value="pracownia" <?php echo ($selected_sala['typ'] ?? '') == 'pracownia' ? 'selected' : ''; ?>>Pracownia</option>
                                    <option value="sportowa" <?php echo ($selected_sala['typ'] ?? '') == 'sportowa' ? 'selected' : ''; ?>>Sportowa</option>
                                    <option value="specjalna" <?php echo ($selected_sala['typ'] ?? '') == 'specjalna' ? 'selected' : ''; ?>>Specjalna</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pojemność</label>
                                <input type="number" name="pojemnosc" value="<?php echo $selected_sala['pojemnosc'] ?? 30; ?>" min="1" max="100">
                            </div>
                        </div>
                        
                        <button type="submit" name="edytuj_sale" class="btn btn-primary">Zapisz zmiany</button>
                    </form>
                </div>
                
                <!-- Przypisywanie przedmiotów -->
                <div class="card">
                    <h3 class="card-title">Przypisz przedmioty do sali</h3>
                    <p>Wybierz przedmioty, które są najczęściej prowadzone w tej sali. System będzie preferował tę salę przy generowaniu planu dla tych przedmiotów.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="sala_id" value="<?php echo $selected_sala['id']; ?>">
                        
                        <div class="checkbox-list">
                            <?php 
                            $przedmioty->data_seek(0);
                            while ($p = $przedmioty->fetch_assoc()): 
                            ?>
                                <label>
                                    <input type="checkbox" 
                                           name="przedmioty[]" 
                                           value="<?php echo $p['id']; ?>"
                                           <?php echo in_array($p['id'], $selected_przedmioty) ? 'checked' : ''; ?>>
                                    <?php echo e($p['nazwa']); ?>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        
                        <button type="submit" name="przypisz_przedmioty" class="btn btn-success" style="margin-top: 15px;">
                            Zapisz przypisania przedmiotów
                        </button>
                    </form>
                </div>
                
                <!-- Przypisywanie nauczycieli -->
                <div class="card">
                    <h3 class="card-title">Przypisz nauczycieli do sali</h3>
                    <p>Wybierz nauczycieli, którzy najczęściej prowadzą zajęcia w tej sali. System będzie preferował tę salę dla wybranych nauczycieli.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="sala_id" value="<?php echo $selected_sala['id']; ?>">
                        
                        <div class="checkbox-list">
                            <?php 
                            $nauczyciele->data_seek(0);
                            while ($n = $nauczyciele->fetch_assoc()): 
                            ?>
                                <label>
                                    <input type="checkbox" 
                                           name="nauczyciele[]" 
                                           value="<?php echo $n['id']; ?>"
                                           <?php echo in_array($n['id'], $selected_nauczyciele) ? 'checked' : ''; ?>>
                                    <?php echo e($n['imie'] . ' ' . $n['nazwisko']); ?>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        
                        <button type="submit" name="przypisz_nauczycieli" class="btn btn-success" style="margin-top: 15px;">
                            Zapisz przypisania nauczycieli
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Informacje o typach sal</h3>
                <table>
                    <tr>
                        <th>Typ</th>
                        <th>Opis</th>
                        <th>Przykładowe przedmioty</th>
                    </tr>
                    <tr>
                        <td><span class="typ-badge typ-standardowa">Standardowa</span></td>
                        <td>Zwykła sala lekcyjna</td>
                        <td>Matematyka, Polski, Historia, WOS</td>
                    </tr>
                    <tr>
                        <td><span class="typ-badge typ-pracownia">Pracownia</span></td>
                        <td>Sala z wyposażeniem specjalistycznym</td>
                        <td>Fizyka, Chemia, Biologia, Informatyka</td>
                    </tr>
                    <tr>
                        <td><span class="typ-badge typ-sportowa">Sportowa</span></td>
                        <td>Sala gimnastyczna lub boisko</td>
                        <td>WF</td>
                    </tr>
                    <tr>
                        <td><span class="typ-badge typ-specjalna">Specjalna</span></td>
                        <td>Sala do zajęć specjalnych</td>
                        <td>Języki obce, Muzyka, Plastyka</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
