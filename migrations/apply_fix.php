<?php
/**
 * Skrypt do naprawy constraintów bazy danych
 * Uruchom w przeglądarce: http://localhost/plan-lekcji/migrations/apply_fix.php
 */

chdir(__DIR__ . '/..');
require_once 'includes/config.php';

echo "<h2>Naprawa constraintów bazy danych...</h2>";

// Napraw constraint dla statystyki_uzytkownikow
echo "<h3>1. Naprawa constraint dla statystyki_uzytkownikow</h3>";

// Usuń stary constraint
$drop_query = "ALTER TABLE `statystyki_uzytkownikow` DROP FOREIGN KEY `statystyki_uzytkownikow_ibfk_2`";
if ($conn->query($drop_query)) {
    echo "✓ Usunięto stary constraint<br>";
} else {
    echo "⚠ Błąd przy usuwaniu: " . $conn->error . "<br>";
}

// Dodaj poprawiony constraint
$add_query = "ALTER TABLE `statystyki_uzytkownikow`
ADD CONSTRAINT `statystyki_uzytkownikow_ibfk_2`
FOREIGN KEY (`uzytkownik_docelowy_id`)
REFERENCES `uzytkownicy` (`id`)
ON DELETE SET NULL";

if ($conn->query($add_query)) {
    echo "✓ Dodano poprawiony constraint z ON DELETE SET NULL<br>";
} else {
    echo "❌ Błąd przy dodawaniu: " . $conn->error . "<br>";
}

echo "<hr>";
echo "<h3>2. Naprawa constraint dla logi_aktywnosci</h3>";

// Sprawdź czy constraint istnieje
$check = $conn->query("SELECT CONSTRAINT_NAME
                       FROM information_schema.TABLE_CONSTRAINTS
                       WHERE TABLE_NAME = 'logi_aktywnosci'
                       AND CONSTRAINT_NAME = 'logi_aktywnosci_ibfk_1'");

if ($check && $check->num_rows > 0) {
    // Usuń stary constraint
    if ($conn->query("ALTER TABLE `logi_aktywnosci` DROP FOREIGN KEY `logi_aktywnosci_ibfk_1`")) {
        echo "✓ Usunięto stary constraint dla logi_aktywnosci<br>";
    }
}

// Dodaj poprawiony constraint
$add_query2 = "ALTER TABLE `logi_aktywnosci`
ADD CONSTRAINT `logi_aktywnosci_ibfk_1`
FOREIGN KEY (`uzytkownik_id`)
REFERENCES `uzytkownicy` (`id`)
ON DELETE SET NULL";

if ($conn->query($add_query2)) {
    echo "✓ Dodano poprawiony constraint dla logi_aktywnosci<br>";
} else {
    // Jeśli constraint już istnieje, to OK
    if (strpos($conn->error, 'Duplicate key') !== false) {
        echo "✓ Constraint już istnieje<br>";
    } else {
        echo "⚠ Info: " . $conn->error . "<br>";
    }
}

echo "<hr>";
echo "<h2 style='color: green;'>✓ Naprawa zakończona!</h2>";
echo "<p>Teraz możesz usuwać użytkowników bez problemów.</p>";
echo "<p><a href='../administrator/dashboard.php'>← Powrót do panelu administratora</a></p>";
?>
