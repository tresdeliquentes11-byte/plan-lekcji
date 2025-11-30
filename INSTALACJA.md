# Szybki Przewodnik Instalacji - System Planu Lekcji

## Krok 1: Przygotowanie

Upewnij się, że masz zainstalowane:
- PHP 7.4+
- MySQL/MariaDB
- Serwer Apache/Nginx
- phpMyAdmin (opcjonalnie, ułatwia zarządzanie bazą)

## Krok 2: Instalacja bazy danych

### Opcja A: Przez phpMyAdmin
1. Otwórz phpMyAdmin w przeglądarce
2. Kliknij "Import" w górnym menu
3. Wybierz plik `database.sql` z folderu aplikacji
4. Kliknij "Wykonaj"

### Opcja B: Przez terminal
```bash
mysql -u root -p < database.sql
```

## Krok 3: Konfiguracja

1. Otwórz plik `includes/config.php`
2. Zmień dane dostępowe do bazy danych:

```php
define('DB_HOST', 'localhost');      // Adres serwera MySQL
define('DB_USER', 'root');           // Użytkownik MySQL
define('DB_PASS', 'twoje_haslo');    // Hasło do MySQL
define('DB_NAME', 'plan_lekcji');    // Nazwa bazy danych
```

## Krok 4: Umieszczenie plików

Skopiuj wszystkie pliki aplikacji do katalogu serwera web:

- **XAMPP (Windows)**: `C:\xampp\htdocs\plan-lekcji\`
- **XAMPP (Linux)**: `/opt/lampp/htdocs/plan-lekcji/`
- **WAMP**: `C:\wamp64\www\plan-lekcji\`
- **Linux (Apache)**: `/var/www/html/plan-lekcji/`

## Krok 5: Pierwsze uruchomienie

1. Uruchom serwer web (Apache) i MySQL
2. Otwórz przeglądarkę i wejdź na:
   - `http://localhost/plan-lekcji/` (jeśli w htdocs)
   - lub odpowiedni adres lokalny

## Krok 6: Logowanie

Zaloguj się używając domyślnych kont:

**Dyrektor:**
- Login: `dyrektor`
- Hasło: `dyrektor123`

**Administrator:**
- Login: `admin`
- Hasło: `admin123`

⚠️ **WAŻNE**: Natychmiast zmień te hasła po pierwszym logowaniu!

## Krok 7: Konfiguracja systemu

### 7.1. Dodaj nauczycieli
1. Zaloguj się jako dyrektor
2. Przejdź do "Nauczyciele"
3. Dodaj nauczycieli i przypisz im przedmioty, które mogą uczyć

### 7.2. Skonfiguruj klasy
1. Przejdź do "Klasy"
2. Dla każdej klasy:
   - Wybierz wychowawcę
   - Wybierz 2 rozszerzenia
   - Przypisz nauczycieli do przedmiotów
   - Ustaw liczbę godzin każdego przedmiotu

### 7.3. Ustaw dni wolne
1. Przejdź do "Kalendarz"
2. Dodaj święta i dni wolne
3. Możesz użyć szybkich przycisków dla typowych świąt

### 7.4. Wygeneruj plan
1. Przejdź do "Generuj Plan"
2. Kliknij "Wygeneruj plan lekcji"
3. Poczekaj na zakończenie procesu

## Krok 8: Dodaj użytkowników

### Uczniowie (przez administratora):
1. Zaloguj się jako administrator
2. Dodaj konta uczniów i przypisz ich do klas

### Nauczyciele (przez dyrektora):
1. Już dodani w kroku 7.1

## Testowanie

Po wykonaniu powyższych kroków możesz:

1. **Jako dyrektor**: Zobacz pełny plan wszystkich klas
2. **Jako nauczyciel**: Sprawdź swój plan zajęć
3. **Jako uczeń**: Zobacz plan swojej klasy
4. **Testuj zastępstwa**: Dodaj nieobecność nauczyciela i zobacz automatyczne zastępstwa

## Rozwiązywanie problemów

### "Błąd połączenia z bazą danych"
- Sprawdź dane w `includes/config.php`
- Upewnij się, że MySQL działa
- Sprawdź czy baza `plan_lekcji` została utworzona

### "Plan nie generuje się"
- Sprawdź czy wszystkie klasy mają przypisane przedmioty
- Sprawdź czy nauczyciele mają przypisane kwalifikacje
- Zobacz logi błędów PHP (zwykle w: `/var/log/apache2/error.log`)

### "Nie mogę się zalogować"
- Upewnij się, że używasz poprawnego loginu/hasła
- Sprawdź czy sesje PHP działają poprawnie
- Wyczyść cookies przeglądarki

## Wsparcie

Szczegółowa dokumentacja znajduje się w pliku `README.md`.

Jeśli napotkasz problemy:
1. Sprawdź README.md - szczegółowa dokumentacja
2. Sprawdź logi błędów serwera
3. Upewnij się, że wszystkie wymagania systemowe są spełnione

---

**Powodzenia!** 🎓
