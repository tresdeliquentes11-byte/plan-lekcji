-- Baza danych dla systemu planu lekcji
CREATE DATABASE IF NOT EXISTS plan_lekcji CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plan_lekcji;

-- Tabela użytkowników
CREATE TABLE uzytkownicy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    login VARCHAR(50) UNIQUE NOT NULL,
    haslo VARCHAR(255) NOT NULL,
    typ ENUM('dyrektor', 'administrator', 'nauczyciel', 'uczen') NOT NULL,
    imie VARCHAR(100) NOT NULL,
    nazwisko VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    aktywny BOOLEAN DEFAULT TRUE,
    data_utworzenia TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela klas
CREATE TABLE klasy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nazwa VARCHAR(10) UNIQUE NOT NULL,
    wychowawca_id INT,
    ilosc_godzin_dziennie INT DEFAULT 7,
    rozszerzenie_1 VARCHAR(50),
    rozszerzenie_2 VARCHAR(50),
    FOREIGN KEY (wychowawca_id) REFERENCES uzytkownicy(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela uczniów (powiązanie z klasami)
CREATE TABLE uczniowie (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uzytkownik_id INT UNIQUE NOT NULL,
    klasa_id INT NOT NULL,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (klasa_id) REFERENCES klasy(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela przedmiotów
CREATE TABLE przedmioty (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nazwa VARCHAR(100) NOT NULL,
    skrot VARCHAR(20),
    czy_rozszerzony BOOLEAN DEFAULT FALSE,
    domyslna_ilosc_godzin INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela nauczycieli (powiązanie z przedmiotami)
CREATE TABLE nauczyciele (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uzytkownik_id INT UNIQUE NOT NULL,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela kwalifikacji nauczycieli (które przedmioty może uczyć)
CREATE TABLE nauczyciel_przedmioty (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nauczyciel_id INT NOT NULL,
    przedmiot_id INT NOT NULL,
    FOREIGN KEY (nauczyciel_id) REFERENCES nauczyciele(id) ON DELETE CASCADE,
    FOREIGN KEY (przedmiot_id) REFERENCES przedmioty(id) ON DELETE CASCADE,
    UNIQUE KEY unique_nauczyciel_przedmiot (nauczyciel_id, przedmiot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela przypisań przedmiotów do klas
CREATE TABLE klasa_przedmioty (
    id INT PRIMARY KEY AUTO_INCREMENT,
    klasa_id INT NOT NULL,
    przedmiot_id INT NOT NULL,
    nauczyciel_id INT NOT NULL,
    ilosc_godzin_tydzien INT NOT NULL,
    FOREIGN KEY (klasa_id) REFERENCES klasy(id) ON DELETE CASCADE,
    FOREIGN KEY (przedmiot_id) REFERENCES przedmioty(id) ON DELETE CASCADE,
    FOREIGN KEY (nauczyciel_id) REFERENCES nauczyciele(id) ON DELETE CASCADE,
    UNIQUE KEY unique_klasa_przedmiot (klasa_id, przedmiot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela sal
CREATE TABLE sale (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numer VARCHAR(20) UNIQUE NOT NULL,
    nazwa VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela dni wolnych i świąt
CREATE TABLE dni_wolne (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data DATE NOT NULL,
    opis VARCHAR(200),
    UNIQUE KEY unique_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela planu lekcji (szablon tygodniowy)
CREATE TABLE plan_lekcji (
    id INT PRIMARY KEY AUTO_INCREMENT,
    klasa_id INT NOT NULL,
    dzien_tygodnia ENUM('poniedzialek', 'wtorek', 'sroda', 'czwartek', 'piatek') NOT NULL,
    numer_lekcji INT NOT NULL,
    godzina_rozpoczecia TIME NOT NULL,
    godzina_zakonczenia TIME NOT NULL,
    przedmiot_id INT NOT NULL,
    nauczyciel_id INT NOT NULL,
    sala_id INT,
    szablon_tygodniowy BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (klasa_id) REFERENCES klasy(id) ON DELETE CASCADE,
    FOREIGN KEY (przedmiot_id) REFERENCES przedmioty(id) ON DELETE CASCADE,
    FOREIGN KEY (nauczyciel_id) REFERENCES nauczyciele(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id) REFERENCES sale(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela planu na konkretne dni (generowany z szablonu)
CREATE TABLE plan_dzienny (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_lekcji_id INT NOT NULL,
    data DATE NOT NULL,
    klasa_id INT NOT NULL,
    numer_lekcji INT NOT NULL,
    godzina_rozpoczecia TIME NOT NULL,
    godzina_zakonczenia TIME NOT NULL,
    przedmiot_id INT NOT NULL,
    nauczyciel_id INT NOT NULL,
    sala_id INT,
    czy_zastepstwo BOOLEAN DEFAULT FALSE,
    oryginalny_nauczyciel_id INT,
    FOREIGN KEY (plan_lekcji_id) REFERENCES plan_lekcji(id) ON DELETE CASCADE,
    FOREIGN KEY (klasa_id) REFERENCES klasy(id) ON DELETE CASCADE,
    FOREIGN KEY (przedmiot_id) REFERENCES przedmioty(id) ON DELETE CASCADE,
    FOREIGN KEY (nauczyciel_id) REFERENCES nauczyciele(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id) REFERENCES sale(id) ON DELETE SET NULL,
    FOREIGN KEY (oryginalny_nauczyciel_id) REFERENCES nauczyciele(id) ON DELETE SET NULL,
    UNIQUE KEY unique_plan_data (data, klasa_id, numer_lekcji)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela nieobecności nauczycieli
CREATE TABLE nieobecnosci (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nauczyciel_id INT NOT NULL,
    data_od DATE NOT NULL,
    data_do DATE NOT NULL,
    powod VARCHAR(200),
    data_zgloszenia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nauczyciel_id) REFERENCES nauczyciele(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela zastępstw
CREATE TABLE zastepstwa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_dzienny_id INT NOT NULL,
    nieobecnosc_id INT NOT NULL,
    nauczyciel_zastepujacy_id INT NOT NULL,
    data_utworzenia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_dzienny_id) REFERENCES plan_dzienny(id) ON DELETE CASCADE,
    FOREIGN KEY (nieobecnosc_id) REFERENCES nieobecnosci(id) ON DELETE CASCADE,
    FOREIGN KEY (nauczyciel_zastepujacy_id) REFERENCES nauczyciele(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wstępne dane - przedmioty
INSERT INTO przedmioty (nazwa, skrot, czy_rozszerzony, domyslna_ilosc_godzin) VALUES
('Matematyka', 'MAT', FALSE, 5),
('Matematyka rozszerzona', 'MAT-R', TRUE, 3),
('Język polski', 'POL', FALSE, 5),
('Język angielski', 'ANG', FALSE, 4),
('Język angielski rozszerzony', 'ANG-R', TRUE, 3),
('Geografia', 'GEO', FALSE, 3),
('Biologia', 'BIO', FALSE, 3),
('Chemia', 'CHEM', FALSE, 3),
('Fizyka', 'FIZ', FALSE, 3),
('Fizyka rozszerzona', 'FIZ-R', TRUE, 3),
('Język niemiecki', 'NIEM', FALSE, 3),
('Język hiszpański', 'HISZ', FALSE, 3),
('Historia', 'HIST', FALSE, 2),
('WOS', 'WOS', FALSE, 2),
('WF', 'WF', FALSE, 4),
('Informatyka', 'INF', FALSE, 2);

-- Wstępne dane - klasy
INSERT INTO klasy (nazwa, ilosc_godzin_dziennie) VALUES
('1A', 7), ('1B', 7), ('1C', 7),
('2A', 7), ('2B', 7), ('2C', 7),
('3A', 7), ('3B', 7), ('3C', 7),
('4A', 8), ('4B', 8), ('4C', 8);

-- Domyślny użytkownik - dyrektor (hasło: dyrektor123)
-- Hash wygenerowany dla hasła 'dyrektor123'
INSERT INTO uzytkownicy (login, haslo, typ, imie, nazwisko, email) VALUES
('dyrektor', '$2y$10$kZ5H3YvN8qX9mW7pL4rJ1.eF2vK8sT6nM9bC1dA3gH4jE5fI6lO7m', 'dyrektor', 'Jan', 'Kowalski', 'dyrektor@szkola.pl');

-- Domyślny użytkownik - administrator (hasło: admin123)
-- Hash wygenerowany dla hasła 'admin123'
INSERT INTO uzytkownicy (login, haslo, typ, imie, nazwisko, email) VALUES
('admin', '$2y$10$vT9B2nX8cQ7fM6gR5jK4sL.aP3bY1eU4wO8zD9hN6iC2mF5tE7kA1', 'administrator', 'Anna', 'Nowak', 'admin@szkola.pl');

-- Przykładowe sale
INSERT INTO sale (numer, nazwa) VALUES
('101', 'Sala matematyczna'),
('102', 'Sala językowa'),
('103', 'Sala polonistyczna'),
('104', 'Sala historyczna'),
('105', 'Sala geograficzna'),
('201', 'Pracownia fizyczna'),
('202', 'Pracownia chemiczna'),
('203', 'Pracownia biologiczna'),
('204', 'Pracownia informatyczna'),
('SALA-WF', 'Sala gimnastyczna');
