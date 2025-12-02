# Panel Administratora - Instrukcja Instalacji i Użytkowania

## 📋 Spis treści
1. [Wprowadzenie](#wprowadzenie)
2. [Wymagania](#wymagania)
3. [Instalacja](#instalacja)
4. [Nowe funkcjonalności](#nowe-funkcjonalności)
5. [Struktura plików](#struktura-plików)
6. [Użytkowanie](#użytkowanie)

---

## 🎯 Wprowadzenie

Panel administratora został całkowicie przebudowany i rozszerzony o zaawansowane funkcje zarządzania użytkownikami oraz szczegółowe statystyki systemu.

### Główne zmiany:
- ✅ **Pełne zarządzanie wszystkimi typami użytkowników** (uczniowie, nauczyciele, dyrektorzy, administratorzy)
- ✅ **Dodawanie, edycja, usuwanie i blokowanie użytkowników**
- ✅ **Panel statystyk** z wykresami i analizą aktywności
- ✅ **Monitorowanie aktywnych sesji** w czasie rzeczywistym
- ✅ **System logowania aktywności** użytkowników
- ✅ **Nowoczesny interfejs** z nawigacją i responsywnym designem

---

## 💻 Wymagania

- PHP 8.0+
- MySQL/MariaDB 10.4+
- Serwer WWW (Apache/Nginx)
- Przeglądarka z obsługą JavaScript

---

## 🔧 Instalacja

### Krok 1: Zastosowanie migracji bazy danych

Musisz dodać nowe tabele do bazy danych. Masz dwa sposoby:

#### Opcja A: Przez phpMyAdmin (zalecane)
1. Zaloguj się do phpMyAdmin
2. Wybierz bazę danych `plan_lekcji`
3. Przejdź do zakładki "SQL"
4. Skopiuj i wklej zawartość pliku `migrations/001_admin_panel_enhancement.sql`
5. Kliknij "Wykonaj"

#### Opcja B: Przez skrypt PHP
1. Uruchom w przeglądarce: `http://localhost/plan-lekcji/migrations/apply_migration.php`
2. Poczekaj na zakończenie procesu
3. Sprawdź czy wszystkie tabele zostały utworzone

### Krok 2: Weryfikacja instalacji

Po zastosowaniu migracji, sprawdź czy w bazie danych pojawiły się następujące tabele:
- `sesje_uzytkownikow` - śledzenie aktywnych sesji
- `logi_aktywnosci` - dziennik wszystkich akcji użytkowników
- `statystyki_generowania` - statystyki generowania planów
- `statystyki_uzytkownikow` - statystyki operacji administracyjnych

---

## 🚀 Nowe funkcjonalności

### 1. Dashboard Główny
**Ścieżka:** `/administrator/dashboard.php`

Dashboard przedstawia:
- Podsumowanie liczby użytkowników
- Liczba aktualnie zalogowanych użytkowników
- Statystyki według typu użytkownika
- Status kont (aktywne/zablokowane)
- Ostatnie akcje w systemie

### 2. Zarządzanie Uczniami
**Ścieżka:** `/administrator/uczniowie.php`

Funkcje:
- ➕ Dodawanie nowych uczniów
- ✏️ Edycja danych uczniów (imię, nazwisko, login, hasło, email)
- 🎓 Przypisywanie do klas
- 🔒 Blokowanie/odblokowanie konta
- ❌ Usuwanie uczniów

### 3. Zarządzanie Nauczycielami
**Ścieżka:** `/administrator/nauczyciele.php`

Funkcje:
- ➕ Dodawanie nowych nauczycieli
- ✏️ Edycja danych nauczycieli
- 🔒 Blokowanie/odblokowanie konta
- ❌ Usuwanie nauczycieli

### 4. Zarządzanie Dyrektorami
**Ścieżka:** `/administrator/dyrektorzy.php`

Funkcje:
- ➕ Dodawanie nowych dyrektorów
- ✏️ Edycja danych dyrektorów
- 🔒 Blokowanie/odblokowanie konta
- ❌ Usuwanie dyrektorów

### 5. Zarządzanie Administratorami
**Ścieżka:** `/administrator/administratorzy.php`

Funkcje:
- ➕ Dodawanie nowych administratorów
- ✏️ Edycja danych administratorów
- 🔒 Blokowanie/odblokowanie konta
- ❌ Usuwanie administratorów

⚠️ **Uwaga:** Nie możesz usunąć ani zablokować swojego własnego konta!

### 6. Panel Statystyk
**Ścieżka:** `/administrator/statystyki.php`

Wyświetla:
- 📊 **Wykres logowań** - liczba logowań w ostatnich 7/30/90 dniach
- 📈 **Wykres operacji zarządzania** - statystyki dodawania, edycji, usuwania użytkowników
- 📉 **Wykres generowania planu** - sukces/błędy generowania planów
- 📋 **Szczegółowe statystyki** użytkowników według typu

### 7. Aktywne Sesje
**Ścieżka:** `/administrator/aktywne-sesje.php`

Pokazuje:
- 👥 Lista wszystkich zalogowanych użytkowników
- 🕐 Czas logowania i ostatnia aktywność
- 🌐 Adres IP użytkownika
- ⏱️ Czas trwania sesji
- 🔄 **Automatyczne odświeżanie** co 30 sekund

---

## 📁 Struktura plików

```
plan-lekcji/
│
├── administrator/                      # Panel administratora
│   ├── includes/
│   │   └── sidebar.php                # Nawigacja boczna
│   ├── dashboard.php                  # Dashboard główny
│   ├── uczniowie.php                  # Zarządzanie uczniami
│   ├── nauczyciele.php                # Zarządzanie nauczycielami
│   ├── dyrektorzy.php                 # Zarządzanie dyrektorami
│   ├── administratorzy.php            # Zarządzanie administratorami
│   ├── statystyki.php                 # Panel statystyk
│   └── aktywne-sesje.php              # Aktywne sesje
│
├── includes/
│   ├── config.php                     # Konfiguracja bazy danych
│   └── admin_functions.php            # Funkcje pomocnicze administratora
│
├── css/
│   ├── style.css                      # Style główne
│   └── admin.css                      # Style panelu administratora (NOWY)
│
├── migrations/
│   ├── 001_admin_panel_enhancement.sql   # Migracja bazy danych
│   └── apply_migration.php               # Skrypt instalacyjny
│
├── index.php                          # Strona logowania (zaktualizowana)
└── logout.php                         # Wylogowanie (zaktualizowane)
```

---

## 📖 Użytkowanie

### Logowanie

1. Przejdź do: `http://localhost/plan-lekcji/`
2. Zaloguj się jako administrator:
   - **Login:** `admin`
   - **Hasło:** `admin123`
3. Zostaniesz przekierowany do nowego panelu administratora

### Nawigacja

**Lewy panel boczny** zawiera wszystkie sekcje:
- 📊 **Przegląd** - Dashboard główny
- 👨‍🎓 **Uczniowie** - Zarządzanie uczniami
- 👨‍🏫 **Nauczyciele** - Zarządzanie nauczycielami
- 👔 **Dyrektorzy** - Zarządzanie dyrektorami
- 🛡️ **Administratorzy** - Zarządzanie administratorami
- 📈 **Statystyki** - Panel statystyk
- 🕐 **Aktywne Sesje** - Monitorowanie sesji

### Dodawanie użytkownika

1. Wybierz odpowiednią sekcję z menu (np. "Uczniowie")
2. Wypełnij formularz dodawania
3. Kliknij "Dodaj użytkownika"
4. Użytkownik pojawi się na liście

### Edycja użytkownika

1. W tabeli użytkowników kliknij przycisk "Edytuj"
2. Zmień dane w formularzu
3. Hasło jest opcjonalne - jeśli puste, nie zmieni się
4. Kliknij "Zapisz zmiany"

### Blokowanie użytkownika

1. W tabeli użytkowników kliknij przycisk "Blokuj"
2. Potwierdź akcję
3. Użytkownik nie będzie mógł się zalogować
4. Możesz go odblokować przyciskiem "Odblokuj"

⚠️ **Uwaga:** Zablokowany użytkownik zostanie automatycznie wylogowany!

### Usuwanie użytkownika

1. W tabeli użytkowników kliknij przycisk "Usuń"
2. Potwierdź akcję
3. Użytkownik zostanie trwale usunięty z bazy danych

⚠️ **Ostrzeżenie:** Ta operacja jest nieodwracalna!

---

## 🔒 Bezpieczeństwo

System zawiera następujące zabezpieczenia:

1. **Hashowanie haseł** - wszystkie hasła są hashowane algorytmem bcrypt
2. **Walidacja sesji** - automatyczne czyszczenie nieaktywnych sesji (30 min)
3. **Ochrona przed blokadą własnego konta** - nie możesz zablokować/usunąć swojego konta
4. **Logowanie wszystkich akcji** - każda operacja jest zapisywana w logach
5. **Sprawdzanie uprawnień** - dostęp tylko dla administratorów
6. **Ochrona przed SQL injection** - prepared statements
7. **Walidacja danych wejściowych** - htmlspecialchars() dla wszystkich danych wyjściowych

---

## 📊 System logowania

Wszystkie poniższe akcje są automatycznie logowane:

- ✅ Logowania (udane i nieudane)
- ✅ Wylogowania
- ✅ Dodawanie użytkowników
- ✅ Edycja użytkowników
- ✅ Usuwanie użytkowników
- ✅ Blokowanie/odblokowanie użytkowników

Logi zawierają:
- ID użytkownika wykonującego akcję
- Typ akcji
- Opis akcji
- Adres IP
- User Agent (przeglądarka)
- Data i czas

---

## 🎨 Responsywność

Panel administratora jest w pełni responsywny:

- **Desktop (>1024px):** Pełny sidebar z opisami
- **Tablet (768-1024px):** Minimalistyczny sidebar (tylko ikony)
- **Mobile (<768px):** Ukryty sidebar (hamburger menu)

---

## 🐛 Rozwiązywanie problemów

### Problem: "Brak tabeli sesje_uzytkownikow"
**Rozwiązanie:** Zastosuj migrację bazy danych (zobacz Krok 1 instalacji)

### Problem: "Nie mogę się zalogować jako admin"
**Rozwiązanie:** Sprawdź w bazie czy konto admin ma `aktywny = 1`

### Problem: "Strona wyświetla się bez stylów"
**Rozwiązanie:** Sprawdź czy plik `/css/admin.css` istnieje i jest dostępny

### Problem: "Wykresy nie działają"
**Rozwiązanie:** Sprawdź połączenie z internetem (wykresy używają Chart.js z CDN)

### Problem: "Sesje nie są zapisywane"
**Rozwiązanie:** Upewnij się, że tabele zostały utworzone i serwer ma uprawnienia do zapisu

---

## 📝 Changelog

### Wersja 2.0 (2025-12-02)

#### Dodane:
- ✅ Kompletny panel zarządzania użytkownikami
- ✅ System blokowania kont
- ✅ Panel statystyk z wykresami
- ✅ Monitorowanie aktywnych sesji
- ✅ System logowania aktywności
- ✅ Nowy responsywny interfejs
- ✅ Automatyczne czyszczenie nieaktywnych sesji

#### Zmienione:
- 🔄 Całkowicie przeprojektowany dashboard administratora
- 🔄 Rozszerzone funkcje zarządzania uczniami
- 🔄 Zaktualizowany system logowania (sprawdza pole `aktywny`)
- 🔄 Nowe style CSS (admin.css)

#### Naprawione:
- 🐛 Brak możliwości zarządzania nauczycielami i dyrektorami
- 🐛 Brak statystyk systemu
- 🐛 Brak informacji o aktywnych użytkownikach

---

## 👨‍💻 Autor

Rozbudowa panelu administratora: Claude (Anthropic)
Data: 2025-12-02

---

## 📞 Wsparcie

W razie problemów:
1. Sprawdź sekcję "Rozwiązywanie problemów"
2. Sprawdź logi błędów PHP
3. Sprawdź logi MySQL
4. Upewnij się, że wszystkie pliki zostały poprawnie skopiowane

---

## ✅ Checklist po instalacji

- [ ] Migracja bazy danych zastosowana
- [ ] Nowe tabele utworzone (sesje_uzytkownikow, logi_aktywnosci, etc.)
- [ ] Możesz zalogować się jako admin
- [ ] Dashboard wyświetla się poprawnie
- [ ] Nawigacja działa
- [ ] Możesz dodać ucznia
- [ ] Możesz edytować ucznia
- [ ] Możesz zablokować/odblokować ucznia
- [ ] Panel statystyk wyświetla dane
- [ ] Aktywne sesje pokazują Twoją sesję
- [ ] Wykresy się renderują

---

🎉 **Gratulacje! Panel administratora jest gotowy do użycia!**
