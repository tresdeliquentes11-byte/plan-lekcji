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
    private $family_cache = [];         // Cache dla getFamilyId() - [przedmiot_id] => family_id

    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function walidujDane() {
        $this->validation_errors = [];

        // Pobierz wszystkie klasy
        $klasy_result = $this->conn->query("SELECT * FROM klasy ORDER BY nazwa");

        if (!$klasy_result) {
            $this->validation_errors[] = "Błąd bazy danych: nie udało się pobrać listy klas - " . $this->conn->error;
            return [
                'success' => false,
                'errors' => $this->validation_errors
            ];
        }

        while ($klasa = $klasy_result->fetch_assoc()) {
            $klasa_id = $klasa['id'];
            $klasa_nazwa = $klasa['nazwa'];

            // Sprawdź czy klasa ma przypisane przedmioty - używamy prepared statement
            $stmt = $this->conn->prepare("
                SELECT kp.*, p.nazwa as przedmiot_nazwa
                FROM klasa_przedmioty kp
                JOIN przedmioty p ON kp.przedmiot_id = p.id
                WHERE kp.klasa_id = ?
            ");

            if (!$stmt) {
                $this->validation_errors[] = "Błąd bazy danych dla klasy $klasa_nazwa: " . $this->conn->error;
                continue;
            }

            $stmt->bind_param("i", $klasa_id);

            if (!$stmt->execute()) {
                $this->validation_errors[] = "Błąd wykonania zapytania dla klasy $klasa_nazwa: " . $stmt->error;
                $stmt->close();
                continue;
            }

            $przedmioty_result = $stmt->get_result();

            if ($przedmioty_result->num_rows === 0) {
                $this->validation_errors[] = "Klasa $klasa_nazwa nie ma przypisanych żadnych przedmiotów";
                $stmt->close();
                continue;
            }

            // Dla każdego przedmiotu sprawdź nauczyciela i salę
            while ($przedmiot = $przedmioty_result->fetch_assoc()) {
                $przedmiot_id = $przedmiot['przedmiot_id'];
                $przedmiot_nazwa = $przedmiot['przedmiot_nazwa'];
                $nauczyciel_id = $przedmiot['nauczyciel_id'];

                // Sprawdź czy przedmiot ma ilosc_godzin_tydzien > 0
                if (!isset($przedmiot['ilosc_godzin_tydzien']) || $przedmiot['ilosc_godzin_tydzien'] <= 0) {
                    $this->validation_errors[] = "Klasa $klasa_nazwa: przedmiot '$przedmiot_nazwa' ma nieprawidłową ilość godzin tygodniowo";
                    continue;
                }

                // Sprawdź czy nauczyciel istnieje - używamy prepared statement
                $stmt_nauczyciel = $this->conn->prepare("
                    SELECT COUNT(*) as cnt FROM nauczyciele WHERE id = ?
                ");

                if (!$stmt_nauczyciel) {
                    $this->validation_errors[] = "Błąd bazy danych sprawdzania nauczyciela dla przedmiotu '$przedmiot_nazwa': " . $this->conn->error;
                    continue;
                }

                $stmt_nauczyciel->bind_param("i", $nauczyciel_id);

                if (!$stmt_nauczyciel->execute()) {
                    $this->validation_errors[] = "Błąd wykonania zapytania dla nauczyciela przedmiotu '$przedmiot_nazwa': " . $stmt_nauczyciel->error;
                    $stmt_nauczyciel->close();
                    continue;
                }

                $result_nauczyciel = $stmt_nauczyciel->get_result();
                $row_nauczyciel = $result_nauczyciel->fetch_assoc();
                $nauczyciel_exists = $row_nauczyciel && $row_nauczyciel['cnt'] > 0;
                $stmt_nauczyciel->close();

                if (!$nauczyciel_exists) {
                    $this->validation_errors[] = "Klasa $klasa_nazwa: przedmiot '$przedmiot_nazwa' nie ma przypisanego nauczyciela";
                    continue;
                }

                // Sprawdź czy istnieje przynajmniej jedna sala dla tego przedmiotu
                $stmt_sala = $this->conn->prepare("
                    SELECT COUNT(*) as cnt
                    FROM sala_przedmioty sp
                    WHERE sp.przedmiot_id = ?
                ");

                if (!$stmt_sala) {
                    $this->validation_errors[] = "Błąd bazy danych sprawdzania sali dla przedmiotu '$przedmiot_nazwa': " . $this->conn->error;
                    continue;
                }

                $stmt_sala->bind_param("i", $przedmiot_id);

                if (!$stmt_sala->execute()) {
                    $this->validation_errors[] = "Błąd wykonania zapytania dla sali przedmiotu '$przedmiot_nazwa': " . $stmt_sala->error;
                    $stmt_sala->close();
                    continue;
                }

                $result_sala = $stmt_sala->get_result();
                $row_sala = $result_sala->fetch_assoc();
                $sala_exists = $row_sala && $row_sala['cnt'] > 0;
                $stmt_sala->close();

                // Alternatywnie sprawdź sale dla nauczyciela
                $stmt_sala_n = $this->conn->prepare("
                    SELECT COUNT(*) as cnt
                    FROM sala_nauczyciele sn
                    WHERE sn.nauczyciel_id = ?
                ");

                if (!$stmt_sala_n) {
                    $this->validation_errors[] = "Błąd bazy danych sprawdzania sali nauczyciela dla przedmiotu '$przedmiot_nazwa': " . $this->conn->error;
                    continue;
                }

                $stmt_sala_n->bind_param("i", $nauczyciel_id);

                if (!$stmt_sala_n->execute()) {
                    $this->validation_errors[] = "Błąd wykonania zapytania dla sali nauczyciela przedmiotu '$przedmiot_nazwa': " . $stmt_sala_n->error;
                    $stmt_sala_n->close();
                    continue;
                }

                $result_sala_n = $stmt_sala_n->get_result();
                $row_sala_n = $result_sala_n->fetch_assoc();
                $sala_nauczyciel_exists = $row_sala_n && $row_sala_n['cnt'] > 0;
                $stmt_sala_n->close();

                // Sprawdź czy istnieje jakakolwiek sala
                $any_sala = $this->conn->query("SELECT COUNT(*) as cnt FROM sale");
                if (!$any_sala) {
                    $this->validation_errors[] = "Błąd bazy danych sprawdzania dostępnych sal: " . $this->conn->error;
                    continue;
                }
                $row_any_sala = $any_sala->fetch_assoc();
                $sala_any_exists = $row_any_sala && $row_any_sala['cnt'] > 0;

                if (!$sala_exists && !$sala_nauczyciel_exists && !$sala_any_exists) {
                    $this->validation_errors[] = "Klasa $klasa_nazwa: przedmiot '$przedmiot_nazwa' - brak dostępnych sal";
                }
            }

            $stmt->close();
        }

        return [
            'success' => empty($this->validation_errors),
            'errors' => $this->validation_errors
        ];
    }

    private function zaladujDane() {
        // Inicjalizacja struktur
        $this->remaining_hours = [];
        $this->teacher_availability = [];
        $this->room_availability = [];
        $this->class_schedule = [];
        $this->teacher_schedule = [];
        $this->occurrences = [];
        $this->daily_count = [];
        $this->family_cache = []; // Reset cache przy ładowaniu nowych danych

        // Załaduj klasy i ich przedmioty
        $klasy_result = $this->conn->query("SELECT * FROM klasy ORDER BY nazwa");

        if (!$klasy_result) {
            throw new Exception("Błąd ładowania danych klas: " . $this->conn->error);
        }

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

            // Załaduj przedmioty dla klasy - używamy prepared statement
            $stmt = $this->conn->prepare("
                SELECT kp.*, p.czy_rozszerzony, p.nazwa, p.skrot
                FROM klasa_przedmioty kp
                JOIN przedmioty p ON kp.przedmiot_id = p.id
                WHERE kp.klasa_id = ?
            ");

            if (!$stmt) {
                throw new Exception("Błąd przygotowania zapytania dla przedmiotów klasy: " . $this->conn->error);
            }

            $stmt->bind_param("i", $klasa_id);

            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception("Błąd wykonania zapytania dla przedmiotów klasy ID $klasa_id: " . $stmt->error);
            }

            $przedmioty_result = $stmt->get_result();

            while ($przedmiot = $przedmioty_result->fetch_assoc()) {
                $przedmiot_id = $przedmiot['przedmiot_id'];

                // Walidacja danych przed użyciem
                if (!isset($przedmiot['ilosc_godzin_tydzien'])) {
                    error_log("Brak pola ilosc_godzin_tydzien dla przedmiotu ID $przedmiot_id w klasie ID $klasa_id");
                    continue;
                }

                $this->remaining_hours[$klasa_id][$przedmiot_id] = [
                    'hours' => intval($przedmiot['ilosc_godzin_tydzien']),
                    'nauczyciel_id' => $przedmiot['nauczyciel_id'] ?? null,
                    'nazwa' => $przedmiot['nazwa'] ?? '',
                    'skrot' => $przedmiot['skrot'] ?? '',
                    'czy_rozszerzony' => $przedmiot['czy_rozszerzony'] ?? 0
                ];
            }

            $stmt->close();
        }

        // Załaduj dostępność sal (na początku wszystkie wolne)
        $sale_result = $this->conn->query("SELECT id FROM sale");

        if (!$sale_result) {
            throw new Exception("Błąd ładowania danych sal: " . $this->conn->error);
        }

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

        if (!$nauczyciele_result) {
            throw new Exception("Błąd ładowania danych nauczycieli: " . $this->conn->error);
        }

        while ($nauczyciel = $nauczyciele_result->fetch_assoc()) {
            $nauczyciel_id = $nauczyciel['id'];
            $this->teacher_availability[$nauczyciel_id] = [];
            $this->teacher_schedule[$nauczyciel_id] = [];

            foreach ($this->dni as $dzien) {
                $this->teacher_availability[$nauczyciel_id][$dzien] = [];
                $this->teacher_schedule[$nauczyciel_id][$dzien] = [];

                for ($i = 1; $i <= 10; $i++) {
                    // Sprawdź dostępność na podstawie godzin pracy
                    try {
                        $dostepny = sprawdz_dostepnosc_nauczyciela_w_czasie(
                            $nauczyciel_id,
                            $dzien,
                            null,
                            $i,
                            $this->conn
                        );
                        $this->teacher_availability[$nauczyciel_id][$dzien][$i] = $dostepny;
                    } catch (Exception $e) {
                        error_log("Błąd sprawdzania dostępności nauczyciela ID $nauczyciel_id: " . $e->getMessage());
                        $this->teacher_availability[$nauczyciel_id][$dzien][$i] = false;
                    }
                }
            }
        }
    }

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

        // LOG: Before sorting
        $before_sort = [];
        foreach ($przedmioty as $p) {
            $before_sort[] = "{$p['nazwa']}({$p['hours']}h, avail:{$p['dostepnosc']})";
        }
        error_log("GENERATOR_DEBUG: Before sorting for class $klasa_id: " . implode(', ', $before_sort));

        // Sortuj: najpierw najwięcej godzin, potem najmniej dostępności
        usort($przedmioty, function($a, $b) {
            if ($a['hours'] != $b['hours']) {
                return $b['hours'] - $a['hours']; // DESC
            }
            return $a['dostepnosc'] - $b['dostepnosc']; // ASC (mniej dostępności = wyższy priorytet)
        });

        // LOG: After sorting
        $after_sort = [];
        foreach ($przedmioty as $p) {
            $after_sort[] = "{$p['nazwa']}({$p['hours']}h, avail:{$p['dostepnosc']})";
        }
        error_log("GENERATOR_DEBUG: After sorting for class $klasa_id: " . implode(', ', $after_sort));

        return $przedmioty;
    }

    /**
     * Improved subject sorting with look-ahead strategy and scoring system
     * Evaluates each potential assignment based on multiple factors
     */
    private function sortujPrzedmiotyDlaKlasyZLookAhead($klasa_id, $dzien, $lekcja_nr) {
        if (!isset($this->remaining_hours[$klasa_id])) {
            return [];
        }

        $przedmioty = [];
        foreach ($this->remaining_hours[$klasa_id] as $przedmiot_id => $data) {
            if ($data['hours'] > 0) {
                $nauczyciel_id = $data['nauczyciel_id'];
                
                // Calculate availability score
                $dostepnosc_count = 0;
                foreach ($this->dni as $dz) {
                    for ($i = 1; $i <= 10; $i++) {
                        if (isset($this->teacher_availability[$nauczyciel_id][$dz][$i]) &&
                            $this->teacher_availability[$nauczyciel_id][$dz][$i] &&
                            !isset($this->teacher_schedule[$nauczyciel_id][$dz][$i])) {
                            $dostepnosc_count++;
                        }
                    }
                }

                // Calculate look-ahead score (0-100, higher is better)
                $score = $this->obliczWynikPrzypisania($klasa_id, $przedmiot_id, $nauczyciel_id, $dzien, $lekcja_nr, $data);

                $przedmioty[] = [
                    'przedmiot_id' => $przedmiot_id,
                    'hours' => $data['hours'],
                    'nauczyciel_id' => $nauczyciel_id,
                    'dostepnosc' => $dostepnosc_count,
                    'nazwa' => $data['nazwa'],
                    'skrot' => $data['skrot'],
                    'czy_rozszerzony' => $data['czy_rozszerzony'],
                    'score' => $score
                ];
            }
        }

        // LOG: Before sorting with scores
        $before_sort = [];
        foreach ($przedmioty as $p) {
            $before_sort[] = "{$p['nazwa']}({$p['hours']}h, score:{$p['score']})";
        }
        error_log("GENERATOR_DEBUG: Before scoring for class $klasa_id, day $dzien, hour $lekcja_nr: " . implode(', ', $before_sort));

        // Sort by score (descending), then by hours (descending), then by availability (ascending)
        usort($przedmioty, function($a, $b) {
            if ($a['score'] != $b['score']) {
                return $b['score'] - $a['score']; // Higher score first
            }
            if ($a['hours'] != $b['hours']) {
                return $b['hours'] - $a['hours']; // More hours first
            }
            return $a['dostepnosc'] - $b['dostepnosc']; // Less availability first
        });

        // LOG: After sorting with scores
        $after_sort = [];
        foreach ($przedmioty as $p) {
            $after_sort[] = "{$p['nazwa']}({$p['hours']}h, score:{$p['score']})";
        }
        error_log("GENERATOR_DEBUG: After scoring for class $klasa_id, day $dzien, hour $lekcja_nr: " . implode(', ', $after_sort));

        return $przedmioty;
    }

    /**
     * Calculate assignment score based on multiple factors
     * Higher score = better assignment choice
     */
    private function obliczWynikPrzypisania($klasa_id, $przedmiot_id, $nauczyciel_id, $dzien, $lekcja_nr, $data) {
        $score = 50; // Base score
        
        // Factor 1: Teacher workload balance (20 points)
        $teacher_weekly_load = $this->policzObciazenieNauczyciela($nauczyciel_id);
        if ($teacher_weekly_load < 15) {
            $score += 20; // Teacher has light load
        } elseif ($teacher_weekly_load < 20) {
            $score += 10; // Teacher has moderate load
        } else {
            $score += 0; // Teacher is busy
        }
        
        // Factor 2: Subject distribution across week (15 points)
        $family = $this->getFamilyId($przedmiot_id, $klasa_id);
        $family_weekly_count = $this->policzWystapieniaRodzinyTygodniowo($klasa_id, $family);
        if ($family_weekly_count == 0) {
            $score += 15; // First occurrence of this subject family
        } elseif ($family_weekly_count < 3) {
            $score += 10; // Few occurrences
        } else {
            $score += 5; // Many occurrences
        }
        
        // Factor 3: Time of day preference (10 points)
        if ($lekcja_nr <= 3) {
            // Morning slots are better for difficult subjects
            if ($data['czy_rozszerzony']) {
                $score += 10; // Extended subjects in morning
            } else {
                $score += 5; // Regular subjects in morning
            }
        } elseif ($lekcja_nr >= 6) {
            // Late slots are worse for difficult subjects
            if ($data['czy_rozszerzony']) {
                $score -= 5; // Avoid extended subjects late
            }
        }
        
        // Factor 4: Daily subject variety (5 points)
        $family_today = isset($this->occurrences[$klasa_id][$dzien][$family])
            ? $this->occurrences[$klasa_id][$dzien][$family]
            : 0;
        if ($family_today == 0) {
            $score += 5; // First occurrence today
        } elseif ($family_today >= 2) {
            $score -= 10; // Too many today (will be filtered anyway)
        }
        
        // Ensure score is within 0-100 range
        return max(0, min(100, $score));
    }

    /**
     * Calculate current weekly load for a teacher
     */
    private function policzObciazenieNauczyciela($nauczyciel_id) {
        $count = 0;
        if (isset($this->teacher_schedule[$nauczyciel_id])) {
            foreach ($this->teacher_schedule[$nauczyciel_id] as $dzien => $lekcje) {
                $count += count($lekcje);
            }
        }
        return $count;
    }

    /**
     * Count weekly occurrences of a subject family for a class
     */
    private function policzWystapieniaRodzinyTygodniowo($klasa_id, $family) {
        $count = 0;
        if (isset($this->occurrences[$klasa_id])) {
            foreach ($this->occurrences[$klasa_id] as $dzien => $families) {
                if (isset($families[$family])) {
                    $count += $families[$family];
                }
            }
        }
        return $count;
    }

    private function przypisujLekcje() {
        $this->unassigned_slots = [];
        $total_slots = 0;
        $successful_assignments = 0;
        $failed_teacher_availability = 0;
        $failed_room_availability = 0;
        $failed_subject_limits = 0;

        // LOG: Start of assignment process
        error_log("GENERATOR_DEBUG: Starting improved lesson assignment process with look-ahead strategy");

        // Pobierz wszystkie klasy
        $klasy_result = $this->conn->query("SELECT * FROM klasy ORDER BY nazwa");

        while ($klasa = $klasy_result->fetch_assoc()) {
            $klasa_id = $klasa['id'];
            $max_godzin_dziennie = $klasa['ilosc_godzin_dziennie'];
            
            // LOG: Class processing start
            error_log("GENERATOR_DEBUG: Processing class ID: $klasa_id, max daily hours: $max_godzin_dziennie");

            // Dla każdego dnia
            foreach ($this->dni as $dzien_idx => $dzien) {
                // Dla każdego slotu
                for ($lekcja_nr = 1; $lekcja_nr <= $max_godzin_dziennie; $lekcja_nr++) {
                    $total_slots++;
                    
                    // Sprawdź dzienny limit
                    if ($this->daily_count[$klasa_id][$dzien] >= $max_godzin_dziennie) {
                        break; // Przejdź do kolejnego dnia
                    }

                    // Pobierz posortowane przedmioty z uwzględnieniem look-ahead
                    $przedmioty = $this->sortujPrzedmiotyDlaKlasyZLookAhead($klasa_id, $dzien, $lekcja_nr);
                    
                    // LOG: Slot processing start
                    error_log("GENERATOR_DEBUG: Processing slot - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Available subjects: " . count($przedmioty));

                    $przydzielono = false;
                    $attempts = 0;

                    // Iteruj po przedmiotach z uwzględnieniem oceny
                    foreach ($przedmioty as $przedmiot) {
                        $attempts++;
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
                            $failed_subject_limits++;
                            // LOG: Subject limit reached
                            error_log("GENERATOR_DEBUG: Subject limit reached - Class: $klasa_id, Day: $dzien, Subject: $przedmiot_id, Family: $family, Occurrences: $occurrences_today");
                            continue; // Limit wystąpień osiągnięty
                        }

                        // Sprawdź dostępność nauczyciela
                        if (!$this->czyNauczycielDostepny($nauczyciel_id, $dzien, $lekcja_nr)) {
                            $failed_teacher_availability++;
                            // LOG: Teacher not available
                            error_log("GENERATOR_DEBUG: Teacher not available - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Teacher: $nauczyciel_id");
                            continue;
                        }

                        // Znajdź salę
                        $sala_id = $this->znajdzSale($dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id);

                        if ($sala_id === null) {
                            $failed_room_availability++;
                            // LOG: No room available
                            error_log("GENERATOR_DEBUG: No room available - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Subject: $przedmiot_id, Teacher: $nauczyciel_id");
                            continue; // Brak dostępnej sali
                        }

                        // PRZYPISZ LEKCJĘ
                        $this->przypiszLekcje($klasa_id, $dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id, $sala_id);
                        $przydzielono = true;
                        $successful_assignments++;
                        
                        // LOG: Successful assignment
                        error_log("GENERATOR_DEBUG: Successfully assigned - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Subject: $przedmiot_id, Teacher: $nauczyciel_id, Room: $sala_id, Score: {$przedmiot['score']}, Attempts: $attempts");
                        break; // Przejdź do kolejnego slotu
                    }

                    // Jeśli nie udało się przypisać - oznacz jako unassigned
                    if (!$przydzielono) {
                        $this->unassigned_slots[] = [
                            'klasa_id' => $klasa_id,
                            'dzien' => $dzien,
                            'lekcja_nr' => $lekcja_nr
                        ];
                        
                        // LOG: Failed to assign slot
                        error_log("GENERATOR_DEBUG: Failed to assign slot - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Attempts: $attempts");
                    }
                }
            }
        }
        
        // LOG: Assignment process summary
        error_log("GENERATOR_DEBUG: Assignment process completed - Total slots: $total_slots, Successful: $successful_assignments, Unassigned: " . count($this->unassigned_slots) .
                  ", Failed teacher availability: $failed_teacher_availability, Failed room availability: $failed_room_availability, Failed subject limits: $failed_subject_limits");
    }


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


    private function getFamilyId($przedmiot_id, $klasa_id) {
        // OPTYMALIZACJA: Sprawdź cache
        if (isset($this->family_cache[$przedmiot_id])) {
            return $this->family_cache[$przedmiot_id];
        }

        // Sprawdź czy to przedmiot rozszerzony - używamy prepared statement
        $stmt = $this->conn->prepare("
            SELECT p.nazwa, p.czy_rozszerzony
            FROM przedmioty p
            WHERE p.id = ?
        ");

        if (!$stmt) {
            error_log("Błąd przygotowania zapytania getFamilyId: " . $this->conn->error);
            $family_id = 'subject_' . $przedmiot_id;
            $this->family_cache[$przedmiot_id] = $family_id;
            return $family_id;
        }

        $stmt->bind_param("i", $przedmiot_id);

        if (!$stmt->execute()) {
            error_log("Błąd wykonania zapytania getFamilyId dla przedmiotu ID $przedmiot_id: " . $stmt->error);
            $stmt->close();
            $family_id = 'subject_' . $przedmiot_id;
            $this->family_cache[$przedmiot_id] = $family_id;
            return $family_id;
        }

        $result = $stmt->get_result();
        $family_id = 'subject_' . $przedmiot_id; // Domyślna wartość

        if ($result && $row = $result->fetch_assoc()) {
            $nazwa = $row['nazwa'] ?? '';
            $czy_rozszerzony = $row['czy_rozszerzony'] ?? 0;

            // Jeśli rozszerzony, usuń "rozszerzony/rozszerzona/rozszerzone" z nazwy
            if ($czy_rozszerzony) {
                $base_name = preg_replace('/(rozszerzony|rozszerzona|rozszerzone)/i', '', $nazwa);
                $base_name = trim($base_name);
                $family_id = 'family_' . md5($base_name);
            }
        }

        $stmt->close();

        // Zapisz do cache
        $this->family_cache[$przedmiot_id] = $family_id;
        return $family_id;
    }


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


    private function znajdzSale($dzien, $lekcja_nr, $przedmiot_id, $nauczyciel_id) {
        // Priorytet 1: Sala przypisana do przedmiotu I nauczyciela
        $stmt = $this->conn->prepare("
            SELECT s.id
            FROM sale s
            INNER JOIN sala_przedmioty sp ON s.id = sp.sala_id
            INNER JOIN sala_nauczyciele sn ON s.id = sn.sala_id
            WHERE sp.przedmiot_id = ?
            AND sn.nauczyciel_id = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("ii", $przedmiot_id, $nauczyciel_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $sala = $result->fetch_assoc();
                    if (isset($this->room_availability[$sala['id']][$dzien][$lekcja_nr]) &&
                        $this->room_availability[$sala['id']][$dzien][$lekcja_nr]) {
                        $stmt->close();
                        return $sala['id'];
                    }
                }
            }

            $stmt->close();
        }

        // Priorytet 2: Sala przypisana do przedmiotu
        $stmt = $this->conn->prepare("
            SELECT s.id
            FROM sale s
            INNER JOIN sala_przedmioty sp ON s.id = sp.sala_id
            WHERE sp.przedmiot_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $przedmiot_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result) {
                    while ($sala = $result->fetch_assoc()) {
                        if (isset($this->room_availability[$sala['id']][$dzien][$lekcja_nr]) &&
                            $this->room_availability[$sala['id']][$dzien][$lekcja_nr]) {
                            $stmt->close();
                            return $sala['id'];
                        }
                    }
                }
            }

            $stmt->close();
        }

        // Priorytet 3: Sala przypisana do nauczyciela
        $stmt = $this->conn->prepare("
            SELECT s.id
            FROM sale s
            INNER JOIN sala_nauczyciele sn ON s.id = sn.sala_id
            WHERE sn.nauczyciel_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $nauczyciel_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result) {
                    while ($sala = $result->fetch_assoc()) {
                        if (isset($this->room_availability[$sala['id']][$dzien][$lekcja_nr]) &&
                            $this->room_availability[$sala['id']][$dzien][$lekcja_nr]) {
                            $stmt->close();
                            return $sala['id'];
                        }
                    }
                }
            }

            $stmt->close();
        }

        // Priorytet 4: Dowolna wolna sala
        foreach ($this->room_availability as $sala_id => $dni) {
            if (isset($dni[$dzien][$lekcja_nr]) && $dni[$dzien][$lekcja_nr]) {
                return $sala_id;
            }
        }

        return null;
    }


    private function naprawSloty() {
        $max_attempts_per_slot = 200;
        $repaired = 0;
        $total_unassigned = count($this->unassigned_slots);
        $strategy1_success = 0;
        $strategy2_success = 0;
        $strategy3_success = 0;
        $strategy4_success = 0;
        $total_attempts = 0;

        // LOG: Start repair process
        error_log("GENERATOR_DEBUG: Starting repair process for $total_unassigned unassigned slots");

        foreach ($this->unassigned_slots as $slot) {
            $klasa_id = $slot['klasa_id'];
            $dzien = $slot['dzien'];
            $lekcja_nr = $slot['lekcja_nr'];

            $naprawiono = false;

            for ($attempt = 0; $attempt < $max_attempts_per_slot && !$naprawiono; $attempt++) {
                $total_attempts++;
                
                // Strategia 1: Spróbuj ponownie przypisać z pozostałych przedmiotów
                $naprawiono = $this->probaPowtornegoPrzypisania($klasa_id, $dzien, $lekcja_nr);

                if ($naprawiono) {
                    $repaired++;
                    $strategy1_success++;
                    error_log("GENERATOR_DEBUG: Strategy 1 (reassign) successful - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Attempt: $attempt");
                    break;
                }

                // Strategia 2: Swap wewnątrz tej samej klasy
                $naprawiono = $this->probaSwapWewnatrzKlasy($klasa_id, $dzien, $lekcja_nr);

                if ($naprawiono) {
                    $repaired++;
                    $strategy2_success++;
                    error_log("GENERATOR_DEBUG: Strategy 2 (swap within class) successful - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Attempt: $attempt");
                    break;
                }

                // Strategia 3: Swap między klasami
                $naprawiono = $this->probaSwapMiedzyKlasami($klasa_id, $dzien, $lekcja_nr);

                if ($naprawiono) {
                    $repaired++;
                    $strategy3_success++;
                    error_log("GENERATOR_DEBUG: Strategy 3 (cross-class swap) successful - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Attempt: $attempt");
                    break;
                }

                // Strategia 4: Przesunięcie lekcji w obrębie dnia
                $naprawiono = $this->probaPrzesuniecia($klasa_id, $dzien, $lekcja_nr);

                if ($naprawiono) {
                    $repaired++;
                    $strategy4_success++;
                    error_log("GENERATOR_DEBUG: Strategy 4 (shift within day) successful - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr, Attempt: $attempt");
                    break;
                }
            }
            
            if (!$naprawiono) {
                error_log("GENERATOR_DEBUG: Failed to repair slot after $max_attempts_per_slot attempts - Class: $klasa_id, Day: $dzien, Hour: $lekcja_nr");
            }
        }

        // LOG: Repair process summary
        error_log("GENERATOR_DEBUG: Repair process completed - Total unassigned: $total_unassigned, Repaired: $repaired, Remaining: " . ($total_unassigned - $repaired) .
                  ", Strategy1: $strategy1_success, Strategy2: $strategy2_success, Strategy3: $strategy3_success, Strategy4: $strategy4_success, Total attempts: $total_attempts");

        return $repaired;
    }


    /**
     * Enhanced multi-class swap strategy
     * Attempts to swap lessons between different classes to resolve conflicts
     */
    private function probaSwapMiedzyKlasami($klasa_id_target, $dzien_target, $lekcja_nr_target) {
        // Get all classes
        $klasy_result = $this->conn->query("SELECT id FROM klasy WHERE id != $klasa_id_target");
        
        if (!$klasy_result) {
            return false;
        }
        
        $swap_attempts = 0;
        $max_swap_attempts = 50; // Limit to prevent infinite loops
        
        while ($klasa_source = $klasy_result->fetch_assoc() && $swap_attempts < $max_swap_attempts) {
            $klasa_id_source = $klasa_source['id'];
            $swap_attempts++;
            
            // Try to find a compatible lesson in another class
            foreach ($this->dni as $dzien_source) {
                if (!isset($this->class_schedule[$klasa_id_source][$dzien_source])) {
                    continue;
                }
                
                foreach ($this->class_schedule[$klasa_id_source][$dzien_source] as $lekcja_nr_source => $lekcja_data) {
                    // Check if this lesson can be moved to target slot
                    if ($this->czyMoznaZamienicMiedzyKlasami(
                        $klasa_id_source, $dzien_source, $lekcja_nr_source,
                        $klasa_id_target, $dzien_target, $lekcja_nr_target
                    )) {
                        // Perform cross-class swap
                        $this->zamienSlotyMiedzyKlasami(
                            $klasa_id_source, $dzien_source, $lekcja_nr_source,
                            $klasa_id_target, $dzien_target, $lekcja_nr_target
                        );
                        
                        error_log("GENERATOR_DEBUG: Cross-class swap successful - Source: $klasa_id_source/$dzien_source/$lekcja_nr_source, Target: $klasa_id_target/$dzien_target/$lekcja_nr_target");
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Check if two lessons from different classes can be swapped
     */
    private function czyMoznaZamienicMiedzyKlasami($klasa1_id, $dzien1, $lekcja1, $klasa2_id, $dzien2, $lekcja2) {
        // Target slot must be empty
        if (isset($this->class_schedule[$klasa2_id][$dzien2][$lekcja2])) {
            return false;
        }
        
        // Get source lesson data
        $lekcja1_data = $this->class_schedule[$klasa1_id][$dzien1][$lekcja1];
        $nauczyciel1_id = $lekcja1_data['nauczyciel_id'];
        $sala1_id = $lekcja1_data['sala_id'];
        $przedmiot1_id = $lekcja1_data['przedmiot_id'];
        
        // Check if source teacher is available at target time
        if (!$this->czyNauczycielDostepny($nauczyciel1_id, $dzien2, $lekcja2)) {
            return false;
        }
        
        // Check if room is available at target time
        if (!$this->room_availability[$sala1_id][$dzien2][$lekcja2]) {
            return false;
        }
        
        // Check subject constraints for target class
        $family = $this->getFamilyId($przedmiot1_id, $klasa2_id);
        $occurrences_today = isset($this->occurrences[$klasa2_id][$dzien2][$family])
            ? $this->occurrences[$klasa2_id][$dzien2][$family]
            : 0;
        
        if ($occurrences_today >= 2) {
            return false; // Would violate subject limit
        }
        
        return true;
    }

    /**
     * Perform swap between two different classes
     */
    private function zamienSlotyMiedzyKlasami($klasa1_id, $dzien1, $lekcja1, $klasa2_id, $dzien2, $lekcja2) {
        // Get lesson 1 data
        $lekcja1_data = $this->class_schedule[$klasa1_id][$dzien1][$lekcja1];
        $nauczyciel1_id = $lekcja1_data['nauczyciel_id'];
        $sala1_id = $lekcja1_data['sala_id'];
        $przedmiot1_id = $lekcja1_data['przedmiot_id'];
        
        // Remove lesson 1 from original position
        unset($this->class_schedule[$klasa1_id][$dzien1][$lekcja1]);
        unset($this->teacher_schedule[$nauczyciel1_id][$dzien1][$lekcja1]);
        $this->room_availability[$sala1_id][$dzien1][$lekcja1] = true;
        $this->daily_count[$klasa1_id][$dzien1]--;
        
        $family1 = $this->getFamilyId($przedmiot1_id, $klasa1_id);
        if (isset($this->occurrences[$klasa1_id][$dzien1][$family1])) {
            $this->occurrences[$klasa1_id][$dzien1][$family1]--;
        }
        
        // Add lesson 1 to new position (class 2)
        $godziny = $this->obliczGodziny($lekcja2);
        $this->class_schedule[$klasa2_id][$dzien2][$lekcja2] = [
            'przedmiot_id' => $przedmiot1_id,
            'nauczyciel_id' => $nauczyciel1_id,
            'sala_id' => $sala1_id,
            'godzina_rozpoczecia' => $godziny['start'],
            'godzina_zakonczenia' => $godziny['koniec']
        ];
        
        $this->teacher_schedule[$nauczyciel1_id][$dzien2][$lekcja2] = [
            'klasa_id' => $klasa2_id,
            'przedmiot_id' => $przedmiot1_id,
            'sala_id' => $sala1_id
        ];
        
        $this->room_availability[$sala1_id][$dzien2][$lekcja2] = false;
        $this->daily_count[$klasa2_id][$dzien2]++;
        
        if (!isset($this->occurrences[$klasa2_id][$dzien2][$family1])) {
            $this->occurrences[$klasa2_id][$dzien2][$family1] = 0;
        }
        $this->occurrences[$klasa2_id][$dzien2][$family1]++;
    }

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


    private function probaPrzesuniecia($klasa_id, $dzien, $lekcja_nr_target) {
        // Znajdź wszystkie lekcje w tym dniu
        if (!isset($this->class_schedule[$klasa_id][$dzien])) {
            return false;
        }

        // Używamy prepared statement
        $stmt = $this->conn->prepare("SELECT ilosc_godzin_dziennie FROM klasy WHERE id = ?");

        if (!$stmt) {
            error_log("Błąd przygotowania zapytania probaPrzesuniecia: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("i", $klasa_id);

        if (!$stmt->execute()) {
            error_log("Błąd wykonania zapytania probaPrzesuniecia dla klasy ID $klasa_id: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        $klasa_data = $result->fetch_assoc();
        $stmt->close();

        if (!$klasa_data || !isset($klasa_data['ilosc_godzin_dziennie'])) {
            error_log("Brak danych o ilości godzin dziennie dla klasy ID $klasa_id");
            return false;
        }

        $max_godzin = intval($klasa_data['ilosc_godzin_dziennie']);

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

                        if (!$stmt->execute()) {
                            throw new Exception("Błąd zapisywania lekcji do bazy dla klasy ID $klasa_id, dzień $dzien, lekcja $lekcja_nr: " . $stmt->error);
                        }

                        $stmt->close();
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
            $stmt->close();

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


    private function obliczGodziny($numer_lekcji) {
        $start_timestamp = strtotime($this->godzina_rozpoczecia);

        if ($start_timestamp === false) {
            error_log("Błąd parsowania godziny rozpoczęcia: " . $this->godzina_rozpoczecia);
            // Zwróć wartości domyślne
            return ['start' => '08:00:00', 'koniec' => '08:45:00'];
        }

        $minutes_offset = ($numer_lekcji - 1) * ($this->czas_lekcji + $this->czas_przerwy);

        $start_calc = strtotime("+$minutes_offset minutes", $start_timestamp);
        $end_calc = strtotime("+" . ($minutes_offset + $this->czas_lekcji) . " minutes", $start_timestamp);

        if ($start_calc === false || $end_calc === false) {
            error_log("Błąd obliczania czasu dla lekcji nr $numer_lekcji");
            // Zwróć wartości domyślne
            return ['start' => '08:00:00', 'koniec' => '08:45:00'];
        }

        $start = date('H:i:s', $start_calc);
        $end = date('H:i:s', $end_calc);

        return ['start' => $start, 'koniec' => $end];
    }

    private function generujPlanRoczny() {
        $rok_biezacy = date('Y');
        $rok_nastepny = $rok_biezacy + 1;

        $data_poczatek = "$rok_biezacy-09-01";
        $data_koniec = "$rok_nastepny-06-30";

        // Pobierz szablon planu
        $plan_szablon = $this->conn->query("SELECT * FROM plan_lekcji WHERE szablon_tygodniowy = 1 ORDER BY klasa_id, dzien_tygodnia, numer_lekcji");

        if (!$plan_szablon) {
            throw new Exception("Błąd pobierania szablonu planu: " . $this->conn->error);
        }

        $szablony = [];
        while ($lekcja = $plan_szablon->fetch_assoc()) {
            $szablony[] = $lekcja;
        }

        // Pobierz dni wolne
        $dni_wolne_result = $this->conn->query("SELECT data FROM dni_wolne");

        if (!$dni_wolne_result) {
            throw new Exception("Błąd pobierania dni wolnych: " . $this->conn->error);
        }

        $dni_wolne = [];
        while ($dzien = $dni_wolne_result->fetch_assoc()) {
            $dni_wolne[] = $dzien['data'];
        }

        // Generuj plan dla każdego dnia roboczego
        $current_date = strtotime($data_poczatek);
        $end_date = strtotime($data_koniec);

        if ($current_date === false || $end_date === false) {
            throw new Exception("Błąd parsowania dat dla planu rocznego");
        }

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

                        if (!$stmt) {
                            throw new Exception("Błąd przygotowania zapytania dla planu dziennego: " . $this->conn->error);
                        }

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

                        if (!$stmt->execute()) {
                            throw new Exception("Błąd zapisywania planu dziennego dla daty $date_string: " . $stmt->error);
                        }

                        $stmt->close();
                    }
                }
            }

            $next_date = strtotime('+1 day', $current_date);

            if ($next_date === false) {
                throw new Exception("Błąd obliczania kolejnej daty w planie rocznym");
            }

            $current_date = $next_date;
        }
    }

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
