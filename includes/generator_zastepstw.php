<?php
require_once '../includes/config.php';
require_once '../includes/dostepnosc_helpers.php';

class GeneratorZastepstw {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Główna funkcja generująca zastępstwa dla nieobecności
    public function generujZastepstwa($nieobecnosc_id) {
        // Pobieramy dane nieobecności
        $stmt = $this->conn->prepare("SELECT * FROM nieobecnosci WHERE id = ?");
        $stmt->bind_param("i", $nieobecnosc_id);
        $stmt->execute();
        $nieobecnosc = $stmt->get_result()->fetch_assoc();
        
        if (!$nieobecnosc) {
            return false;
        }
        
        $nauczyciel_id = $nieobecnosc['nauczyciel_id'];
        $data_od = $nieobecnosc['data_od'];
        $data_do = $nieobecnosc['data_do'];
        
        // Pobieramy wszystkie lekcje nauczyciela w tym okresie
        $lekcje = $this->conn->query("
            SELECT pd.*, p.nazwa as przedmiot_nazwa, k.nazwa as klasa_nazwa
            FROM plan_dzienny pd
            JOIN przedmioty p ON pd.przedmiot_id = p.id
            JOIN klasy k ON pd.klasa_id = k.id
            WHERE pd.nauczyciel_id = $nauczyciel_id
            AND pd.data >= '$data_od'
            AND pd.data <= '$data_do'
            AND pd.czy_zastepstwo = 0
            ORDER BY pd.data, pd.numer_lekcji
        ");
        
        $zastepstwa_utworzone = 0;
        $zastepstwa_niemozliwe = [];
        
        while ($lekcja = $lekcje->fetch_assoc()) {
            // Szukamy nauczyciela zastępującego
            $nauczyciel_zastepujacy = $this->znajdzNauczycielaZastepujacego(
                $lekcja['przedmiot_id'],
                $lekcja['data'],
                $lekcja['numer_lekcji'],
                $nauczyciel_id
            );
            
            if ($nauczyciel_zastepujacy) {
                // Tworzymy zastępstwo
                $this->utworzZastepstwo(
                    $lekcja['id'],
                    $nieobecnosc_id,
                    $nauczyciel_zastepujacy['id']
                );
                
                // Aktualizujemy plan dzienny
                $this->conn->query("
                    UPDATE plan_dzienny
                    SET czy_zastepstwo = 1,
                        oryginalny_nauczyciel_id = $nauczyciel_id,
                        nauczyciel_id = {$nauczyciel_zastepujacy['id']}
                    WHERE id = {$lekcja['id']}
                ");
                
                $zastepstwa_utworzone++;
            } else {
                $zastepstwa_niemozliwe[] = [
                    'data' => $lekcja['data'],
                    'lekcja' => $lekcja['numer_lekcji'],
                    'przedmiot' => $lekcja['przedmiot_nazwa'],
                    'klasa' => $lekcja['klasa_nazwa']
                ];
            }
        }
        
        return [
            'utworzone' => $zastepstwa_utworzone,
            'niemozliwe' => $zastepstwa_niemozliwe
        ];
    }
    
    // Znajdowanie nauczyciela zastępującego
    private function znajdzNauczycielaZastepujacego($przedmiot_id, $data, $numer_lekcji, $nieobecny_nauczyciel_id) {
        // Szukamy nauczycieli, którzy:
        // 1. Mogą uczyć tego przedmiotu
        // 2. Są wolni w tym czasie
        // 3. Nie są nieobecni
        // 4. Są dostępni w tych godzinach (nowe!)

        // Najpierw pobierz dzień tygodnia dla daty
        $timestamp = strtotime($data);
        $dzien_tygodnia_nr = date('N', $timestamp); // 1-7 (poniedziałek-niedziela)
        $dni_mapping = [
            1 => 'poniedzialek',
            2 => 'wtorek',
            3 => 'sroda',
            4 => 'czwartek',
            5 => 'piatek'
        ];
        $dzien_nazwa = $dni_mapping[$dzien_tygodnia_nr] ?? null;

        $result = $this->conn->query("
            SELECT n.id, u.imie, u.nazwisko
            FROM nauczyciele n
            JOIN uzytkownicy u ON n.uzytkownik_id = u.id
            JOIN nauczyciel_przedmioty np ON n.id = np.nauczyciel_id
            WHERE np.przedmiot_id = $przedmiot_id
            AND n.id != $nieobecny_nauczyciel_id
            AND n.id NOT IN (
                -- Nauczyciele zajęci w tym czasie
                SELECT nauczyciel_id
                FROM plan_dzienny
                WHERE data = '$data'
                AND numer_lekcji = $numer_lekcji
            )
            AND n.id NOT IN (
                -- Nauczyciele nieobecni
                SELECT nauczyciel_id
                FROM nieobecnosci
                WHERE '$data' BETWEEN data_od AND data_do
            )
        ");

        // Sprawdź każdego kandydata pod kątem dostępności w godzinach
        while ($nauczyciel = $result->fetch_assoc()) {
            $jest_dostepny = sprawdz_dostepnosc_nauczyciela_w_czasie(
                $nauczyciel['id'],
                $dzien_nazwa,
                $data,
                $numer_lekcji,
                $this->conn
            );

            if ($jest_dostepny) {
                return $nauczyciel; // Znaleziono dostępnego nauczyciela
            }
        }

        return null; // Nie znaleziono żadnego dostępnego nauczyciela
    }
    
    // Utworzenie rekordu zastępstwa
    private function utworzZastepstwo($plan_dzienny_id, $nieobecnosc_id, $nauczyciel_zastepujacy_id) {
        $stmt = $this->conn->prepare("
            INSERT INTO zastepstwa (plan_dzienny_id, nieobecnosc_id, nauczyciel_zastepujacy_id)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $plan_dzienny_id, $nieobecnosc_id, $nauczyciel_zastepujacy_id);
        return $stmt->execute();
    }
    
    // Usuwanie zastępstw dla nieobecności
    public function usunZastepstwa($nieobecnosc_id) {
        // Pobieramy wszystkie zastępstwa
        $zastepstwa = $this->conn->query("
            SELECT * FROM zastepstwa WHERE nieobecnosc_id = $nieobecnosc_id
        ");
        
        while ($zastepstwo = $zastepstwa->fetch_assoc()) {
            // Przywracamy oryginalnego nauczyciela
            $this->conn->query("
                UPDATE plan_dzienny pd
                JOIN zastepstwa z ON pd.id = z.plan_dzienny_id
                SET pd.nauczyciel_id = pd.oryginalny_nauczyciel_id,
                    pd.czy_zastepstwo = 0,
                    pd.oryginalny_nauczyciel_id = NULL
                WHERE z.id = {$zastepstwo['id']}
            ");
        }
        
        // Usuwamy zastępstwa
        $this->conn->query("DELETE FROM zastepstwa WHERE nieobecnosc_id = $nieobecnosc_id");
        
        return true;
    }
}
?>
