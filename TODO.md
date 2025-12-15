# Plan rozwoju aplikacji - System Planu Lekcji

## Obecna wersja: 2.4

---

## Wersja 4.0 - System Ocen oraz Wiadomości

### Opis ogólny
Kompletny system zarządzania ocenami uczniów z panelem administracyjnym oraz wewnętrzny system wiadomości z możliwością załączania plików.

### Moduł Ocen

#### Funkcjonalności dla nauczyciela
- **Wystawianie ocen**:
  - Oceny cząstkowe (z różnymi wagami)
  - Oceny końcowe (śródroczne, roczne)
  - Kategorie ocen: sprawdzian, kartkówka, odpowiedź ustna, aktywność, praca domowa
  - Komentarze do ocen
  - Możliwość poprawy ocen
- **Statystyki klasy**:
  - Średnia klasy z przedmiotu
  - Wykresy rozkładu ocen
  - Identyfikacja uczniów wymagających wsparcia

#### Funkcjonalności dla ucznia
- Przeglądanie swoich ocen z wszystkich przedmiotów
- Średnia ważona dla każdego przedmiotu
- Średnia ogólna ze wszystkich przedmiotów
- Przewidywana ocena końcowa na podstawie dotychczasowych wyników
- Historia ocen z datami wystawienia
- Wykresy postępów w nauce

#### Funkcjonalności dla rodziców
- Dostęp do ocen swojego dziecka
- Powiadomienia o nowych ocenach (email/SMS)
- Porównanie wyników z poprzednimi okresami
- Możliwość kontaktu z nauczycielem

#### Panel dyrektora
- **Statystyki szkoły**:
  - Średnie ocen dla poszczególnych klas
  - Porównanie wyników między klasami
  - Ranking nauczycieli według średnich ocen uczniów
  - Analiza trendów (poprawa/pogorszenie wyników)
- **Raporty**:
  - Export danych do Excel/PDF
  - Raporty okresowe (miesięczne, kwartalne, roczne)
  - Zestawienia dla kuratorium

### Moduł Wiadomości

#### Funkcjonalności komunikacji
- **Tworzenie wiadomości**:
  - Wybór odbiorcy z listy (nauczyciele, uczniowie, rodzice, dyrektor)
  - Pole tematu i treści
  - Znacznik "ważne" dla pilnych spraw
  - Potwierdzenie przeczytania
- **Załączniki**:
  - Możliwość załączania plików (PDF, DOC, JPG, PNG - max 10MB)
  - Podgląd załączonych plików
  - Skanowanie antywirusowe przesyłanych plików
- **Organizacja**:
  - Skrzynka odbiorcza
  - Wysłane
  - Szkice
  - Archiwum
  - Oznaczanie jako przeczytane/nieprzeczytane
  - Usuwanie wiadomości

#### Wiadomości grupowe
- Wysyłanie do całej klasy
- Wysyłanie do wszystkich nauczycieli przedmiotu
- Wysyłanie do wszystkich rodziców klasy
- Lista odbiorców z potwierdzeniem przeczytania

#### System powiadomień
- Powiadomienia email o nowej wiadomości
- Powiadomienia w aplikacji (badge z liczbą nieprzeczytanych)
- Opcja wyłączenia powiadomień

### Wymagania techniczne
- **Backend**:
  - REST API dla ocen i wiadomości
  - Websockets dla powiadomień w czasie rzeczywistym
  - Storage dla załączników (np. AWS S3, MinIO)
  - Szyfrowanie danych osobowych
- **Frontend**:
  - Edytor wiadomości (np. Quill, TinyMCE)
  - Biblioteka do wykresów (np. Chart.js, Recharts)
  - Uploader plików z progress bar
- **Baza danych**:
  - Tabele: grades, grade_categories, messages, message_attachments, message_recipients
  - Indeksy dla szybkiego wyszukiwania

### Bezpieczeństwo
- Autoryzacja oparta na rolach (RBAC)
- Walidacja uprawnień - uczeń widzi tylko swoje oceny
- Rodzic widzi tylko oceny swojego dziecka
- Szyfrowanie załączników
- Logi dostępu do wrażliwych danych

---

## Wersja 5.0 - Nieobecności i ich usprawiedliwienia

### Opis ogólny
System sprawdzania listy obecności na każdej lekcji który automatycznie wpisuje nieobecności dla nieobecnych uczniów oraz możliwość usprawiedliwiania tych nieobecności.

### Funkcjonalności dla nauczyciela

#### Sprawdzanie obecności
- **Lista obecności**:
  - Wyświetlanie listy uczniów dla danej klasy
  - Szybkie zaznaczanie: obecny (domyślnie), nieobecny, spóźniony
  - Możliwość dodania uwagi do nieobecności
  - Auto-save podczas zaznaczania
- **Statystyki frekwencji**:
  - Procent obecności dla ucznia
  - Liczba nieobecności usprawiedliwionych/nieusprawiedliwionych
  - Liczba spóźnień
  - Alerty przy przekroczeniu dozwolonej liczby nieobecności

#### Raporty dla wychowawcy
- Lista uczniów z niską frekwencją (< 80%)
- Zestawienie nieobecności nieusprawiedliwionych
- Export raportów do druku (wezwania dla rodziców)

### Funkcjonalności dla ucznia/rodzica

#### Przeglądanie nieobecności
- Kalendarz z zaznaczonymi dniami nieobecności
- Lista wszystkich nieobecności ze statusem (usprawiedliwione/nieusprawiedliwione)
- Możliwość filtrowania po przedmiocie/okresie

#### Usprawiedliwianie nieobecności
- **Formularz usprawiedliwienia**:
  - Wybór daty/dat nieobecności
  - Powód nieobecności (choroba, sprawy rodzinne, inne)
  - Załącznik (zwolnienie lekarskie w formacie PDF/JPG)
  - Data złożenia usprawiedliwienia
- **Proces zatwierdzania**:
  - Złożenie przez ucznia/rodzica
  - Powiadomienie wychowawcy o nowym usprawiedliwieniu
  - Akceptacja lub odrzucenie przez wychowawcę
  - Powiadomienie o decyzji

### Funkcjonalności dla wychowawcy

#### Zarządzanie usprawiedliwieniami
- Lista oczekujących usprawiedliwień
- Możliwość zaakceptowania lub odrzucenia z podaniem powodu
- Historia wszystkich usprawiedliwień w klasie
- Masowe usprawiedliwianie (np. dla całej klasy - wycieczka)

#### Automatyczne usprawiedliwienia
- Konfiguracja zasad automatycznego usprawiedliwiania
- Np. zwolnienie lekarskie automatycznie usprawiedliwia wszystkie nieobecności w tym okresie

### Panel dyrektora
- Statystyki frekwencji dla całej szkoły
- Porównanie frekwencji między klasami
- Identyfikacja uczniów z chronicznie niską frekwencją
- Raporty dla kuratorium oświaty

### Wymagania techniczne
- **Automatyzacja**: Zadania cron sprawdzające czy lista obecności została wypełniona
- **Powiadomienia**: Email/SMS do rodziców o nieobecności
- **Integracja**: Połączenie z systemem wydarzeń (v6.0) - automatyczne usprawiedliwienie podczas wycieczek
- **Baza danych**: Tabele attendance, absence_justifications

### Compliance
- Zgodność z RODO - dane o nieobecnościach jako dane wrażliwe
- Przechowywanie danych przez określony czas (zgodnie z przepisami)
- Prawo do usunięcia danych po zakończeniu edukacji

---

## Wersja 6.0 - Wydarzenia w planie (Sprawdziany, kartkówki i inne)

### Opis ogólny
Możliwość wpisywania wydarzeń na danej lekcji dla danej klasy przez nauczycieli i widoczność tych wydarzeń w planie.

### Typy wydarzeń

#### Wydarzenia oceniane
- **Sprawdzian**: Zapowiedziany z wyprzedzeniem (min. tydzień), obejmuje większy zakres materiału
- **Kartkówka**: Może być niezapowiedziana, obejmuje ostatnie 3 lekcje
- **Praca klasowa**: Dłuższa forma pracy pisemnej
- **Odpowiedź ustna**: Planowane przesłuchania

#### Wydarzenia nieoceniane
- **Wycieczka szkolna**: Z opisem miejsca i celu wyjazdu
- **Akademia/Uroczystość**: Szkolne wydarzenia
- **Film edukacyjny**: Projekcja na lekcji
- **Gość specjalny**: Wizyta eksperta/prelegenta
- **Konkurs**: Konkursy przedmiotowe
- **Zajęcia dodatkowe**: Koła zainteresowań, konsultacje

### Funkcjonalności dla nauczyciela

#### Dodawanie wydarzenia
- **Formularz wydarzenia**:
  - Wybór typu wydarzenia
  - Tytuł i szczegółowy opis
  - Data i lekcja (godzina)
  - Klasa lub grupy uczniów
  - Zakres materiału (dla sprawdzianów)
  - Materiały do powtórzenia
  - Kategoria wagowa (dla wydarzeń ocenianych)
- **Powiadomienia**:
  - Automatyczne powiadomienie uczniów i rodziców o wydarzeniu
  - Przypomnienie na 1-2 dni przed wydarzeniem

#### Zarządzanie wydarzeniami
- Lista wszystkich zaplanowanych wydarzeń
- Edycja i usuwanie wydarzeń
- Przenoszenie wydarzenia na inny termin
- Kopiowanie wydarzenia do innej klasy

### Funkcjonalności dla ucznia

#### Widok wydarzeń
- **Kalendarz wydarzeń**:
  - Widok miesięczny z zaznaczonymi wydarzeniami
  - Widok tygodniowy ze szczegółami
  - Widok listy (chronologiczny)
- **Oznaczenia kolorystyczne**:
  - Sprawdziany - czerwony
  - Kartkówki - pomarańczowy
  - Wycieczki - zielony
  - Inne - niebieski
- **Filtrowanie**:
  - Po typie wydarzenia
  - Po przedmiocie
  - Tylko wydarzenia oceniane

#### Funkcje pomocnicze
- Eksport wydarzeń do kalendarza zewnętrznego (Google Calendar, iCal)
- Licznik dni do najbliższego sprawdzianu
- Lista materiałów do powtórzenia
- Oznaczanie jako "przygotowane"

### Funkcjonalności dla rodziców
- Podgląd wszystkich wydarzeń dziecka
- Powiadomienia o zbliżających się sprawdzianach
- Możliwość eksportu do własnego kalendarza

### Reguły biznesowe

#### Limity wydarzeń
- Maksymalnie 1 sprawdzian dziennie dla klasy
- Maksymalnie 3 sprawdziany w tygodniu
- Zakaz planowania sprawdzianów w ostatnim tygodniu przed klasyfikacją
- System ostrzeżeń przy przekroczeniu limitów

#### Kolizje
- Wykrywanie dni z nadmierną liczbą wydarzeń
- Propozycje alternatywnych terminów
- Wymuszenie zatwierdzenia przez dyrektora przy przekroczeniu limitów

### Panel dyrektora
- Kalendarz wszystkich wydarzeń w szkole
- Kontrola równomiernego rozłożenia sprawdzianów
- Zatwierdzanie wydarzeń naruszających reguły
- Statystyki - liczba sprawdzianów na nauczyciela/klasę

### Wymagania techniczne
- **Integracja**:
  - Połączenie z systemem ocen (v4.0) - po wydarzeniu automatyczne dodanie kolumny na oceny
  - Połączenie z systemem nieobecności (v5.0) - wycieczka = automatyczne usprawiedliwienie
  - Synchronizacja z zewnętrznymi kalendarzami (Google Calendar API, CalDAV)
- **Powiadomienia**:
  - System kolejkowania powiadomień
  - Push notifications w aplikacji mobilnej
  - Email/SMS dla rodziców
- **Baza danych**: Tabela events, event_participants, event_materials

---

## Wersja 7.0 - System Prac Domowych i Nieprzygotowań

### Opis ogólny
Nauczyciel wpisuje pracę domową dla danej klasy oraz termin jej sprawdzenia oraz system sprawdzania przygotowania podczas sprawdzania listy obecności.

### Moduł Prac Domowych

#### Funkcjonalności dla nauczyciela

**Zadawanie pracy domowej**:
- **Formularz zadania**:
  - Przedmiot i klasa
  - Opis zadania (edytor rich text)
  - Data zadania i termin wykonania
  - Materiały źródłowe (strony w podręczniku, linki, załączniki)
  - Szacowany czas wykonania
  - Oznaczenie trudności (łatwe, średnie, trudne)
  - Opcjonalnie: maksymalna liczba punktów
- **Załączniki**:
  - Pliki PDF z materiałami
  - Linki do zasobów online
  - Obrazki, schematy
- **Zarządzanie**:
  - Edycja i usuwanie zadań
  - Przedłużanie terminu
  - Kopiowanie zadania do innej klasy

**Sprawdzanie wykonania**:
- Lista uczniów z checkboxami (wykonał/nie wykonał)
- Szybkie zaznaczanie podczas lekcji
- Możliwość dodania oceny lub punktów za pracę domową
- Komentarz do pracy ucznia
- Integracja ze sprawdzaniem obecności

**Statystyki**:
- Procent wykonania prac domowych dla klasy
- Identyfikacja uczniów często nieprzygotowanych
- Raport dla wychowawcy o uczniach zaniedbujących obowiązki

#### Funkcjonalności dla ucznia

**Widok prac domowych**:
- **Lista zadań**:
  - Aktywne prace domowe (do zrobienia)
  - Historia wykonanych prac
  - Oznaczenie kolorem: zielony (zrobione), żółty (bliski termin), czerwony (po terminie)
- **Szczegóły zadania**:
  - Pełny opis
  - Załączone materiały
  - Termin oddania
  - Licznik czasu do deadline
- **Zarządzanie własnymi zadaniami**:
  - Oznaczanie jako "zrobione"
  - Notatki prywatne do zadania
  - Przypomnienia

**Kalendarz prac domowych**:
- Widok wszystkich zadań z różnych przedmiotów
- Identyfikacja dni z dużą ilością zadań
- Planowanie czasu nauki

#### Funkcjonalności dla rodziców
- Podgląd wszystkich zadanych prac domowych
- Powiadomienia o nowych pracach domowych
- Powiadomienia o zbliżających się terminach
- Statystyki wykonywania prac przez dziecko

### Moduł Nieprzygotowań

#### System zgłaszania nieprzygotowania

**Dla ucznia**:
- Możliwość zgłoszenia nieprzygotowania przed lekcją (do północy przed dniem lekcji)
- Wybór przedmiotu i typu nieprzygotowania:
  - Nieprzygotowanie do odpytywania
  - Brak pracy domowej
  - Brak materiałów
- Powód (opcjonalnie)

**Limity nieprzygotowań**:
- Konfiguracja przez szkołę (np. 3 nieprzygotowania na semestr)
- System zliczający wykorzystane nieprzygotowania
- Ostrzeżenia przy wyczerpaniu limitu
- Konsekwencje przekroczenia (np. automatyczna ocena niedostateczna)

#### Funkcjonalności dla nauczyciela

**Sprawdzanie przygotowania podczas obecności**:
- Zintegrowany widok: obecność + przygotowanie
- Szybkie zaznaczanie w jednym interfejsie:
  - Obecny i przygotowany (domyślnie)
  - Obecny ale nieprzygotowany
  - Nieobecny
  - Spóźniony
- Lista zgłoszonych nieprzygotowań do zatwierdzenia
- Możliwość odrzucenia nieprzygotowania (przy nadużyciach)

**Polityka nieprzygotowań**:
- Konfiguracja własnych zasad (np. akceptacja wszystkich, wybiórcza akceptacja)
- Możliwość ustawienia konsekwencji (np. ocena niedostateczna po 3 nieprzygotowaniach)

**Statystyki**:
- Ranking uczniów według liczby nieprzygotowań
- Identyfikacja uczniów nadużywających systemu
- Raport dla wychowawcy

#### Panel wychowawcy
- Zestawienie wszystkich nieprzygotowań ucznia ze wszystkich przedmiotów
- Alerty przy przekroczeniu limitów
- Kontakt z rodzicami uczniów zaniedbujących naukę
- Interwencje pedagogiczne

### Reguły biznesowe

#### Prace domowe
- Termin wykonania minimum 1 dzień od zadania
- Maksymalnie 5 prac domowych dziennie dla klasy (suma ze wszystkich przedmiotów)
- Zakaz zadawania w weekendy i święta (konfigurowane)
- System ostrzeżeń dla nauczycieli przy nadmiernym obciążeniu uczniów

#### Nieprzygotowania
- Limit nieprzygotowań na semestr (konfigurowalny)
- Termin zgłoszenia: do północy przed lekcją
- Nieprzygotowanie nie chroni przed negatywną oceną jeżeli zostanie odrzucone
- System automatycznego informowania rodziców po przekroczeniu 50% limitu

### Funkcje dodatkowe

#### Analityka dla dyrektora
- Statystyki zadawania prac domowych (średnia ilość na nauczyciela)
- Kontrola obciążenia uczniów
- Porównanie między klasami
- Identyfikacja nauczycieli zadających zbyt dużo/mało

#### Gamifikacja (opcjonalnie)
- Punkty za systematyczne wykonywanie prac
- Odznaki za serie wykonanych prac
- Ranking najbardziej pilnych uczniów
- Nagrody za wysoką punktację (np. zwolnienie z 1 nieprzygotowania)

### Wymagania techniczne
- **Integracja**:
  - System ocen (v4.0) - oceny za prace domowe
  - System wydarzeń (v6.0) - prace domowe powiązane ze sprawdzianami
  - System obecności (v5.0) - sprawdzanie przygotowania + obecności w jednym interfejsie
- **Powiadomienia**:
  - Push o nowej pracy domowej
  - Przypomnienia o zbliżającym się terminie
  - Alerty o przekroczeniu limitu nieprzygotowań
- **Baza danych**:
  - Tabele: homework, homework_submissions, unpreparedness, unpreparedness_limits
  - Indeksy dla wydajnego pobierania

---

## Zależności między wersjami

- **v4.0** wymaga **v3.0** - system ocen korzysta ze stabilnego planu lekcji
- **v5.0** może działać niezależnie, ale integruje się z **v6.0**
- **v6.0** integruje się z **v4.0** (wydarzenia oceniane) i **v5.0** (wycieczki)
- **v7.0** wymaga **v4.0** (oceny za prace domowe) i **v5.0** (sprawdzanie przygotowania)

## Potencjalne usprawnienia na przyszłość

### Wersja 8.0+ (pomysły)
- **System zastępstw** - automatyczne planowanie zastępstw za nieobecnych nauczycieli
- **Integracja z dziennikiem elektronicznym** - synchronizacja z krajowymi systemami
- **Aplikacja mobilna** - natywne aplikacje iOS/Android
- **System rezerwacji sal i sprzętu** - rezerwacja pracowni komputerowej, projektora, sali gimnastycznej
- **E-learning** - integracja z platformami edukacyjnymi (Moodle, Google Classroom)
- **Statystyki zaawansowane** - AI/ML do predykcji wyników uczniów, rekomendacje dla nauczycieli
- **Portal rodziców** - rozbudowany dostęp dla rodziców z dodatkowymi funkcjami
- **System płatności** - opłaty za obiady, wycieczki, podręczniki

---

## Harmonogram (wstępny)

| Wersja | Złożoność | Szacowany czas | Priorytet |
|--------|-----------|----------------|-----------|
| 3.0    | Średnia   | 2-3 miesiące   | Wysoki    |
| 4.0    | Wysoka    | 4-6 miesięcy   | Krytyczny |
| 5.0    | Średnia   | 2-3 miesiące   | Wysoki    |
| 6.0    | Niska     | 1-2 miesiące   | Średni    |
| 7.0    | Średnia   | 2-3 miesiące   | Średni    |

**Całkowity szacowany czas realizacji: 11-17 miesięcy**

## Uwagi techniczne

### Stack technologiczny (sugestie)
- **Backend**: Node.js/Express lub Python/Django
- **Frontend**: React/Vue.js
- **Baza danych**: PostgreSQL (relacyjne dane) + Redis (cache, sesje)
- **File storage**: S3/MinIO dla załączników
- **Real-time**: WebSockets (Socket.io) dla powiadomień
- **Mobile**: React Native lub Flutter

### Bezpieczeństwo i compliance
- Zgodność z RODO
- Szyfrowanie danych wrażliwych (oceny, dane osobowe)
- Audyt logów dostępu
- Regularne backupy
- 2FA dla kont administracyjnych
- Testy penetracyjne przed wdrożeniem każdej wersji

---

**Ostatnia aktualizacja**: 2025-12-04
