-- Migracja dla rozbudowy panelu administratora
-- Data: 2025-12-02
-- Opis: Dodanie tabel dla logów aktywności i statystyk

-- --------------------------------------------------------

--
-- Tabela dla aktywnych sesji użytkowników
--

CREATE TABLE IF NOT EXISTS `sesje_uzytkownikow` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uzytkownik_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ostatnia_aktywnosc` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_logowania` timestamp NOT NULL DEFAULT current_timestamp(),
  `aktywna` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `uzytkownik_id` (`uzytkownik_id`),
  KEY `session_id` (`session_id`),
  KEY `aktywna` (`aktywna`),
  CONSTRAINT `sesje_uzytkownikow_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabela dla logów aktywności użytkowników
--

CREATE TABLE IF NOT EXISTS `logi_aktywnosci` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uzytkownik_id` int(11) DEFAULT NULL,
  `typ_akcji` varchar(50) NOT NULL,
  `opis` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `data_akcji` timestamp NOT NULL DEFAULT current_timestamp(),
  `dodatkowe_dane` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uzytkownik_id` (`uzytkownik_id`),
  KEY `typ_akcji` (`typ_akcji`),
  KEY `data_akcji` (`data_akcji`),
  CONSTRAINT `logi_aktywnosci_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabela dla statystyk generowania planu
--

CREATE TABLE IF NOT EXISTS `statystyki_generowania` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uzytkownik_id` int(11) NOT NULL,
  `typ_generowania` enum('plan_tygodniowy','plan_dzienny','zastepstwa') NOT NULL,
  `data_generowania` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sukces','blad','przerwane') DEFAULT 'sukces',
  `czas_trwania_sekundy` decimal(10,2) DEFAULT NULL,
  `ilosc_wygenerowanych_lekcji` int(11) DEFAULT 0,
  `komunikat_bledu` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uzytkownik_id` (`uzytkownik_id`),
  KEY `data_generowania` (`data_generowania`),
  KEY `typ_generowania` (`typ_generowania`),
  CONSTRAINT `statystyki_generowania_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabela dla statystyk zarządzania użytkownikami
--

CREATE TABLE IF NOT EXISTS `statystyki_uzytkownikow` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `administrator_id` int(11) NOT NULL,
  `typ_operacji` enum('dodanie','edycja','usuniecie','blokada','odblokowanie') NOT NULL,
  `uzytkownik_docelowy_id` int(11) DEFAULT NULL,
  `typ_uzytkownika_docelowego` enum('dyrektor','administrator','nauczyciel','uczen') DEFAULT NULL,
  `data_operacji` timestamp NOT NULL DEFAULT current_timestamp(),
  `opis_zmian` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `administrator_id` (`administrator_id`),
  KEY `uzytkownik_docelowy_id` (`uzytkownik_docelowy_id`),
  KEY `data_operacji` (`data_operacji`),
  CONSTRAINT `statystyki_uzytkownikow_ibfk_1` FOREIGN KEY (`administrator_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE,
  CONSTRAINT `statystyki_uzytkownikow_ibfk_2` FOREIGN KEY (`uzytkownik_docelowy_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Indeksy optymalizacyjne
--

-- Index dla szybkiego wyszukiwania aktywnych sesji
CREATE INDEX idx_sesje_aktywne ON sesje_uzytkownikow(aktywna, ostatnia_aktywnosc);

-- Index dla szybkiego wyszukiwania logów z ostatnich 7 dni
CREATE INDEX idx_logi_ostatnie ON logi_aktywnosci(data_akcji DESC);

-- Index dla statystyk generowania z grupowaniem po dniach
CREATE INDEX idx_stat_gen_dzien ON statystyki_generowania(DATE(data_generowania));

COMMIT;
