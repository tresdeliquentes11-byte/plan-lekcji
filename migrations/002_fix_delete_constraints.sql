-- Naprawa constraintów dla usuwania użytkowników
-- Data: 2025-12-02
-- Opis: Zmiana ON DELETE NO ACTION na ON DELETE SET NULL

-- Usuń stary constraint
ALTER TABLE `statystyki_uzytkownikow`
DROP FOREIGN KEY `statystyki_uzytkownikow_ibfk_2`;

-- Dodaj poprawiony constraint z ON DELETE SET NULL
ALTER TABLE `statystyki_uzytkownikow`
ADD CONSTRAINT `statystyki_uzytkownikow_ibfk_2`
FOREIGN KEY (`uzytkownik_docelowy_id`)
REFERENCES `uzytkownicy` (`id`)
ON DELETE SET NULL;

-- Naprawa dla logi_aktywnosci (na wszelki wypadek)
ALTER TABLE `logi_aktywnosci`
DROP FOREIGN KEY IF EXISTS `logi_aktywnosci_ibfk_1`;

ALTER TABLE `logi_aktywnosci`
ADD CONSTRAINT `logi_aktywnosci_ibfk_1`
FOREIGN KEY (`uzytkownik_id`)
REFERENCES `uzytkownicy` (`id`)
ON DELETE SET NULL;

COMMIT;
