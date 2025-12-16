<?php
/**
 * Funkcje modułu ocen - Wersja 4.0
 * Zarządzanie ocenami uczniów
 */

/**
 * Pobiera wszystkie kategorie ocen z wagami
 */
function pobierz_kategorie_ocen($conn)
{
    $result = $conn->query("SELECT * FROM kategorie_ocen ORDER BY nazwa");
    $kategorie = [];
    while ($row = $result->fetch_assoc()) {
        $kategorie[] = $row;
    }
    return $kategorie;
}

/**
 * Dodaje nową ocenę dla ucznia
 */
function dodaj_ocene($conn, $uczen_id, $przedmiot_id, $nauczyciel_id, $kategoria_id, $ocena, $komentarz = null, $poprawia_ocene_id = null)
{
    $stmt = $conn->prepare("
        INSERT INTO oceny (uczen_id, przedmiot_id, nauczyciel_id, kategoria_id, ocena, komentarz, poprawia_ocene_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiiidsi", $uczen_id, $przedmiot_id, $nauczyciel_id, $kategoria_id, $ocena, $komentarz, $poprawia_ocene_id);
    $result = $stmt->execute();

    // Jeśli to poprawka, oznacz starą ocenę jako poprawioną
    if ($poprawia_ocene_id && $result) {
        $upd_stmt = $conn->prepare("UPDATE oceny SET czy_poprawiona = 1 WHERE id = ?");
        $upd_stmt->bind_param("i", $poprawia_ocene_id);
        $upd_stmt->execute();
        $upd_stmt->close();
    }

    $stmt->close();
    return $result;
}

/**
 * Pobiera oceny ucznia z danego przedmiotu
 */
function pobierz_oceny_ucznia_przedmiot($conn, $uczen_id, $przedmiot_id)
{
    $stmt = $conn->prepare("
        SELECT o.*, k.nazwa as kategoria, k.waga,
               u.imie as nauczyciel_imie, u.nazwisko as nauczyciel_nazwisko
        FROM oceny o
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        JOIN nauczyciele n ON o.nauczyciel_id = n.id
        JOIN uzytkownicy u ON n.uzytkownik_id = u.id
        WHERE o.uczen_id = ? AND o.przedmiot_id = ?
        ORDER BY o.data_wystawienia DESC
    ");
    $stmt->bind_param("ii", $uczen_id, $przedmiot_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $oceny = [];
    while ($row = $result->fetch_assoc()) {
        $oceny[] = $row;
    }
    $stmt->close();
    return $oceny;
}

/**
 * Pobiera wszystkie oceny ucznia pogrupowane po przedmiotach
 */
function pobierz_wszystkie_oceny_ucznia($conn, $uczen_id)
{
    $stmt = $conn->prepare("
        SELECT o.*, p.nazwa as przedmiot, k.nazwa as kategoria, k.waga,
               u.imie as nauczyciel_imie, u.nazwisko as nauczyciel_nazwisko
        FROM oceny o
        JOIN przedmioty p ON o.przedmiot_id = p.id
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        JOIN nauczyciele n ON o.nauczyciel_id = n.id
        JOIN uzytkownicy u ON n.uzytkownik_id = u.id
        WHERE o.uczen_id = ?
        ORDER BY p.nazwa, o.data_wystawienia DESC
    ");
    $stmt->bind_param("i", $uczen_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $oceny = [];
    while ($row = $result->fetch_assoc()) {
        $przedmiot = $row['przedmiot'];
        if (!isset($oceny[$przedmiot])) {
            $oceny[$przedmiot] = [];
        }
        $oceny[$przedmiot][] = $row;
    }
    $stmt->close();
    return $oceny;
}

/**
 * Oblicza średnią ważoną ocen ucznia z przedmiotu
 */
function oblicz_srednia_wazona($conn, $uczen_id, $przedmiot_id)
{
    $stmt = $conn->prepare("
        SELECT SUM(o.ocena * k.waga) / SUM(k.waga) as srednia
        FROM oceny o
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        WHERE o.uczen_id = ? AND o.przedmiot_id = ? AND o.czy_poprawiona = 0
    ");
    $stmt->bind_param("ii", $uczen_id, $przedmiot_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result['srednia'] ? round($result['srednia'], 2) : null;
}

/**
 * Oblicza średnią ogólną ucznia ze wszystkich przedmiotów
 */
function oblicz_srednia_ogolna($conn, $uczen_id)
{
    $stmt = $conn->prepare("
        SELECT AVG(srednia) as srednia_ogolna FROM (
            SELECT o.przedmiot_id, SUM(o.ocena * k.waga) / SUM(k.waga) as srednia
            FROM oceny o
            JOIN kategorie_ocen k ON o.kategoria_id = k.id
            WHERE o.uczen_id = ? AND o.czy_poprawiona = 0
            GROUP BY o.przedmiot_id
        ) as srednie
    ");
    $stmt->bind_param("i", $uczen_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result['srednia_ogolna'] ? round($result['srednia_ogolna'], 2) : null;
}

/**
 * Pobiera oceny klasy z danego przedmiotu (dla nauczyciela)
 */
function pobierz_oceny_klasy($conn, $klasa_id, $przedmiot_id)
{
    $stmt = $conn->prepare("
        SELECT o.*, u.imie, u.nazwisko, k.nazwa as kategoria, k.waga
        FROM oceny o
        JOIN uczniowie uc ON o.uczen_id = uc.id
        JOIN uzytkownicy u ON uc.uzytkownik_id = u.id
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        WHERE uc.klasa_id = ? AND o.przedmiot_id = ?
        ORDER BY u.nazwisko, u.imie, o.data_wystawienia DESC
    ");
    $stmt->bind_param("ii", $klasa_id, $przedmiot_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $oceny = [];
    while ($row = $result->fetch_assoc()) {
        $uczen = $row['imie'] . ' ' . $row['nazwisko'];
        if (!isset($oceny[$uczen])) {
            $oceny[$uczen] = [];
        }
        $oceny[$uczen][] = $row;
    }
    $stmt->close();
    return $oceny;
}

/**
 * Oblicza średnią klasy z przedmiotu
 */
function oblicz_srednia_klasy($conn, $klasa_id, $przedmiot_id = null)
{
    $sql = "
        SELECT AVG(srednia) as srednia_klasy FROM (
            SELECT uc.id, SUM(o.ocena * k.waga) / SUM(k.waga) as srednia
            FROM uczniowie uc
            JOIN oceny o ON uc.id = o.uczen_id
            JOIN kategorie_ocen k ON o.kategoria_id = k.id
            WHERE uc.klasa_id = ? AND o.czy_poprawiona = 0
    ";

    if ($przedmiot_id) {
        $sql .= " AND o.przedmiot_id = ?";
    }

    $sql .= " GROUP BY uc.id) as srednie";

    $stmt = $conn->prepare($sql);
    if ($przedmiot_id) {
        $stmt->bind_param("ii", $klasa_id, $przedmiot_id);
    } else {
        $stmt->bind_param("i", $klasa_id);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result['srednia_klasy'] ? round($result['srednia_klasy'], 2) : null;
}

/**
 * Pobiera rozkład ocen dla klasy (do wykresów)
 */
function pobierz_rozklad_ocen($conn, $klasa_id, $przedmiot_id = null)
{
    $sql = "
        SELECT 
            FLOOR(o.ocena) as ocena_zaokraglona,
            COUNT(*) as ilosc
        FROM oceny o
        JOIN uczniowie uc ON o.uczen_id = uc.id
        WHERE uc.klasa_id = ? AND o.czy_poprawiona = 0
    ";

    if ($przedmiot_id) {
        $sql .= " AND o.przedmiot_id = ?";
    }

    $sql .= " GROUP BY FLOOR(o.ocena) ORDER BY ocena_zaokraglona";

    $stmt = $conn->prepare($sql);
    if ($przedmiot_id) {
        $stmt->bind_param("ii", $klasa_id, $przedmiot_id);
    } else {
        $stmt->bind_param("i", $klasa_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rozklad = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
    while ($row = $result->fetch_assoc()) {
        $ocena = intval($row['ocena_zaokraglona']);
        if ($ocena >= 1 && $ocena <= 6) {
            $rozklad[$ocena] = intval($row['ilosc']);
        }
    }
    $stmt->close();
    return $rozklad;
}

/**
 * Pobiera uczniów zagrożonych (średnia < 2.0)
 */
function pobierz_uczniow_zagrozonych($conn, $klasa_id, $przedmiot_id = null)
{
    $sql = "
        SELECT uc.id, u.imie, u.nazwisko, 
               SUM(o.ocena * k.waga) / SUM(k.waga) as srednia
        FROM uczniowie uc
        JOIN uzytkownicy u ON uc.uzytkownik_id = u.id
        JOIN oceny o ON uc.id = o.uczen_id
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        WHERE uc.klasa_id = ? AND o.czy_poprawiona = 0
    ";

    if ($przedmiot_id) {
        $sql .= " AND o.przedmiot_id = ?";
    }

    $sql .= " GROUP BY uc.id, u.imie, u.nazwisko HAVING srednia < 2.0 ORDER BY srednia";

    $stmt = $conn->prepare($sql);
    if ($przedmiot_id) {
        $stmt->bind_param("ii", $klasa_id, $przedmiot_id);
    } else {
        $stmt->bind_param("i", $klasa_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $uczniowie = [];
    while ($row = $result->fetch_assoc()) {
        $row['srednia'] = round($row['srednia'], 2);
        $uczniowie[] = $row;
    }
    $stmt->close();
    return $uczniowie;
}

/**
 * Formatuje ocenę do wyświetlenia (np. 4.5 -> 4+, 4.75 -> 5-)
 */
function formatuj_ocene($ocena)
{
    $calkowita = floor($ocena);
    $ulamkowa = $ocena - $calkowita;

    if ($ulamkowa >= 0.75) {
        return ($calkowita + 1) . '-';
    } elseif ($ulamkowa >= 0.25) {
        return $calkowita . '+';
    } else {
        return strval($calkowita);
    }
}

/**
 * Parsuje ocenę z formatu tekstowego (np. "4+", "5-") lub liczbowego na wartość liczbową
 */
function parsuj_ocene($ocena_text)
{
    $ocena_text = trim($ocena_text);

    // Format z plusem (np. "4+")
    if (preg_match('/^([1-6])\+$/', $ocena_text, $matches)) {
        return floatval($matches[1]) + 0.5;
    }
    // Format z minusem (np. "5-")
    elseif (preg_match('/^([2-6])\-$/', $ocena_text, $matches)) {
        return floatval($matches[1]) - 0.25;
    }
    // Pojedyncza cyfra (np. "4")
    elseif (preg_match('/^[1-6]$/', $ocena_text)) {
        return floatval($ocena_text);
    }
    // Wartość liczbowa z kropką lub przecinkiem (np. "4.5", "4,5")
    elseif (preg_match('/^[1-6][.,]\d+$/', $ocena_text)) {
        $ocena_text = str_replace(',', '.', $ocena_text);
        $value = floatval($ocena_text);
        if ($value >= 1 && $value <= 6) {
            return $value;
        }
    }

    return null;
}

/**
 * Pobiera kolor dla oceny (do stylizacji)
 */
function kolor_oceny($ocena)
{
    if ($ocena >= 5)
        return '#28a745'; // zielony
    if ($ocena >= 4)
        return '#5cb85c'; // jasnozielony
    if ($ocena >= 3)
        return '#f0ad4e'; // pomarańczowy
    if ($ocena >= 2)
        return '#d9534f'; // czerwony
    return '#c9302c'; // ciemnoczerwony
}

/**
 * Pobiera pojedynczą ocenę po ID
 */
function pobierz_ocene($conn, $ocena_id)
{
    $stmt = $conn->prepare("
        SELECT o.*, k.nazwa as kategoria, k.waga,
               uc.id as uczen_id, u.imie, u.nazwisko, p.nazwa as przedmiot
        FROM oceny o
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        JOIN uczniowie uc ON o.uczen_id = uc.id
        JOIN uzytkownicy u ON uc.uzytkownik_id = u.id
        JOIN przedmioty p ON o.przedmiot_id = p.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $ocena_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

/**
 * Edytuje istniejącą ocenę
 */
function edytuj_ocene($conn, $ocena_id, $ocena, $kategoria_id, $komentarz = null)
{
    // Jawna konwersja typów
    $ocena_id = intval($ocena_id);
    $ocena = floatval($ocena);
    $kategoria_id = intval($kategoria_id);

    $stmt = $conn->prepare("
        UPDATE oceny 
        SET ocena = ?, kategoria_id = ?, komentarz = ?
        WHERE id = ?
    ");
    $stmt->bind_param("disi", $ocena, $kategoria_id, $komentarz, $ocena_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Usuwa ocenę (tylko jeśli nauczyciel jest autorem)
 */
function usun_ocene($conn, $ocena_id, $nauczyciel_id)
{
    // Sprawdź czy ocena należy do tego nauczyciela
    $stmt = $conn->prepare("SELECT id FROM oceny WHERE id = ? AND nauczyciel_id = ?");
    $stmt->bind_param("ii", $ocena_id, $nauczyciel_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    // Usuń ocenę
    $stmt = $conn->prepare("DELETE FROM oceny WHERE id = ?");
    $stmt->bind_param("i", $ocena_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Oznacza ocenę jako poprawioną i dodaje nową ocenę poprawkową
 */
function popraw_ocene($conn, $stara_ocena_id, $nowa_ocena, $nauczyciel_id, $komentarz = null)
{
    // Pobierz dane starej oceny
    $stara = pobierz_ocene($conn, $stara_ocena_id);
    if (!$stara) {
        return false;
    }

    // Dodaj nową ocenę jako poprawkę
    $result = dodaj_ocene(
        $conn,
        $stara['uczen_id'],
        $stara['przedmiot_id'],
        $nauczyciel_id,
        $stara['kategoria_id'],
        $nowa_ocena,
        $komentarz ?: 'Poprawka oceny',
        $stara_ocena_id
    );

    return $result;
}

/**
 * Pobiera oceny do poprawy (nie poprawione jeszcze) dla ucznia z przedmiotu
 */
function pobierz_oceny_do_poprawy($conn, $uczen_id, $przedmiot_id)
{
    $stmt = $conn->prepare("
        SELECT o.id, o.ocena, o.data_wystawienia, k.nazwa as kategoria
        FROM oceny o
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        WHERE o.uczen_id = ? AND o.przedmiot_id = ? AND o.czy_poprawiona = 0
        ORDER BY o.data_wystawienia DESC
    ");
    $stmt->bind_param("ii", $uczen_id, $przedmiot_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $oceny = [];
    while ($row = $result->fetch_assoc()) {
        $oceny[] = $row;
    }
    $stmt->close();
    return $oceny;
}

/**
 * Dodaje lub aktualizuje niestandardową kategorię ocen
 */
function dodaj_kategorie_niestandardowa($conn, $nazwa, $waga, $opis = null)
{
    // Sprawdź czy kategoria już istnieje
    $stmt = $conn->prepare("SELECT id FROM kategorie_ocen WHERE nazwa = ?");
    $stmt->bind_param("s", $nazwa);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        return $result->fetch_assoc()['id'];
    }
    $stmt->close();

    // Dodaj nową kategorię
    $stmt = $conn->prepare("INSERT INTO kategorie_ocen (nazwa, waga, opis) VALUES (?, ?, ?)");
    $stmt->bind_param("sds", $nazwa, $waga, $opis);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Sprawdza czy ocena została poprawiona i zwraca ocenę poprawkową
 */
function pobierz_poprawke($conn, $ocena_id)
{
    $stmt = $conn->prepare("
        SELECT o.*, k.nazwa as kategoria
        FROM oceny o
        JOIN kategorie_ocen k ON o.kategoria_id = k.id
        WHERE o.poprawia_ocene_id = ?
    ");
    $stmt->bind_param("i", $ocena_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}
?>