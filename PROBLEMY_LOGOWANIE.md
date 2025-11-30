# 🔧 Rozwiązywanie Problemów z Logowaniem

## Problem: Nie mogę się zalogować pomimo prawidłowych danych

Jeśli nie możesz się zalogować używając domyślnych kont (`dyrektor`/`dyrektor123` lub `admin`/`admin123`), wykonaj poniższe kroki:

### ✅ Rozwiązanie KROK PO KROKU:

#### **Krok 1: Uruchom diagnostykę**

Otwórz w przeglądarce:
```
http://localhost/plan-lekcji/diagnostyka.php
```

Ten skrypt automatycznie sprawdzi:
- Połączenie z bazą danych
- Czy tabele istnieją
- Czy użytkownicy są w bazie
- Czy hasła są poprawne

#### **Krok 2: Napraw hasła (jeśli diagnostyka pokazała problem)**

Jeśli diagnostyka wykazała problem z hasłami, otwórz:
```
http://localhost/plan-lekcji/naprawa_hasel.php
```

Ten skrypt:
- Wygeneruje nowe, poprawne hashe
- Zaktualizuje hasła w bazie danych
- Utworzy użytkowników jeśli ich brakuje
- Przetestuje czy logowanie działa

#### **Krok 3: Spróbuj się zalogować**

Po uruchomieniu skryptu naprawy, wróć do strony logowania:
```
http://localhost/plan-lekcji/
```

Użyj danych:
- **Login**: `dyrektor` **Hasło**: `dyrektor123`
- **Login**: `admin` **Hasło**: `admin123`

---

## 🔍 Inne możliwe przyczyny problemu

### 1. Baza danych nie została zaimportowana

**Symptomy:**
- Strona logowania wyświetla się, ale nie ma użytkowników
- Diagnostyka pokazuje że tabela nie istnieje

**Rozwiązanie:**
```bash
# Zaloguj się do MySQL
mysql -u root -p

# Utwórz bazę
CREATE DATABASE plan_lekcji CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Zaimportuj dane
mysql -u root -p plan_lekcji < database.sql
```

Lub przez phpMyAdmin:
1. Otwórz phpMyAdmin
2. Kliknij "Import"
3. Wybierz plik `database.sql`
4. Kliknij "Wykonaj"

### 2. Błędne dane w config.php

**Symptomy:**
- Błąd "Błąd połączenia z bazą danych"
- Strona w ogóle się nie ładuje

**Rozwiązanie:**

Edytuj plik `includes/config.php`:
```php
define('DB_HOST', 'localhost');      // Adres serwera MySQL
define('DB_USER', 'root');           // Twój użytkownik MySQL
define('DB_PASS', 'twoje_haslo');    // Twoje hasło MySQL
define('DB_NAME', 'plan_lekcji');    // Nazwa bazy danych
```

### 3. Problem z sesjami PHP

**Symptomy:**
- Logowanie "przechodzi" ale od razu wraca do strony logowania
- Brak komunikatu o błędzie

**Rozwiązanie:**

Sprawdź czy katalog sesji jest zapisywalny:
```bash
# Linux
sudo chmod 1777 /var/lib/php/sessions

# Lub sprawdź php.ini
session.save_path = "/tmp"
```

### 4. Problem z przekierowaniami

**Symptomy:**
- Po logowaniu nic się nie dzieje
- Biała strona po zalogowaniu

**Rozwiązanie:**

1. Sprawdź czy katalogi `dyrektor/`, `administrator/`, `nauczyciel/`, `uczen/` istnieją
2. Sprawdź logi błędów Apache/Nginx
3. Włącz wyświetlanie błędów w PHP (tylko dla debugowania):
```php
// Na początku pliku index.php dodaj:
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

## 🛠️ Manualna naprawa haseł przez MySQL

Jeśli skrypty nie działają, możesz naprawić hasła bezpośrednio w MySQL:

```sql
-- Zaloguj się do MySQL
mysql -u root -p plan_lekcji

-- Zaktualizuj hasło dyrektora (hasło: dyrektor123)
UPDATE uzytkownicy 
SET haslo = '$2y$10$kZ5H3YvN8qX9mW7pL4rJ1.eF2vK8sT6nM9bC1dA3gH4jE5fI6lO7m' 
WHERE login = 'dyrektor';

-- Zaktualizuj hasło admina (hasło: admin123)
UPDATE uzytkownicy 
SET haslo = '$2y$10$vT9B2nX8cQ7fM6gR5jK4sL.aP3bY1eU4wO8zD9hN6iC2mF5tE7kA1' 
WHERE login = 'admin';

-- Sprawdź czy się zapisało
SELECT login, typ FROM uzytkownicy;
```

---

## 📝 Generowanie własnych haseł

Jeśli chcesz utworzyć własne hasła, użyj tego kodu PHP:

```php
<?php
// Utwórz plik test_hash.php z tym kodem
$twoje_haslo = "nowe_haslo123";
$hash = password_hash($twoje_haslo, PASSWORD_DEFAULT);
echo "Hash dla hasła '$twoje_haslo':<br>";
echo $hash;
?>
```

Następnie użyj tego hasha w zapytaniu SQL:
```sql
UPDATE uzytkownicy SET haslo = 'WYGENEROWANY_HASH' WHERE login = 'dyrektor';
```

---

## 🔐 Zmiana hasła po pierwszym logowaniu

**Dla bezpieczeństwa, ZAWSZE zmień domyślne hasła!**

Możesz to zrobić przez MySQL:
```sql
UPDATE uzytkownicy 
SET haslo = 'NOWY_HASH' 
WHERE login = 'dyrektor';
```

Lub dodaj funkcjonalność zmiany hasła do panelu użytkownika.

---

## ⚠️ Ważne uwagi bezpieczeństwa

Po rozwiązaniu problemów:

1. **Usuń pliki diagnostyczne:**
   - `diagnostyka.php`
   - `naprawa_hasel.php`
   - `test_hash.php` (jeśli utworzyłeś)

2. **Zmień domyślne hasła** na silne i unikalne

3. **Wyłącz wyświetlanie błędów** w produkcji:
```php
// W includes/config.php
error_reporting(0);
ini_set('display_errors', 0);
```

4. **Włącz HTTPS** w środowisku produkcyjnym

---

## 📞 Dalsze wsparcie

Jeśli problem nadal występuje:

1. Sprawdź logi błędów serwera:
   - Apache: `/var/log/apache2/error.log`
   - Nginx: `/var/log/nginx/error.log`
   - PHP: `php_error.log`

2. Sprawdź czy wszystkie wymagania są spełnione:
   - PHP 7.4+
   - MySQL 5.7+
   - Rozszerzenia: mysqli, session

3. Upewnij się że serwer web i MySQL są uruchomione

---

**Powodzenia!** 🎓
