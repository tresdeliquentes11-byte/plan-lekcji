# LibreLessons - Dokumentacja

 <img width="1536" height="1024" alt="LogoLibreLessons" src="https://github.com/user-attachments/assets/10d83a08-33b2-4f67-8700-5f1e6d579213" />


## Co to jest LibreLessons?

 

**LibreLessons** to otwarty system zarządzania planem lekcji dla szkół, napisany w PHP z wykorzystaniem bazy danych MySQL/MariaDB. System umożliwia kompleksowe zarządzanie organizacją zajęć szkolnych.

 

### Główne funkcje:

- Automatyczne generowanie planu lekcji dla wszystkich klas

- Zarządzanie zastępstwami za nieobecnych nauczycieli

- Przeglądanie planu przez uczniów, nauczycieli i dyrekcję

- Zarządzanie kalendarzem dni wolnych

- Przypisywanie nauczycieli do przedmiotów i klas

- Zarządzanie salami lekcyjnymi

 

### Link do repozytorium:

[https://github.com/tresdeliquentes11-byte/plan-lekcji](https://github.com/tresdeliquentes11-byte/librelessons)

 

---

 

## Minimalne wymagania systemowe (serwer)

 

| Komponent | Minimalne | Zalecane |
|-----------|-----------|----------|
| Procesor | 1 rdzeń | 2+ rdzenie |
| RAM | 512 MB | 1 GB+ |
| Dysk | 100 MB | 500 MB+ |
| System | Linux/Windows | Ubuntu Server 22.04+ |

 

### Wymagania sieciowe:

- Port 80 (HTTP) lub 443 (HTTPS) otwarty w sieci lokalnej

- Statyczny adres IP w sieci lokalnej (zalecane)

 

---

 

## Wymagane oprogramowanie

 

| Program | Wersja | Uwagi |
|---------|--------|-------|
| PHP | 8.2+ | z rozszerzeniami: mysqli, session |
| MySQL | 8.0+ | lub MariaDB 10.4+ |
| Serwer WWW | Apache 2.4+ | lub Nginx |

 

---

 

## Instalacja na Ubuntu Server (Apache2 + MySQL)

 

### Krok 1: Aktualizacja systemu

 

```bash

sudo apt update && sudo apt upgrade -y

```

 

### Krok 2: Instalacja Apache2

 

```bash

sudo apt install apache2 -y
sudo systemctl enable apache2
sudo systemctl start apache2

```

 

### Krok 3: Instalacja MySQL

 

```bash

sudo apt install mysql-server -y
sudo systemctl enable mysql
sudo systemctl start mysql

```

 

Zabezpiecz instalację MySQL:

```bash

sudo mysql_secure_installation

```

 

### Krok 4: Instalacja PHP i rozszerzeń

 

```bash

sudo apt install php php-mysqli php-cli libapache2-mod-php -y

```

 

### Krok 5: Pobranie LibreLessons

 

```bash

cd /var/www/html
sudo curl -L https://github.com/tresdeliquentes11-byte/librelessons/archive/refs/heads/main.zip -o librelessons.zip
sudo apt install unzip -y
sudo unzip librelessons.zip
sudo mv librelessons-main librelessons
sudo rm librelessons.zip

```

 

### Krok 6: Ustawienie uprawnień

 

```bash

sudo chown -R www-data:www-data /var/www/html/librelessons
sudo chmod -R 755 /var/www/html/librelessons

```

 

### Krok 7: Konfiguracja bazy danych

 

Zaloguj się do MySQL:

```bash

sudo mysql -u root -p

```

 

Wykonaj następujące polecenia:

```sql

CREATE DATABASE plan_lekcji CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'librelessons'@'localhost' IDENTIFIED BY 'TwojeHaslo123!';
GRANT ALL PRIVILEGES ON plan_lekcji.* TO 'librelessons'@'localhost';
FLUSH PRIVILEGES;
EXIT;

```

 

Zaimportuj strukturę bazy danych:

```bash

sudo mysql -u librelessons -p plan_lekcji < /var/www/html/librelessons/database.sql

```

 

### Krok 8: Konfiguracja połączenia z bazą

 

Edytuj plik konfiguracyjny:

```bash

sudo nano /var/www/html/librelessons/includes/config.php

```

 

Zmień parametry połączenia:

```php

define('DB_HOST', 'localhost');
define('DB_USER', 'librelessons');
define('DB_PASS', 'TwojeHaslo123!');
define('DB_NAME', 'plan_lekcji');

```

 

### Krok 9: Restart Apache

 

```bash

sudo systemctl restart apache2

```

 

### Krok 10: Dostęp do systemu

 

Otwórz przeglądarkę i wejdź na:

```

http://ADRES_IP_SERWERA/librelessons

```

 

---

 

## Instalacja na Windows (XAMPP)

 

### Krok 1: Pobierz i zainstaluj XAMPP

 

1. Pobierz XAMPP z [https://www.apachefriends.org](https://www.apachefriends.org)

2. Uruchom instalator i zainstaluj w domyślnej lokalizacji (`C:\xampp`)

3. Podczas instalacji zaznacz komponenty: **Apache**, **MySQL**, **PHP**

 

### Krok 2: Uruchom XAMPP

 

1. Otwórz **XAMPP Control Panel**

2. Kliknij **Start** przy **Apache**

3. Kliknij **Start** przy **MySQL**

 

### Krok 3: Pobierz LibreLessons

 

1. Pobierz ZIP z repozytorium: [https://github.com/tresdeliquentes11-byte/plan-lekcji/archive/refs/heads/main.zip](https://github.com/tresdeliquentes11-byte/plan-lekcji/archive/refs/heads/main.zip)

2. Rozpakuj archiwum

3. Zmień nazwę folderu `librelessons-main` na `librelessons`

4. Przenieś folder `librelessons` do `C:\xampp\htdocs\`

 

### Krok 4: Utwórz bazę danych

 

1. Otwórz przeglądarkę i wejdź na: `http://localhost/phpmyadmin`

2. Kliknij **Nowa** (po lewej stronie)

3. Wpisz nazwę bazy: `plan_lekcji`

4. Wybierz kodowanie: `utf8mb4_general_ci`

5. Kliknij **Utwórz**

 

### Krok 5: Zaimportuj strukturę bazy

 

1. W phpMyAdmin wybierz bazę `plan_lekcji`

2. Kliknij zakładkę **Import**

3. Kliknij **Wybierz plik** i wskaż: `C:\xampp\htdocs\librelessons\database.sql`

4. Kliknij **Wykonaj**

 

### Krok 6: Konfiguracja połączenia

 

1. Otwórz plik `C:\xampp\htdocs\librelessons\includes\config.php` w edytorze tekstu

2. Ustaw parametry (domyślnie XAMPP nie ma hasła):

 

```php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'plan_lekcji');

```

 

### Krok 7: Dostęp do systemu

 

Otwórz przeglądarkę i wejdź na:

```

http://localhost/librelessons

```

 

---

 

## Krótki tutorial - pierwsze kroki

 

### Domyślne konta

 

| Rola | Login | Hasło |
|------|-------|-------|
| Dyrektor | `dyrektor` | `dyrektor123` |
| Administrator | `admin` | `admin123` |

 

**Zmień hasła natychmiast po pierwszym logowaniu!**

 

### Krok 1: Logowanie jako Dyrektor

 

1. Wejdź na stronę główną systemu

2. Wpisz login: `dyrektor`

3. Wpisz hasło: `dyrektor123`

4. Kliknij **Zaloguj się**

 

### Krok 2: Dodanie nauczycieli

 

1. W menu bocznym kliknij **Nauczyciele**

2. Kliknij **Dodaj nauczyciela**

3. Wypełnij formularz (imię, nazwisko, login, hasło)

4. Przypisz przedmioty, które nauczyciel może uczyć

5. Zapisz

 

### Krok 3: Konfiguracja klas

 

1. W menu kliknij **Klasy**

2. Dla każdej klasy:

   - Wybierz wychowawcę

   - Wybierz 2 rozszerzenia (np. matematyka rozszerzona, fizyka rozszerzona)

   - Ustaw liczbę godzin dziennie (5-8)

   - Przypisz przedmioty i nauczycieli

 

### Krok 4: Konfiguracja sal

 

1. W menu kliknij **Sale**

2. Dodaj sale lekcyjne (np. 101, 102, sala gimnastyczna)

3. Określ typ sali (standardowa, pracownia, sportowa)

 

### Krok 5: Generowanie planu lekcji

 

1. W menu kliknij **Generuj Plan**

2. Sprawdź, czy wszystkie klasy mają przypisane przedmioty

3. Kliknij **Wygeneruj plan lekcji**

4. Poczekaj na zakończenie generowania

5. Sprawdź wygenerowany plan w zakładce **Podgląd planu**


 
### Krok 6: Tworzenie/Zmiana planu lekcji

 

1. W menu kliknij **Edytuj plan**

2. Dodaj/Usuń/Edutyj/Przesuń Lekcje

3. Sprawdź czy nie występują konflikty, naciśnij `Sprawdź konflikty`

4. Zapisz zmiany i sprawdź czy transakcja się udała



### Krok 7: Zarządzanie zastępstwami

 

Gdy nauczyciel jest nieobecny:

1. W menu kliknij **Zastępstwa**

2. Wybierz nieobecnego nauczyciela

3. Podaj daty nieobecności

4. Kliknij **Dodaj nieobecność i wygeneruj zastępstwa**

5. System automatycznie znajdzie zastępstwa

 

### Role użytkowników

 

| Rola | Możliwości |
|------|------------|
| **Dyrektor** | Pełen dostęp - generowanie planu, zastępstwa, zarządzanie wszystkim |
| **Administrator** | Zarządzanie kontami użytkowników (uczniowie, nauczyciele) |
| **Nauczyciel** | Podgląd własnego planu lekcji |
| **Uczeń** | Podgląd planu swojej klasy |

 

---

 

### Doatkowe informacje

 

W systemie dostępnych jest znacznie więcej funkcji, niż przedstawiono w niniejszym poradniku. Ma on jedynie wprowadzić użytkownika w ekosystem LibreLessons i ułatwić rozpoczęcie pracy z aplikacją. Aby lepiej poznać pełne możliwości programu, warto samodzielnie przejrzeć wszystkie panele dostępne w interfejsach Dyrektora oraz Administratora.

 

---

 

## Licencja

 

LibreLessons jest licencjonowane na zasadach **TEUL** (TresDeliquentes Educational Use License).

 

- Dozwolone użycie wyłącznie w celach edukacyjnych

- Modyfikacje dozwolone na potrzeby własnej instytucji

- Zakaz kopiowania, publikacji i odsprzedaży bez zgody autora

 

© 2025 TresDeliquentes
