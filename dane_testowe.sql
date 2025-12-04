-- =====================================================

-- DANE TESTOWE DLA GENERATORA PLANU LEKCJI

-- Liceum: 10 przedmiotów, 20 klas (1A-5D), 500 uczniów, 55 nauczycieli, 30 sal

-- =====================================================

 

-- Wyłącz sprawdzanie kluczy obcych dla szybszego insertu

SET FOREIGN_KEY_CHECKS = 0;

 

-- =====================================================

-- 1. PRZEDMIOTY (10 przedmiotów licealnych)

-- =====================================================

 

INSERT INTO przedmioty (id, nazwa, skrot, czy_rozszerzony, domyslna_ilosc_godzin) VALUES

(1, 'Matematyka', 'MAT', 0, 4),

(2, 'Matematyka rozszerzona', 'MAT-R', 1, 6),

(3, 'Fizyka', 'FIZ', 0, 2),

(4, 'Fizyka rozszerzona', 'FIZ-R', 1, 4),

(5, 'Chemia', 'CHEM', 0, 2),

(6, 'Chemia rozszerzona', 'CHEM-R', 1, 4),

(7, 'Biologia', 'BIO', 0, 2),

(8, 'Biologia rozszerzona', 'BIO-R', 1, 4),

(9, 'Język polski', 'POL', 0, 4),

(10, 'Język angielski', 'ANG', 0, 3),

(11, 'Język angielski rozszerzony', 'ANG-R', 1, 5),

(12, 'Historia', 'HIST', 0, 2),

(13, 'Historia rozszerzona', 'HIST-R', 1, 4),

(14, 'Geografia', 'GEO', 0, 2),

(15, 'Geografia rozszerzona', 'GEO-R', 1, 4),

(16, 'Informatyka', 'INF', 0, 2),

(17, 'Informatyka rozszerzona', 'INF-R', 1, 4),

(18, 'Wiedza o społeczeństwie', 'WOS', 0, 1),

(19, 'Wychowanie fizyczne', 'WF', 0, 3);

 

-- =====================================================

-- 2. KLASY (1A-5D = 20 klas)

-- =====================================================

 

INSERT INTO klasy (id, nazwa, ilosc_godzin_dziennie, rozszerzenie_1, rozszerzenie_2) VALUES

-- Klasa 1 (profil mat-fiz)

(1, '1A', 7, 'Matematyka rozszerzona', 'Fizyka rozszerzona'),

(2, '1B', 7, 'Matematyka rozszerzona', 'Informatyka rozszerzona'),

(3, '1C', 7, 'Biologia rozszerzona', 'Chemia rozszerzona'),

(4, '1D', 7, 'Historia rozszerzona', 'Geografia rozszerzona'),

-- Klasa 2 (profil mat-fiz)

(5, '2A', 7, 'Matematyka rozszerzona', 'Fizyka rozszerzona'),

(6, '2B', 7, 'Matematyka rozszerzona', 'Informatyka rozszerzona'),

(7, '2C', 7, 'Biologia rozszerzona', 'Chemia rozszerzona'),

(8, '2D', 7, 'Historia rozszerzona', 'Język angielski rozszerzony'),

-- Klasa 3 (profil mat-fiz)

(9, '3A', 7, 'Matematyka rozszerzona', 'Fizyka rozszerzona'),

(10, '3B', 7, 'Matematyka rozszerzona', 'Informatyka rozszerzona'),

(11, '3C', 7, 'Biologia rozszerzona', 'Chemia rozszerzona'),

(12, '3D', 7, 'Historia rozszerzona', 'Geografia rozszerzona'),

-- Klasa 4 (matura)

(13, '4A', 7, 'Matematyka rozszerzona', 'Fizyka rozszerzona'),

(14, '4B', 7, 'Matematyka rozszerzona', 'Informatyka rozszerzona'),

(15, '4C', 7, 'Biologia rozszerzona', 'Chemia rozszerzona'),

(16, '4D', 7, 'Historia rozszerzona', 'Język angielski rozszerzony'),

-- Klasa 5 (poprawkowa)

(17, '5A', 7, 'Matematyka rozszerzona', 'Fizyka rozszerzona'),

(18, '5B', 7, 'Matematyka rozszerzona', 'Informatyka rozszerzona'),

(19, '5C', 7, 'Biologia rozszerzona', 'Chemia rozszerzona'),

(20, '5D', 7, 'Historia rozszerzona', 'Geografia rozszerzona');

 

-- =====================================================

-- 3. NAUCZYCIELE (55 nauczycieli)

-- =====================================================

 

-- Najpierw dodajemy użytkowników-nauczycieli

INSERT INTO uzytkownicy (id, login, haslo, typ, imie, nazwisko, email, aktywny) VALUES

-- Matematycy (10)

(101, 'n.kowalski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Nowak', 'Kowalski', 'n.kowalski@liceum.pl', 1),

(102, 'a.nowak', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Anna', 'Nowak', 'a.nowak@liceum.pl', 1),

(103, 'p.wisniewski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Piotr', 'Wiśniewski', 'p.wisniewski@liceum.pl', 1),

(104, 'm.wojcik', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Maria', 'Wójcik', 'm.wojcik@liceum.pl', 1),

(105, 'j.kowalczyk', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Jan', 'Kowalczyk', 'j.kowalczyk@liceum.pl', 1),

(106, 'k.kaminski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Katarzyna', 'Kamiński', 'k.kaminski@liceum.pl', 1),

(107, 't.lewandowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Tomasz', 'Lewandowski', 't.lewandowski@liceum.pl', 1),

(108, 'e.zielinski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Ewa', 'Zieliński', 'e.zielinski@liceum.pl', 1),

(109, 'r.szymanski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Robert', 'Szymański', 'r.szymanski@liceum.pl', 1),

(110, 'd.wozniak', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Dorota', 'Woźniak', 'd.wozniak@liceum.pl', 1),

-- Fizycy (6)

(111, 'a.dąbrowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Andrzej', 'Dąbrowski', 'a.dabrowski@liceum.pl', 1),

(112, 'm.kozlowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Michał', 'Kozłowski', 'm.kozlowski@liceum.pl', 1),

(113, 'b.jankowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Barbara', 'Jankowski', 'b.jankowski@liceum.pl', 1),

(114, 'g.mazur', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Grzegorz', 'Mazur', 'g.mazur@liceum.pl', 1),

(115, 'i.wojciechowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Irena', 'Wojciechowski', 'i.wojciechowski@liceum.pl', 1),

(116, 'l.krawczyk', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Leszek', 'Krawczyk', 'l.krawczyk@liceum.pl', 1),

-- Chemicy (5)

(117, 'h.krol', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Helena', 'Król', 'h.krol@liceum.pl', 1),

(118, 'w.piotrowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Wojciech', 'Piotrowski', 'w.piotrowski@liceum.pl', 1),

(119, 'z.grabowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Zofia', 'Grabowski', 'z.grabowski@liceum.pl', 1),

(120, 's.pawlak', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Stanisław', 'Pawlak', 's.pawlak@liceum.pl', 1),

(121, 'u.michalski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Urszula', 'Michalski', 'u.michalski@liceum.pl', 1),

-- Biolodzy (5)

(122, 'o.nowicki', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Olga', 'Nowicki', 'o.nowicki@liceum.pl', 1),

(123, 'f.adamczyk', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Filip', 'Adamczyk', 'f.adamczyk@liceum.pl', 1),

(124, 'n.dudek', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Natalia', 'Dudek', 'n.dudek@liceum.pl', 1),

(125, 'x.zajac', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Ksawery', 'Zając', 'x.zajac@liceum.pl', 1),

(126, 'c.wieczorek', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Celina', 'Wieczorek', 'c.wieczorek@liceum.pl', 1),

-- Poloniści (5)

(127, 'q.sikora', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Quentyna', 'Sikora', 'q.sikora@liceum.pl', 1),

(128, 'y.baran', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Yolanda', 'Baran', 'y.baran@liceum.pl', 1),

(129, 'v.rutkowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Victor', 'Rutkowski', 'v.rutkowski@liceum.pl', 1),

(130, 'ag.jaworski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Agnieszka', 'Jaworski', 'ag.jaworski@liceum.pl', 1),

(131, 'bg.kwiatkowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Bogdan', 'Kwiatkowski', 'bg.kwiatkowski@liceum.pl', 1),

-- Anglicy (6)

(132, 'cg.kucharski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Cecylia', 'Kucharski', 'cg.kucharski@liceum.pl', 1),

(133, 'dg.mazurek', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Damian', 'Mazurek', 'dg.mazurek@liceum.pl', 1),

(134, 'eg.sawicki', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Elżbieta', 'Sawicki', 'eg.sawicki@liceum.pl', 1),

(135, 'fg.olszewski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Fryderyk', 'Olszewski', 'fg.olszewski@liceum.pl', 1),

(136, 'gg.maciejewski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Grażyna', 'Maciejewski', 'gg.maciejewski@liceum.pl', 1),

(137, 'hg.tomaszewski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Henryk', 'Tomaszewski', 'hg.tomaszewski@liceum.pl', 1),

-- Historycy (5)

(138, 'ig.stępień', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Iwona', 'Stępień', 'ig.stepien@liceum.pl', 1),

(139, 'jg.gorski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Jacek', 'Górski', 'jg.gorski@liceum.pl', 1),

(140, 'kg.wlodarczyk', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Krystyna', 'Włodarczyk', 'kg.wlodarczyk@liceum.pl', 1),

(141, 'lg.witkowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Ludwik', 'Witkowski', 'lg.witkowski@liceum.pl', 1),

(142, 'mg.walczak', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Małgorzata', 'Walczak', 'mg.walczak@liceum.pl', 1),

-- Geografowie (4)

(143, 'ng.stepkowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Norbert', 'Stępkowski', 'ng.stepkowski@liceum.pl', 1),

(144, 'og.sobczak', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Otylia', 'Sobczak', 'og.sobczak@liceum.pl', 1),

(145, 'pg.czerwinski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Paweł', 'Czerwiński', 'pg.czerwinski@liceum.pl', 1),

(146, 'rg.borkowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Regina', 'Borkowski', 'rg.borkowski@liceum.pl', 1),

-- Informatycy (5)

(147, 'sg.sokolowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Sebastian', 'Sokołowski', 'sg.sokolowski@liceum.pl', 1),

(148, 'tg.bielecki', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Teresa', 'Bielecki', 'tg.bielecki@liceum.pl', 1),

(149, 'ug.szczepanski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Urszula', 'Szczepański', 'ug.szczepanski@liceum.pl', 1),

(150, 'wg.sadowski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Waldemar', 'Sadowski', 'wg.sadowski@liceum.pl', 1),

(151, 'xg.czarnecki', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Ксения', 'Czarnecki', 'xg.czarnecki@liceum.pl', 1),

-- WF (4)

(152, 'yg.sł', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Ygnacy', 'Słomiński', 'yg.slominski@liceum.pl', 1),

(153, 'zg.glowacki', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Zdzisław', 'Głowacki', 'zg.glowacki@liceum.pl', 1),

(154, 'ah.zakrzewski', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Aleksandra', 'Zakrzewski', 'ah.zakrzewski@liceum.pl', 1),

(155, 'bh.krupa', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'nauczyciel', 'Bronisław', 'Krupa', 'bh.krupa@liceum.pl', 1);

 

-- Dodaj nauczycieli do tabeli nauczyciele

INSERT INTO nauczyciele (id, uzytkownik_id) VALUES

(1, 101), (2, 102), (3, 103), (4, 104), (5, 105), (6, 106), (7, 107), (8, 108), (9, 109), (10, 110),

(11, 111), (12, 112), (13, 113), (14, 114), (15, 115), (16, 116),

(17, 117), (18, 118), (19, 119), (20, 120), (21, 121),

(22, 122), (23, 123), (24, 124), (25, 125), (26, 126),

(27, 127), (28, 128), (29, 129), (30, 130), (31, 131),

(32, 132), (33, 133), (34, 134), (35, 135), (36, 136), (37, 137),

(38, 138), (39, 139), (40, 140), (41, 141), (42, 142),

(43, 143), (44, 144), (45, 145), (46, 146),

(47, 147), (48, 148), (49, 149), (50, 150), (51, 151),

(52, 152), (53, 153), (54, 154), (55, 155);

 

-- =====================================================

-- 4. PRZYPISANIE PRZEDMIOTÓW DO NAUCZYCIELI (min 2 na nauczyciela)

-- =====================================================

 

INSERT INTO nauczyciel_przedmioty (nauczyciel_id, przedmiot_id) VALUES

-- Matematycy (podstawowa + rozszerzona + fizyka/informatyka)

(1, 1), (1, 2), (1, 3),

(2, 1), (2, 2), (2, 16),

(3, 1), (3, 2), (3, 4),

(4, 1), (4, 2), (4, 3),

(5, 1), (5, 2), (5, 17),

(6, 1), (6, 2), (6, 16),

(7, 1), (7, 2), (7, 3),

(8, 1), (8, 2), (8, 4),

(9, 1), (9, 2), (9, 17),

(10, 1), (10, 2), (10, 16),

-- Fizycy (podstawowa + rozszerzona + matematyka)

(11, 3), (11, 4), (11, 1),

(12, 3), (12, 4), (12, 2),

(13, 3), (13, 4), (13, 1),

(14, 3), (14, 4), (14, 16),

(15, 3), (15, 4), (15, 1),

(16, 3), (16, 4), (16, 2),

-- Chemicy (podstawowa + rozszerzona + biologia)

(17, 5), (17, 6), (17, 7),

(18, 5), (18, 6), (18, 8),

(19, 5), (19, 6), (19, 7),

(20, 5), (20, 6), (20, 8),

(21, 5), (21, 6), (21, 7),

-- Biolodzy (podstawowa + rozszerzona + chemia)

(22, 7), (22, 8), (22, 5),

(23, 7), (23, 8), (23, 6),

(24, 7), (24, 8), (24, 5),

(25, 7), (25, 8), (25, 6),

(26, 7), (26, 8), (26, 5),

-- Poloniści (polski + WOS)

(27, 9), (27, 18),

(28, 9), (28, 18),

(29, 9), (29, 18),

(30, 9), (30, 18),

(31, 9), (31, 18),

-- Anglicy (podstawowy + rozszerzony)

(32, 10), (32, 11),

(33, 10), (33, 11),

(34, 10), (34, 11),

(35, 10), (35, 11),

(36, 10), (36, 11),

(37, 10), (37, 11),

-- Historycy (podstawowa + rozszerzona + geografia/WOS)

(38, 12), (38, 13), (38, 18),

(39, 12), (39, 13), (39, 14),

(40, 12), (40, 13), (40, 18),

(41, 12), (41, 13), (41, 15),

(42, 12), (42, 13), (42, 18),

-- Geografowie (podstawowa + rozszerzona + historia)

(43, 14), (43, 15), (43, 12),

(44, 14), (44, 15), (44, 13),

(45, 14), (45, 15), (45, 12),

(46, 14), (46, 15), (46, 13),

-- Informatycy (podstawowa + rozszerzona + matematyka)

(47, 16), (47, 17), (47, 1),

(48, 16), (48, 17), (48, 2),

(49, 16), (49, 17), (49, 1),

(50, 16), (50, 17), (50, 2),

(51, 16), (51, 17), (51, 1),

-- WF (tylko WF)

(52, 19), (53, 19), (54, 19), (55, 19);

 

-- =====================================================

-- 5. GODZINY PRACY NAUCZYCIELI (pon-pt 8:00-16:00)

-- =====================================================

 

INSERT INTO nauczyciel_godziny_pracy (nauczyciel_id, dzien_tygodnia, godzina_od, godzina_do) VALUES

-- Dla każdego nauczyciela, wszystkie dni tygodnia

-- Matematycy

(1, 1, '08:00', '16:00'), (1, 2, '08:00', '16:00'), (1, 3, '08:00', '16:00'), (1, 4, '08:00', '16:00'), (1, 5, '08:00', '16:00'),

(2, 1, '08:00', '16:00'), (2, 2, '08:00', '16:00'), (2, 3, '08:00', '16:00'), (2, 4, '08:00', '16:00'), (2, 5, '08:00', '16:00'),

(3, 1, '08:00', '16:00'), (3, 2, '08:00', '16:00'), (3, 3, '08:00', '16:00'), (3, 4, '08:00', '16:00'), (3, 5, '08:00', '16:00'),

(4, 1, '08:00', '16:00'), (4, 2, '08:00', '16:00'), (4, 3, '08:00', '16:00'), (4, 4, '08:00', '16:00'), (4, 5, '08:00', '16:00'),

(5, 1, '08:00', '16:00'), (5, 2, '08:00', '16:00'), (5, 3, '08:00', '16:00'), (5, 4, '08:00', '16:00'), (5, 5, '08:00', '16:00'),

(6, 1, '08:00', '16:00'), (6, 2, '08:00', '16:00'), (6, 3, '08:00', '16:00'), (6, 4, '08:00', '16:00'), (6, 5, '08:00', '16:00'),

(7, 1, '08:00', '16:00'), (7, 2, '08:00', '16:00'), (7, 3, '08:00', '16:00'), (7, 4, '08:00', '16:00'), (7, 5, '08:00', '16:00'),

(8, 1, '08:00', '16:00'), (8, 2, '08:00', '16:00'), (8, 3, '08:00', '16:00'), (8, 4, '08:00', '16:00'), (8, 5, '08:00', '16:00'),

(9, 1, '08:00', '16:00'), (9, 2, '08:00', '16:00'), (9, 3, '08:00', '16:00'), (9, 4, '08:00', '16:00'), (9, 5, '08:00', '16:00'),

(10, 1, '08:00', '16:00'), (10, 2, '08:00', '16:00'), (10, 3, '08:00', '16:00'), (10, 4, '08:00', '16:00'), (10, 5, '08:00', '16:00'),

-- Fizycy

(11, 1, '08:00', '16:00'), (11, 2, '08:00', '16:00'), (11, 3, '08:00', '16:00'), (11, 4, '08:00', '16:00'), (11, 5, '08:00', '16:00'),

(12, 1, '08:00', '16:00'), (12, 2, '08:00', '16:00'), (12, 3, '08:00', '16:00'), (12, 4, '08:00', '16:00'), (12, 5, '08:00', '16:00'),

(13, 1, '08:00', '16:00'), (13, 2, '08:00', '16:00'), (13, 3, '08:00', '16:00'), (13, 4, '08:00', '16:00'), (13, 5, '08:00', '16:00'),

(14, 1, '08:00', '16:00'), (14, 2, '08:00', '16:00'), (14, 3, '08:00', '16:00'), (14, 4, '08:00', '16:00'), (14, 5, '08:00', '16:00'),

(15, 1, '08:00', '16:00'), (15, 2, '08:00', '16:00'), (15, 3, '08:00', '16:00'), (15, 4, '08:00', '16:00'), (15, 5, '08:00', '16:00'),

(16, 1, '08:00', '16:00'), (16, 2, '08:00', '16:00'), (16, 3, '08:00', '16:00'), (16, 4, '08:00', '16:00'), (16, 5, '08:00', '16:00'),

-- Chemicy

(17, 1, '08:00', '16:00'), (17, 2, '08:00', '16:00'), (17, 3, '08:00', '16:00'), (17, 4, '08:00', '16:00'), (17, 5, '08:00', '16:00'),

(18, 1, '08:00', '16:00'), (18, 2, '08:00', '16:00'), (18, 3, '08:00', '16:00'), (18, 4, '08:00', '16:00'), (18, 5, '08:00', '16:00'),

(19, 1, '08:00', '16:00'), (19, 2, '08:00', '16:00'), (19, 3, '08:00', '16:00'), (19, 4, '08:00', '16:00'), (19, 5, '08:00', '16:00'),

(20, 1, '08:00', '16:00'), (20, 2, '08:00', '16:00'), (20, 3, '08:00', '16:00'), (20, 4, '08:00', '16:00'), (20, 5, '08:00', '16:00'),

(21, 1, '08:00', '16:00'), (21, 2, '08:00', '16:00'), (21, 3, '08:00', '16:00'), (21, 4, '08:00', '16:00'), (21, 5, '08:00', '16:00'),

-- Biolodzy

(22, 1, '08:00', '16:00'), (22, 2, '08:00', '16:00'), (22, 3, '08:00', '16:00'), (22, 4, '08:00', '16:00'), (22, 5, '08:00', '16:00'),

(23, 1, '08:00', '16:00'), (23, 2, '08:00', '16:00'), (23, 3, '08:00', '16:00'), (23, 4, '08:00', '16:00'), (23, 5, '08:00', '16:00'),

(24, 1, '08:00', '16:00'), (24, 2, '08:00', '16:00'), (24, 3, '08:00', '16:00'), (24, 4, '08:00', '16:00'), (24, 5, '08:00', '16:00'),

(25, 1, '08:00', '16:00'), (25, 2, '08:00', '16:00'), (25, 3, '08:00', '16:00'), (25, 4, '08:00', '16:00'), (25, 5, '08:00', '16:00'),

(26, 1, '08:00', '16:00'), (26, 2, '08:00', '16:00'), (26, 3, '08:00', '16:00'), (26, 4, '08:00', '16:00'), (26, 5, '08:00', '16:00'),

-- Poloniści

(27, 1, '08:00', '16:00'), (27, 2, '08:00', '16:00'), (27, 3, '08:00', '16:00'), (27, 4, '08:00', '16:00'), (27, 5, '08:00', '16:00'),

(28, 1, '08:00', '16:00'), (28, 2, '08:00', '16:00'), (28, 3, '08:00', '16:00'), (28, 4, '08:00', '16:00'), (28, 5, '08:00', '16:00'),

(29, 1, '08:00', '16:00'), (29, 2, '08:00', '16:00'), (29, 3, '08:00', '16:00'), (29, 4, '08:00', '16:00'), (29, 5, '08:00', '16:00'),

(30, 1, '08:00', '16:00'), (30, 2, '08:00', '16:00'), (30, 3, '08:00', '16:00'), (30, 4, '08:00', '16:00'), (30, 5, '08:00', '16:00'),

(31, 1, '08:00', '16:00'), (31, 2, '08:00', '16:00'), (31, 3, '08:00', '16:00'), (31, 4, '08:00', '16:00'), (31, 5, '08:00', '16:00'),

-- Anglicy

(32, 1, '08:00', '16:00'), (32, 2, '08:00', '16:00'), (32, 3, '08:00', '16:00'), (32, 4, '08:00', '16:00'), (32, 5, '08:00', '16:00'),

(33, 1, '08:00', '16:00'), (33, 2, '08:00', '16:00'), (33, 3, '08:00', '16:00'), (33, 4, '08:00', '16:00'), (33, 5, '08:00', '16:00'),

(34, 1, '08:00', '16:00'), (34, 2, '08:00', '16:00'), (34, 3, '08:00', '16:00'), (34, 4, '08:00', '16:00'), (34, 5, '08:00', '16:00'),

(35, 1, '08:00', '16:00'), (35, 2, '08:00', '16:00'), (35, 3, '08:00', '16:00'), (35, 4, '08:00', '16:00'), (35, 5, '08:00', '16:00'),

(36, 1, '08:00', '16:00'), (36, 2, '08:00', '16:00'), (36, 3, '08:00', '16:00'), (36, 4, '08:00', '16:00'), (36, 5, '08:00', '16:00'),

(37, 1, '08:00', '16:00'), (37, 2, '08:00', '16:00'), (37, 3, '08:00', '16:00'), (37, 4, '08:00', '16:00'), (37, 5, '08:00', '16:00'),

-- Historycy

(38, 1, '08:00', '16:00'), (38, 2, '08:00', '16:00'), (38, 3, '08:00', '16:00'), (38, 4, '08:00', '16:00'), (38, 5, '08:00', '16:00'),

(39, 1, '08:00', '16:00'), (39, 2, '08:00', '16:00'), (39, 3, '08:00', '16:00'), (39, 4, '08:00', '16:00'), (39, 5, '08:00', '16:00'),

(40, 1, '08:00', '16:00'), (40, 2, '08:00', '16:00'), (40, 3, '08:00', '16:00'), (40, 4, '08:00', '16:00'), (40, 5, '08:00', '16:00'),

(41, 1, '08:00', '16:00'), (41, 2, '08:00', '16:00'), (41, 3, '08:00', '16:00'), (41, 4, '08:00', '16:00'), (41, 5, '08:00', '16:00'),

(42, 1, '08:00', '16:00'), (42, 2, '08:00', '16:00'), (42, 3, '08:00', '16:00'), (42, 4, '08:00', '16:00'), (42, 5, '08:00', '16:00'),

-- Geografowie

(43, 1, '08:00', '16:00'), (43, 2, '08:00', '16:00'), (43, 3, '08:00', '16:00'), (43, 4, '08:00', '16:00'), (43, 5, '08:00', '16:00'),

(44, 1, '08:00', '16:00'), (44, 2, '08:00', '16:00'), (44, 3, '08:00', '16:00'), (44, 4, '08:00', '16:00'), (44, 5, '08:00', '16:00'),

(45, 1, '08:00', '16:00'), (45, 2, '08:00', '16:00'), (45, 3, '08:00', '16:00'), (45, 4, '08:00', '16:00'), (45, 5, '08:00', '16:00'),

(46, 1, '08:00', '16:00'), (46, 2, '08:00', '16:00'), (46, 3, '08:00', '16:00'), (46, 4, '08:00', '16:00'), (46, 5, '08:00', '16:00'),

-- Informatycy

(47, 1, '08:00', '16:00'), (47, 2, '08:00', '16:00'), (47, 3, '08:00', '16:00'), (47, 4, '08:00', '16:00'), (47, 5, '08:00', '16:00'),

(48, 1, '08:00', '16:00'), (48, 2, '08:00', '16:00'), (48, 3, '08:00', '16:00'), (48, 4, '08:00', '16:00'), (48, 5, '08:00', '16:00'),

(49, 1, '08:00', '16:00'), (49, 2, '08:00', '16:00'), (49, 3, '08:00', '16:00'), (49, 4, '08:00', '16:00'), (49, 5, '08:00', '16:00'),

(50, 1, '08:00', '16:00'), (50, 2, '08:00', '16:00'), (50, 3, '08:00', '16:00'), (50, 4, '08:00', '16:00'), (50, 5, '08:00', '16:00'),

(51, 1, '08:00', '16:00'), (51, 2, '08:00', '16:00'), (51, 3, '08:00', '16:00'), (51, 4, '08:00', '16:00'), (51, 5, '08:00', '16:00'),

-- WF

(52, 1, '08:00', '16:00'), (52, 2, '08:00', '16:00'), (52, 3, '08:00', '16:00'), (52, 4, '08:00', '16:00'), (52, 5, '08:00', '16:00'),

(53, 1, '08:00', '16:00'), (53, 2, '08:00', '16:00'), (53, 3, '08:00', '16:00'), (53, 4, '08:00', '16:00'), (53, 5, '08:00', '16:00'),

(54, 1, '08:00', '16:00'), (54, 2, '08:00', '16:00'), (54, 3, '08:00', '16:00'), (54, 4, '08:00', '16:00'), (54, 5, '08:00', '16:00'),

(55, 1, '08:00', '16:00'), (55, 2, '08:00', '16:00'), (55, 3, '08:00', '16:00'), (55, 4, '08:00', '16:00'), (55, 5, '08:00', '16:00');

 

-- =====================================================

-- 6. SALE (30 sal)

-- =====================================================

 

INSERT INTO sale (id, numer, nazwa, typ, pojemnosc) VALUES

(1, '101', 'Sala matematyczna 1', 'standardowa', 30),

(2, '102', 'Sala matematyczna 2', 'standardowa', 30),

(3, '103', 'Sala matematyczna 3', 'standardowa', 30),

(4, '201', 'Pracownia fizyczna 1', 'pracownia', 25),

(5, '202', 'Pracownia fizyczna 2', 'pracownia', 25),

(6, '203', 'Pracownia chemiczna 1', 'pracownia', 25),

(7, '204', 'Pracownia chemiczna 2', 'pracownia', 25),

(8, '205', 'Pracownia biologiczna 1', 'pracownia', 25),

(9, '206', 'Pracownia biologiczna 2', 'pracownia', 25),

(10, '301', 'Pracownia informatyczna 1', 'pracownia', 28),

(11, '302', 'Pracownia informatyczna 2', 'pracownia', 28),

(12, '303', 'Pracownia informatyczna 3', 'pracownia', 28),

(13, '401', 'Sala językowa 1', 'standardowa', 30),

(14, '402', 'Sala językowa 2', 'standardowa', 30),

(15, '403', 'Sala językowa 3', 'standardowa', 30),

(16, '404', 'Sala językowa 4', 'standardowa', 30),

(17, '501', 'Sala humanistyczna 1', 'standardowa', 32),

(18, '502', 'Sala humanistyczna 2', 'standardowa', 32),

(19, '503', 'Sala humanistyczna 3', 'standardowa', 32),

(20, '504', 'Sala humanistyczna 4', 'standardowa', 32),

(21, '505', 'Sala humanistyczna 5', 'standardowa', 32),

(22, '601', 'Sala geograficzna 1', 'standardowa', 30),

(23, '602', 'Sala geograficzna 2', 'standardowa', 30),

(24, '701', 'Sala ogólna 1', 'standardowa', 35),

(25, '702', 'Sala ogólna 2', 'standardowa', 35),

(26, '703', 'Sala ogólna 3', 'standardowa', 35),

(27, '704', 'Sala ogólna 4', 'standardowa', 35),

(28, 'S1', 'Sala gimnastyczna duża', 'sportowa', 60),

(29, 'S2', 'Sala gimnastyczna mała', 'sportowa', 40),

(30, 'AULA', 'Aula szkolna', 'specjalna', 150);

 

-- =====================================================

-- 7. PRZYPISANIE SAL DO PRZEDMIOTÓW

-- =====================================================

 

INSERT INTO sala_przedmioty (sala_id, przedmiot_id) VALUES

-- Sale matematyczne

(1, 1), (1, 2), (2, 1), (2, 2), (3, 1), (3, 2),

-- Pracownie fizyczne

(4, 3), (4, 4), (5, 3), (5, 4),

-- Pracownie chemiczne

(6, 5), (6, 6), (7, 5), (7, 6),

-- Pracownie biologiczne

(8, 7), (8, 8), (9, 7), (9, 8),

-- Pracownie informatyczne

(10, 16), (10, 17), (11, 16), (11, 17), (12, 16), (12, 17),

-- Sale językowe

(13, 10), (13, 11), (14, 10), (14, 11), (15, 10), (15, 11), (16, 10), (16, 11),

-- Sale humanistyczne

(17, 9), (17, 12), (17, 13), (18, 9), (18, 12), (18, 13),

(19, 9), (19, 12), (19, 13), (20, 9), (20, 12), (20, 13), (21, 9), (21, 18),

-- Sale geograficzne

(22, 14), (22, 15), (23, 14), (23, 15),

-- Sale ogólne (wszystkie przedmioty podstawowe)

(24, 1), (24, 9), (24, 10), (24, 18),

(25, 1), (25, 9), (25, 10), (25, 18),

(26, 1), (26, 9), (26, 10), (26, 18),

(27, 1), (27, 9), (27, 10), (27, 18),

-- Sale sportowe

(28, 19), (29, 19);

 

-- =====================================================

-- 8. PRZYPISANIE SAL DO NAUCZYCIELI

-- =====================================================

 

INSERT INTO sala_nauczyciele (sala_id, nauczyciel_id) VALUES

-- Sale matematyczne - matematycy

(1, 1), (1, 2), (1, 3), (2, 4), (2, 5), (2, 6), (3, 7), (3, 8), (3, 9), (3, 10),

-- Pracownie fizyczne - fizycy

(4, 11), (4, 12), (4, 13), (5, 14), (5, 15), (5, 16),

-- Pracownie chemiczne - chemicy

(6, 17), (6, 18), (6, 19), (7, 20), (7, 21),

-- Pracownie biologiczne - biolodzy

(8, 22), (8, 23), (8, 24), (9, 25), (9, 26),

-- Pracownie informatyczne - informatycy

(10, 47), (10, 48), (11, 49), (11, 50), (12, 51),

-- Sale językowe - anglicy

(13, 32), (13, 33), (14, 34), (14, 35), (15, 36), (16, 37),

-- Sale humanistyczne - poloniści i historycy

(17, 27), (17, 38), (18, 28), (18, 39), (19, 29), (19, 40),

(20, 30), (20, 41), (21, 31), (21, 42),

-- Sale geograficzne - geografowie

(22, 43), (22, 44), (23, 45), (23, 46),

-- Sale ogólne - wszyscy

(24, 1), (24, 27), (25, 2), (25, 28), (26, 3), (26, 29), (27, 4), (27, 30),

-- Sale sportowe - nauczyciele WF

(28, 52), (28, 53), (29, 54), (29, 55);

 

-- =====================================================

-- 9. PRZYPISANIE PRZEDMIOTÓW DO KLAS (z nauczycielami i ilością godzin)

-- =====================================================

 

-- KLASA 1A (mat-fiz)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(1, 1, 1, 4), (1, 2, 1, 2), (1, 3, 11, 2), (1, 4, 11, 2), (1, 9, 27, 4),

(1, 10, 32, 3), (1, 12, 38, 2), (1, 14, 43, 2), (1, 18, 38, 1), (1, 19, 52, 3);

 

-- KLASA 1B (mat-inf)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(2, 1, 2, 4), (2, 2, 2, 2), (2, 16, 47, 2), (2, 17, 47, 2), (2, 9, 27, 4),

(2, 10, 32, 3), (2, 3, 11, 2), (2, 12, 38, 2), (2, 18, 38, 1), (2, 19, 52, 3);

 

-- KLASA 1C (bio-chem)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(3, 1, 3, 4), (3, 7, 22, 2), (3, 8, 22, 2), (3, 5, 17, 2), (3, 6, 17, 2),

(3, 9, 28, 4), (3, 10, 33, 3), (3, 12, 39, 2), (3, 18, 39, 1), (3, 19, 53, 3);

 

-- KLASA 1D (hist-geo)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(4, 1, 4, 4), (4, 12, 38, 2), (4, 13, 38, 2), (4, 14, 43, 2), (4, 15, 43, 2),

(4, 9, 28, 4), (4, 10, 33, 3), (4, 3, 12, 2), (4, 18, 40, 1), (4, 19, 53, 3);

 

-- KLASA 2A (mat-fiz)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(5, 1, 5, 4), (5, 2, 5, 2), (5, 3, 12, 2), (5, 4, 12, 2), (5, 9, 29, 4),

(5, 10, 34, 3), (5, 12, 39, 2), (5, 14, 44, 2), (5, 18, 39, 1), (5, 19, 54, 3);

 

-- KLASA 2B (mat-inf)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(6, 1, 6, 4), (6, 2, 6, 2), (6, 16, 48, 2), (6, 17, 48, 2), (6, 9, 29, 4),

(6, 10, 34, 3), (6, 3, 12, 2), (6, 12, 39, 2), (6, 18, 40, 1), (6, 19, 54, 3);

 

-- KLASA 2C (bio-chem)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(7, 1, 7, 4), (7, 7, 23, 2), (7, 8, 23, 2), (7, 5, 18, 2), (7, 6, 18, 2),

(7, 9, 30, 4), (7, 10, 35, 3), (7, 12, 40, 2), (7, 18, 40, 1), (7, 19, 55, 3);

 

-- KLASA 2D (hist-ang)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(8, 1, 8, 4), (8, 12, 40, 2), (8, 13, 40, 2), (8, 10, 35, 3), (8, 11, 35, 2),

(8, 9, 30, 4), (8, 3, 13, 2), (8, 14, 44, 2), (8, 18, 41, 1), (8, 19, 55, 3);

 

-- KLASA 3A (mat-fiz)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(9, 1, 1, 4), (9, 2, 1, 2), (9, 3, 13, 2), (9, 4, 13, 2), (9, 9, 31, 4),

(9, 10, 36, 3), (9, 12, 41, 2), (9, 14, 45, 2), (9, 18, 41, 1), (9, 19, 52, 3);

 

-- KLASA 3B (mat-inf)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(10, 1, 2, 4), (10, 2, 2, 2), (10, 16, 49, 2), (10, 17, 49, 2), (10, 9, 31, 4),

(10, 10, 36, 3), (10, 3, 13, 2), (10, 12, 41, 2), (10, 18, 42, 1), (10, 19, 53, 3);

 

-- KLASA 3C (bio-chem)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(11, 1, 9, 4), (11, 7, 24, 2), (11, 8, 24, 2), (11, 5, 19, 2), (11, 6, 19, 2),

(11, 9, 27, 4), (11, 10, 37, 3), (11, 12, 42, 2), (11, 18, 42, 1), (11, 19, 54, 3);

 

-- KLASA 3D (hist-geo)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(12, 1, 10, 4), (12, 12, 41, 2), (12, 13, 41, 2), (12, 14, 45, 2), (12, 15, 45, 2),

(12, 9, 28, 4), (12, 10, 37, 3), (12, 3, 14, 2), (12, 18, 38, 1), (12, 19, 55, 3);

 

-- KLASA 4A (mat-fiz)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(13, 1, 3, 4), (13, 2, 3, 2), (13, 3, 14, 2), (13, 4, 14, 2), (13, 9, 29, 4),

(13, 10, 32, 3), (13, 12, 38, 2), (13, 14, 46, 2), (13, 18, 38, 1), (13, 19, 52, 3);

 

-- KLASA 4B (mat-inf)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(14, 1, 4, 4), (14, 2, 4, 2), (14, 16, 50, 2), (14, 17, 50, 2), (14, 9, 30, 4),

(14, 10, 33, 3), (14, 3, 14, 2), (14, 12, 39, 2), (14, 18, 39, 1), (14, 19, 53, 3);

 

-- KLASA 4C (bio-chem)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(15, 1, 5, 4), (15, 7, 25, 2), (15, 8, 25, 2), (15, 5, 20, 2), (15, 6, 20, 2),

(15, 9, 31, 4), (15, 10, 34, 3), (15, 12, 40, 2), (15, 18, 40, 1), (15, 19, 54, 3);

 

-- KLASA 4D (hist-ang)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(16, 1, 6, 4), (16, 12, 42, 2), (16, 13, 42, 2), (16, 10, 35, 3), (16, 11, 35, 2),

(16, 9, 28, 4), (16, 3, 15, 2), (16, 14, 46, 2), (16, 18, 41, 1), (16, 19, 55, 3);

 

-- KLASA 5A (mat-fiz)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(17, 1, 7, 4), (17, 2, 7, 2), (17, 3, 15, 2), (17, 4, 15, 2), (17, 9, 27, 4),

(17, 10, 36, 3), (17, 12, 38, 2), (17, 14, 43, 2), (17, 18, 42, 1), (17, 19, 52, 3);

 

-- KLASA 5B (mat-inf)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(18, 1, 8, 4), (18, 2, 8, 2), (18, 16, 51, 2), (18, 17, 51, 2), (18, 9, 28, 4),

(18, 10, 37, 3), (18, 3, 15, 2), (18, 12, 39, 2), (18, 18, 38, 1), (18, 19, 53, 3);

 

-- KLASA 5C (bio-chem)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(19, 1, 9, 4), (19, 7, 26, 2), (19, 8, 26, 2), (19, 5, 21, 2), (19, 6, 21, 2),

(19, 9, 29, 4), (19, 10, 32, 3), (19, 12, 40, 2), (19, 18, 39, 1), (19, 19, 54, 3);

 

-- KLASA 5D (hist-geo)

INSERT INTO klasa_przedmioty (klasa_id, przedmiot_id, nauczyciel_id, ilosc_godzin_tydzien) VALUES

(20, 1, 10, 4), (20, 12, 38, 2), (20, 13, 38, 2), (20, 14, 46, 2), (20, 15, 46, 2),

(20, 9, 30, 4), (20, 10, 33, 3), (20, 3, 16, 2), (20, 18, 40, 1), (20, 19, 55, 3);

 

-- =====================================================

-- 10. UCZNIOWIE (500 uczniów - 25 na klasę)

-- =====================================================

 

-- Użytkownicy uczniów (id 1001-1500)

INSERT INTO uzytkownicy (id, login, haslo, typ, imie, nazwisko, email, aktywny) VALUES

-- Klasa 1A (25 uczniów)

(1001, 'u1001', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Adam', 'Adamski', 'u1001@liceum.pl', 1),

(1002, 'u1002', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Barbara', 'Bartosz', 'u1002@liceum.pl', 1),

(1003, 'u1003', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Cecylia', 'Chmiel', 'u1003@liceum.pl', 1),

(1004, 'u1004', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Damian', 'Dąb', 'u1004@liceum.pl', 1),

(1005, 'u1005', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Ewa', 'Eko', 'u1005@liceum.pl', 1),

(1006, 'u1006', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Filip', 'Filar', 'u1006@liceum.pl', 1),

(1007, 'u1007', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Gabriela', 'Góra', 'u1007@liceum.pl', 1),

(1008, 'u1008', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Hubert', 'Huk', 'u1008@liceum.pl', 1),

(1009, 'u1009', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Iga', 'Ister', 'u1009@liceum.pl', 1),

(1010, 'u1010', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Jakub', 'Jaki', 'u1010@liceum.pl', 1),

(1011, 'u1011', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Kasia', 'Kot', 'u1011@liceum.pl', 1),

(1012, 'u1012', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Łukasz', 'Las', 'u1012@liceum.pl', 1),

(1013, 'u1013', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Maria', 'Maj', 'u1013@liceum.pl', 1),

(1014, 'u1014', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Norbert', 'Nowy', 'u1014@liceum.pl', 1),

(1015, 'u1015', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Oliwia', 'Olek', 'u1015@liceum.pl', 1),

(1016, 'u1016', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Piotr', 'Ptak', 'u1016@liceum.pl', 1),

(1017, 'u1017', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Róża', 'Rak', 'u1017@liceum.pl', 1),

(1018, 'u1018', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Szymon', 'Ser', 'u1018@liceum.pl', 1),

(1019, 'u1019', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Teresa', 'Tok', 'u1019@liceum.pl', 1),

(1020, 'u1020', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Urszula', 'Ulica', 'u1020@liceum.pl', 1),

(1021, 'u1021', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Wiktoria', 'Wóz', 'u1021@liceum.pl', 1),

(1022, 'u1022', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Xawery', 'Xero', 'u1022@liceum.pl', 1),

(1023, 'u1023', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Yvonne', 'Yk', 'u1023@liceum.pl', 1),

(1024, 'u1024', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Zuzanna', 'Zając', 'u1024@liceum.pl', 1),

(1025, 'u1025', '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6', 'uczen', 'Albert', 'Antol', 'u1025@liceum.pl', 1);

 

-- Kontynuacja dla pozostałych 19 klas (475 uczniów) - wygenerujemy w pętli bazując na klasach

-- Klasa 1B (id 1026-1050)

INSERT INTO uzytkownicy (id, login, haslo, typ, imie, nazwisko, email, aktywny)

SELECT

    1025 + n,

    CONCAT('u', 1025 + n),

    '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6',

    'uczen',

    CONCAT('Uczen', n),

    CONCAT('Nazwisko', n),

    CONCAT('u', 1025 + n, '@liceum.pl'),

    1

FROM (

    SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION

    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION

    SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION

    SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION

    SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25

) nums;

 

-- Pozostałe 18 klas (id 1051-1500) - po 25 uczniów każda

INSERT INTO uzytkownicy (id, login, haslo, typ, imie, nazwisko, email, aktywny)

SELECT

    1050 + (k-1)*25 + n,

    CONCAT('u', 1050 + (k-1)*25 + n),

    '$2y$10$avKUzcz9kJE408oj0pu4suhTTVXHyTGnRVyHGzswoxNT/.wyLbCl6',

    'uczen',

    CONCAT('Uczen', k, '_', n),

    CONCAT('Klasa', k),

    CONCAT('u', 1050 + (k-1)*25 + n, '@liceum.pl'),

    1

FROM (

    SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION

    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION

    SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION

    SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION

    SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25

) nums

CROSS JOIN (

    SELECT 1 AS k UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION

    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION

    SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION

    SELECT 16 UNION SELECT 17 UNION SELECT 18

) klasy;

 

-- Przypisanie uczniów do klas w tabeli uczniowie

INSERT INTO uczniowie (uzytkownik_id, klasa_id)

SELECT

    1000 + (k-1)*25 + n,

    k

FROM (

    SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION

    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION

    SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION

    SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION

    SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25

) nums

CROSS JOIN (

    SELECT 1 AS k UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION

    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION

    SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION

    SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20

) klasy;

 

-- Włącz z powrotem sprawdzanie kluczy obcych

SET FOREIGN_KEY_CHECKS = 1;

 

-- =====================================================

-- PODSUMOWANIE

-- =====================================================

-- 19 przedmiotów (10 podstawowych + 9 rozszerzonych)

-- 20 klas (1A-5D)

-- 55 nauczycieli z godzinami pracy (pon-pt 8:00-16:00)

-- 500 uczniów (25 na klasę)

-- 30 sal z przypisaniami do przedmiotów i nauczycieli

-- Wszystkie klasy mają przypisane przedmioty z nauczycielami i ilością godzin

-- =====================================================