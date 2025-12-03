-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 03, 2025 at 09:32 PM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `plan_lekcji`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `dni_wolne`
--

CREATE TABLE `dni_wolne` (
  `id` int(11) NOT NULL,
  `data` date NOT NULL,
  `opis` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `klasa_przedmioty`
--

CREATE TABLE `klasa_przedmioty` (
  `id` int(11) NOT NULL,
  `klasa_id` int(11) NOT NULL,
  `przedmiot_id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL,
  `ilosc_godzin_tydzien` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `klasy`
--

CREATE TABLE `klasy` (
  `id` int(11) NOT NULL,
  `nazwa` varchar(10) NOT NULL,
  `wychowawca_id` int(11) DEFAULT NULL,
  `ilosc_godzin_dziennie` int(11) DEFAULT 7,
  `rozszerzenie_1` varchar(50) DEFAULT NULL,
  `rozszerzenie_2` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logi_aktywnosci`
--

CREATE TABLE `logi_aktywnosci` (
  `id` int(11) NOT NULL,
  `uzytkownik_id` int(11) DEFAULT NULL,
  `typ_akcji` varchar(50) NOT NULL,
  `opis` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `data_akcji` timestamp NOT NULL DEFAULT current_timestamp(),
  `dodatkowe_dane` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `nauczyciele`
--

CREATE TABLE `nauczyciele` (
  `id` int(11) NOT NULL,
  `uzytkownik_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `nauczyciel_godziny_pracy`
--

CREATE TABLE `nauczyciel_godziny_pracy` (
  `id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL,
  `dzien_tygodnia` int(11) NOT NULL COMMENT '1-5',
  `godzina_od` time NOT NULL,
  `godzina_do` time NOT NULL,
  `utworzono` timestamp NOT NULL DEFAULT current_timestamp(),
  `zaktualizowano` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `nauczyciel_przedmioty`
--

CREATE TABLE `nauczyciel_przedmioty` (
  `id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL,
  `przedmiot_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `nieobecnosci`
--

CREATE TABLE `nieobecnosci` (
  `id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL,
  `data_od` date NOT NULL,
  `data_do` date NOT NULL,
  `powod` varchar(200) DEFAULT NULL,
  `data_zgloszenia` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `plan_dzienny`
--

CREATE TABLE `plan_dzienny` (
  `id` int(11) NOT NULL,
  `plan_lekcji_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `klasa_id` int(11) NOT NULL,
  `numer_lekcji` int(11) NOT NULL,
  `godzina_rozpoczecia` time NOT NULL,
  `godzina_zakonczenia` time NOT NULL,
  `przedmiot_id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL,
  `sala_id` int(11) DEFAULT NULL,
  `czy_zastepstwo` tinyint(1) DEFAULT 0,
  `oryginalny_nauczyciel_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `plan_lekcji`
--

CREATE TABLE `plan_lekcji` (
  `id` int(11) NOT NULL,
  `klasa_id` int(11) NOT NULL,
  `dzien_tygodnia` enum('poniedzialek','wtorek','sroda','czwartek','piatek') NOT NULL,
  `numer_lekcji` int(11) NOT NULL,
  `godzina_rozpoczecia` time NOT NULL,
  `godzina_zakonczenia` time NOT NULL,
  `przedmiot_id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL,
  `sala_id` int(11) DEFAULT NULL,
  `szablon_tygodniowy` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `przedmioty`
--

CREATE TABLE `przedmioty` (
  `id` int(11) NOT NULL,
  `nazwa` varchar(100) NOT NULL,
  `skrot` varchar(20) DEFAULT NULL,
  `czy_rozszerzony` tinyint(1) DEFAULT 0,
  `domyslna_ilosc_godzin` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `sala_nauczyciele`
--

CREATE TABLE `sala_nauczyciele` (
  `id` int(11) NOT NULL,
  `sala_id` int(11) NOT NULL,
  `nauczyciel_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `sala_przedmioty`
--

CREATE TABLE `sala_przedmioty` (
  `id` int(11) NOT NULL,
  `sala_id` int(11) NOT NULL,
  `przedmiot_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `sale`
--

CREATE TABLE `sale` (
  `id` int(11) NOT NULL,
  `numer` varchar(20) NOT NULL,
  `nazwa` varchar(100) DEFAULT NULL,
  `typ` enum('standardowa','pracownia','sportowa','specjalna') DEFAULT 'standardowa',
  `pojemnosc` int(11) DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `sesje_uzytkownikow`
--

CREATE TABLE `sesje_uzytkownikow` (
  `id` int(11) NOT NULL,
  `uzytkownik_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ostatnia_aktywnosc` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_logowania` timestamp NOT NULL DEFAULT current_timestamp(),
  `aktywna` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `statystyki_generowania`
--

CREATE TABLE `statystyki_generowania` (
  `id` int(11) NOT NULL,
  `uzytkownik_id` int(11) NOT NULL,
  `typ_generowania` enum('plan_tygodniowy','plan_dzienny','zastepstwa') NOT NULL,
  `data_generowania` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sukces','blad','przerwane') DEFAULT 'sukces',
  `czas_trwania_sekundy` decimal(10,2) DEFAULT NULL,
  `ilosc_wygenerowanych_lekcji` int(11) DEFAULT 0,
  `komunikat_bledu` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `statystyki_uzytkownikow`
--

CREATE TABLE `statystyki_uzytkownikow` (
  `id` int(11) NOT NULL,
  `administrator_id` int(11) NOT NULL,
  `typ_operacji` enum('dodanie','edycja','usuniecie','blokada','odblokowanie') NOT NULL,
  `uzytkownik_docelowy_id` int(11) DEFAULT NULL,
  `typ_uzytkownika_docelowego` enum('dyrektor','administrator','nauczyciel','uczen') DEFAULT NULL,
  `data_operacji` timestamp NOT NULL DEFAULT current_timestamp(),
  `opis_zmian` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `uczniowie`
--

CREATE TABLE `uczniowie` (
  `id` int(11) NOT NULL,
  `uzytkownik_id` int(11) NOT NULL,
  `klasa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `ustawienia_planu`
--

CREATE TABLE `ustawienia_planu` (
  `id` int(11) NOT NULL,
  `nazwa` varchar(100) NOT NULL,
  `wartosc` varchar(255) NOT NULL,
  `opis` text DEFAULT NULL,
  `data_modyfikacji` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `uzytkownicy`
--

CREATE TABLE `uzytkownicy` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `haslo` varchar(255) NOT NULL,
  `typ` enum('dyrektor','administrator','nauczyciel','uczen') NOT NULL,
  `imie` varchar(100) NOT NULL,
  `nazwisko` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `aktywny` tinyint(1) DEFAULT 1,
  `data_utworzenia` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uzytkownicy`
--

INSERT INTO `uzytkownicy` (`id`, `login`, `haslo`, `typ`, `imie`, `nazwisko`, `email`, `aktywny`, `data_utworzenia`) VALUES
(1, 'dyrektor', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'dyrektor', 'Jan', 'Kowalski', 'dyrektor@szkola.pl', 1, '2025-11-30 11:38:04'),
(2, 'admin', '$2y$10$VnMkJR.T.ASSa8i49XwuyOx2A3VrMPx8IuNAw0i0roZiqU.eMxm6e', 'administrator', 'Anna', 'Nowak', 'admin@szkola.pl', 1, '2025-11-30 11:38:04');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `zastepstwa`
--

CREATE TABLE `zastepstwa` (
  `id` int(11) NOT NULL,
  `plan_dzienny_id` int(11) NOT NULL,
  `nieobecnosc_id` int(11) NOT NULL,
  `nauczyciel_zastepujacy_id` int(11) NOT NULL,
  `data_utworzenia` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `dni_wolne`
--
ALTER TABLE `dni_wolne`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_data` (`data`);

--
-- Indeksy dla tabeli `klasa_przedmioty`
--
ALTER TABLE `klasa_przedmioty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_klasa_przedmiot` (`klasa_id`,`przedmiot_id`),
  ADD KEY `przedmiot_id` (`przedmiot_id`),
  ADD KEY `nauczyciel_id` (`nauczyciel_id`);

--
-- Indeksy dla tabeli `klasy`
--
ALTER TABLE `klasy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nazwa` (`nazwa`),
  ADD KEY `klasy_ibfk_1` (`wychowawca_id`);

--
-- Indeksy dla tabeli `logi_aktywnosci`
--
ALTER TABLE `logi_aktywnosci`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uzytkownik_id` (`uzytkownik_id`),
  ADD KEY `typ_akcji` (`typ_akcji`),
  ADD KEY `data_akcji` (`data_akcji`),
  ADD KEY `idx_logi_ostatnie` (`data_akcji`);

--
-- Indeksy dla tabeli `nauczyciele`
--
ALTER TABLE `nauczyciele`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uzytkownik_id` (`uzytkownik_id`);

--
-- Indeksy dla tabeli `nauczyciel_godziny_pracy`
--
ALTER TABLE `nauczyciel_godziny_pracy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nauczyciel_dzien` (`nauczyciel_id`,`dzien_tygodnia`),
  ADD KEY `idx_nauczyciel` (`nauczyciel_id`),
  ADD KEY `idx_dzien` (`dzien_tygodnia`);

--
-- Indeksy dla tabeli `nauczyciel_przedmioty`
--
ALTER TABLE `nauczyciel_przedmioty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nauczyciel_przedmiot` (`nauczyciel_id`,`przedmiot_id`),
  ADD KEY `przedmiot_id` (`przedmiot_id`);

--
-- Indeksy dla tabeli `nieobecnosci`
--
ALTER TABLE `nieobecnosci`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nauczyciel_id` (`nauczyciel_id`);

--
-- Indeksy dla tabeli `plan_dzienny`
--
ALTER TABLE `plan_dzienny`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plan_data` (`data`,`klasa_id`,`numer_lekcji`),
  ADD KEY `plan_lekcji_id` (`plan_lekcji_id`),
  ADD KEY `klasa_id` (`klasa_id`),
  ADD KEY `przedmiot_id` (`przedmiot_id`),
  ADD KEY `nauczyciel_id` (`nauczyciel_id`),
  ADD KEY `sala_id` (`sala_id`),
  ADD KEY `oryginalny_nauczyciel_id` (`oryginalny_nauczyciel_id`);

--
-- Indeksy dla tabeli `plan_lekcji`
--
ALTER TABLE `plan_lekcji`
  ADD PRIMARY KEY (`id`),
  ADD KEY `klasa_id` (`klasa_id`),
  ADD KEY `przedmiot_id` (`przedmiot_id`),
  ADD KEY `nauczyciel_id` (`nauczyciel_id`),
  ADD KEY `sala_id` (`sala_id`);

--
-- Indeksy dla tabeli `przedmioty`
--
ALTER TABLE `przedmioty`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `sala_nauczyciele`
--
ALTER TABLE `sala_nauczyciele`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sala_nauczyciel` (`sala_id`,`nauczyciel_id`),
  ADD KEY `nauczyciel_id` (`nauczyciel_id`);

--
-- Indeksy dla tabeli `sala_przedmioty`
--
ALTER TABLE `sala_przedmioty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sala_przedmiot` (`sala_id`,`przedmiot_id`),
  ADD KEY `przedmiot_id` (`przedmiot_id`);

--
-- Indeksy dla tabeli `sale`
--
ALTER TABLE `sale`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numer` (`numer`);

--
-- Indeksy dla tabeli `sesje_uzytkownikow`
--
ALTER TABLE `sesje_uzytkownikow`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uzytkownik_id` (`uzytkownik_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `aktywna` (`aktywna`),
  ADD KEY `idx_sesje_aktywne` (`aktywna`,`ostatnia_aktywnosc`);

--
-- Indeksy dla tabeli `statystyki_generowania`
--
ALTER TABLE `statystyki_generowania`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uzytkownik_id` (`uzytkownik_id`),
  ADD KEY `data_generowania` (`data_generowania`),
  ADD KEY `typ_generowania` (`typ_generowania`);

--
-- Indeksy dla tabeli `statystyki_uzytkownikow`
--
ALTER TABLE `statystyki_uzytkownikow`
  ADD PRIMARY KEY (`id`),
  ADD KEY `administrator_id` (`administrator_id`),
  ADD KEY `uzytkownik_docelowy_id` (`uzytkownik_docelowy_id`),
  ADD KEY `data_operacji` (`data_operacji`);

--
-- Indeksy dla tabeli `uczniowie`
--
ALTER TABLE `uczniowie`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uzytkownik_id` (`uzytkownik_id`),
  ADD KEY `klasa_id` (`klasa_id`);

--
-- Indeksy dla tabeli `ustawienia_planu`
--
ALTER TABLE `ustawienia_planu`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nazwa` (`nazwa`);

--
-- Indeksy dla tabeli `uzytkownicy`
--
ALTER TABLE `uzytkownicy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- Indeksy dla tabeli `zastepstwa`
--
ALTER TABLE `zastepstwa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_dzienny_id` (`plan_dzienny_id`),
  ADD KEY `nieobecnosc_id` (`nieobecnosc_id`),
  ADD KEY `nauczyciel_zastepujacy_id` (`nauczyciel_zastepujacy_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dni_wolne`
--
ALTER TABLE `dni_wolne`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `klasa_przedmioty`
--
ALTER TABLE `klasa_przedmioty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `klasy`
--
ALTER TABLE `klasy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `logi_aktywnosci`
--
ALTER TABLE `logi_aktywnosci`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nauczyciele`
--
ALTER TABLE `nauczyciele`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `nauczyciel_godziny_pracy`
--
ALTER TABLE `nauczyciel_godziny_pracy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `nauczyciel_przedmioty`
--
ALTER TABLE `nauczyciel_przedmioty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `nieobecnosci`
--
ALTER TABLE `nieobecnosci`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `plan_dzienny`
--
ALTER TABLE `plan_dzienny`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=269985;

--
-- AUTO_INCREMENT for table `plan_lekcji`
--
ALTER TABLE `plan_lekcji`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6421;

--
-- AUTO_INCREMENT for table `przedmioty`
--
ALTER TABLE `przedmioty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sala_nauczyciele`
--
ALTER TABLE `sala_nauczyciele`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `sala_przedmioty`
--
ALTER TABLE `sala_przedmioty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `sale`
--
ALTER TABLE `sale`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sesje_uzytkownikow`
--
ALTER TABLE `sesje_uzytkownikow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `statystyki_generowania`
--
ALTER TABLE `statystyki_generowania`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `statystyki_uzytkownikow`
--
ALTER TABLE `statystyki_uzytkownikow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uczniowie`
--
ALTER TABLE `uczniowie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=364;

--
-- AUTO_INCREMENT for table `ustawienia_planu`
--
ALTER TABLE `ustawienia_planu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uzytkownicy`
--
ALTER TABLE `uzytkownicy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=443;

--
-- AUTO_INCREMENT for table `zastepstwa`
--
ALTER TABLE `zastepstwa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `klasa_przedmioty`
--
ALTER TABLE `klasa_przedmioty`
  ADD CONSTRAINT `klasa_przedmioty_ibfk_1` FOREIGN KEY (`klasa_id`) REFERENCES `klasy` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `klasa_przedmioty_ibfk_2` FOREIGN KEY (`przedmiot_id`) REFERENCES `przedmioty` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `klasa_przedmioty_ibfk_3` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `klasy`
--
ALTER TABLE `klasy`
  ADD CONSTRAINT `klasy_ibfk_1` FOREIGN KEY (`wychowawca_id`) REFERENCES `nauczyciele` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `logi_aktywnosci`
--
ALTER TABLE `logi_aktywnosci`
  ADD CONSTRAINT `logi_aktywnosci_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `nauczyciele`
--
ALTER TABLE `nauczyciele`
  ADD CONSTRAINT `nauczyciele_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nauczyciel_godziny_pracy`
--
ALTER TABLE `nauczyciel_godziny_pracy`
  ADD CONSTRAINT `nauczyciel_godziny_pracy_ibfk_1` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nauczyciel_przedmioty`
--
ALTER TABLE `nauczyciel_przedmioty`
  ADD CONSTRAINT `nauczyciel_przedmioty_ibfk_1` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nauczyciel_przedmioty_ibfk_2` FOREIGN KEY (`przedmiot_id`) REFERENCES `przedmioty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nieobecnosci`
--
ALTER TABLE `nieobecnosci`
  ADD CONSTRAINT `nieobecnosci_ibfk_1` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plan_dzienny`
--
ALTER TABLE `plan_dzienny`
  ADD CONSTRAINT `plan_dzienny_ibfk_1` FOREIGN KEY (`plan_lekcji_id`) REFERENCES `plan_lekcji` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_dzienny_ibfk_2` FOREIGN KEY (`klasa_id`) REFERENCES `klasy` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_dzienny_ibfk_3` FOREIGN KEY (`przedmiot_id`) REFERENCES `przedmioty` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_dzienny_ibfk_4` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_dzienny_ibfk_5` FOREIGN KEY (`sala_id`) REFERENCES `sale` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `plan_dzienny_ibfk_6` FOREIGN KEY (`oryginalny_nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `plan_lekcji`
--
ALTER TABLE `plan_lekcji`
  ADD CONSTRAINT `plan_lekcji_ibfk_1` FOREIGN KEY (`klasa_id`) REFERENCES `klasy` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_lekcji_ibfk_2` FOREIGN KEY (`przedmiot_id`) REFERENCES `przedmioty` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_lekcji_ibfk_3` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_lekcji_ibfk_4` FOREIGN KEY (`sala_id`) REFERENCES `sale` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sala_nauczyciele`
--
ALTER TABLE `sala_nauczyciele`
  ADD CONSTRAINT `sala_nauczyciele_ibfk_1` FOREIGN KEY (`sala_id`) REFERENCES `sale` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sala_nauczyciele_ibfk_2` FOREIGN KEY (`nauczyciel_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sala_przedmioty`
--
ALTER TABLE `sala_przedmioty`
  ADD CONSTRAINT `sala_przedmioty_ibfk_1` FOREIGN KEY (`sala_id`) REFERENCES `sale` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sala_przedmioty_ibfk_2` FOREIGN KEY (`przedmiot_id`) REFERENCES `przedmioty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sesje_uzytkownikow`
--
ALTER TABLE `sesje_uzytkownikow`
  ADD CONSTRAINT `sesje_uzytkownikow_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `statystyki_generowania`
--
ALTER TABLE `statystyki_generowania`
  ADD CONSTRAINT `statystyki_generowania_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `statystyki_uzytkownikow`
--
ALTER TABLE `statystyki_uzytkownikow`
  ADD CONSTRAINT `statystyki_uzytkownikow_ibfk_1` FOREIGN KEY (`administrator_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `statystyki_uzytkownikow_ibfk_2` FOREIGN KEY (`uzytkownik_docelowy_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `uczniowie`
--
ALTER TABLE `uczniowie`
  ADD CONSTRAINT `uczniowie_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uczniowie_ibfk_2` FOREIGN KEY (`klasa_id`) REFERENCES `klasy` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `zastepstwa`
--
ALTER TABLE `zastepstwa`
  ADD CONSTRAINT `zastepstwa_ibfk_1` FOREIGN KEY (`plan_dzienny_id`) REFERENCES `plan_dzienny` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zastepstwa_ibfk_2` FOREIGN KEY (`nieobecnosc_id`) REFERENCES `nieobecnosci` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zastepstwa_ibfk_3` FOREIGN KEY (`nauczyciel_zastepujacy_id`) REFERENCES `nauczyciele` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
