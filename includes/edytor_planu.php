<?php
/**
 * Klasa EdytorPlanu - obsługuje manualne tworzenie i edycję planu lekcji
 *
 * Funkcjonalności:
 * - Dodawanie, edycja, usuwanie, przesuwanie lekcji
 * - Walidacja konfliktów (nauczyciel, sala, klasa, wymiar godzin, dostępność)
 * - Historia zmian z możliwością cofnięcia (undo)
 * - Transakcyjność wszystkich operacji
 */

require_once __DIR__ . '/dostepnosc_helpers.php';
require_once __DIR__ . '/admin_functions.php';

class EdytorPlanu {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Dodaj nową lekcję do planu
     *
     * @param array $dane - klasa_id, data, numer_lekcji, przedmiot_id, nauczyciel_id, sala_id
     * @param int $uzytkownik_id - ID użytkownika wykonującego operację
     * @return array - ['success' => bool, 'message' => string, 'konflikt_id' => int|null, 'plan_id' => int|null]
     */
    public function dodajLekcje($dane, $uzytkownik_id) {
        // Walidacja podstawowa
        $required = ['klasa_id', 'data', 'numer_lekcji', 'przedmiot_id', 'nauczyciel_id'];
        foreach ($required as $field) {
            if (!isset($dane[$field]) || empty($dane[$field])) {
                return ['success' => false, 'message' => "Pole $field jest wymagane"];
            }
        }

        // Sprawdź konflikty PRZED zapisem
        $konflikty = $this->sprawdzKonflikty($dane);
        if ($konflikty['ma_konflikty']) {
            return [
                'success' => false,
                'message' => 'Wykryto konflikty - operacja zablokowana',
                'konflikty' => $konflikty['lista']
            ];
        }

        // Rozpocznij transakcję
        $this->conn->begin_transaction();

        try {
            // Oblicz godziny lekcji
            $czas = oblicz_czas_lekcji($dane['numer_lekcji'], $this->conn);

            // Najpierw utwórz szablon w plan_lekcji
            $dzien_tygodnia = $this->getDzienTygodnia($dane['data']);

            $stmt = $this->conn->prepare("
                INSERT INTO plan_lekcji
                (klasa_id, dzien_tygodnia, numer_lekcji, godzina_rozpoczecia, godzina_zakonczenia,
                 przedmiot_id, nauczyciel_id, sala_id, szablon_tygodniowy)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");

            $sala_id = $dane['sala_id'] ?: null;

            $stmt->bind_param(
                "isiissii",
                $dane['klasa_id'],
                $dzien_tygodnia,
                $dane['numer_lekcji'],
                $czas['start'],
                $czas['koniec'],
                $dane['przedmiot_id'],
                $dane['nauczyciel_id'],
                $sala_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Błąd przy tworzeniu szablonu lekcji: " . $stmt->error);
            }

            $plan_lekcji_id = $this->conn->insert_id;

            // Teraz dodaj do plan_dzienny
            $stmt = $this->conn->prepare("
                INSERT INTO plan_dzienny
                (plan_lekcji_id, data, klasa_id, numer_lekcji, godzina_rozpoczecia, godzina_zakonczenia,
                 przedmiot_id, nauczyciel_id, sala_id, utworzony_recznie, ostatnia_modyfikacja, zmodyfikowany_przez)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)
            ");

            $stmt->bind_param(
                "isiissiiii",
                $plan_lekcji_id,
                $dane['data'],
                $dane['klasa_id'],
                $dane['numer_lekcji'],
                $czas['start'],
                $czas['koniec'],
                $dane['przedmiot_id'],
                $dane['nauczyciel_id'],
                $sala_id,
                $uzytkownik_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Błąd przy dodawaniu lekcji: " . $stmt->error);
            }

            $plan_dzienny_id = $this->conn->insert_id;

            // Zapisz do historii
            $this->zapiszHistorie(
                $plan_dzienny_id,
                'utworzenie',
                null,
                json_encode($dane),
                $uzytkownik_id,
                "Utworzono nową lekcję ręcznie"
            );

            // Loguj operację
            loguj_aktywnosc(
                $uzytkownik_id,
                'dodanie_lekcji_recznie',
                "Dodano lekcję: klasa_id={$dane['klasa_id']}, data={$dane['data']}, numer_lekcji={$dane['numer_lekcji']}",
                ['plan_dzienny_id' => $plan_dzienny_id, 'dane' => $dane]
            );

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Lekcja została dodana pomyślnie',
                'plan_id' => $plan_dzienny_id
            ];

        } catch (Exception $e) {
            $this->conn->rollback();

            return [
                'success' => false,
                'message' => 'Błąd podczas dodawania lekcji: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Edytuj istniejącą lekcję
     *
     * @param int $plan_dzienny_id - ID lekcji do edycji
     * @param array $dane - nowe dane (przedmiot_id, nauczyciel_id, sala_id)
     * @param int $uzytkownik_id - ID użytkownika
     * @return array - ['success' => bool, 'message' => string]
     */
    public function edytujLekcje($plan_dzienny_id, $dane, $uzytkownik_id) {
        // Pobierz obecny stan lekcji
        $stmt = $this->conn->prepare("
            SELECT * FROM plan_dzienny WHERE id = ?
        ");
        $stmt->bind_param("i", $plan_dzienny_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Lekcja nie istnieje'];
        }

        $obecny_stan = $result->fetch_assoc();

        // Przygotuj nowe dane z zachowaniem obecnych wartości
        $nowe_dane = [
            'klasa_id' => $obecny_stan['klasa_id'],
            'data' => $obecny_stan['data'],
            'numer_lekcji' => $obecny_stan['numer_lekcji'],
            'przedmiot_id' => $dane['przedmiot_id'] ?? $obecny_stan['przedmiot_id'],
            'nauczyciel_id' => $dane['nauczyciel_id'] ?? $obecny_stan['nauczyciel_id'],
            'sala_id' => $dane['sala_id'] ?? $obecny_stan['sala_id']
        ];

        // Sprawdź konflikty (z wykluczeniem obecnej lekcji)
        $konflikty = $this->sprawdzKonflikty($nowe_dane, $plan_dzienny_id);
        if ($konflikty['ma_konflikty']) {
            return [
                'success' => false,
                'message' => 'Wykryto konflikty - operacja zablokowana',
                'konflikty' => $konflikty['lista']
            ];
        }

        // Rozpocznij transakcję
        $this->conn->begin_transaction();

        try {
            $sala_id = $nowe_dane['sala_id'] ?: null;

            // Aktualizuj plan_dzienny
            $stmt = $this->conn->prepare("
                UPDATE plan_dzienny
                SET przedmiot_id = ?,
                    nauczyciel_id = ?,
                    sala_id = ?,
                    ostatnia_modyfikacja = NOW(),
                    zmodyfikowany_przez = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "iiiii",
                $nowe_dane['przedmiot_id'],
                $nowe_dane['nauczyciel_id'],
                $sala_id,
                $uzytkownik_id,
                $plan_dzienny_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Błąd przy aktualizacji lekcji: " . $stmt->error);
            }

            // Zaktualizuj również plan_lekcji (szablon)
            $stmt = $this->conn->prepare("
                UPDATE plan_lekcji
                SET przedmiot_id = ?,
                    nauczyciel_id = ?,
                    sala_id = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "iiii",
                $nowe_dane['przedmiot_id'],
                $nowe_dane['nauczyciel_id'],
                $sala_id,
                $obecny_stan['plan_lekcji_id']
            );

            $stmt->execute();

            // Zapisz do historii
            $this->zapiszHistorie(
                $plan_dzienny_id,
                'edycja',
                json_encode($obecny_stan),
                json_encode($nowe_dane),
                $uzytkownik_id,
                "Edytowano lekcję"
            );

            // Loguj operację
            loguj_aktywnosc(
                $uzytkownik_id,
                'edycja_lekcji',
                "Edytowano lekcję ID=$plan_dzienny_id",
                ['przed' => $obecny_stan, 'po' => $nowe_dane]
            );

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Lekcja została zaktualizowana pomyślnie'
            ];

        } catch (Exception $e) {
            $this->conn->rollback();

            return [
                'success' => false,
                'message' => 'Błąd podczas edycji lekcji: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Usuń lekcję
     *
     * @param int $plan_dzienny_id - ID lekcji do usunięcia
     * @param int $uzytkownik_id - ID użytkownika
     * @return array - ['success' => bool, 'message' => string]
     */
    public function usunLekcje($plan_dzienny_id, $uzytkownik_id) {
        // Pobierz dane lekcji przed usunięciem
        $stmt = $this->conn->prepare("SELECT * FROM plan_dzienny WHERE id = ?");
        $stmt->bind_param("i", $plan_dzienny_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Lekcja nie istnieje'];
        }

        $lekcja = $result->fetch_assoc();

        // Rozpocznij transakcję
        $this->conn->begin_transaction();

        try {
            // Zapisz do historii PRZED usunięciem
            $this->zapiszHistorie(
                $plan_dzienny_id,
                'usuniecie',
                json_encode($lekcja),
                null,
                $uzytkownik_id,
                "Usunięto lekcję"
            );

            // Usuń z plan_dzienny (CASCADE usunie też zastępstwa)
            $stmt = $this->conn->prepare("DELETE FROM plan_dzienny WHERE id = ?");
            $stmt->bind_param("i", $plan_dzienny_id);

            if (!$stmt->execute()) {
                throw new Exception("Błąd przy usuwaniu lekcji: " . $stmt->error);
            }

            // Loguj operację
            loguj_aktywnosc(
                $uzytkownik_id,
                'usuniecie_lekcji',
                "Usunięto lekcję ID=$plan_dzienny_id",
                $lekcja
            );

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Lekcja została usunięta pomyślnie'
            ];

        } catch (Exception $e) {
            $this->conn->rollback();

            return [
                'success' => false,
                'message' => 'Błąd podczas usuwania lekcji: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Przenieś lekcję (drag & drop)
     *
     * @param int $plan_dzienny_id - ID lekcji do przeniesienia
     * @param array $nowe_dane - nowa_data, nowy_numer_lekcji
     * @param int $uzytkownik_id - ID użytkownika
     * @return array - ['success' => bool, 'message' => string]
     */
    public function przeniesLekcje($plan_dzienny_id, $nowe_dane, $uzytkownik_id) {
        // Pobierz obecny stan
        $stmt = $this->conn->prepare("SELECT * FROM plan_dzienny WHERE id = ?");
        $stmt->bind_param("i", $plan_dzienny_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Lekcja nie istnieje'];
        }

        $obecny_stan = $result->fetch_assoc();

        // Przygotuj dane do walidacji
        $dane_walidacja = [
            'klasa_id' => $obecny_stan['klasa_id'],
            'data' => $nowe_dane['nowa_data'] ?? $obecny_stan['data'],
            'numer_lekcji' => $nowe_dane['nowy_numer_lekcji'] ?? $obecny_stan['numer_lekcji'],
            'przedmiot_id' => $obecny_stan['przedmiot_id'],
            'nauczyciel_id' => $obecny_stan['nauczyciel_id'],
            'sala_id' => $obecny_stan['sala_id']
        ];

        // Sprawdź konflikty
        $konflikty = $this->sprawdzKonflikty($dane_walidacja, $plan_dzienny_id);
        if ($konflikty['ma_konflikty']) {
            return [
                'success' => false,
                'message' => 'Wykryto konflikty - operacja zablokowana',
                'konflikty' => $konflikty['lista']
            ];
        }

        // Rozpocznij transakcję
        $this->conn->begin_transaction();

        try {
            // Oblicz nowe godziny
            $czas = oblicz_czas_lekcji($dane_walidacja['numer_lekcji'], $this->conn);
            $dzien_tygodnia = $this->getDzienTygodnia($dane_walidacja['data']);

            // Aktualizuj plan_dzienny
            $stmt = $this->conn->prepare("
                UPDATE plan_dzienny
                SET data = ?,
                    numer_lekcji = ?,
                    godzina_rozpoczecia = ?,
                    godzina_zakonczenia = ?,
                    ostatnia_modyfikacja = NOW(),
                    zmodyfikowany_przez = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sissii",
                $dane_walidacja['data'],
                $dane_walidacja['numer_lekcji'],
                $czas['start'],
                $czas['koniec'],
                $uzytkownik_id,
                $plan_dzienny_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Błąd przy przesuwaniu lekcji: " . $stmt->error);
            }

            // Aktualizuj plan_lekcji (szablon)
            $stmt = $this->conn->prepare("
                UPDATE plan_lekcji
                SET dzien_tygodnia = ?,
                    numer_lekcji = ?,
                    godzina_rozpoczecia = ?,
                    godzina_zakonczenia = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sissi",
                $dzien_tygodnia,
                $dane_walidacja['numer_lekcji'],
                $czas['start'],
                $czas['koniec'],
                $obecny_stan['plan_lekcji_id']
            );

            $stmt->execute();

            // Zapisz do historii
            $this->zapiszHistorie(
                $plan_dzienny_id,
                'przesuniecie',
                json_encode($obecny_stan),
                json_encode($dane_walidacja),
                $uzytkownik_id,
                "Przeniesiono lekcję"
            );

            // Loguj operację
            loguj_aktywnosc(
                $uzytkownik_id,
                'przesuniecie_lekcji',
                "Przeniesiono lekcję ID=$plan_dzienny_id",
                ['przed' => $obecny_stan, 'po' => $dane_walidacja]
            );

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Lekcja została przeniesiona pomyślnie'
            ];

        } catch (Exception $e) {
            $this->conn->rollback();

            return [
                'success' => false,
                'message' => 'Błąd podczas przesuwania lekcji: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sprawdź konflikty dla danej lekcji
     *
     * @param array $dane - dane lekcji do sprawdzenia
     * @param int|null $exclude_id - ID lekcji do wykluczenia (przy edycji)
     * @return array - ['ma_konflikty' => bool, 'lista' => array]
     */
    public function sprawdzKonflikty($dane, $exclude_id = null) {
        $konflikty = [];

        // 1. Konflikt nauczyciela (zajęty w tym samym czasie)
        $konflikt_nauczyciela = $this->wykryjKonfliktNauczyciela(
            $dane['nauczyciel_id'],
            $dane['data'],
            $dane['numer_lekcji'],
            $exclude_id
        );
        if ($konflikt_nauczyciela) {
            $konflikty[] = $konflikt_nauczyciela;
        }

        // 2. Konflikt sali
        if (isset($dane['sala_id']) && $dane['sala_id']) {
            $konflikt_sali = $this->wykryjKonfliktSali(
                $dane['sala_id'],
                $dane['data'],
                $dane['numer_lekcji'],
                $exclude_id
            );
            if ($konflikt_sali) {
                $konflikty[] = $konflikt_sali;
            }
        }

        // 3. Konflikt klasy (duplikat lekcji)
        $konflikt_klasy = $this->wykryjKonfliktKlasy(
            $dane['klasa_id'],
            $dane['data'],
            $dane['numer_lekcji'],
            $exclude_id
        );
        if ($konflikt_klasy) {
            $konflikty[] = $konflikt_klasy;
        }

        // 4. Sprawdzenie dostępności nauczyciela (godziny pracy)
        $dzien_tygodnia = $this->getDzienTygodnia($dane['data']);
        $dostepny = sprawdz_dostepnosc_nauczyciela_w_czasie(
            $dane['nauczyciel_id'],
            $dzien_tygodnia,
            $dane['data'],
            $dane['numer_lekcji'],
            $this->conn
        );

        if (!$dostepny) {
            $konflikty[] = [
                'typ' => 'dostepnosc',
                'opis' => 'Nauczyciel nie jest dostępny w tym czasie (brak godzin pracy)',
                'szczegoly' => [
                    'nauczyciel_id' => $dane['nauczyciel_id'],
                    'dzien' => $dzien_tygodnia,
                    'numer_lekcji' => $dane['numer_lekcji']
                ]
            ];
        }

        // 5. Sprawdzenie wymiaru godzin (tygodniowy limit)
        $konflikt_wymiaru = $this->wykryjKonfliktWymiaru(
            $dane['klasa_id'],
            $dane['przedmiot_id'],
            $dane['data'],
            $exclude_id
        );
        if ($konflikt_wymiaru) {
            $konflikty[] = $konflikt_wymiaru;
        }

        return [
            'ma_konflikty' => count($konflikty) > 0,
            'lista' => $konflikty
        ];
    }

    /**
     * Wykryj konflikt nauczyciela
     */
    private function wykryjKonfliktNauczyciela($nauczyciel_id, $data, $numer_lekcji, $exclude_id = null) {
        $sql = "
            SELECT pd.*,
                   k.nazwa as klasa_nazwa,
                   p.nazwa as przedmiot_nazwa,
                   s.numer as sala_numer
            FROM plan_dzienny pd
            LEFT JOIN klasy k ON pd.klasa_id = k.id
            LEFT JOIN przedmioty p ON pd.przedmiot_id = p.id
            LEFT JOIN sale s ON pd.sala_id = s.id
            WHERE pd.nauczyciel_id = ?
            AND pd.data = ?
            AND pd.numer_lekcji = ?
        ";

        if ($exclude_id) {
            $sql .= " AND pd.id != ?";
        }

        $stmt = $this->conn->prepare($sql);

        if ($exclude_id) {
            $stmt->bind_param("isii", $nauczyciel_id, $data, $numer_lekcji, $exclude_id);
        } else {
            $stmt->bind_param("isi", $nauczyciel_id, $data, $numer_lekcji);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $konflikt_dane = $result->fetch_assoc();
            return [
                'typ' => 'nauczyciel',
                'opis' => "Nauczyciel ma już lekcję w tym czasie",
                'szczegoly' => [
                    'plan_dzienny_id' => $konflikt_dane['id'],
                    'klasa' => $konflikt_dane['klasa_nazwa'],
                    'przedmiot' => $konflikt_dane['przedmiot_nazwa'],
                    'sala' => $konflikt_dane['sala_numer']
                ]
            ];
        }

        return null;
    }

    /**
     * Wykryj konflikt sali
     */
    private function wykryjKonfliktSali($sala_id, $data, $numer_lekcji, $exclude_id = null) {
        $sql = "
            SELECT pd.*,
                   k.nazwa as klasa_nazwa,
                   p.nazwa as przedmiot_nazwa,
                   CONCAT(u.imie, ' ', u.nazwisko) as nauczyciel_nazwa
            FROM plan_dzienny pd
            LEFT JOIN klasy k ON pd.klasa_id = k.id
            LEFT JOIN przedmioty p ON pd.przedmiot_id = p.id
            LEFT JOIN nauczyciele n ON pd.nauczyciel_id = n.id
            LEFT JOIN uzytkownicy u ON n.uzytkownik_id = u.id
            WHERE pd.sala_id = ?
            AND pd.data = ?
            AND pd.numer_lekcji = ?
        ";

        if ($exclude_id) {
            $sql .= " AND pd.id != ?";
        }

        $stmt = $this->conn->prepare($sql);

        if ($exclude_id) {
            $stmt->bind_param("isii", $sala_id, $data, $numer_lekcji, $exclude_id);
        } else {
            $stmt->bind_param("isi", $sala_id, $data, $numer_lekcji);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $konflikt_dane = $result->fetch_assoc();
            return [
                'typ' => 'sala',
                'opis' => "Sala jest już zajęta w tym czasie",
                'szczegoly' => [
                    'plan_dzienny_id' => $konflikt_dane['id'],
                    'klasa' => $konflikt_dane['klasa_nazwa'],
                    'przedmiot' => $konflikt_dane['przedmiot_nazwa'],
                    'nauczyciel' => $konflikt_dane['nauczyciel_nazwa']
                ]
            ];
        }

        return null;
    }

    /**
     * Wykryj konflikt klasy (duplikat lekcji)
     */
    private function wykryjKonfliktKlasy($klasa_id, $data, $numer_lekcji, $exclude_id = null) {
        $sql = "
            SELECT pd.*,
                   p.nazwa as przedmiot_nazwa,
                   CONCAT(u.imie, ' ', u.nazwisko) as nauczyciel_nazwa,
                   s.numer as sala_numer
            FROM plan_dzienny pd
            LEFT JOIN przedmioty p ON pd.przedmiot_id = p.id
            LEFT JOIN nauczyciele n ON pd.nauczyciel_id = n.id
            LEFT JOIN uzytkownicy u ON n.uzytkownik_id = u.id
            LEFT JOIN sale s ON pd.sala_id = s.id
            WHERE pd.klasa_id = ?
            AND pd.data = ?
            AND pd.numer_lekcji = ?
        ";

        if ($exclude_id) {
            $sql .= " AND pd.id != ?";
        }

        $stmt = $this->conn->prepare($sql);

        if ($exclude_id) {
            $stmt->bind_param("isii", $klasa_id, $data, $numer_lekcji, $exclude_id);
        } else {
            $stmt->bind_param("isi", $klasa_id, $data, $numer_lekcji);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $konflikt_dane = $result->fetch_assoc();
            return [
                'typ' => 'klasa',
                'opis' => "Klasa ma już lekcję w tym czasie",
                'szczegoly' => [
                    'plan_dzienny_id' => $konflikt_dane['id'],
                    'przedmiot' => $konflikt_dane['przedmiot_nazwa'],
                    'nauczyciel' => $konflikt_dane['nauczyciel_nazwa'],
                    'sala' => $konflikt_dane['sala_numer']
                ]
            ];
        }

        return null;
    }

    /**
     * Wykryj konflikt wymiaru godzin (przekroczenie tygodniowego limitu)
     */
    private function wykryjKonfliktWymiaru($klasa_id, $przedmiot_id, $data, $exclude_id = null) {
        // Pobierz limit godzin dla przedmiotu w tej klasie
        $stmt = $this->conn->prepare("
            SELECT ilosc_godzin_tydzien
            FROM klasa_przedmioty
            WHERE klasa_id = ? AND przedmiot_id = ?
        ");
        $stmt->bind_param("ii", $klasa_id, $przedmiot_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Brak przypisania przedmiotu do klasy
            return [
                'typ' => 'wymiar_godzin',
                'opis' => "Przedmiot nie jest przypisany do tej klasy",
                'szczegoly' => [
                    'klasa_id' => $klasa_id,
                    'przedmiot_id' => $przedmiot_id
                ]
            ];
        }

        $limit = $result->fetch_assoc()['ilosc_godzin_tydzien'];

        // Pobierz początek i koniec tygodnia
        $poczatek_tygodnia = pobierz_poczatek_tygodnia($data);
        $koniec_tygodnia = pobierz_koniec_tygodnia($data);

        // Policz ile lekcji jest już w tym tygodniu
        $sql = "
            SELECT COUNT(*) as suma
            FROM plan_dzienny
            WHERE klasa_id = ?
            AND przedmiot_id = ?
            AND data BETWEEN ? AND ?
        ";

        if ($exclude_id) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->conn->prepare($sql);

        if ($exclude_id) {
            $stmt->bind_param("iissi", $klasa_id, $przedmiot_id, $poczatek_tygodnia, $koniec_tygodnia, $exclude_id);
        } else {
            $stmt->bind_param("iiss", $klasa_id, $przedmiot_id, $poczatek_tygodnia, $koniec_tygodnia);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $suma = $result->fetch_assoc()['suma'];

        // Jeśli dodanie tej lekcji przekroczy limit
        if ($suma >= $limit) {
            return [
                'typ' => 'wymiar_godzin',
                'opis' => "Przekroczono tygodniowy wymiar godzin dla tego przedmiotu",
                'szczegoly' => [
                    'obecna_liczba' => $suma,
                    'limit' => $limit,
                    'tydzien' => "od $poczatek_tygodnia do $koniec_tygodnia"
                ]
            ];
        }

        return null;
    }

    /**
     * Zapisz zmianę do historii
     */
    private function zapiszHistorie($plan_dzienny_id, $typ_zmiany, $stan_przed, $stan_po, $uzytkownik_id, $komentarz = null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $this->conn->prepare("
            INSERT INTO historia_zmian_planu
            (plan_dzienny_id, typ_zmiany, uzytkownik_id, stan_przed, stan_po, ip_address, komentarz)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isissss",
            $plan_dzienny_id,
            $typ_zmiany,
            $uzytkownik_id,
            $stan_przed,
            $stan_po,
            $ip_address,
            $komentarz
        );

        $stmt->execute();

        return $this->conn->insert_id;
    }

    /**
     * Pobierz historię zmian dla lekcji
     */
    public function pobierzHistorie($plan_dzienny_id) {
        $stmt = $this->conn->prepare("
            SELECT h.*,
                   CONCAT(u.imie, ' ', u.nazwisko) as uzytkownik_nazwa
            FROM historia_zmian_planu h
            LEFT JOIN uzytkownicy u ON h.uzytkownik_id = u.id
            WHERE h.plan_dzienny_id = ?
            ORDER BY h.data_zmiany DESC
        ");

        $stmt->bind_param("i", $plan_dzienny_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $historia = [];
        while ($row = $result->fetch_assoc()) {
            $historia[] = $row;
        }

        return $historia;
    }

    /**
     * Cofnij ostatnią zmianę (UNDO)
     */
    public function cofnijOstatniaZmiane($uzytkownik_id) {
        // Pobierz ostatnią zmianę użytkownika
        $stmt = $this->conn->prepare("
            SELECT * FROM historia_zmian_planu
            WHERE uzytkownik_id = ?
            ORDER BY data_zmiany DESC
            LIMIT 1
        ");

        $stmt->bind_param("i", $uzytkownik_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Brak zmian do cofnięcia'];
        }

        $zmiana = $result->fetch_assoc();

        // Rozpocznij transakcję
        $this->conn->begin_transaction();

        try {
            $plan_dzienny_id = $zmiana['plan_dzienny_id'];
            $typ_zmiany = $zmiana['typ_zmiany'];
            $stan_przed = json_decode($zmiana['stan_przed'], true);

            switch ($typ_zmiany) {
                case 'utworzenie':
                    // Cofnij utworzenie = usuń lekcję
                    $stmt = $this->conn->prepare("DELETE FROM plan_dzienny WHERE id = ?");
                    $stmt->bind_param("i", $plan_dzienny_id);
                    $stmt->execute();
                    break;

                case 'edycja':
                case 'przesuniecie':
                    // Przywróć poprzedni stan
                    if ($stan_przed) {
                        $stmt = $this->conn->prepare("
                            UPDATE plan_dzienny
                            SET przedmiot_id = ?,
                                nauczyciel_id = ?,
                                sala_id = ?,
                                data = ?,
                                numer_lekcji = ?,
                                godzina_rozpoczecia = ?,
                                godzina_zakonczenia = ?,
                                ostatnia_modyfikacja = NOW(),
                                zmodyfikowany_przez = ?
                            WHERE id = ?
                        ");

                        $stmt->bind_param(
                            "iiisissi",
                            $stan_przed['przedmiot_id'],
                            $stan_przed['nauczyciel_id'],
                            $stan_przed['sala_id'],
                            $stan_przed['data'],
                            $stan_przed['numer_lekcji'],
                            $stan_przed['godzina_rozpoczecia'],
                            $stan_przed['godzina_zakonczenia'],
                            $uzytkownik_id,
                            $plan_dzienny_id
                        );

                        $stmt->execute();
                    }
                    break;

                case 'usuniecie':
                    // Przywróć usuniętą lekcję
                    if ($stan_przed) {
                        $stmt = $this->conn->prepare("
                            INSERT INTO plan_dzienny
                            (id, plan_lekcji_id, data, klasa_id, numer_lekcji,
                             godzina_rozpoczecia, godzina_zakonczenia,
                             przedmiot_id, nauczyciel_id, sala_id,
                             utworzony_recznie, ostatnia_modyfikacja, zmodyfikowany_przez)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)
                        ");

                        $stmt->bind_param(
                            "iisisssiiii",
                            $stan_przed['id'],
                            $stan_przed['plan_lekcji_id'],
                            $stan_przed['data'],
                            $stan_przed['klasa_id'],
                            $stan_przed['numer_lekcji'],
                            $stan_przed['godzina_rozpoczecia'],
                            $stan_przed['godzina_zakonczenia'],
                            $stan_przed['przedmiot_id'],
                            $stan_przed['nauczyciel_id'],
                            $stan_przed['sala_id'],
                            $uzytkownik_id
                        );

                        $stmt->execute();
                    }
                    break;
            }

            // Usuń wpis z historii (został cofnięty)
            $stmt = $this->conn->prepare("DELETE FROM historia_zmian_planu WHERE id = ?");
            $stmt->bind_param("i", $zmiana['id']);
            $stmt->execute();

            // Loguj cofnięcie
            loguj_aktywnosc(
                $uzytkownik_id,
                'cofniecie_zmiany',
                "Cofnięto zmianę typu: $typ_zmiany",
                $zmiana
            );

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Zmiana została cofnięta pomyślnie'
            ];

        } catch (Exception $e) {
            $this->conn->rollback();

            return [
                'success' => false,
                'message' => 'Błąd podczas cofania zmiany: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pobierz dzień tygodnia z daty
     */
    private function getDzienTygodnia($data) {
        $timestamp = strtotime($data);
        $dzien_nr = date('N', $timestamp); // 1-7 (pon-niedz)

        $mapping = [
            1 => 'poniedzialek',
            2 => 'wtorek',
            3 => 'sroda',
            4 => 'czwartek',
            5 => 'piatek'
        ];

        return $mapping[$dzien_nr] ?? 'poniedzialek';
    }

    /**
     * Zapisz konflikty do bazy danych
     */
    public function zapiszKonflikty($plan_dzienny_id, $konflikty) {
        foreach ($konflikty as $konflikt) {
            $stmt = $this->conn->prepare("
                INSERT INTO konflikty_planu
                (plan_dzienny_id, typ_konfliktu, opis, konflikty_z)
                VALUES (?, ?, ?, ?)
            ");

            $konflikty_z = isset($konflikt['szczegoly']) ? json_encode($konflikt['szczegoly']) : null;

            $stmt->bind_param(
                "isss",
                $plan_dzienny_id,
                $konflikt['typ'],
                $konflikt['opis'],
                $konflikty_z
            );

            $stmt->execute();
        }
    }

    /**
     * Pobierz wszystkie nierozwiązane konflikty
     */
    public function pobierzKonflikty($tylko_nierozwiazane = true) {
        $sql = "
            SELECT k.*,
                   pd.data,
                   pd.numer_lekcji,
                   kl.nazwa as klasa_nazwa,
                   p.nazwa as przedmiot_nazwa
            FROM konflikty_planu k
            LEFT JOIN plan_dzienny pd ON k.plan_dzienny_id = pd.id
            LEFT JOIN klasy kl ON pd.klasa_id = kl.id
            LEFT JOIN przedmioty p ON pd.przedmiot_id = p.id
        ";

        if ($tylko_nierozwiazane) {
            $sql .= " WHERE k.czy_rozwiazany = 0";
        }

        $sql .= " ORDER BY k.data_wykrycia DESC";

        $result = $this->conn->query($sql);

        $konflikty = [];
        while ($row = $result->fetch_assoc()) {
            $konflikty[] = $row;
        }

        return $konflikty;
    }

    /**
     * Oznacz konflikt jako rozwiązany
     */
    public function rozwiazKonflikt($konflikt_id) {
        $stmt = $this->conn->prepare("
            UPDATE konflikty_planu
            SET czy_rozwiazany = 1,
                data_rozwiazania = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param("i", $konflikt_id);
        return $stmt->execute();
    }
}
?>
