<?php
require_once '../includes/config.php';
require_once '../includes/dostepnosc_helpers.php';

class GeneratorZastepstw {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Sprawdzenie czy lekcja jest na początku lub końcu dnia dla klasy
    private function czyLekcjaNaPoczatkuLubKoncuDnia($klasa_id, $data, $numer_lekcji) {
        // Pobierz wszystkie lekcje klasy w tym dniu - używamy prepared statement
        $stmt = $this->conn->prepare("
            SELECT numer_lekcji
            FROM plan_dzienny
            WHERE klasa_id = ?
            AND data = ?
            ORDER BY numer_lekcji
        ");

        if (!$stmt) {
            error_log("czyLekcjaNaPoczatkuLubKoncuDnia: Błąd przygotowania zapytania: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("is", $klasa_id, $data);

        if (!$stmt->execute()) {
            error_log("czyLekcjaNaPoczatkuLubKoncuDnia: Błąd wykonania zapytania: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $lekcje_klasy = $stmt->get_result();
        $stmt->close();

        if ($lekcje_klasy->num_rows == 0) {
            return false; // Brak lekcji w tym dniu
        }

        $numery_lekcji = [];
        while ($row = $lekcje_klasy->fetch_assoc()) {
            $numery_lekcji[] = $row['numer_lekcji'];
        }

        // Sprawdź czy to pierwsza lub ostatnia lekcja
        $pierwsza_lekcja = min($numery_lekcji);
        $ostatnia_lekcja = max($numery_lekcji);

        return ($numer_lekcji == $pierwsza_lekcja || $numer_lekcji == $ostatnia_lekcja);
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

        // Pobieramy wszystkie lekcje nauczyciela w tym okresie - używamy prepared statement
        $stmt_lekcje = $this->conn->prepare("
            SELECT pd.*, p.nazwa as przedmiot_nazwa, k.nazwa as klasa_nazwa
            FROM plan_dzienny pd
            JOIN przedmioty p ON pd.przedmiot_id = p.id
            JOIN klasy k ON pd.klasa_id = k.id
            WHERE pd.nauczyciel_id = ?
            AND pd.data >= ?
            AND pd.data <= ?
            AND pd.czy_zastepstwo = 0
            ORDER BY pd.data, pd.numer_lekcji
        ");

        if (!$stmt_lekcje) {
            error_log("generujZastepstwa: Błąd przygotowania zapytania dla lekcji: " . $this->conn->error);
            return false;
        }

        $stmt_lekcje->bind_param("iss", $nauczyciel_id, $data_od, $data_do);

        if (!$stmt_lekcje->execute()) {
            error_log("generujZastepstwa: Błąd wykonania zapytania dla lekcji: " . $stmt_lekcje->error);
            $stmt_lekcje->close();
            return false;
        }

        $lekcje = $stmt_lekcje->get_result();
        $stmt_lekcje->close();

        $zastepstwa_utworzone = 0;
        $zastepstwa_niemozliwe = [];
        $zastepstwa_pominiete = [];

        while ($lekcja = $lekcje->fetch_assoc()) {
            // Priorytet 1: Szukamy nauczyciela tego samego przedmiotu
            $nauczyciel_zastepujacy = $this->znajdzNauczycielaZastepujacego(
                $lekcja['przedmiot_id'],
                $lekcja['data'],
                $lekcja['numer_lekcji'],
                $nauczyciel_id
            );

            // Priorytet 2: Jeśli nie znaleziono, szukamy nauczyciela innego przedmiotu
            if (!$nauczyciel_zastepujacy) {
                $nauczyciel_zastepujacy = $this->znajdzNauczycielaInnegoPremiotu(
                    $lekcja['klasa_id'],
                    $lekcja['przedmiot_id'],
                    $lekcja['data'],
                    $lekcja['numer_lekcji'],
                    $nauczyciel_id
                );
            }

            if ($nauczyciel_zastepujacy) {
                // Znaleziono nauczyciela - tworzymy zastępstwo dla każdej lekcji (nawet na początku/końcu dnia)
                $this->utworzZastepstwo(
                    $lekcja['id'],
                    $nieobecnosc_id,
                    $nauczyciel_zastepujacy['id']
                );

                // Aktualizujemy plan dzienny - używamy prepared statement
                $stmt_update = $this->conn->prepare("
                    UPDATE plan_dzienny
                    SET czy_zastepstwo = 1,
                        oryginalny_nauczyciel_id = ?,
                        nauczyciel_id = ?
                    WHERE id = ?
                ");

                if ($stmt_update) {
                    $stmt_update->bind_param("iii", $nauczyciel_id, $nauczyciel_zastepujacy['id'], $lekcja['id']);

                    if (!$stmt_update->execute()) {
                        error_log("generujZastepstwa: Błąd aktualizacji planu dziennego: " . $stmt_update->error);
                    }

                    $stmt_update->close();
                } else {
                    error_log("generujZastepstwa: Błąd przygotowania zapytania aktualizacji: " . $this->conn->error);
                }

                $zastepstwa_utworzone++;
            } else {
                // Nie znaleziono nauczyciela - sprawdź czy można pominąć
                if ($this->czyLekcjaNaPoczatkuLubKoncuDnia($lekcja['klasa_id'], $lekcja['data'], $lekcja['numer_lekcji'])) {
                    // Lekcja na początku/końcu dnia - można pominąć (uczniowie przyjdą później/wyjdą wcześniej)
                    $zastepstwa_pominiete[] = [
                        'data' => $lekcja['data'],
                        'lekcja' => $lekcja['numer_lekcji'],
                        'przedmiot' => $lekcja['przedmiot_nazwa'],
                        'klasa' => $lekcja['klasa_nazwa'],
                        'powod' => 'pierwsza_lub_ostatnia_lekcja_brak_nauczyciela'
                    ];
                } else {
                    // Lekcja w środku dnia - oznaczamy jako niemożliwe
                    $zastepstwa_niemozliwe[] = [
                        'data' => $lekcja['data'],
                        'lekcja' => $lekcja['numer_lekcji'],
                        'przedmiot' => $lekcja['przedmiot_nazwa'],
                        'klasa' => $lekcja['klasa_nazwa']
                    ];
                }
            }
        }

        return [
            'utworzone' => $zastepstwa_utworzone,
            'niemozliwe' => $zastepstwa_niemozliwe,
            'pominiete' => $zastepstwa_pominiete
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

        // Używamy prepared statement
        $stmt = $this->conn->prepare("
            SELECT n.id, u.imie, u.nazwisko
            FROM nauczyciele n
            JOIN uzytkownicy u ON n.uzytkownik_id = u.id
            JOIN nauczyciel_przedmioty np ON n.id = np.nauczyciel_id
            WHERE np.przedmiot_id = ?
            AND n.id != ?
            AND n.id NOT IN (
                -- Nauczyciele zajęci w tym czasie
                SELECT nauczyciel_id
                FROM plan_dzienny
                WHERE data = ?
                AND numer_lekcji = ?
            )
            AND n.id NOT IN (
                -- Nauczyciele nieobecni
                SELECT nauczyciel_id
                FROM nieobecnosci
                WHERE ? BETWEEN data_od AND data_do
            )
        ");

        if (!$stmt) {
            error_log("znajdzNauczycielaZastepujacego: Błąd przygotowania zapytania: " . $this->conn->error);
            return null;
        }

        $stmt->bind_param("iisis", $przedmiot_id, $nieobecny_nauczyciel_id, $data, $numer_lekcji, $data);

        if (!$stmt->execute()) {
            error_log("znajdzNauczycielaZastepujacego: Błąd wykonania zapytania: " . $stmt->error);
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $stmt->close();

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

    // Znajdowanie nauczyciela innego przedmiotu (fallback)
    private function znajdzNauczycielaInnegoPremiotu($klasa_id, $oryginalny_przedmiot_id, $data, $numer_lekcji, $nieobecny_nauczyciel_id) {
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

        // Szukamy nauczycieli, którzy:
        // 1. Uczą INNY przedmiot niż oryginalny
        // 2. Uczą tę klasę (są w klasa_przedmioty dla tej klasy)
        // 3. Są wolni w tym czasie
        // 4. Nie są nieobecni
        // 5. Są dostępni w tych godzinach

        // Używamy prepared statement
        $stmt = $this->conn->prepare("
            SELECT DISTINCT n.id, u.imie, u.nazwisko, p.nazwa as przedmiot_nazwa
            FROM nauczyciele n
            JOIN uzytkownicy u ON n.uzytkownik_id = u.id
            JOIN klasa_przedmioty kp ON n.id = kp.nauczyciel_id
            JOIN przedmioty p ON kp.przedmiot_id = p.id
            WHERE kp.klasa_id = ?
            AND kp.przedmiot_id != ?
            AND n.id != ?
            AND n.id NOT IN (
                -- Nauczyciele zajęci w tym czasie
                SELECT nauczyciel_id
                FROM plan_dzienny
                WHERE data = ?
                AND numer_lekcji = ?
            )
            AND n.id NOT IN (
                -- Nauczyciele nieobecni
                SELECT nauczyciel_id
                FROM nieobecnosci
                WHERE ? BETWEEN data_od AND data_do
            )
        ");

        if (!$stmt) {
            error_log("znajdzNauczycielaInnegoPremiotu: Błąd przygotowania zapytania: " . $this->conn->error);
            return null;
        }

        $stmt->bind_param("iiiisi", $klasa_id, $oryginalny_przedmiot_id, $nieobecny_nauczyciel_id, $data, $numer_lekcji, $data);

        if (!$stmt->execute()) {
            error_log("znajdzNauczycielaInnegoPremiotu: Błąd wykonania zapytania: " . $stmt->error);
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $stmt->close();

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
        // Pobieramy wszystkie zastępstwa - używamy prepared statement
        $stmt = $this->conn->prepare("
            SELECT * FROM zastepstwa WHERE nieobecnosc_id = ?
        ");

        if (!$stmt) {
            error_log("usunZastepstwa: Błąd przygotowania zapytania pobierania zastępstw: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("i", $nieobecnosc_id);

        if (!$stmt->execute()) {
            error_log("usunZastepstwa: Błąd wykonania zapytania pobierania zastępstw: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $zastepstwa = $stmt->get_result();
        $stmt->close();

        while ($zastepstwo = $zastepstwa->fetch_assoc()) {
            // Przywracamy oryginalnego nauczyciela - używamy prepared statement
            $stmt_update = $this->conn->prepare("
                UPDATE plan_dzienny pd
                JOIN zastepstwa z ON pd.id = z.plan_dzienny_id
                SET pd.nauczyciel_id = pd.oryginalny_nauczyciel_id,
                    pd.czy_zastepstwo = 0,
                    pd.oryginalny_nauczyciel_id = NULL
                WHERE z.id = ?
            ");

            if ($stmt_update) {
                $stmt_update->bind_param("i", $zastepstwo['id']);

                if (!$stmt_update->execute()) {
                    error_log("usunZastepstwa: Błąd przywracania oryginalnego nauczyciela dla zastępstwa ID " . $zastepstwo['id'] . ": " . $stmt_update->error);
                }

                $stmt_update->close();
            } else {
                error_log("usunZastepstwa: Błąd przygotowania zapytania aktualizacji: " . $this->conn->error);
            }
        }

        // Usuwamy zastępstwa - używamy prepared statement
        $stmt_delete = $this->conn->prepare("DELETE FROM zastepstwa WHERE nieobecnosc_id = ?");

        if (!$stmt_delete) {
            error_log("usunZastepstwa: Błąd przygotowania zapytania usuwania: " . $this->conn->error);
            return false;
        }

        $stmt_delete->bind_param("i", $nieobecnosc_id);

        if (!$stmt_delete->execute()) {
            error_log("usunZastepstwa: Błąd usuwania zastępstw: " . $stmt_delete->error);
            $stmt_delete->close();
            return false;
        }

        $stmt_delete->close();

        return true;
    }
}
?>
