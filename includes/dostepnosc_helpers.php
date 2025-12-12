<?php
/*
 * © 2025 TresDeliquentes. All rights reserved.
 * LibreLessons jest licencjonowane na zasadach TEUL – do użytku edukacyjnego.
 * Zakazana jest dystrybucja, publikacja i komercyjne wykorzystanie bez zgody autora.
 * Korzystając z kodu, akceptujesz warunki licencji (LICENSE.md).
 */
function oblicz_czas_lekcji($numer_lekcji, $conn) {
    // Walidacja numeru lekcji
    if (!is_numeric($numer_lekcji) || $numer_lekcji < 1 || $numer_lekcji > 10) {
        error_log("oblicz_czas_lekcji: Nieprawidłowy numer lekcji: " . $numer_lekcji);
        return [
            'start' => '08:00:00',
            'koniec' => '08:45:00'
        ];
    }

    // Pobierz ustawienia
    $ustawienia = pobierz_ustawienia_czasu($conn);

    if (!is_array($ustawienia) || !isset($ustawienia['godzina_rozpoczecia'], $ustawienia['dlugosc_lekcji'], $ustawienia['przerwy'])) {
        error_log("oblicz_czas_lekcji: Nieprawidłowe ustawienia czasu");
        return [
            'start' => '08:00:00',
            'koniec' => '08:45:00'
        ];
    }

    $godzina_start = $ustawienia['godzina_rozpoczecia'];
    $dlugosc_lekcji = $ustawienia['dlugosc_lekcji'];
    $przerwy = $ustawienia['przerwy'];

    // Walidacja długości lekcji
    if (!is_numeric($dlugosc_lekcji) || $dlugosc_lekcji <= 0) {
        error_log("oblicz_czas_lekcji: Nieprawidłowa długość lekcji: " . $dlugosc_lekcji);
        $dlugosc_lekcji = 45; // Wartość domyślna
    }

    // Oblicz czas rozpoczęcia
    $start_timestamp = strtotime($godzina_start);

    if ($start_timestamp === false) {
        error_log("oblicz_czas_lekcji: Nie udało się sparsować godziny rozpoczęcia: " . $godzina_start);
        return [
            'start' => '08:00:00',
            'koniec' => '08:45:00'
        ];
    }

    // Dodaj czas poprzednich lekcji i przerw
    for ($i = 1; $i < $numer_lekcji; $i++) {
        $start_timestamp += $dlugosc_lekcji * 60;
        if (isset($przerwy[$i]) && is_numeric($przerwy[$i])) {
            $start_timestamp += $przerwy[$i] * 60;
        }
    }

    $koniec_timestamp = $start_timestamp + ($dlugosc_lekcji * 60);

    return [
        'start' => date('H:i:s', $start_timestamp),
        'koniec' => date('H:i:s', $koniec_timestamp)
    ];
}

/**
 * Pobiera ustawienia czasu z bazy danych
 * ZOPTYMALIZOWANE: jedno zapytanie zamiast wielu
 *
 * @param mysqli $conn Połączenie z bazą danych
 * @return array
 */
function pobierz_ustawienia_czasu($conn) {
    // Cache statyczny - unikamy wielokrotnych zapytań w tej samej sesji
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // Wartości domyślne
    $domyslne = [
        'godzina_rozpoczecia' => '08:00',
        'dlugosc_lekcji' => 45,
        'przerwy' => [1 => 10, 2 => 10, 3 => 15, 4 => 10, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10]
    ];

    // Sprawdź czy tabela istnieje
    $check_table = $conn->query("SHOW TABLES LIKE 'ustawienia_planu'");

    if (!$check_table || $check_table->num_rows == 0) {
        if (!$check_table) {
            error_log("pobierz_ustawienia_czasu: Błąd sprawdzania istnienia tabeli: " . $conn->error);
        }
        $cache = $domyslne;
        return $cache;
    }

    // OPTYMALIZACJA: Pobierz wszystkie ustawienia jednym zapytaniem
    $result = $conn->query("SELECT nazwa, wartosc FROM ustawienia_planu");

    if (!$result) {
        error_log("pobierz_ustawienia_czasu: Błąd pobierania ustawień: " . $conn->error);
        $cache = $domyslne;
        return $cache;
    }

    // Wczytaj wszystkie ustawienia do tablicy asocjacyjnej
    $ustawienia_raw = [];
    while ($row = $result->fetch_assoc()) {
        $ustawienia_raw[$row['nazwa']] = $row['wartosc'];
    }

    // Parsuj wartości z walidacją
    $godzina_rozpoczecia = $ustawienia_raw['godzina_rozpoczecia'] ?? '08:00';

    $dlugosc_lekcji = intval($ustawienia_raw['dlugosc_lekcji'] ?? 45);
    $dlugosc_lekcji = ($dlugosc_lekcji > 0) ? $dlugosc_lekcji : 45;

    $liczba_lekcji = intval($ustawienia_raw['liczba_lekcji'] ?? 8);
    $liczba_lekcji = ($liczba_lekcji > 0 && $liczba_lekcji <= 10) ? $liczba_lekcji : 8;

    // Parsuj przerwy
    $przerwy = [];
    for ($i = 1; $i < $liczba_lekcji; $i++) {
        $nazwa_przerwy = "przerwa_po_$i";
        if (isset($ustawienia_raw[$nazwa_przerwy])) {
            $wartosc = intval($ustawienia_raw[$nazwa_przerwy]);
            $przerwy[$i] = ($wartosc >= 0) ? $wartosc : 10;
        } else {
            $przerwy[$i] = ($i == 3) ? 15 : 10; // Domyślne wartości
        }
    }

    $cache = [
        'godzina_rozpoczecia' => $godzina_rozpoczecia,
        'dlugosc_lekcji' => $dlugosc_lekcji,
        'przerwy' => $przerwy
    ];

    return $cache;
}

/**
 * Sprawdza czy nauczyciel jest dostępny (ma godziny pracy) w danym czasie
 *
 * NOWA LOGIKA: Jeśli nauczyciel NIE ma ustawionych godzin pracy = jest NIEDOSTĘPNY
 *
 * @param int $nauczyciel_id ID nauczyciela
 * @param string $dzien_tygodnia Nazwa dnia ('poniedzialek', 'wtorek', etc.)
 * @param string $data Data w formacie YYYY-MM-DD (opcjonalnie, nie używane w nowym modelu)
 * @param int $numer_lekcji Numer lekcji
 * @param mysqli $conn Połączenie z bazą danych
 * @return bool True jeśli nauczyciel jest dostępny
 */
function sprawdz_dostepnosc_nauczyciela_w_czasie($nauczyciel_id, $dzien_tygodnia, $data, $numer_lekcji, $conn) {
    // Sprawdź czy tabela godzin pracy istnieje
    $check_table = $conn->query("SHOW TABLES LIKE 'nauczyciel_godziny_pracy'");
    if ($check_table->num_rows == 0) {
        // Jeśli tabeli nie ma, zakładamy że wszyscy są niedostępni
        // (wymaga konfiguracji godzin pracy)
        return false;
    }

    // Mapowanie nazwy dnia na numer
    $dni_mapping = [
        'poniedzialek' => 1,
        'wtorek' => 2,
        'sroda' => 3,
        'czwartek' => 4,
        'piatek' => 5
    ];
    $dzien_nr = isset($dni_mapping[$dzien_tygodnia]) ? $dni_mapping[$dzien_tygodnia] : null;

    if (!$dzien_nr) {
        return false; // Nieprawidłowy dzień tygodnia
    }

    // Oblicz rzeczywisty czas lekcji
    $czas_lekcji = oblicz_czas_lekcji($numer_lekcji, $conn);
    $lekcja_start = $czas_lekcji['start'];
    $lekcja_koniec = $czas_lekcji['koniec'];

    // Sprawdź czy nauczyciel ma godziny pracy dla tego dnia
    $stmt = $conn->prepare("
        SELECT godzina_od, godzina_do
        FROM nauczyciel_godziny_pracy
        WHERE nauczyciel_id = ?
        AND dzien_tygodnia = ?
    ");
    $stmt->bind_param("ii", $nauczyciel_id, $dzien_nr);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // Nauczyciel NIE ma ustawionych godzin pracy dla tego dnia
        // = jest NIEDOSTĘPNY
        return false;
    }

    $godziny = $result->fetch_assoc();
    $praca_od = $godziny['godzina_od'];
    $praca_do = $godziny['godzina_do'];

    // Sprawdź czy lekcja mieści się w godzinach pracy
    // Lekcja musi zaczynać się nie wcześniej niż godzina_od
    // i kończyć się nie później niż godzina_do
    if ($lekcja_start >= $praca_od && $lekcja_koniec <= $praca_do) {
        return true; // Nauczyciel jest dostępny
    }

    // Lekcja wykracza poza godziny pracy
    return false;
}

?>
