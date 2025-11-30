<?php
/**
 * Skrypt diagnostyczny - testowanie systemu logowania
 * 
 * Ten skrypt pomoże zdiagnozować problemy z logowaniem
 */

require_once 'includes/config.php';

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostyka Logowania</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: #f5f5f5; 
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 { color: #333; }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 5px; 
            border-left: 4px solid #28a745;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 5px; 
            border-left: 4px solid #dc3545;
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 5px; 
            border-left: 4px solid #17a2b8;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            background: white; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background: #667eea; 
            color: white; 
        }
        tr:hover { background: #f5f5f5; }
        code { 
            background: #f4f4f4; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-family: monospace;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>
    <h1>🔍 Diagnostyka Systemu Logowania</h1>
    
    <?php
    // Test 1: Połączenie z bazą danych
    echo "<div class='test-section'>";
    echo "<h2>Test 1: Połączenie z bazą danych</h2>";
    if ($conn->ping()) {
        echo "<div class='success'>✓ Połączenie z bazą danych działa poprawnie</div>";
        echo "<p>Serwer: " . $conn->host_info . "</p>";
        echo "<p>Wersja MySQL: " . $conn->server_info . "</p>";
    } else {
        echo "<div class='error'>✗ Brak połączenia z bazą danych</div>";
        echo "<p>Sprawdź plik <code>includes/config.php</code></p>";
    }
    echo "</div>";
    
    // Test 2: Sprawdzenie tabeli użytkowników
    echo "<div class='test-section'>";
    echo "<h2>Test 2: Tabela użytkowników</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'uzytkownicy'");
    if ($result->num_rows > 0) {
        echo "<div class='success'>✓ Tabela 'uzytkownicy' istnieje</div>";
        
        // Sprawdź liczbę użytkowników
        $count = $conn->query("SELECT COUNT(*) as cnt FROM uzytkownicy")->fetch_assoc()['cnt'];
        echo "<p>Liczba użytkowników w bazie: <strong>$count</strong></p>";
        
        // Wyświetl użytkowników
        $users = $conn->query("SELECT id, login, typ, imie, nazwisko, aktywny FROM uzytkownicy");
        if ($users->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Login</th><th>Typ</th><th>Imię</th><th>Nazwisko</th><th>Aktywny</th></tr>";
            while ($user = $users->fetch_assoc()) {
                $aktywny = $user['aktywny'] ? '✓' : '✗';
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td><code>{$user['login']}</code></td>";
                echo "<td>{$user['typ']}</td>";
                echo "<td>{$user['imie']}</td>";
                echo "<td>{$user['nazwisko']}</td>";
                echo "<td>{$aktywny}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='error'>✗ Tabela 'uzytkownicy' nie istnieje</div>";
        echo "<p>Musisz zaimportować plik <code>database.sql</code></p>";
    }
    echo "</div>";
    
    // Test 3: Test weryfikacji hasła
    echo "<div class='test-section'>";
    echo "<h2>Test 3: Weryfikacja haseł</h2>";
    
    $test_accounts = [
        ['login' => 'dyrektor', 'haslo' => 'dyrektor123'],
        ['login' => 'admin', 'haslo' => 'admin123']
    ];
    
    foreach ($test_accounts as $test) {
        echo "<h3>Testowanie: {$test['login']}</h3>";
        
        $stmt = $conn->prepare("SELECT id, login, haslo, typ, aktywny FROM uzytkownicy WHERE login = ?");
        $stmt->bind_param("s", $test['login']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            echo "<p>✓ Użytkownik <code>{$test['login']}</code> istnieje w bazie</p>";
            echo "<p>Typ konta: <strong>{$user['typ']}</strong></p>";
            echo "<p>Aktywny: <strong>" . ($user['aktywny'] ? 'TAK' : 'NIE') . "</strong></p>";
            
            // Test weryfikacji hasła
            if (password_verify($test['haslo'], $user['haslo'])) {
                echo "<div class='success'>✓ Hasło <code>{$test['haslo']}</code> jest POPRAWNE</div>";
            } else {
                echo "<div class='error'>✗ Hasło <code>{$test['haslo']}</code> jest NIEPOPRAWNE</div>";
                echo "<div class='warning'>";
                echo "<strong>Problem znaleziony!</strong><br>";
                echo "Hash w bazie nie pasuje do hasła. Musisz uruchomić skrypt naprawy haseł:<br>";
                echo "<a href='naprawa_hasel.php' class='btn'>Napraw hasła</a>";
                echo "</div>";
            }
        } else {
            echo "<div class='error'>✗ Użytkownik <code>{$test['login']}</code> NIE ISTNIEJE w bazie</div>";
            echo "<div class='warning'>";
            echo "Musisz utworzyć użytkownika. Uruchom:<br>";
            echo "<a href='naprawa_hasel.php' class='btn'>Utwórz domyślnych użytkowników</a>";
            echo "</div>";
        }
        $stmt->close();
    }
    echo "</div>";
    
    // Test 4: Sesje PHP
    echo "<div class='test-section'>";
    echo "<h2>Test 4: Sesje PHP</h2>";
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "<div class='success'>✓ Sesje PHP działają poprawnie</div>";
        echo "<p>Session ID: " . session_id() . "</p>";
    } else {
        echo "<div class='error'>✗ Problem z sesjami PHP</div>";
        echo "<p>Sprawdź konfigurację PHP</p>";
    }
    echo "</div>";
    
    // Test 5: Uprawnienia do plików
    echo "<div class='test-section'>";
    echo "<h2>Test 5: Uprawnienia do plików</h2>";
    $files_to_check = ['includes/config.php', 'index.php', 'css/style.css'];
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            if (is_readable($file)) {
                echo "<p>✓ <code>$file</code> - OK (czytelny)</p>";
            } else {
                echo "<p class='error'>✗ <code>$file</code> - brak uprawnień do odczytu</p>";
            }
        } else {
            echo "<p class='error'>✗ <code>$file</code> - plik nie istnieje</p>";
        }
    }
    echo "</div>";
    
    // Podsumowanie
    echo "<div class='test-section'>";
    echo "<h2>📋 Następne kroki</h2>";
    echo "<div class='info'>";
    echo "<ol>";
    echo "<li>Jeśli test weryfikacji haseł pokazuje błąd - kliknij przycisk <strong>Napraw hasła</strong> powyżej</li>";
    echo "<li>Po naprawie haseł, spróbuj zalogować się na <a href='index.php'>stronie logowania</a></li>";
    echo "<li>Jeśli nadal masz problemy, sprawdź logi błędów PHP</li>";
    echo "<li>Po rozwiązaniu problemu, usuń pliki <code>diagnostyka.php</code> i <code>naprawa_hasel.php</code></li>";
    echo "</ol>";
    echo "</div>";
    echo "</div>";
    ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="btn">Przejdź do logowania</a>
        <a href="naprawa_hasel.php" class="btn">Napraw hasła</a>
    </div>
</body>
</html>
