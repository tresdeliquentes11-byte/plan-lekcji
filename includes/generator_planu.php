<?php
require_once '../includes/config.php';
require_once '../includes/dostepnosc_helpers.php';

class GeneratorPlanu {
    private $conn;
    private $dni = ['poniedzialek', 'wtorek', 'sroda', 'czwartek', 'piatek'];
    private $godzina_rozpoczecia = '08:00';
    private $czas_lekcji = 45; // minuty
    private $czas_przerwy = 10; // minuty
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Główna funkcja generująca plan
    public function generujPlan() {
        // Czyścimy stary plan
        $this->conn->query("DELETE FROM plan_lekcji");
        $this->conn->query("DELETE FROM plan_dzienny");
        
        // Pobieramy wszystkie klasy
        $klasy = $this->conn->query("SELECT * FROM klasy ORDER BY nazwa");
        
        $sukces = true;
        while ($klasa = $klasy->fetch_assoc()) {
            if (!$this->generujPlanDlaKlasy($klasa)) {
                $sukces = false;
            }
        }
        
        if ($sukces) {
            // Generujemy plan dzienny na cały rok
            $this->generujPlanRoczny();
        }
        
        return $sukces;
    }
    
    // Generowanie planu dla konkretnej klasy - NAPRAWIONE
    private function generujPlanDlaKlasy($klasa) {
        $klasa_id = $klasa['id'];
        
        // Pobieramy przedmioty przypisane do klasy
        $przedmioty = $this->conn->query("
            SELECT kp.*, p.nazwa, p.skrot
            FROM klasa_przedmioty kp
            JOIN przedmioty p ON kp.przedmiot_id = p.id
            WHERE kp.klasa_id = $klasa_id
            ORDER BY kp.ilosc_godzin_tydzien DESC
        ");
        
        if ($przedmioty->num_rows === 0) {
            return false;
        }
        
        // Tworzymy listę wszystkich lekcji do rozplanowania - DOKŁADNIE tyle ile w ilosc_godzin_tydzien
        $lekcje_do_rozplanowania = [];
        $suma_godzin = 0;
        
        while ($przedmiot = $przedmioty->fetch_assoc()) {
            $godziny_tego_przedmiotu = intval($przedmiot['ilosc_godzin_tydzien']);
            $suma_godzin += $godziny_tego_przedmiotu;
            
            // Dodajemy DOKŁADNIE tyle lekcji ile jest w ilosc_godzin_tydzien
            for ($i = 0; $i < $godziny_tego_przedmiotu; $i++) {
                $lekcje_do_rozplanowania[] = [
                    'przedmiot_id' => $przedmiot['przedmiot_id'],
                    'nauczyciel_id' => $przedmiot['nauczyciel_id'],
                    'nazwa' => $przedmiot['nazwa'],
                    'skrot' => $przedmiot['skrot'],
                    'numer_w_przedmiocie' => $i + 1,
                    'suma_dla_przedmiotu' => $godziny_tego_przedmiotu
                ];
            }
        }
        
        // Sprawdź czy suma godzin nie przekracza limitu
        $max_godzin_tydzien = 5 * $klasa['ilosc_godzin_dziennie'];
        if ($suma_godzin > $max_godzin_tydzien) {
            error_log("BŁĄD: Klasa {$klasa['nazwa']} ma {$suma_godzin}h przypisanych, ale maksimum to {$max_godzin_tydzien}h");
            return false;
        }
        
        // Inteligentne rozłożenie - rozdzielamy ten sam przedmiot równomiernie
        $lekcje_rozlozone = $this->rozmieszczLekcjeRownomiernie($lekcje_do_rozplanowania);
        
        // Rozplanowujemy lekcje dzień po dniu
        $max_godzin = $klasa['ilosc_godzin_dziennie'];
        $przydzielone_lekcje = 0;
        
        foreach ($this->dni as $dzien_idx => $dzien) {
            for ($lekcja_nr = 1; $lekcja_nr <= $max_godzin; $lekcja_nr++) {
                if (count($lekcje_rozlozone) == 0) {
                    break; // Wszystkie lekcje już przydzielone
                }
                
                $max_proby = 20; // Zwiększone do 20 prób
                $przydzielono = false;
                
                // Próbujemy przydzielić tę lekcję lub znaleźć alternatywę
                for ($proba = 0; $proba < $max_proby; $proba++) {
                    if (count($lekcje_rozlozone) == 0) break;
                    
                    $idx_do_sprawdzenia = $proba % count($lekcje_rozlozone);
                    $lekcja_do_sprawdzenia = $lekcje_rozlozone[$idx_do_sprawdzenia];
                    
                    // Sprawdzamy czy nauczyciel jest dostępny
                    if ($this->sprawdzDostepnoscNauczyciela($lekcja_do_sprawdzenia['nauczyciel_id'], $dzien, $lekcja_nr, $klasa_id)) {
                        // Pobieramy salę
                        $sala_id = $this->przydzielSale($dzien, $lekcja_nr, $klasa_id, $lekcja_do_sprawdzenia['przedmiot_id'], $lekcja_do_sprawdzenia['nauczyciel_id']);
                        
                        // Obliczamy godziny
                        $godziny = $this->obliczGodziny($lekcja_nr);
                        
                        // Dodajemy do planu
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
                            $godziny['start'],
                            $godziny['koniec'],
                            $lekcja_do_sprawdzenia['przedmiot_id'],
                            $lekcja_do_sprawdzenia['nauczyciel_id'],
                            $sala_id
                        );
                        
                        if ($stmt->execute()) {
                            // Usuwamy przydzieloną lekcję z listy
                            array_splice($lekcje_rozlozone, $idx_do_sprawdzenia, 1);
                            $przydzielone_lekcje++;
                            $przydzielono = true;
                            break;
                        }
                    }
                }
                
                if (!$przydzielono && count($lekcje_rozlozone) > 0) {
                    // Jeśli nie udało się przydzielić, przesuń na koniec
                    $lekcja_problematyczna = array_shift($lekcje_rozlozone);
                    array_push($lekcje_rozlozone, $lekcja_problematyczna);
                    error_log("UWAGA: Nie można przydzielić lekcji dla klasy {$klasa['nazwa']}, $dzien, godzina $lekcja_nr");
                }
            }
        }
        
        // FAZA 2: Wypełnij pozostałe okienka jeśli coś zostało
        if (count($lekcje_rozlozone) > 0) {
            error_log("FAZA 2 dla klasy {$klasa['nazwa']}: Próba wypełnienia okienek. Pozostało: " . count($lekcje_rozlozone) . "/{$suma_godzin} lekcji");
            
            // Znajdź wszystkie puste sloty w już wygenerowanym planie
            foreach ($this->dni as $dzien_idx => $dzien) {
                for ($lekcja_nr = 1; $lekcja_nr <= $max_godzin; $lekcja_nr++) {
                    if (count($lekcje_rozlozone) == 0) break 2; // Wszystko przydzielone
                    
                    // Sprawdź czy ten slot jest pusty
                    $sprawdz = $this->conn->query("
                        SELECT COUNT(*) as cnt
                        FROM plan_lekcji
                        WHERE klasa_id = $klasa_id
                        AND dzien_tygodnia = '$dzien'
                        AND numer_lekcji = $lekcja_nr
                        AND szablon_tygodniowy = 1
                    ");
                    $jest_zajety = $sprawdz->fetch_assoc()['cnt'] > 0;
                    
                    if (!$jest_zajety) {
                        // Slot pusty - próbuj wstawić jakąś pozostałą lekcję
                        for ($i = 0; $i < count($lekcje_rozlozone); $i++) {
                            $lekcja = $lekcje_rozlozone[$i];
                            
                            if ($this->sprawdzDostepnoscNauczyciela($lekcja['nauczyciel_id'], $dzien, $lekcja_nr, $klasa_id)) {
                                $sala_id = $this->przydzielSale($dzien, $lekcja_nr, $klasa_id, $lekcja['przedmiot_id'], $lekcja['nauczyciel_id']);
                                $godziny = $this->obliczGodziny($lekcja_nr);
                                
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
                                    $godziny['start'],
                                    $godziny['koniec'],
                                    $lekcja['przedmiot_id'],
                                    $lekcja['nauczyciel_id'],
                                    $sala_id
                                );
                                
                                if ($stmt->execute()) {
                                    array_splice($lekcje_rozlozone, $i, 1);
                                    $przydzielone_lekcje++;
                                    error_log("FAZA 2 SUKCES: Wypełniono okienko $dzien lekcja $lekcja_nr dla klasy {$klasa['nazwa']}");
                                    break; // Przejdź do następnego slotu
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Raport końcowy
        if ($przydzielone_lekcje < $suma_godzin) {
            error_log("UWAGA: Dla klasy {$klasa['nazwa']} przydzielono {$przydzielone_lekcje}/{$suma_godzin} lekcji");
        } else {
            error_log("SUKCES: Dla klasy {$klasa['nazwa']} przydzielono wszystkie {$suma_godzin} lekcji");
        }
        
        return true;
    }
    
    // Inteligentne rozmieszczenie lekcji - rozdziela te same przedmioty
    private function rozmieszczLekcjeRownomiernie($lekcje) {
        // Grupuj lekcje według przedmiotu
        $grupy_przedmiotow = [];
        foreach ($lekcje as $lekcja) {
            $przedmiot_id = $lekcja['przedmiot_id'];
            if (!isset($grupy_przedmiotow[$przedmiot_id])) {
                $grupy_przedmiotow[$przedmiot_id] = [];
            }
            $grupy_przedmiotow[$przedmiot_id][] = $lekcja;
        }
        
        // Sortuj grupy - największe najpierw
        uasort($grupy_przedmiotow, function($a, $b) {
            return count($b) - count($a);
        });
        
        // Rozplanuj równomiernie - "round-robin"
        $wynik = [];
        $max_size = max(array_map('count', $grupy_przedmiotow));
        
        for ($i = 0; $i < $max_size; $i++) {
            foreach ($grupy_przedmiotow as $grupa) {
                if (isset($grupa[$i])) {
                    $wynik[] = $grupa[$i];
                }
            }
        }
        
        return $wynik;
    }
    
    // Sprawdzanie dostępności nauczyciela
    private function sprawdzDostepnoscNauczyciela($nauczyciel_id, $dzien, $lekcja_nr, $aktualna_klasa_id) {
        // KROK 1: Sprawdź czy nauczyciel nie ma innej lekcji w tym samym czasie
        $result = $this->conn->query("
            SELECT COUNT(*) as count
            FROM plan_lekcji
            WHERE nauczyciel_id = $nauczyciel_id
            AND dzien_tygodnia = '$dzien'
            AND numer_lekcji = $lekcja_nr
            AND klasa_id != $aktualna_klasa_id
        ");

        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            return false; // Nauczyciel ma już inną lekcję
        }

        // KROK 2: Sprawdź dostępność nauczyciela w tym czasie (godziny pracy)
        // Dla generowania planu szablon nie ma konkretnej daty, więc przekazujemy NULL
        $jest_dostepny = sprawdz_dostepnosc_nauczyciela_w_czasie(
            $nauczyciel_id,
            $dzien,
            null, // Brak konkretnej daty dla szablonu
            $lekcja_nr,
            $this->conn
        );

        return $jest_dostepny;
    }
    
    // Przydzielanie sali z uwzględnieniem preferencji dla przedmiotu i nauczyciela
    private function przydzielSale($dzien, $lekcja_nr, $klasa_id, $przedmiot_id = null, $nauczyciel_id = null) {
        // Lista zajętych sal w tym terminie
        $zajete_sale_query = "
            SELECT sala_id
            FROM plan_lekcji
            WHERE dzien_tygodnia = '$dzien'
            AND numer_lekcji = $lekcja_nr
            AND sala_id IS NOT NULL
        ";

        // Priorytet 1: Sala przypisana zarówno do przedmiotu JAK I nauczyciela
        if ($przedmiot_id && $nauczyciel_id) {
            $result = $this->conn->query("
                SELECT s.id
                FROM sale s
                INNER JOIN sala_przedmioty sp ON s.id = sp.sala_id
                INNER JOIN sala_nauczyciele sn ON s.id = sn.sala_id
                WHERE sp.przedmiot_id = $przedmiot_id
                AND sn.nauczyciel_id = $nauczyciel_id
                AND s.id NOT IN ($zajete_sale_query)
                LIMIT 1
            ");

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return $row['id'];
            }
        }

        // Priorytet 2: Sala przypisana do przedmiotu
        if ($przedmiot_id) {
            $result = $this->conn->query("
                SELECT s.id
                FROM sale s
                INNER JOIN sala_przedmioty sp ON s.id = sp.sala_id
                WHERE sp.przedmiot_id = $przedmiot_id
                AND s.id NOT IN ($zajete_sale_query)
                LIMIT 1
            ");

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return $row['id'];
            }
        }

        // Priorytet 3: Sala przypisana do nauczyciela
        if ($nauczyciel_id) {
            $result = $this->conn->query("
                SELECT s.id
                FROM sale s
                INNER JOIN sala_nauczyciele sn ON s.id = sn.sala_id
                WHERE sn.nauczyciel_id = $nauczyciel_id
                AND s.id NOT IN ($zajete_sale_query)
                LIMIT 1
            ");

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return $row['id'];
            }
        }

        // Priorytet 4 (fallback): Dowolna wolna sala
        $result = $this->conn->query("
            SELECT s.id
            FROM sale s
            WHERE s.id NOT IN ($zajete_sale_query)
            LIMIT 1
        ");

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['id'];
        }

        return null;
    }
    
    // Obliczanie godzin rozpoczęcia i zakończenia lekcji
    private function obliczGodziny($numer_lekcji) {
        $start_timestamp = strtotime($this->godzina_rozpoczecia);
        
        // Każda lekcja + przerwa to 55 minut (45 + 10)
        $minutes_offset = ($numer_lekcji - 1) * ($this->czas_lekcji + $this->czas_przerwy);
        
        $start = date('H:i:s', strtotime("+$minutes_offset minutes", $start_timestamp));
        $end = date('H:i:s', strtotime("+" . ($minutes_offset + $this->czas_lekcji) . " minutes", $start_timestamp));
        
        return ['start' => $start, 'koniec' => $end];
    }
    
    // Generowanie planu dziennego na cały rok szkolny
    public function generujPlanRoczny() {
        // Rok szkolny: 1 września - 30 czerwca
        $rok_biezacy = date('Y');
        $rok_nastepny = $rok_biezacy + 1;
        
        $data_poczatek = "$rok_biezacy-09-01";
        $data_koniec = "$rok_nastepny-06-30";
        
        // Pobieramy szablon planu
        $plan_szablon = $this->conn->query("SELECT * FROM plan_lekcji ORDER BY klasa_id, dzien_tygodnia, numer_lekcji");
        
        $szablony = [];
        while ($lekcja = $plan_szablon->fetch_assoc()) {
            $szablony[] = $lekcja;
        }
        
        // Pobieramy dni wolne
        $dni_wolne_result = $this->conn->query("SELECT data FROM dni_wolne");
        $dni_wolne = [];
        while ($dzien = $dni_wolne_result->fetch_assoc()) {
            $dni_wolne[] = $dzien['data'];
        }
        
        // Generujemy plan dla każdego dnia roboczego
        $current_date = strtotime($data_poczatek);
        $end_date = strtotime($data_koniec);
        
        while ($current_date <= $end_date) {
            $date_string = date('Y-m-d', $current_date);
            $day_of_week = date('N', $current_date); // 1 = poniedziałek, 7 = niedziela
            
            // Pomijamy weekendy i dni wolne
            if ($day_of_week <= 5 && !in_array($date_string, $dni_wolne)) {
                $dzien_nazwa = $this->getDzienNazwa($day_of_week);
                
                // Dodajemy lekcje z szablonu dla tego dnia
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
        
        return true;
    }
    
    // Mapowanie numeru dnia na nazwę
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
}
?>
