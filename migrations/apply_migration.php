<?php
/**
 * Skrypt do zastosowania migracji bazy danych
 * Uruchom ten skrypt w przeglądarce lub z linii poleceń
 */

// Zmiana katalogu roboczego
chdir(__DIR__ . '/..');
require_once 'includes/config.php';

// Sprawdź czy migracja już została zastosowana
$check_table = $conn->query("SHOW TABLES LIKE 'sesje_uzytkownikow'");
if ($check_table->num_rows > 0) {
    echo "✓ Migracja została już wcześniej zastosowana.<br>";
    echo "Tabele już istnieją w bazie danych.<br>";
    exit;
}

echo "<h2>Rozpoczynam migrację bazy danych...</h2>";

// Wczytaj plik migracji
$migration_file = __DIR__ . '/001_admin_panel_enhancement.sql';
if (!file_exists($migration_file)) {
    die("❌ Błąd: Plik migracji nie istnieje!<br>");
}

$sql = file_get_contents($migration_file);

// Usuń komentarze i podziel na poszczególne zapytania
$sql = preg_replace('/--.*$/m', '', $sql);
$queries = array_filter(array_map('trim', explode(';', $sql)));

echo "<h3>Wykonywanie migracji...</h3>";

$success_count = 0;
$error_count = 0;

foreach ($queries as $query) {
    if (empty($query)) continue;

    if ($conn->query($query)) {
        $success_count++;
        // Wyświetl tylko pierwsze 80 znaków zapytania
        $short_query = substr($query, 0, 80);
        echo "✓ Zapytanie wykonane: " . htmlspecialchars($short_query) . "...<br>";
    } else {
        $error_count++;
        echo "❌ Błąd: " . $conn->error . "<br>";
        echo "Zapytanie: " . htmlspecialchars(substr($query, 0, 200)) . "...<br>";
    }
}

echo "<hr>";
echo "<h3>Podsumowanie:</h3>";
echo "✓ Pomyślnie wykonanych zapytań: <strong>$success_count</strong><br>";

if ($error_count > 0) {
    echo "❌ Błędów: <strong>$error_count</strong><br>";
} else {
    echo "✓ <strong style='color: green;'>Migracja zakończona sukcesem!</strong><br>";
}

echo "<hr>";
echo "<h3>Utworzone tabele:</h3>";
$tables = ['sesje_uzytkownikow', 'logi_aktywnosci', 'statystyki_generowania', 'statystyki_uzytkownikow'];

foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows > 0) {
        echo "✓ Tabela <strong>$table</strong> została utworzona<br>";
    } else {
        echo "❌ Tabela <strong>$table</strong> nie została utworzona<br>";
    }
}

echo "<hr>";
echo "<p><a href='../administrator/dashboard.php'>← Przejdź do panelu administratora</a></p>";
?>
