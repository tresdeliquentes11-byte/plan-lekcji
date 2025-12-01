# System Planu Lekcji - Dokumentacja

## Opis projektu

Kompleksowy system zarządzania planem lekcji dla szkół, napisany w PHP z wykorzystaniem bazy danych MySQL. System umożliwia:

- Automatyczne generowanie planu lekcji dla wszystkich klas
- Zarządzanie zastępstwami za nieobecnych nauczycieli
- Przeglądanie planu przez uczniów, nauczycieli i dyrekcję
- Zarządzanie kalendarzem dni wolnych
- Przypisywanie nauczycieli do przedmiotów i klas
- Zarządzanie rozszerzeniami dla poszczególnych klas
- Zarządzanie salami lekcyjnymi

## Wymagania systemowe

- PHP 8.2 lub nowszy
- MySQL 9.5 lub nowszy / MariaDB 12.0 lub nowszy
- Serwer web (Apache/Nginx)
- Włączone rozszerzenia PHP: mysqli, session

## Instalacja

### 1. Przygotowanie bazy danych

```bash
# Zaloguj się do MySQL
mysql -u root -p

# Utwórz bazę danych (lub użyj importu pliku SQL)
source database.sql
```

Alternatywnie możesz zaimportować plik `database.sql` przez phpMyAdmin.

### 2. Konfiguracja połączenia z bazą danych

Edytuj plik `includes/config.php` i dostosuj parametry połączenia:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'twoje_haslo');
define('DB_NAME', 'plan_lekcji');
```

### 3. Uprawnienia do plików

Upewnij się, że katalog aplikacji ma odpowiednie uprawnienia:

```bash
chmod -R 755 /sciezka/do/aplikacji
```

### 4. Konfiguracja serwera web

#### Apache

Upewnij się, że mod_rewrite jest włączony i katalog główny wskazuje na folder aplikacji.

#### Nginx

Przykładowa konfiguracja:

```nginx
server {
    listen 80;
    server_name twoja-domena.pl;
    root /sciezka/do/aplikacji;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## Domyślne konta użytkowników

Po instalacji dostępne są następujące konta testowe:

- **Dyrektor**: login: `dyrektor`, hasło: `dyrektor123`
- **Administrator**: login: `admin`, hasło: `admin123`

**WAŻNE**: Zmień te hasła natychmiast po pierwszym logowaniu!

## Struktura systemu

### Typy użytkowników

1. **Dyrektor** - pełen dostęp do systemu:
   - Generowanie planu lekcji
   - Zarządzanie zastępstwami
   - Zarządzanie nauczycielami i klasami
   - Zarządzanie kalendarzem dni wolnych
   - Podgląd wszystkich planów

2. **Administrator** - zarządzanie kontami:
   - Dodawanie i usuwanie kont uczniów
   - Przypisywanie uczniów do klas

3. **Nauczyciel** - dostęp do odczytu:
   - Przeglądanie własnego planu lekcji
   - Widok tygodniowy z możliwością nawigacji

4. **Uczeń** - dostęp do odczytu:
   - Przeglądanie planu swojej klasy
   - Widok tygodniowy z możliwością nawigacji

### Główne funkcjonalności

#### Generowanie planu lekcji

System automatycznie generuje plan według algorytmu, który:

1. Równomiernie rozkłada przedmioty w ciągu tygodnia
2. Unika nakładania się sal (jedna sala = jedna klasa w danym czasie)
3. Eliminuje okienka - lekcje bez przerw
4. Sprawdza dostępność nauczycieli (jeden nauczyciel może uczyć tylko jedną klasę w danym czasie)
5. Generuje plan na cały rok szkolny (wrzesień - czerwiec)
6. Uwzględnia dni wolne z kalendarza

**Jak wygenerować plan:**

1. Zaloguj się jako dyrektor
2. Przejdź do sekcji "Klasy" i przypisz przedmioty oraz nauczycieli do każdej klasy
3. Wybierz 2 rozszerzenia dla każdej klasy
4. Przejdź do "Generuj Plan"
5. Kliknij "Wygeneruj plan lekcji"

#### Automatyczne zastępstwa

System automatycznie tworzy zastępstwa gdy:

1. Dyrektor wprowadzi nieobecność nauczyciela
2. System znajduje wolnych nauczycieli z odpowiednimi kwalifikacjami
3. Automatycznie przydziela zastępstwa
4. Oznacza lekcje jako zastępstwa w planie

**Jak dodać zastępstwo:**

1. Zaloguj się jako dyrektor
2. Przejdź do "Zastępstwa"
3. Wybierz nauczyciela, daty i powód nieobecności
4. Kliknij "Dodaj nieobecność i wygeneruj zastępstwa"
5. System automatycznie utworzy zastępstwa

#### Zarządzanie klasami

Dyrektor może:
- Przypisać wychowawcę do klasy
- Wybrać 2 rozszerzenia dla klasy
- Ustawić maksymalną liczbę godzin dziennie (5-8)
- Przypisać przedmioty i nauczycieli do klasy
- Określić liczbę godzin każdego przedmiotu w tygodniu

#### Zarządzanie nauczycielami

Dyrektor może:
- Dodawać nowych nauczycieli
- Przypisać przedmioty, które nauczyciel może uczyć (nauczyciel może mieć wiele przedmiotów)
- Usuwać nauczycieli z systemu

#### Kalendarz dni wolnych

Dyrektor może:
- Dodawać święta i dni wolne
- Usuwać dni z kalendarza
- Używać szybkich przycisków dla typowych świąt
- System automatycznie pomija te dni przy generowaniu planu

## Parametry planu lekcji

### Godziny lekcji

- Godzina rozpoczęcia: **8:00**
- Czas trwania lekcji: **45 minut**
- Przerwa między lekcjami: **10 minut**

### Rozkład godzin:

1. Lekcja: 08:00 - 08:45
2. Lekcja: 08:55 - 09:40
3. Lekcja: 09:50 - 10:35
4. Lekcja: 10:45 - 11:30
5. Lekcja: 11:40 - 12:25
6. Lekcja: 12:35 - 13:20
7. Lekcja: 13:30 - 14:15
8. Lekcja: 14:25 - 15:10

### Przedmioty i godziny tygodniowe

System zawiera następujące przedmioty (domyślnie):

- Matematyka: 5h/tydzień
- Matematyka rozszerzona: 3h/tydzień (rozszerzenie)
- Język polski: 5h/tydzień
- Język angielski: 4h/tydzień
- Język angielski rozszerzony: 3h/tydzień (rozszerzenie)
- Geografia: 3h/tydzień
- Biologia: 3h/tydzień
- Chemia: 3h/tydzień
- Fizyka: 3h/tydzień
- Fizyka rozszerzona: 3h/tydzień (rozszerzenie)
- Język niemiecki: 3h/tydzień
- Język hiszpański: 3h/tydzień
- Historia: 2h/tydzień
- WOS: 2h/tydzień
- WF: 4h/tydzień
- Informatyka: 2h/tydzień

Każda klasa wybiera 2 rozszerzenia (po 3h każde).

## Klasy w systemie

Domyślnie system zawiera 12 klas:
- Klasy pierwsze: 1A, 1B, 1C
- Klasy drugie: 2A, 2B, 2C
- Klasy trzecie: 3A, 3B, 3C
- Klasy czwarte: 4A, 4B, 4C

Klasy 1-3 mają domyślnie 7 godzin dziennie.
Klasy 4 mają domyślnie 8 godzin dziennie.

## Rozwiązywanie problemów

### Plan nie generuje się poprawnie

1. Sprawdź czy wszystkie klasy mają przypisane przedmioty
2. Sprawdź czy nauczyciele mają przypisane przedmioty (kwalifikacje)
3. Upewnij się, że jest wystarczająca liczba sal
4. Sprawdź czy suma godzin przedmiotów nie przekracza tygodniowego limitu

### Nie można utworzyć zastępstwa

1. Sprawdź czy są dostępni nauczyciele z odpowiednimi kwalifikacjami
2. Upewnij się, że nauczyciele nie są jednocześnie nieobecni
3. Sprawdź czy nauczyciele nie są zajęci w tym samym czasie

### Błąd połączenia z bazą danych

1. Sprawdź parametry w pliku `includes/config.php`
2. Upewnij się, że baza danych została utworzona
3. Sprawdź uprawnienia użytkownika MySQL

## Bezpieczeństwo

### Zalecenia:

1. **Zmień domyślne hasła** natychmiast po instalacji
2. **Używaj silnych haseł** dla wszystkich kont
3. **Regularnie aktualizuj** system i PHP
4. **Ogranicz dostęp** do plików konfiguracyjnych
5. **Włącz HTTPS** w środowisku produkcyjnym
6. **Regularnie twórz kopie zapasowe** bazy danych

### Tworzenie kopii zapasowej:

```bash
mysqldump -u root -p plan_lekcji > backup_$(date +%Y%m%d).sql
```

### Przywracanie z kopii:

```bash
mysql -u root -p plan_lekcji < backup_20240101.sql
```

## Wsparcie i rozwój

System można rozwijać o dodatkowe funkcjonalności:
- Oceny i frekwencja
- Komunikacja nauczyciel-rodzic
- Zadania domowe
- Ogłoszenia
- Export planu do PDF
- Aplikacja mobilna
- Powiadomienia email/SMS

## Licencja

Ten system został stworzony na potrzeby edukacyjne. Możesz go swobodnie modyfikować i dostosowywać do swoich potrzeb.

## Autor

System stworzony przez Claude (Anthropic) na zamówienie użytkownika.

---

Ostatnia aktualizacja: Listopad 2024
