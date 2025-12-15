-- Wersja 4.0 - Migracja bazy danych
-- Uruchom ten skrypt w phpMyAdmin lub CLI MySQL
-- UWAGA: Wykonaj backup bazy danych przed uruchomieniem!

-- --------------------------------------------------------
-- TABELE SYSTEMU OCEN
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `kategorie_ocen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazwa` varchar(50) NOT NULL,
  `waga` decimal(3,2) NOT NULL DEFAULT 1.00,
  `opis` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `kategorie_ocen` (`id`, `nazwa`, `waga`, `opis`) VALUES
(1, 'Sprawdzian', 3.00, 'Sprawdzian z większego zakresu materiału'),
(2, 'Kartkówka', 2.00, 'Krótka kartkówka z ostatnich lekcji'),
(3, 'Odpowiedź ustna', 2.00, 'Odpowiedź ustna przy tablicy'),
(4, 'Aktywność', 1.00, 'Aktywność na lekcji'),
(5, 'Praca domowa', 1.00, 'Praca domowa'),
(6, 'Praca klasowa', 3.00, 'Dłuższa praca pisemna'),
(7, 'Projekt', 2.00, 'Projekt lub prezentacja');

CREATE TABLE IF NOT EXISTS `oceny` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uczen_id` int(11) NOT NULL,
  `przedmiot_id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL,
  `kategoria_id` int(11) NOT NULL,
  `ocena` decimal(3,2) NOT NULL,
  `komentarz` text DEFAULT NULL,
  `data_wystawienia` timestamp NOT NULL DEFAULT current_timestamp(),
  `czy_poprawiona` tinyint(1) NOT NULL DEFAULT 0,
  `poprawia_ocene_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uczen_id` (`uczen_id`),
  KEY `przedmiot_id` (`przedmiot_id`),
  KEY `nauczyciel_id` (`nauczyciel_id`),
  KEY `kategoria_id` (`kategoria_id`),
  KEY `data_wystawienia` (`data_wystawienia`),
  KEY `idx_oceny_uczen_przedmiot` (`uczen_id`, `przedmiot_id`),
  CONSTRAINT `oceny_ibfk_1` FOREIGN KEY (`uczen_id`) REFERENCES `uczniowie` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oceny_ibfk_2` FOREIGN KEY (`przedmiot_id`) REFERENCES `przedmioty` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oceny_ibfk_3` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oceny_ibfk_4` FOREIGN KEY (`kategoria_id`) REFERENCES `kategorie_ocen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oceny_ibfk_5` FOREIGN KEY (`poprawia_ocene_id`) REFERENCES `oceny` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- TABELE SYSTEMU WIADOMOŚCI
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `wiadomosci` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nadawca_id` int(11) NOT NULL,
  `temat` varchar(255) NOT NULL,
  `tresc` text NOT NULL,
  `czy_wazne` tinyint(1) NOT NULL DEFAULT 0,
  `data_wyslania` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `nadawca_id` (`nadawca_id`),
  KEY `data_wyslania` (`data_wyslania`),
  CONSTRAINT `wiadomosci_ibfk_1` FOREIGN KEY (`nadawca_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wiadomosci_odbiorcy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wiadomosc_id` int(11) NOT NULL,
  `odbiorca_id` int(11) NOT NULL,
  `czy_przeczytana` tinyint(1) NOT NULL DEFAULT 0,
  `data_przeczytania` timestamp NULL DEFAULT NULL,
  `czy_usunieta` tinyint(1) NOT NULL DEFAULT 0,
  `folder` enum('odebrane','wyslane','archiwum') NOT NULL DEFAULT 'odebrane',
  PRIMARY KEY (`id`),
  KEY `wiadomosc_id` (`wiadomosc_id`),
  KEY `odbiorca_id` (`odbiorca_id`),
  KEY `idx_odbiorcy_folder` (`odbiorca_id`, `folder`, `czy_usunieta`),
  CONSTRAINT `wiadomosci_odbiorcy_ibfk_1` FOREIGN KEY (`wiadomosc_id`) REFERENCES `wiadomosci` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wiadomosci_odbiorcy_ibfk_2` FOREIGN KEY (`odbiorca_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wiadomosci_zalaczniki` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wiadomosc_id` int(11) NOT NULL,
  `nazwa_pliku` varchar(255) NOT NULL,
  `sciezka` varchar(500) NOT NULL,
  `rozmiar` int(11) NOT NULL,
  `typ_mime` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wiadomosc_id` (`wiadomosc_id`),
  CONSTRAINT `wiadomosci_zalaczniki_ibfk_1` FOREIGN KEY (`wiadomosc_id`) REFERENCES `wiadomosci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- KONIEC MIGRACJI
-- --------------------------------------------------------
