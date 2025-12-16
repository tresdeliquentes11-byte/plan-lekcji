<?php
/**
 * Funkcje modułu wiadomości - Wersja 4.0
 * System komunikacji wewnętrznej
 */

/**
 * Wysyła nową wiadomość
 */
function wyslij_wiadomosc($conn, $nadawca_id, $odbiorcy_ids, $temat, $tresc, $czy_wazne = false)
{
    $conn->begin_transaction();

    try {
        // Utwórz wiadomość
        $stmt = $conn->prepare("
            INSERT INTO wiadomosci (nadawca_id, temat, tresc, czy_wazne)
            VALUES (?, ?, ?, ?)
        ");
        $czy_wazne_int = $czy_wazne ? 1 : 0;
        $stmt->bind_param("issi", $nadawca_id, $temat, $tresc, $czy_wazne_int);
        $stmt->execute();
        $wiadomosc_id = $conn->insert_id;
        $stmt->close();

        // Dodaj odbiorców
        $stmt = $conn->prepare("
            INSERT INTO wiadomosci_odbiorcy (wiadomosc_id, odbiorca_id, folder)
            VALUES (?, ?, 'odebrane')
        ");

        foreach ($odbiorcy_ids as $odbiorca_id) {
            $stmt->bind_param("ii", $wiadomosc_id, $odbiorca_id);
            $stmt->execute();
        }
        $stmt->close();

        // Dodaj nadawcę jako odbiorcę (do folderu wysłane)
        $stmt = $conn->prepare("
            INSERT INTO wiadomosci_odbiorcy (wiadomosc_id, odbiorca_id, folder, czy_przeczytana)
            VALUES (?, ?, 'wyslane', 1)
        ");
        $stmt->bind_param("ii", $wiadomosc_id, $nadawca_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return $wiadomosc_id;

    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Pobiera wiadomości użytkownika z danego folderu
 */
function pobierz_skrzynke($conn, $uzytkownik_id, $folder = 'odebrane', $limit = 50, $offset = 0)
{
    $stmt = $conn->prepare("
        SELECT w.*, wo.czy_przeczytana, wo.data_przeczytania, wo.id as odbiorca_id,
               u.imie as nadawca_imie, u.nazwisko as nadawca_nazwisko,
               (SELECT COUNT(*) FROM wiadomosci_zalaczniki wz WHERE wz.wiadomosc_id = w.id) as liczba_zalacznikow
        FROM wiadomosci w
        JOIN wiadomosci_odbiorcy wo ON w.id = wo.wiadomosc_id
        JOIN uzytkownicy u ON w.nadawca_id = u.id
        WHERE wo.odbiorca_id = ? AND wo.folder = ? AND wo.czy_usunieta = 0
        ORDER BY w.data_wyslania DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("isii", $uzytkownik_id, $folder, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $wiadomosci = [];
    while ($row = $result->fetch_assoc()) {
        $wiadomosci[] = $row;
    }
    $stmt->close();
    return $wiadomosci;
}

/**
 * Pobiera szczegóły wiadomości
 */
function pobierz_wiadomosc($conn, $wiadomosc_id, $uzytkownik_id)
{
    $stmt = $conn->prepare("
        SELECT w.*, wo.czy_przeczytana, wo.folder,
               u.imie as nadawca_imie, u.nazwisko as nadawca_nazwisko, u.typ as nadawca_typ
        FROM wiadomosci w
        JOIN wiadomosci_odbiorcy wo ON w.id = wo.wiadomosc_id
        JOIN uzytkownicy u ON w.nadawca_id = u.id
        WHERE w.id = ? AND wo.odbiorca_id = ? AND wo.czy_usunieta = 0
    ");
    $stmt->bind_param("ii", $wiadomosc_id, $uzytkownik_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $wiadomosc = $result->fetch_assoc();
    $stmt->close();

    if ($wiadomosc) {
        // Pobierz załączniki
        $wiadomosc['zalaczniki'] = pobierz_zalaczniki($conn, $wiadomosc_id);

        // Pobierz listę wszystkich odbiorców
        $wiadomosc['odbiorcy'] = pobierz_odbiorcy_wiadomosci($conn, $wiadomosc_id);
    }

    return $wiadomosc;
}

/**
 * Pobiera załączniki wiadomości
 */
function pobierz_zalaczniki($conn, $wiadomosc_id)
{
    $stmt = $conn->prepare("
        SELECT * FROM wiadomosci_zalaczniki WHERE wiadomosc_id = ?
    ");
    $stmt->bind_param("i", $wiadomosc_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $zalaczniki = [];
    while ($row = $result->fetch_assoc()) {
        $zalaczniki[] = $row;
    }
    $stmt->close();
    return $zalaczniki;
}

/**
 * Pobiera odbiorców wiadomości
 */
function pobierz_odbiorcy_wiadomosci($conn, $wiadomosc_id)
{
    $stmt = $conn->prepare("
        SELECT u.imie, u.nazwisko, u.typ, wo.czy_przeczytana, wo.data_przeczytania
        FROM wiadomosci_odbiorcy wo
        JOIN uzytkownicy u ON wo.odbiorca_id = u.id
        WHERE wo.wiadomosc_id = ? AND wo.folder = 'odebrane'
    ");
    $stmt->bind_param("i", $wiadomosc_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $odbiorcy = [];
    while ($row = $result->fetch_assoc()) {
        $odbiorcy[] = $row;
    }
    $stmt->close();
    return $odbiorcy;
}

/**
 * Oznacza wiadomość jako przeczytaną
 */
function oznacz_przeczytana($conn, $wiadomosc_id, $uzytkownik_id)
{
    $stmt = $conn->prepare("
        UPDATE wiadomosci_odbiorcy 
        SET czy_przeczytana = 1, data_przeczytania = CURRENT_TIMESTAMP
        WHERE wiadomosc_id = ? AND odbiorca_id = ? AND czy_przeczytana = 0
    ");
    $stmt->bind_param("ii", $wiadomosc_id, $uzytkownik_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Usuwa wiadomość (soft delete)
 */
function usun_wiadomosc($conn, $wiadomosc_id, $uzytkownik_id)
{
    $stmt = $conn->prepare("
        UPDATE wiadomosci_odbiorcy 
        SET czy_usunieta = 1
        WHERE wiadomosc_id = ? AND odbiorca_id = ?
    ");
    $stmt->bind_param("ii", $wiadomosc_id, $uzytkownik_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Przenosi wiadomość do archiwum
 */
function archiwizuj_wiadomosc($conn, $wiadomosc_id, $uzytkownik_id)
{
    $stmt = $conn->prepare("
        UPDATE wiadomosci_odbiorcy 
        SET folder = 'archiwum'
        WHERE wiadomosc_id = ? AND odbiorca_id = ?
    ");
    $stmt->bind_param("ii", $wiadomosc_id, $uzytkownik_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Liczy nieprzeczytane wiadomości użytkownika
 */
function liczba_nieprzeczytanych($conn, $uzytkownik_id)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM wiadomosci_odbiorcy
        WHERE odbiorca_id = ? AND folder = 'odebrane' AND czy_przeczytana = 0 AND czy_usunieta = 0
    ");
    $stmt->bind_param("i", $uzytkownik_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($result['count']);
}

/**
 * Pobiera listę użytkowników do wyboru odbiorcy
 */
/**
 * Pobiera listę użytkowników do wyboru odbiorcy
 */
function pobierz_liste_odbiorcow($conn, $uzytkownik_id, $typ_uzytkownika)
{
    $odbiorcy = [];

    // Pobierz nauczycieli (dostępni dla wszystkich)
    $stmt = $conn->prepare("
        SELECT u.id, u.imie, u.nazwisko, 'nauczyciel' as typ
        FROM uzytkownicy u
        JOIN nauczyciele n ON u.id = n.uzytkownik_id
        WHERE u.aktywny = 1 AND u.id != ?
        ORDER BY u.nazwisko, u.imie
    ");
    $stmt->bind_param("i", $uzytkownik_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $odbiorcy['Nauczyciele'][] = $row;
    }
    $stmt->close();

    // Dyrektor (dostępny dla wszystkich)
    $stmt = $conn->prepare("
        SELECT id, imie, nazwisko, 'dyrektor' as typ
        FROM uzytkownicy
        WHERE typ = 'dyrektor' AND aktywny = 1 AND id != ?
        ORDER BY nazwisko, imie
    ");
    $stmt->bind_param("i", $uzytkownik_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $odbiorcy['Dyrekcja'][] = $row;
    }
    $stmt->close();

    // Dla nauczycieli - dostęp do uczniów z ich klas
    if ($typ_uzytkownika === 'nauczyciel') {
        $stmt = $conn->prepare("
            SELECT DISTINCT u.id, u.imie, u.nazwisko, k.nazwa as klasa, 'uczen' as typ
            FROM uzytkownicy u
            JOIN uczniowie uc ON u.id = uc.uzytkownik_id
            JOIN klasy k ON uc.klasa_id = k.id
            JOIN klasa_przedmioty kp ON k.id = kp.klasa_id
            JOIN nauczyciele n ON kp.nauczyciel_id = n.id
            JOIN uzytkownicy nu ON n.uzytkownik_id = nu.id
            WHERE nu.id = ? AND u.aktywny = 1
            ORDER BY k.nazwa, u.nazwisko, u.imie
        ");
        $stmt->bind_param("i", $uzytkownik_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $klasa = $row['klasa'];
            $odbiorcy["Uczniowie klasy $klasa"][] = $row;
        }
        $stmt->close();
    }

    // Dla dyrektora - dostęp do wszystkich
    if ($typ_uzytkownika === 'dyrektor') {
        $result = $conn->query("
            SELECT u.id, u.imie, u.nazwisko, k.nazwa as klasa, 'uczen' as typ
            FROM uzytkownicy u
            JOIN uczniowie uc ON u.id = uc.uzytkownik_id
            JOIN klasy k ON uc.klasa_id = k.id
            WHERE u.aktywny = 1
            ORDER BY k.nazwa, u.nazwisko, u.imie
        ");
        while ($row = $result->fetch_assoc()) {
            $klasa = $row['klasa'];
            $odbiorcy["Uczniowie klasy $klasa"][] = $row;
        }
    }

    return $odbiorcy;
}

/**
 * Wysyła wiadomość do całej klasy
 */
function wyslij_do_klasy($conn, $nadawca_id, $klasa_id, $temat, $tresc, $czy_wazne = false)
{
    // Pobierz wszystkich uczniów z klasy
    $stmt = $conn->prepare("
        SELECT u.id
        FROM uzytkownicy u
        JOIN uczniowie uc ON u.id = uc.uzytkownik_id
        WHERE uc.klasa_id = ? AND u.aktywny = 1
    ");
    $stmt->bind_param("i", $klasa_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $odbiorcy_ids = [];
    while ($row = $result->fetch_assoc()) {
        $odbiorcy_ids[] = $row['id'];
    }
    $stmt->close();

    if (empty($odbiorcy_ids)) {
        return false;
    }

    return wyslij_wiadomosc($conn, $nadawca_id, $odbiorcy_ids, $temat, $tresc, $czy_wazne);
}

/**
 * Dodaje załącznik do wiadomości
 */
function dodaj_zalacznik($conn, $wiadomosc_id, $nazwa_pliku, $sciezka, $rozmiar, $typ_mime)
{
    $stmt = $conn->prepare("
        INSERT INTO wiadomosci_zalaczniki (wiadomosc_id, nazwa_pliku, sciezka, rozmiar, typ_mime)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issis", $wiadomosc_id, $nazwa_pliku, $sciezka, $rozmiar, $typ_mime);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Obsługuje upload załącznika
 */
function upload_zalacznik($plik, $wiadomosc_id)
{
    $dozwolone_typy = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png'
    ];
    $max_rozmiar = 10 * 1024 * 1024; // 10MB

    if ($plik['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Błąd podczas przesyłania pliku'];
    }

    if ($plik['size'] > $max_rozmiar) {
        return ['error' => 'Plik jest za duży (max 10MB)'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $typ_mime = finfo_file($finfo, $plik['tmp_name']);
    finfo_close($finfo);

    // Mapa dozwolonych typów MIME na bezpieczne rozszerzenia
    $mime_map = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!array_key_exists($typ_mime, $mime_map)) {
        return ['error' => 'Niedozwolony typ pliku'];
    }

    $upload_dir = dirname(__DIR__) . '/uploads/wiadomosci/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Ustal bezpieczne rozszerzenie na podstawie typu MIME
    $rozszerzenie = $mime_map[$typ_mime];
    $bezpieczna_nazwa = uniqid('attach_') . '_' . time() . '.' . $rozszerzenie;
    $sciezka = $upload_dir . $bezpieczna_nazwa;

    if (move_uploaded_file($plik['tmp_name'], $sciezka)) {
        return [
            'nazwa_pliku' => $plik['name'], // Zachowujemy oryginalną nazwę do wyświetlania
            'sciezka' => 'uploads/wiadomosci/' . $bezpieczna_nazwa,
            'rozmiar' => $plik['size'],
            'typ_mime' => $typ_mime
        ];
    }

    return ['error' => 'Nie udało się zapisać pliku'];
}

/**
 * Formatuje rozmiar pliku do czytelnej formy
 */
function formatuj_rozmiar($bytes)
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Formatuje datę wiadomości
 */
function formatuj_date_wiadomosci($data)
{
    $timestamp = strtotime($data);
    $dzisiaj = strtotime('today');
    $wczoraj = strtotime('yesterday');

    if ($timestamp >= $dzisiaj) {
        return 'Dzisiaj, ' . date('H:i', $timestamp);
    } elseif ($timestamp >= $wczoraj) {
        return 'Wczoraj, ' . date('H:i', $timestamp);
    } else {
        return date('d.m.Y H:i', $timestamp);
    }
}
/**
 * Czyści stare załączniki z serwera i bazy danych
 */
function wyczysc_stare_zalaczniki($conn, $dni = 30)
{
    $limit_czasu = date('Y-m-d H:i:s', strtotime("-{$dni} days"));

    // Pobierz załączniki do usunięcia
    $stmt = $conn->prepare("
        SELECT wz.id, wz.sciezka 
        FROM wiadomosci_zalaczniki wz
        JOIN wiadomosci w ON wz.wiadomosc_id = w.id
        WHERE w.data_wyslania < ?
    ");
    $stmt->bind_param("s", $limit_czasu);
    $stmt->execute();
    $result = $stmt->get_result();

    $usuniete_pliki = 0;
    $ids_to_delete = [];

    $base_dir = dirname(__DIR__) . '/';

    while ($row = $result->fetch_assoc()) {
        $pelna_sciezka = $base_dir . $row['sciezka'];
        if (file_exists($pelna_sciezka)) {
            if (unlink($pelna_sciezka)) {
                $usuniete_pliki++;
            }
        } else {
            // Plik nie istnieje, też uznajemy za usunięty "z systemu"
            $usuniete_pliki++;
        }
        $ids_to_delete[] = $row['id'];
    }
    $stmt->close();

    // Usuń rekordy z bazy
    if (!empty($ids_to_delete)) {
        $ids_string = implode(',', $ids_to_delete);
        $conn->query("DELETE FROM wiadomosci_zalaczniki WHERE id IN ($ids_string)");
    }

    return $usuniete_pliki;
}

/**
 * Czyści wszystkie załączniki z serwera i bazy danych
 */
function wyczysc_wszystkie_zalaczniki($conn)
{
    // Pobierz wszystkie załączniki
    $result = $conn->query("SELECT id, sciezka FROM wiadomosci_zalaczniki");

    $usuniete_pliki = 0;

    $base_dir = dirname(__DIR__) . '/';

    while ($row = $result->fetch_assoc()) {
        $pelna_sciezka = $base_dir . $row['sciezka'];
        if (file_exists($pelna_sciezka)) {
            if (unlink($pelna_sciezka)) {
                $usuniete_pliki++;
            }
        }
    }

    // Wyczyść tabelę
    $conn->query("TRUNCATE TABLE wiadomosci_zalaczniki");

    return $usuniete_pliki;
}
?>