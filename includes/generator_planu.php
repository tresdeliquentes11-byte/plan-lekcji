<?php
require_once '../includes/config.php';
require_once '../includes/dostepnosc_helpers.php';
require_once '../includes/admin_functions.php';

class GeneratorPlanu {
    private $conn;
    private $dni = ['poniedzialek', 'wtorek', 'sroda', 'czwartek', 'piatek'];
    private $godzina_rozpoczecia = '08:00';
    private $czas_lekcji = 45; // minuty
    private $czas_przerwy = 10; // minuty

    // Struktury danych w pamięci
    private $remaining_hours = [];      // [class_id][subject_id] => remaining hours
    private $teacher_availability = []; // [teacher_id][day][lesson] => bool
    private $room_availability = [];    // [room_id][day][lesson] => bool
    private $class_schedule = [];       // [class_id][day][lesson] => lesson data
    private $teacher_schedule = [];     // [teacher_id][day][lesson] => lesson data
    private $occurrences = [];          // [class_id][day][family] => count
    private $daily_count = [];          // [class_id][day] => count
    private $validation_errors = [];
    private $unassigned_slots = [];     // Lista nieprzypisanych slotów

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // =====================================================================
    // KROK 1: WALIDACJA
    // =====================================================================

    /**
     * Waliduje dane wejściowe przed rozpoczęciem generowania
     * @return array ['success' => bool, 'errors' => array]
     */
    private function walidujDane() {
        $this->validation_errors = [];

        // Pobierz wszystkie klasy
        $klasy_result = $this->conn->query("SELECT * FROM klasy ORDER BY nazwa");

        while ($klasa = $klasy_result->fetch_assoc()) {
            $klasa_id = $klasa['id'];
            $klasa_nazwa = $klasa['nazwa'];

            // Sprawdź czy klasa ma przypisane przedmioty
            $przedmioty_result = $this->conn->query("
                SELECT kp.*, p.nazwa as przedmiot_nazwa
                FROM klasa_przedmioty kp
                JOIN przedmioty p ON kp.przedmiot_id = p.id
                WHERE kp.klasa_id = $klasa_id
            ");

            if ($przedmioty_result->num_rows === 0) {
                $this->validation_errors[] = "Klasa $klasa_nazwa nie ma przypisanych żadnych przedmiotów";
                continue;
            }

            // Dla każdego przedmiotu sprawdź nauczyciela i salę
            while ($przedmiot = $przedmioty_result->fetch_assoc()) {
                $przedmiot_id = $przedmiot['przedmiot_id'];
                $przedmiot_nazwa = $przedmiot['przedmiot_nazwa'];
                $nauczyciel_id = $przedmiot['nauczyciel_id'];

                // Sprawdź czy przedmiot ma ilosc_godzin_tydzien > 0
                if ($przedmiot['ilosc_godzin_tydzien'] <= 0) {
                    $this->validation_errors[] = "Klasa $klasa_nazwa: przedmiot '$przedmiot_nazwa' ma nieprawidłową ilość godzin tygodniowo";
                    continue;
                }

                // Sprawdź czy nauczyciel istnieje
                $nauczyciel_check = $this->conn->query("
                    SELECT COUNT(*) as cnt FROM nauczyciele WHERE id = $nauczyciel_id
                ");
                $nauczyciel_exists = $nauczyciel_check->fetch_assoc()['cnt'] > 0;

                if (!$nauczyciel_exists) {
                    $this->validation_errors[] = "Klasa $klasa_nazwa: przedmiot '$przedmiot_nazwa' nie ma przypisanego nauczyciela";
                    continue;
                }

                // Sprawdź czy istnieje przynajmniej jedna sala dla tego przedmiotu
                $sala_check = $this->conn->query("
                    SELECT COUNT(*) as cnt
                    FROM sala_przedmioty sp
                    WHERE sp.przedmiot_id = $przedmiot_id
                ");
                $sala_exists = $sala_check->fetch_assoc()['cnt'] > 0;

                // Alternatywnie sprawdź sale dla nauczyciela
                $sala_nauczyciel_check = $this->conn->query("
                    SELECT COUNT(*) as cnt
                    FROM sala_nauczyciele sn
                    WHERE sn.nauczyciel_id = $nauczyciel_id
                ");
                $sala_nauczyciel_exists = $sala_nauczyciel_check->fetch_assoc()['cnt'] > 0;

                // Sprawdź czy istnieje jakakolwiek sala
                $any_sala = $this->conn->query("SELECT COUNT(*) as cnt FROM sale");
                $sala_any_exists = $any_sala->fetch_assoc()['cnt'] > 0;

                if (!$sala_exists && !$sala_nauczyciel_exists && !$sala_any_exists) {
                    $this->validation_errors[] = "Klasa $klasa_nazwa: przedmiot '$przedmiot_nazwa' - brak dostępnych sal";
                }
            }
        }

        return [
            'success' => empty($this->validation_errors),
            'errors' => $this->validation_errors
        ];
    }

    // =====================================================================
    // KROK 2: ZAŁADOWANIE DANYCH
    // =====================================================================

    /**
     * Ładuje wszystkie dane do pamięci
     */
    private function zaladujDane() {
        // Inicjalizacja struktur
        $this->remaining_hours = [];
        $this->teacher_availability = [];
        $this->room_availability = [];
        $this->class_schedule = [];
        $this->teacher_schedule = [];
        $this->occurrences = [];
        $this->daily_count = [];

        // Załaduj klasy i ich przedmioty
        $klasy_result = $this->conn->query("SELECT * FROM klasy ORDER BY nazwa");

        while ($klasa = $klasy_result->fetch_assoc()) {
            $klasa_id = $klasa['id'];

            // Inicjalizacja dla tej klasy
            $this->remaining_hours[$klasa_id] = [];
            $this->class_schedule[$klasa_id] = [];
            $this->occurrences[$klasa_id] = [];
            $this->daily_count[$klasa_id] = [];

            foreach ($this->dni as $dzien) {
                $this->class_schedule[$klasa_id][$dzien] = [];
                $this->occurrences[$klasa_id][$dzien] = [];
                $this->daily_count[$klasa_id][$dzien] = 0;
            }

            // Załaduj przedmioty dla klasy
            $przedmioty_result = $this->conn->query("
                SELECT kp.*, p.czy_rozszerzony, p.nazwa, p.skrot
                FROM klasa_przedmioty kp
                JOIN przedmioty p ON kp.przedmiot_id = p.id
                WHERE kp.klasa_id = $klasa_id
            ");

            while ($przedmiot = $przedmioty_result->fetch_assoc()) {
                $przedmiot_id = $przedmiot['przedmiot_id'];
                $this->remaining_hours[$klasa_id][$przedmiot_id] = [
                    'hours' => intval($przedmiot['ilosc_godzin_tydzien']),
                    'nauczyciel_id' => $przedmiot['nauczyciel_id'],
                    'nazwa' => $przedmiot['nazwa'],
                    'skrot' => $przedmiot['skrot'],
                    'czy_rozszerzony' => $przedmiot['czy_rozszerzony']
                ];
            }
        }

        // Załaduj dostępność sal (na początku wszystkie wolne)
        $sale_result = $this->conn->query("SELECT id FROM sale");
        while ($sala = $sale_result->fetch_assoc()) {
            $sala_id = $sala['id'];
            $this->room_availability[$sala_id] = [];

            foreach ($this->dni as $dzien) {
                $this->room_availability[$sala_id][$dzien] = [];
                for ($i = 1; $i <= 10; $i++) {
                    $this->room_availability[$sala_id][$dzien][$i] = true;
                }
            }
        }

        // Załaduj dostępność nauczycieli (bazując na godzinach pracy i nieobecnościach)
        $nauczyciele_result = $this->conn->query("SELECT id FROM nauczyciele");
        while ($nauczyciel = $nauczyciele_result->fetch_assoc()) {
            $nauczyciel_id = $nauczyciel['id'];
            $this->teacher_availability[$nauczyciel_id] = [];
            $this->teacher_schedule[$nauczyciel_id] = [];

            foreach ($this->dni as $dzien) {
                $this->teacher_availability[$nauczyciel_id][$dzien] = [];
                $this->teacher_schedule[$nauczyciel_id][$dzien] = [];

                for ($i = 1; $i <= 10; $i++) {
                    // Sprawdź dostępność na podstawie godzin pracy
                    $dostepny = sprawdz_dostepnosc_nauczyciela_w_czasie(
                        $nauczyciel_id,
                        $dzien,
                        null,
                        $i,
                        $this->conn
                    );
                    $this->teacher_availability[$nauczyciel_id][$dzien][$i] = $dostepny;
                }
            }
        }
    }

    // =====================================================================
    // KROK 3: HEURYSTYKA - SORTOWANIE PRZEDMIOTÓW
    // =====================================================================

    /**
     * Sortuje przedmioty dla klasy według heurystyki
     * @param int $klasa_id
     * @return array Posortowana lista przedmiotów
     */
    private function sortujPrzedmiotyDlaKlasy($klasa_id) {
        if (!isset($this->remaining_hours[$klasa_id])) {
            return [];
        }

        $przedmioty = [];
        foreach ($this->remaining_hours[$klasa_id] as $przedmiot_id => $data) {
            if ($data['hours'] > 0) {
                // Policz dostępnych nauczycieli dla tego przedmiotu
                $nauczyciel_id = $data['nauczyciel_id'];
                $dostepnosc_count = 0;

                foreach ($this->dni as $dzien) {
                    for ($i = 1; $i <= 10; $i++) {
                        if (isset($this->teacher_availability[$nauczyciel_id][$dzien][$i]) &&
                            $this->teacher_availability[$nauczyciel_id][$dzien][$i] &&
                            !isset($this->teacher_schedule[$nauczyciel_id][$dzien][$i])) {
                            $dostepnosc_count++;
                        }
                    }
                }

                $przedmioty[] = [
                    'przedmiot_id' => $przedmiot_id,
                    'hours' => $data['hours'],
                    'nauczyciel_id' => $nauczyciel_id,
                    'dostepnosc' => $dostepnosc_count,
                    'nazwa' => $data['nazwa'],
                    'skrot' => $data['skrot'],
                    'czy_rozszerzony' => $data['czy_rozszerzony']
                ];
            }
        }

        // Sortuj: najpierw najwięcej godzin, potem najmniej dostępności
        usort($przedmioty, function($a, $b) {
            if ($a['hours'] != $b['hours']) {
                return $b['hours'] - $a['hours']; // DESC
            }
            return $a['dostepnosc'] - $b['dostepnosc']; // ASC (mniej dostępności = wyższy priorytet)
        });

        return $przedmioty;
    }

    // =====================================================================
    // KROK 4: GŁÓWNA PĘTLA PRZYPISYWANIA
    // =====================================================================

    /**
     * Główna pętla przypisująca lekcje do slotów
     */
    private function przypisujLekcje() {
        $this->unassigned_slots = [];

        // Pobierz wszystkie klasy
        $klasy_result = $this->conn->query("SELECT * FROM klasy ORDER BY nazwa");

        while ($klasa = $klasy_result->fetch_assoc()) {
            $klasa_id = $klasa['id'];
            $max_godzin_dziennie = $klasa['ilosc_godzin_dziennie'];

            // Dla każdego dnia
            foreach ($this->dni as $dzien_idx => $dzien) {
                // Dla każdego slotu
                for ($lekcja_nr = 1; $lekcja_nr <= $max_godzin_dziennie; $lekcja_nr++) {
                    // Sprawdź dzienny limit
                    if ($this->daily_count[$klasa_id][$dzien] >= $max_godzin_dziennie) {
                        break; // Przejdź do kolejnego dnia
                    }

                    // Pobierz posortowane przedmioty
                    $przedmioty = $this->sortujPrzedmiotyDlaKlasy($klasa_id);

                    $przydzielono = false;

                    // Iteruj po przedmiotach
                    foreach ($przedmioty as $przedmiot) {
                        $przedmiot_id = $przedmiot['przedmiot_id'];
                        $nauczyciel_id = $przedmiot['nauczyciel_id'];

                        // Pomiń jeśli brak godzin
                        if ($this->remaining_hours[$klasa_id][$przedmiot_id]['hours'] <= 0) {
                            continue;
                        }

                        // Sprawdź limit wystąpień dla family (podstawa + rozszerzenie)
                        $family = $this->getFamilyId($przedmiot_id, $klasa_id);
                        $occurrences_today = isset($this->occurrences[$klasa_id][$dzien][$family])
                            ? $this->occurrences[$klasa_id][$dzien][$family]
                            : 0;

                        if ($occurrences_today >= 2) {
                            continue; // Limit wystąpień osiągnięty
                        }

                        // Sprawdź dostępność nauczyciela
                        if (!$this->czyNauczycielDostepny($nauczyciel_id, $dzien, $lekcja_nr)) {
                            continue;
                        }

                        // Znajdź salę
                        $sala_id = $this->znajdzSale($dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id);

                        if ($sala_id === null) {
                            continue; // Brak dostępnej sali
                        }

                        // PRZYPISZ LEKCJĘ
                        $this->przypiszLekcje($klasa_id, $dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id, $sala_id);
                        $przydzielono = true;
                        break; // Przejdź do kolejnego slotu
                    }

                    // Jeśli nie udało się przypisać - oznacz jako unassigned
                    if (!$przydzielono) {
                        $this->unassigned_slots[] = [
                            'klasa_id' => $klasa_id,
                            'dzien' => $dzien,
                            'lekcja_nr' => $lekcja_nr
                        ];
                    }
                }
            }
        }
    }

    /**
     * Przypisuje lekcję do slotu i aktualizuje wszystkie struktury
     */
    private function przypiszLekcje($klasa_id, $dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id, $sala_id) {
        // Oblicz godziny
        $godziny = $this->obliczGodziny($lekcja_nr);

        // Zapisz w harmonogramie klasy
        $this->class_schedule[$klasa_id][$dzien][$lekcja_nr] = [
            'przedmiot_id' => $przedmiot_id,
            'nauczyciel_id' => $nauczyciel_id,
            'sala_id' => $sala_id,
            'godzina_rozpoczecia' => $godziny['start'],
            'godzina_zakonczenia' => $godziny['koniec']
        ];

        // Zapisz w harmonogramie nauczyciela
        $this->teacher_schedule[$nauczyciel_id][$dzien][$lekcja_nr] = [
            'klasa_id' => $klasa_id,
            'przedmiot_id' => $przedmiot_id,
            'sala_id' => $sala_id
        ];

        // Zaktualizuj dostępność sali
        $this->room_availability[$sala_id][$dzien][$lekcja_nr] = false;

        // Zmniejsz remaining hours
        $this->remaining_hours[$klasa_id][$przedmiot_id]['hours']--;

        // Zwiększ licznik dziennego limitu
        $this->daily_count[$klasa_id][$dzien]++;

        // Zwiększ licznik occurrences dla family
        $family = $this->getFamilyId($przedmiot_id, $klasa_id);
        if (!isset($this->occurrences[$klasa_id][$dzien][$family])) {
            $this->occurrences[$klasa_id][$dzien][$family] = 0;
        }
        $this->occurrences[$klasa_id][$dzien][$family]++;
    }

    /**
     * Określa family ID dla przedmiotu (podstawa + rozszerzenie traktowane jako jedno)
     */
    private function getFamilyId($przedmiot_id, $klasa_id) {
        // Sprawdź czy to przedmiot rozszerzony
        $result = $this->conn->query("
            SELECT p.nazwa, p.czy_rozszerzony
            FROM przedmioty p
            WHERE p.id = $przedmiot_id
        ");

        if ($result && $row = $result->fetch_assoc()) {
            $nazwa = $row['nazwa'];
            $czy_rozszerzony = $row['czy_rozszerzony'];

            // Jeśli rozszerzony, usuń "rozszerzony/rozszerzona/rozszerzone" z nazwy
            if ($czy_rozszerzony) {
                $base_name = preg_replace('/(rozszerzony|rozszerzona|rozszerzone)/i', '', $nazwa);
                $base_name = trim($base_name);
                return 'family_' . md5($base_name);
            }
        }

        // Domyślnie zwróć ID przedmiotu
        return 'subject_' . $przedmiot_id;
    }

    /**
     * Sprawdza czy nauczyciel jest dostępny w danym slocie
     */
    private function czyNauczycielDostepny($nauczyciel_id, $dzien, $lekcja_nr) {
        // Sprawdź godziny pracy
        if (!isset($this->teacher_availability[$nauczyciel_id][$dzien][$lekcja_nr]) ||
            !$this->teacher_availability[$nauczyciel_id][$dzien][$lekcja_nr]) {
            return false;
        }

        // Sprawdź czy nie ma już innej lekcji
        if (isset($this->teacher_schedule[$nauczyciel_id][$dzien][$lekcja_nr])) {
            return false;
        }

        return true;
    }

    /**
     * Znajduje dostępną salę według priorytetów
     */
    private function znajdzSale($dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id) {
        // Priorytet 1: Sala przypisana do przedmiotu I nauczyciela
        $result = $this->conn->query("
            SELECT s.id
            FROM sale s
            INNER JOIN sala_przedmioty sp ON s.id = sp.sala_id
            INNER JOIN sala_nauczyciele sn ON s.id = sn.sala_id
            WHERE sp.przedmiot_id = $przedmiot_id
            AND sn.nauczyciel_id = $nauczyciel_id
            LIMIT 1
        ");

        if ($result && $result->num_rows > 0) {
            $sala = $result->fetch_assoc();
            if ($this->room_availability[$sala['id']][$dzien][$lekcja_nr]) {
                return $sala['id'];
            }
        }

        // Priorytet 2: Sala przypisana do przedmiotu
        $result = $this->conn->query("
            SELECT s.id
            FROM sale s
            INNER JOIN sala_przedmioty sp ON s.id = sp.sala_id
            WHERE sp.przedmiot_id = $przedmiot_id
        ");

        if ($result) {
            while ($sala = $result->fetch_assoc()) {
                if ($this->room_availability[$sala['id']][$dzien][$lekcja_nr]) {
                    return $sala['id'];
                }
            }
        }

        // Priorytet 3: Sala przypisana do nauczyciela
        $result = $this->conn->query("
            SELECT s.id
            FROM sale s
            INNER JOIN sala_nauczyciele sn ON s.id = sn.sala_id
            WHERE sn.nauczyciel_id = $nauczyciel_id
        ");

        if ($result) {
            while ($sala = $result->fetch_assoc()) {
                if ($this->room_availability[$sala['id']][$dzien][$lekcja_nr]) {
                    return $sala['id'];
                }
            }
        }

        // Priorytet 4: Dowolna wolna sala
        foreach ($this->room_availability as $sala_id => $dni) {
            if (isset($dni[$dzien][$lekcja_nr]) && $dni[$dzien][$lekcja_nr]) {
                return $sala_id;
            }
        }

        return null;
    }

    // =====================================================================
    // KROK 5: REPAIR - NAPRAWA PRZEZ SWAP I PRZESUNIĘCIA
    // =====================================================================

    /**
     * Próbuje naprawić nieprzypisane sloty przez swap i przesunięcia
     */
    private function naprawSloty() {
        $max_attempts_per_slot = 200;
        $repaired = 0;

        foreach ($this->unassigned_slots as $slot) {
            $klasa_id = $slot['klasa_id'];
            $dzien = $slot['dzien'];
            $lekcja_nr = $slot['lekcja_nr'];

            $naprawiono = false;

            for ($attempt = 0; $attempt < $max_attempts_per_slot && !$naprawiono; $attempt++) {
                // Strategia 1: Spróbuj ponownie przypisać z pozostałych przedmiotów
                $naprawiono = $this->probaPowtornegoPrzypisania($klasa_id, $dzien, $lekcja_nr);

                if ($naprawiono) {
                    $repaired++;
                    break;
                }

                // Strategia 2: Swap wewnątrz tej samej klasy
                $naprawiono = $this->probaSwapWewnatrzKlasy($klasa_id, $dzien, $lekcja_nr);

                if ($naprawiono) {
                    $repaired++;
                    break;
                }

                // Strategia 3: Swap między klasami
                // (wymagałoby znacznie więcej logiki - pominięte dla uproszczenia)

                // Strategia 4: Przesunięcie lekcji w obrębie dnia
                $naprawiono = $this->probaPrzesuniecia($klasa_id, $dzien, $lekcja_nr);

                if ($naprawiono) {
                    $repaired++;
                    break;
                }
            }
        }

        return $repaired;
    }

    /**
     * Próbuje ponownie przypisać przedmiot do slotu
     */
    private function probaPowtornegoPrzypisania($klasa_id, $dzien, $lekcja_nr) {
        $przedmioty = $this->sortujPrzedmiotyDlaKlasy($klasa_id);

        foreach ($przedmioty as $przedmiot) {
            $przedmiot_id = $przedmiot['przedmiot_id'];
            $nauczyciel_id = $przedmiot['nauczyciel_id'];

            if ($this->remaining_hours[$klasa_id][$przedmiot_id]['hours'] <= 0) {
                continue;
            }

            $family = $this->getFamilyId($przedmiot_id, $klasa_id);
            $occurrences_today = isset($this->occurrences[$klasa_id][$dzien][$family])
                ? $this->occurrences[$klasa_id][$dzien][$family]
                : 0;

            if ($occurrences_today >= 2) {
                continue;
            }

            if (!$this->czyNauczycielDostepny($nauczyciel_id, $dzien, $lekcja_nr)) {
                continue;
            }

            $sala_id = $this->znajdzSale($dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id);

            if ($sala_id !== null) {
                $this->przypiszLekcje($klasa_id, $dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id, $sala_id);
                return true;
            }
        }

        return false;
    }

    /**
     * Próbuje zamienić lekcje w obrębie tej samej klasy
     */
    private function probaSwapWewnatrzKlasy($klasa_id, $dzien_target, $lekcja_nr_target) {
        // Znajdź wszystkie zajęte sloty tej klasy
        foreach ($this->dni as $dzien_source) {
            if (!isset($this->class_schedule[$klasa_id][$dzien_source])) {
                continue;
            }

            foreach ($this->class_schedule[$klasa_id][$dzien_source] as $lekcja_nr_source => $lekcja_data) {
                // Spróbuj zamienić
                if ($this->czyMoznaZamienicSloty($klasa_id, $dzien_source, $lekcja_nr_source, $dzien_target, $lekcja_nr_target)) {
                    $this->zamienSloty($klasa_id, $dzien_source, $lekcja_nr_source, $dzien_target, $lekcja_nr_target);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sprawdza czy można zamienić dwa sloty
     */
    private function czyMoznaZamienicSloty($klasa_id, $dzien1, $lekcja1, $dzien2, $lekcja2) {
        // Slot 2 musi być pusty
        if (isset($this->class_schedule[$klasa_id][$dzien2][$lekcja2])) {
            return false;
        }

        // Pobierz dane lekcji ze slotu 1
        $lekcja_data = $this->class_schedule[$klasa_id][$dzien1][$lekcja1];
        $nauczyciel_id = $lekcja_data['nauczyciel_id'];
        $sala_id = $lekcja_data['sala_id'];

        // Sprawdź dostępność nauczyciela w nowym slocie
        if (!$this->czyNauczycielDostepny($nauczyciel_id, $dzien2, $lekcja2)) {
            return false;
        }

        // Sprawdź dostępność sali w nowym slocie
        if (!$this->room_availability[$sala_id][$dzien2][$lekcja2]) {
            return false;
        }

        return true;
    }

    /**
     * Zamienia dwa sloty miejscami
     */
    private function zamienSloty($klasa_id, $dzien1, $lekcja1, $dzien2, $lekcja2) {
        // Pobierz dane lekcji
        $lekcja_data = $this->class_schedule[$klasa_id][$dzien1][$lekcja1];
        $nauczyciel_id = $lekcja_data['nauczyciel_id'];
        $sala_id = $lekcja_data['sala_id'];
        $przedmiot_id = $lekcja_data['przedmiot_id'];

        // Usuń ze starego slotu
        unset($this->class_schedule[$klasa_id][$dzien1][$lekcja1]);
        unset($this->teacher_schedule[$nauczyciel_id][$dzien1][$lekcja1]);
        $this->room_availability[$sala_id][$dzien1][$lekcja1] = true;
        $this->daily_count[$klasa_id][$dzien1]--;

        $family = $this->getFamilyId($przedmiot_id, $klasa_id);
        if (isset($this->occurrences[$klasa_id][$dzien1][$family])) {
            $this->occurrences[$klasa_id][$dzien1][$family]--;
        }

        // Dodaj do nowego slotu
        $godziny = $this->obliczGodziny($lekcja2);
        $this->class_schedule[$klasa_id][$dzien2][$lekcja2] = [
            'przedmiot_id' => $przedmiot_id,
            'nauczyciel_id' => $nauczyciel_id,
            'sala_id' => $sala_id,
            'godzina_rozpoczecia' => $godziny['start'],
            'godzina_zakonczenia' => $godziny['koniec']
        ];

        $this->teacher_schedule[$nauczyciel_id][$dzien2][$lekcja2] = [
            'klasa_id' => $klasa_id,
            'przedmiot_id' => $przedmiot_id,
            'sala_id' => $sala_id
        ];

        $this->room_availability[$sala_id][$dzien2][$lekcja2] = false;
        $this->daily_count[$klasa_id][$dzien2]++;

        if (!isset($this->occurrences[$klasa_id][$dzien2][$family])) {
            $this->occurrences[$klasa_id][$dzien2][$family] = 0;
        }
        $this->occurrences[$klasa_id][$dzien2][$family]++;
    }

    /**
     * Próbuje przesunąć lekcje w obrębie dnia
     */
    private function probaPrzesuniecia($klasa_id, $dzien, $lekcja_nr_target) {
        // Znajdź wszystkie lekcje w tym dniu
        if (!isset($this->class_schedule[$klasa_id][$dzien])) {
            return false;
        }

        $klasa_result = $this->conn->query("SELECT ilosc_godzin_dziennie FROM klasy WHERE id = $klasa_id");
        $klasa_data = $klasa_result->fetch_assoc();
        $max_godzin = $klasa_data['ilosc_godzin_dziennie'];

        // Spróbuj przesunąć każdą lekcję
        foreach ($this->class_schedule[$klasa_id][$dzien] as $lekcja_nr_source => $lekcja_data) {
            // Znajdź wolny slot w tym samym dniu
            for ($new_slot = 1; $new_slot <= $max_godzin; $new_slot++) {
                if ($new_slot == $lekcja_nr_source) {
                    continue;
                }

                if (!isset($this->class_schedule[$klasa_id][$dzien][$new_slot])) {
                    // Ten slot jest wolny
                    if ($this->czyMoznaZamienicSloty($klasa_id, $dzien, $lekcja_nr_source, $dzien, $new_slot)) {
                        $this->zamienSloty($klasa_id, $dzien, $lekcja_nr_source, $dzien, $new_slot);

                        // Teraz spróbuj przypisać coś do zwolnionego slotu
                        return $this->probaPowtornegoPrzypisania($klasa_id, $dzien, $lekcja_nr_source);
                    }
                }
            }
        }

        return false;
    }

    // =====================================================================
    // KROK 6: ZAPIS DO BAZY DANYCH
    // =====================================================================

    /**
     * Zapisuje wygenerowany plan do bazy danych
     */
    private function zapiszDoBazy($uzytkownik_id = 1) {
        $start_time = microtime(true);

        // Rozpocznij transakcję
        $this->conn->begin_transaction();

        try {
            // Wyczyść stary plan
            $this->conn->query("DELETE FROM plan_lekcji WHERE szablon_tygodniowy = 1");
            $this->conn->query("DELETE FROM plan_dzienny");

            $ilosc_lekcji = 0;

            // Zapisz nowy plan
            foreach ($this->class_schedule as $klasa_id => $dni) {
                foreach ($dni as $dzien => $lekcje) {
                    foreach ($lekcje as $lekcja_nr => $lekcja_data) {
                        $stmt = $this->conn->prepare("
                            INSERT INTO plan_lekcji
                            (klasa_id, dzien_tygodnia, numer_lekcji, godzina_rozpoczecia, godzina_zakonczenia,
                             przedmiot_id, nauczyciel_id, sala_id, szablon_tygodniowy)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ");

                        $stmt->bind_param("isissiii",
                            $klasa_id,
                            $dzien,
                            $lekcja_nr,
                            $lekcja_data['godzina_rozpoczecia'],
                            $lekcja_data['godzina_zakonczenia'],
                            $lekcja_data['przedmiot_id'],
                            $lekcja_data['nauczyciel_id'],
                            $lekcja_data['sala_id']
                        );

                        $stmt->execute();
                        $ilosc_lekcji++;
                    }
                }
            }

            // Generuj plan roczny
            $this->generujPlanRoczny();

            // Zapisz statystyki
            $end_time = microtime(true);
            $czas_trwania = round($end_time - $start_time, 2);

            $status = count($this->unassigned_slots) > 0 ? 'blad' : 'sukces';
            $komunikat = count($this->unassigned_slots) > 0
                ? "Nie udało się przypisać " . count($this->unassigned_slots) . " slotów"
                : null;

            $stmt = $this->conn->prepare("
                INSERT INTO statystyki_generowania
                (uzytkownik_id, typ_generowania, status, czas_trwania_sekundy, ilosc_wygenerowanych_lekcji, komunikat_bledu)
                VALUES (?, 'plan_tygodniowy', ?, ?, ?, ?)
            ");

            $stmt->bind_param("isdis",
                $uzytkownik_id,
                $status,
                $czas_trwania,
                $ilosc_lekcji,
                $komunikat
            );

            $stmt->execute();

            // Commit
            $this->conn->commit();

            return [
                'success' => true,
                'ilosc_lekcji' => $ilosc_lekcji,
                'czas_trwania' => $czas_trwania,
                'unassigned' => count($this->unassigned_slots)
            ];

        } catch (Exception $e) {
            $this->conn->rollback();

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // =====================================================================
    // FUNKCJE POMOCNICZE
    // =====================================================================

    /**
     * Oblicza godziny rozpoczęcia i zakończenia lekcji
     */
    private function obliczGodziny($numer_lekcji) {
        $start_timestamp = strtotime($this->godzina_rozpoczecia);
        $minutes_offset = ($numer_lekcji - 1) * ($this->czas_lekcji + $this->czas_przerwy);

        $start = date('H:i:s', strtotime("+$minutes_offset minutes", $start_timestamp));
        $end = date('H:i:s', strtotime("+" . ($minutes_offset + $this->czas_lekcji) . " minutes", $start_timestamp));

        return ['start' => $start, 'koniec' => $end];
    }

    /**
     * Generuje plan dzienny na cały rok szkolny
     */
    private function generujPlanRoczny() {
        $rok_biezacy = date('Y');
        $rok_nastepny = $rok_biezacy + 1;

        $data_poczatek = "$rok_biezacy-09-01";
        $data_koniec = "$rok_nastepny-06-30";

        // Pobierz szablon planu
        $plan_szablon = $this->conn->query("SELECT * FROM plan_lekcji WHERE szablon_tygodniowy = 1 ORDER BY klasa_id, dzien_tygodnia, numer_lekcji");

        $szablony = [];
        while ($lekcja = $plan_szablon->fetch_assoc()) {
            $szablony[] = $lekcja;
        }

        // Pobierz dni wolne
        $dni_wolne_result = $this->conn->query("SELECT data FROM dni_wolne");
        $dni_wolne = [];
        while ($dzien = $dni_wolne_result->fetch_assoc()) {
            $dni_wolne[] = $dzien['data'];
        }

        // Generuj plan dla każdego dnia roboczego
        $current_date = strtotime($data_poczatek);
        $end_date = strtotime($data_koniec);

        while ($current_date <= $end_date) {
            $date_string = date('Y-m-d', $current_date);
            $day_of_week = date('N', $current_date);

            if ($day_of_week <= 5 && !in_array($date_string, $dni_wolne)) {
                $dzien_nazwa = $this->getDzienNazwa($day_of_week);

                foreach ($szablony as $szablon) {
                    if ($szablon['dzien_tygodnia'] === $dzien_nazwa) {
                        $stmt = $this->conn->prepare("
                            INSERT INTO plan_dzienny
                            (plan_lekcji_id, data, klasa_id, numer_lekcji, godzina_rozpoczecia,
                             godzina_zakonczenia, przedmiot_id, nauczyciel_id, sala_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->bind_param("isiisssii",
                            $szablon['id'],
                            $date_string,
                            $szablon['klasa_id'],
                            $szablon['numer_lekcji'],
                            $szablon['godzina_rozpoczecia'],
                            $szablon['godzina_zakonczenia'],
                            $szablon['przedmiot_id'],
                            $szablon['nauczyciel_id'],
                            $szablon['sala_id']
                        );

                        $stmt->execute();
                    }
                }
            }

            $current_date = strtotime('+1 day', $current_date);
        }
    }

    /**
     * Mapowanie numeru dnia na nazwę
     */
    private function getDzienNazwa($day_number) {
        $mapping = [
            1 => 'poniedzialek',
            2 => 'wtorek',
            3 => 'sroda',
            4 => 'czwartek',
            5 => 'piatek'
        ];
        return $mapping[$day_number] ?? '';
    }

    // =====================================================================
    // GŁÓWNA FUNKCJA GENERUJĄCA
    // =====================================================================

    /**
     * Główna funkcja generująca plan
     * @param int $uzytkownik_id ID użytkownika generującego plan
     * @return array Status generowania
     */
    public function generujPlan($uzytkownik_id = 1) {
        // KROK 1: Walidacja
        $walidacja = $this->walidujDane();
        if (!$walidacja['success']) {
            // Loguj nieudaną próbę generowania
            loguj_aktywnosc(
                $uzytkownik_id,
                'generowanie_planu_blad',
                'Nieudana próba generowania planu - błędy walidacji: ' . count($walidacja['errors']),
                ['errors' => $walidacja['errors']]
            );

            return [
                'success' => false,
                'errors' => $walidacja['errors'],
                'etap' => 'walidacja'
            ];
        }

        // KROK 2: Załadowanie danych
        $this->zaladujDane();

        // KROK 3 & 4: Przypisywanie lekcji (z heurystyką)
        $this->przypisujLekcje();

        // KROK 5: Naprawa
        $repaired = $this->naprawSloty();

        // KROK 6: Zapis
        $wynik_zapisu = $this->zapiszDoBazy($uzytkownik_id);

        // Loguj wynik generowania
        if ($wynik_zapisu['success']) {
            $status_opis = $wynik_zapisu['unassigned'] > 0
                ? "z ostrzeżeniami ({$wynik_zapisu['unassigned']} nieprzypisanych slotów)"
                : "pełny sukces";

            loguj_aktywnosc(
                $uzytkownik_id,
                'generowanie_planu_sukces',
                "Wygenerowano plan lekcji - {$status_opis}",
                [
                    'ilosc_lekcji' => $wynik_zapisu['ilosc_lekcji'],
                    'czas_trwania' => $wynik_zapisu['czas_trwania'],
                    'unassigned' => $wynik_zapisu['unassigned'],
                    'repaired' => $repaired
                ]
            );
        } else {
            loguj_aktywnosc(
                $uzytkownik_id,
                'generowanie_planu_blad',
                'Błąd podczas generowania planu: ' . ($wynik_zapisu['error'] ?? 'Nieznany błąd'),
                ['error' => $wynik_zapisu['error'] ?? 'Nieznany błąd']
            );
        }

        return array_merge($wynik_zapisu, [
            'repaired_slots' => $repaired,
            'validation_errors' => $walidacja['errors']
        ]);
    }
}
?>
