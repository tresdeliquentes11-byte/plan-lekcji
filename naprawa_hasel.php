<?php
/**
 * Skrypt do naprawy haseł w bazie danych
 * 
 * Ten skrypt wygeneruje poprawne hashe dla domyślnych kont
 * i zaktualizuje je w bazie danych.
 * 
 * UWAGA: Uruchom ten skrypt TYLKO RAZ po zainstalowaniu bazy danych!
 */

require_once 'includes/config.php';

echo "<h2>Naprawa haseł - System Planu Lekcji</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #667eea; color: white; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
</style>";

// Tablica z domyślnymi kontami i ich hasłami
$default_accounts = [
    [
        'login' => 'dyrektor',
        'haslo_plaintext' => 'dyrektor123',
        'typ' => 'dyrektor',
        'imie' => 'Jan',
        'nazwisko' => 'Kowalski'
    ],
    [
        'login' => 'admin',
        'haslo_plaintext' => 'admin123',
        'typ' => 'administrator',
        'imie' => 'Anna',
        'nazwisko' => 'Nowak'
    ]
];

echo "<div class='info'><strong>Krok 1:</strong> Generowanie bezpiecznych hashów haseł...</div>";

$hashe = [];
foreach ($default_accounts as $account) {
    $hash = password_hash($account['haslo_plaintext'], PASSWORD_DEFAULT);
    $hashe[$account['login']] = [
        'hash' => $hash,
        'plaintext' => $account['haslo_plaintext']
    ];
    echo "<p>✓ Wygenerowano hash dla użytkownika: <strong>{$account['login']}</strong></p>";
}

echo "<div class='info'><strong>Krok 2:</strong> Aktualizacja haseł w bazie danych...</div>";

$updated = 0;
$errors = 0;

foreach ($default_accounts as $account) {
    $login = $account['login'];
    $hash = $hashe[$login]['hash'];
    
    // Sprawdź czy użytkownik istnieje
    $check = $conn->query("SELECT id FROM uzytkownicy WHERE login = '$login'");
    
    if ($check->num_rows > 0) {
        // Aktualizuj hasło
        $stmt = $conn->prepare("UPDATE uzytkownicy SET haslo = ? WHERE login = ?");
        $stmt->bind_param("ss", $hash, $login);
        
        if ($stmt->execute()) {
            echo "<p class='success'>✓ Zaktualizowano hasło dla użytkownika: <strong>$login</strong></p>";
            $updated++;
        } else {
            echo "<p class='error'>✗ Błąd aktualizacji dla użytkownika: <strong>$login</strong></p>";
            $errors++;
        }
        $stmt->close();
    } else {
        // Utwórz użytkownika
        $stmt = $conn->prepare("INSERT INTO uzytkownicy (login, haslo, typ, imie, nazwisko, aktywny) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssss", $login, $hash, $account['typ'], $account['imie'], $account['nazwisko']);
        
        if ($stmt->execute()) {
            echo "<p class='success'>✓ Utworzono nowego użytkownika: <strong>$login</strong></p>";
            $updated++;
        } else {
            echo "<p class='error'>✗ Błąd tworzenia użytkownika: <strong>$login</strong></p>";
            $errors++;
        }
        $stmt->close();
    }
}

echo "<hr>";
echo "<h3>Podsumowanie</h3>";
echo "<p>Zaktualizowano/utworzono kont: <strong>$updated</strong></p>";
if ($errors > 0) {
    echo "<p>Błędy: <strong>$errors</strong></p>";
}

echo "<hr>";
echo "<h3>Dane logowania</h3>";
echo "<table>";
echo "<tr><th>Login</th><th>Hasło</th><th>Typ konta</th></tr>";
foreach ($default_accounts as $account) {
    echo "<tr>";
    echo "<td><code>{$account['login']}</code></td>";
    echo "<td><code>{$account['haslo_plaintext']}</code></td>";
    echo "<td>{$account['typ']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<div class='info'>";
echo "<strong>Teraz możesz się zalogować!</strong><br>";
echo "Przejdź do: <a href='index.php'>Strona logowania</a><br><br>";
echo "<strong>WAŻNE:</strong> Po zalogowaniu usuń ten plik (<code>naprawa_hasel.php</code>) ze względów bezpieczeństwa!";
echo "</div>";

// Test weryfikacji hasła
echo "<hr>";
echo "<h3>Test weryfikacji haseł</h3>";
foreach ($default_accounts as $account) {
    $login = $account['login'];
    $plaintext = $account['haslo_plaintext'];
    
    // Pobierz hash z bazy
    $result = $conn->query("SELECT haslo FROM uzytkownicy WHERE login = '$login'");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hash_from_db = $row['haslo'];
        
        // Testuj weryfikację
        if (password_verify($plaintext, $hash_from_db)) {
            echo "<p class='success'>✓ Test weryfikacji OK dla: <strong>$login</strong></p>";
        } else {
            echo "<p class='error'>✗ Test weryfikacji FAILED dla: <strong>$login</strong></p>";
        }
    }
}

?>
