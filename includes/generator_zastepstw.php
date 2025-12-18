<?php
require_once '../includes/config.php';
require_once '../includes/dostepnosc_helpers.php';

class GeneratorZastepstw
{
    private $conn;
    private $cache_plan = [];     // Cache for plan_dzienny structure [date][teacher_id] => [lessons...]
    private $cache_subs = [];     // Cache for weekly substitutions count [week][teacher_id] => count

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Sprawdzenie czy lekcja jest na początku lub końcu dnia dla klasy
    private function czyLekcjaNaPoczatkuLubKoncuDnia($klasa_id, $data, $numer_lekcji)
    {
        $stmt = $this->conn->prepare("
            SELECT numer_lekcji
            FROM plan_dzienny
            WHERE klasa_id = ?
            AND data = ?
            ORDER BY numer_lekcji
        ");

        if (!$stmt)
            return false;

        $stmt->bind_param("is", $klasa_id, $data);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows == 0)
            return false;

        $numery_lekcji = [];
        while ($row = $result->fetch_assoc()) {
            $numery_lekcji[] = $row['numer_lekcji'];
        }

        $pierwsza_lekcja = min($numery_lekcji);
        $ostatnia_lekcja = max($numery_lekcji);

        return ($numer_lekcji == $pierwsza_lekcja || $numer_lekcji == $ostatnia_lekcja);
    }

    // Load data for scoring optimizations
    private function zaladujDaneDnia($data)
    {
        if (isset($this->cache_plan[$data]))
            return;

        // 1. Get all lessons for this day (for gaps and load calculation)
        $this->cache_plan[$data] = [];
        $stmt = $this->conn->prepare("SELECT nauczyciel_id, numer_lekcji FROM plan_dzienny WHERE data = ?");
        $stmt->bind_param("s", $data);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $this->cache_plan[$data][$row['nauczyciel_id']][] = $row['numer_lekcji'];
            }
        }
        $stmt->close();
    }

    private function pobierzLiczbeZastepstwWTygodniu($nauczyciel_id, $data)
    {
        $week = date('W', strtotime($data));
        $year = date('Y', strtotime($data));
        $key = "$year-$week";

        if (!isset($this->cache_subs[$key])) {
            $this->cache_subs[$key] = [];
            // Calculate start and end of week
            $dto = new DateTime($data);
            $dto->setISODate($year, $week);
            $start = $dto->format('Y-m-d');
            $dto->modify('+6 days');
            $end = $dto->format('Y-m-d');

            $stmt = $this->conn->prepare("
                SELECT nauczyciel_zastepujacy_id, COUNT(*) as cnt 
                FROM zastepstwa z
                JOIN plan_dzienny pd ON z.plan_dzienny_id = pd.id
                WHERE pd.data BETWEEN ? AND ?
                GROUP BY nauczyciel_zastepujacy_id
            ");
            $stmt->bind_param("ss", $start, $end);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $this->cache_subs[$key][$row['nauczyciel_zastepujacy_id']] = $row['cnt'];
                }
            }
            $stmt->close();
        }

        return $this->cache_subs[$key][$nauczyciel_id] ?? 0;
    }

    // Główna funkcja generująca zastępstwa
    public function generujZastepstwa($nieobecnosc_id)
    {
        error_log("SUBSTITUTE_DEBUG: Starting smart generation for absence ID: $nieobecnosc_id");

        // Pobierz dane nieobecności
        $stmt = $this->conn->prepare("SELECT * FROM nieobecnosci WHERE id = ?");
        $stmt->bind_param("i", $nieobecnosc_id);
        $stmt->execute();
        $nieobecnosc = $stmt->get_result()->fetch_assoc();

        if (!$nieobecnosc)
            return false;

        $nauczyciel_id = $nieobecnosc['nauczyciel_id'];
        $data_od = $nieobecnosc['data_od'];
        $data_do = $nieobecnosc['data_do'];

        // Pobierz lekcje do zastąpienia
        $stmt_lekcje = $this->conn->prepare("
            SELECT pd.*, p.nazwa as przedmiot_nazwa, k.nazwa as klasa_nazwa
            FROM plan_dzienny pd
            JOIN przedmioty p ON pd.przedmiot_id = p.id
            JOIN klasy k ON pd.klasa_id = k.id
            WHERE pd.nauczyciel_id = ?
            AND pd.data >= ?
            AND pd.data <= ?
            AND pd.czy_zastepstwo = 0
            ORDER BY pd.data, pd.numer_lekcji
        ");

        $stmt_lekcje->bind_param("iss", $nauczyciel_id, $data_od, $data_do);
        $stmt_lekcje->execute();
        $lekcje = $stmt_lekcje->get_result();

        $zastepstwa_utworzone = 0;
        $zastepstwa_niemozliwe = [];
        $zastepstwa_pominiete = [];

        // Cache teacher subjects map
        $nauczyciel_przedmioty = [];
        $kp_res = $this->conn->query("SELECT nauczyciel_id, przedmiot_id FROM nauczyciel_przedmioty");
        while ($row = $kp_res->fetch_assoc()) {
            $nauczyciel_przedmioty[$row['nauczyciel_id']][] = $row['przedmiot_id'];
        }

        // Cache teachers teaching classes map
        $klasa_nauczyciele = [];
        $kn_res = $this->conn->query("SELECT klasa_id, nauczyciel_id FROM klasa_przedmioty");
        while ($row = $kn_res->fetch_assoc()) {
            $klasa_nauczyciele[$row['klasa_id']][] = $row['nauczyciel_id'];
        }

        while ($lekcja = $lekcje->fetch_assoc()) {
            $this->zaladujDaneDnia($lekcja['data']);

            $kandydat = $this->znajdzNajlepszegoKandydata($lekcja, $nauczyciel_id, $nauczyciel_przedmioty, $klasa_nauczyciele);

            if ($kandydat) {
                // Utwórz zastępstwo
                $this->utworzZastepstwo($lekcja['id'], $nieobecnosc_id, $kandydat['id']);

                // Aktualizuj plan
                $this->aktualizujPlanDzienny($lekcja['id'], $nauczyciel_id, $kandydat['id']);

                // Update cache locally so next iteration knows about this load
                $this->cache_plan[$lekcja['data']][$kandydat['id']][] = $lekcja['numer_lekcji'];
                // Update weekly subs cache (approximate, or just let it be slightly stale for batch)
                // Better to update to avoid overloading same person in one batch
                $week = date('W', strtotime($lekcja['data']));
                $year = date('Y', strtotime($lekcja['data']));
                $key = "$year-$week";
                if (isset($this->cache_subs[$key][$kandydat['id']])) {
                    $this->cache_subs[$key][$kandydat['id']]++;
                } else {
                    $this->cache_subs[$key][$kandydat['id']] = 1;
                }

                $zastepstwa_utworzone++;
                error_log("SUBSTITUTE_SUCCESS: Assigned Teacher {$kandydat['id']} for Lesson {$lekcja['id']}");
            } else {
                // Fallback analysis
                if ($this->czyLekcjaNaPoczatkuLubKoncuDnia($lekcja['klasa_id'], $lekcja['data'], $lekcja['numer_lekcji'])) {
                    $zastepstwa_pominiete[] = [
                        'data' => $lekcja['data'],
                        'lekcja' => $lekcja['numer_lekcji'],
                        'przedmiot' => $lekcja['przedmiot_nazwa'],
                        'klasa' => $lekcja['klasa_nazwa'],
                        'powod' => 'pierwsza_lub_ostatnia_lekcja_brak_nauczyciela'
                    ];
                } else {
                    $zastepstwa_niemozliwe[] = [
                        'data' => $lekcja['data'],
                        'lekcja' => $lekcja['numer_lekcji'],
                        'przedmiot' => $lekcja['przedmiot_nazwa'],
                        'klasa' => $lekcja['klasa_nazwa']
                    ];
                }
            }
        }

        return [
            'utworzone' => $zastepstwa_utworzone,
            'niemozliwe' => $zastepstwa_niemozliwe,
            'pominiete' => $zastepstwa_pominiete
        ];
    }

    private function znajdzNajlepszegoKandydata($lekcja, $absent_teacher_id, $map_subjects, $map_class_teachers)
    {
        // 1. Get all potential candidates (exclude absent one)
        // We'll fetch basic info for all teachers, then filter/score
        static $all_teachers = null;
        if ($all_teachers === null) {
            $res = $this->conn->query("SELECT n.id, u.imie, u.nazwisko FROM nauczyciele n JOIN uzytkownicy u ON n.uzytkownik_id = u.id");
            $all_teachers = [];
            while ($row = $res->fetch_assoc()) {
                $all_teachers[] = $row;
            }
        }

        $best_candidate = null;
        $best_score = -1000;

        $data = $lekcja['data'];
        $lekcja_nr = $lekcja['numer_lekcji'];

        // Absence check for candidates
        $absent_ids = $this->pobierzIdNieobecnychNauczycieli($data);

        foreach ($all_teachers as $teacher) {
            $tid = $teacher['id'];
            if ($tid == $absent_teacher_id)
                continue;
            if (in_array($tid, $absent_ids))
                continue;

            // Check availability (hours)
            $dzien_tygodnia = $this->getDzienTygodnia($data);
            if (!sprawdz_dostepnosc_nauczyciela_w_czasie($tid, $dzien_tygodnia, $data, $lekcja_nr, $this->conn)) {
                continue;
            }

            // Check if busy (has lesson)
            $lessons_today = $this->cache_plan[$data][$tid] ?? [];
            if (in_array($lekcja_nr, $lessons_today)) {
                continue; // Busy
            }

            // --- SCORING ---
            $score = 0;

            // 1. Competence
            $teaches_subject = in_array($lekcja['przedmiot_id'], $map_subjects[$tid] ?? []);
            $teaches_class = in_array($tid, $map_class_teachers[$lekcja['klasa_id']] ?? []);

            if ($teaches_subject) {
                $score += 100;
            } elseif ($teaches_class) {
                $score += 30; // Knows the class
            } else {
                $score += 10; // Just supervision
            }

            // 2. Load Balancing
            $lessons_count = count($lessons_today);
            $score -= ($lessons_count * 5); // Fatigue

            $subs_week = $this->pobierzLiczbeZastepstwWTygodniu($tid, $data);
            $score -= ($subs_week * 10); // Fairness

            // 3. Gaps (Okienka)
            // If assigning this lesson creates a gap
            $temp_lessons = $lessons_today;
            $temp_lessons[] = $lekcja_nr;
            sort($temp_lessons);

            $gaps_before = $this->policzOkienka($lessons_today);
            $gaps_after = $this->policzOkienka($temp_lessons);

            if ($gaps_after > $gaps_before) {
                // Check if the gap is really bad? 
                // Any created gap is bad.
                $score -= 50;
            } elseif ($gaps_after < $gaps_before) {
                // Should not happen unless we are filling a gap
                $score += 20;
            } else {
                // Gaps unchanged. If we filled a gap, we haven't increased gaps.
                // Wait, policzOkienka counts TOTAL gaps.
                // If total gaps stays same, it might mean we appended to end or start.
                // If we filled a gap, total gaps decreases?
                // Example: [1, 3], gap count = 1 (2 is missing).
                // Add 2 -> [1, 2, 3], gap count = 0. Decreases.
                // Example: [1, 2]. Add 4. [1, 2, 4]. Gap count = 1. Increases.
                // Logic holds.
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best_candidate = $teacher;
            }
        }

        return $best_candidate;
    }

    private function policzOkienka($lekcje)
    {
        if (count($lekcje) < 2)
            return 0;
        $gaps = 0;
        for ($i = 0; $i < count($lekcje) - 1; $i++) {
            $diff = $lekcje[$i + 1] - $lekcje[$i];
            if ($diff > 1) {
                $gaps += ($diff - 1);
            }
        }
        return $gaps;
    }

    private function pobierzIdNieobecnychNauczycieli($data)
    {
        static $cache = [];
        if (isset($cache[$data]))
            return $cache[$data];

        $ids = [];
        $stmt = $this->conn->prepare("SELECT nauczyciel_id FROM nieobecnosci WHERE ? BETWEEN data_od AND data_do");
        $stmt->bind_param("s", $data);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc())
                $ids[] = $row['nauczyciel_id'];
        }
        $cache[$data] = $ids;
        return $ids;
    }

    private function getDzienTygodnia($data)
    {
        $timestamp = strtotime($data);
        $numer = date('N', $timestamp);
        $map = [1 => 'poniedzialek', 2 => 'wtorek', 3 => 'sroda', 4 => 'czwartek', 5 => 'piatek', 6 => 'sobota', 7 => 'niedziela'];
        return $map[$numer];
    }

    private function utworzZastepstwo($plan_dzienny_id, $nieobecnosc_id, $nauczyciel_zastepujacy_id)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO zastepstwa (plan_dzienny_id, nieobecnosc_id, nauczyciel_zastepujacy_id)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $plan_dzienny_id, $nieobecnosc_id, $nauczyciel_zastepujacy_id);
        return $stmt->execute();
    }

    private function aktualizujPlanDzienny($id, $oryg_nauczyciel_id, $nowy_nauczyciel_id)
    {
        $stmt = $this->conn->prepare("
            UPDATE plan_dzienny
            SET czy_zastepstwo = 1,
                oryginalny_nauczyciel_id = ?,
                nauczyciel_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param("iii", $oryg_nauczyciel_id, $nowy_nauczyciel_id, $id);
        $stmt->execute();
    }

    public function usunZastepstwa($nieobecnosc_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM zastepstwa WHERE nieobecnosc_id = ?");
        $stmt->bind_param("i", $nieobecnosc_id);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($z = $res->fetch_assoc()) {
            // Restore original in plan_dzienny
            $s_upd = $this->conn->prepare("
                UPDATE plan_dzienny 
                SET nauczyciel_id = oryginalny_nauczyciel_id,
                    czy_zastepstwo = 0,
                    oryginalny_nauczyciel_id = NULL
                WHERE id = ?
            ");
            $s_upd->bind_param("i", $z['plan_dzienny_id']);
            $s_upd->execute();
        }

        $stmt = $this->conn->prepare("DELETE FROM zastepstwa WHERE nieobecnosc_id = ?");
        $stmt->bind_param("i", $nieobecnosc_id);
        return $stmt->execute();
    }
}
?>