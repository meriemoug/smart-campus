<?php
// ============================================================================
//  Assistant intelligent proactif (règles + calculs, sans service externe)
//  Fonctions : plan de révision, simulateur de note, créneaux libres + consultations,
//              ressources conseillées. Toutes les données viennent de la base.
// ============================================================================
const AST_CRENEAUX = [['08:00','09:30'],['09:40','11:10'],['11:20','12:50'],['13:00','14:30'],['14:40','16:10'],['16:20','17:50']];
const AST_JOURS = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

/** Chevauchement de deux intervalles "HH:MM" */
function ast_overlap($a1, $a2, $b1, $b2): bool {
    return substr($a1, 0, 5) < substr($b2, 0, 5) && substr($a2, 0, 5) > substr($b1, 0, 5);
}

/** Modules du programme avec notes et paramètres de la formule (moy = a + w × examen) */
function ast_modules(PDO $pdo, array $etu): array {
    $st = $pdo->prepare("SELECT p.semestre, p.coefficient, m.id_mod, m.Nom_mod, n.TD, n.TP, n.Exam
                         FROM programme p JOIN module m ON m.id_mod = p.id_mod
                         LEFT JOIN note n ON n.id_Mod = p.id_mod AND n.id_Etud = ?
                         WHERE p.id_sp = ? ORDER BY p.semestre, m.Nom_mod");
    $st->execute([$etu['id_Etu'], $etu['id_sp']]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $td = $r['TD'] !== null ? (float)$r['TD'] : null;
        $tp = $r['TP'] !== null ? (float)$r['TP'] : null;
        $ex = $r['Exam'] !== null ? (float)$r['Exam'] : null;
        // Même règle que la saisie des notes : TD/TP 20-40 %, examen 60 % (100 % s'il est seul)
        if ($td !== null && $tp !== null) { $a = 0.2 * ($td + $tp); $w = 0.6; }
        elseif ($tp !== null)            { $a = 0.4 * $tp; $w = 0.6; }
        elseif ($td !== null)            { $a = 0.4 * $td; $w = 0.6; }
        else                             { $a = 0.0; $w = 1.0; }
        $ca = array_filter([$td, $tp], fn($v) => $v !== null);
        $out[(int)$r['id_mod']] = [
            'id' => (int)$r['id_mod'], 'nom' => $r['Nom_mod'], 'sem' => (int)$r['semestre'], 'coef' => (float)$r['coefficient'],
            'td' => $td, 'tp' => $tp, 'ex' => $ex, 'a' => $a, 'w' => $w, 'has_ex' => $ex !== null,
            'ca' => $ca ? array_sum($ca) / count($ca) : null,                         // moyenne du contrôle continu
            'moy' => ($td !== null || $tp !== null || $ex !== null) ? $a + $w * ($ex ?? 0) : null,
        ];
    }
    return $out;
}

/** Examens à venir de la section */
function ast_exams(PDO $pdo, array $etu): array {
    $st = $pdo->prepare("SELECT x.id_mod, x.dateEx, x.heurD, x.heurF, x.type_exam, sa.nom_salle
                         FROM exam x LEFT JOIN salle sa ON sa.id_salle = x.id_salle
                         WHERE x.id_sec = ? AND x.dateEx >= CURDATE() ORDER BY x.dateEx, x.heurD");
    $st->execute([$etu['id_Sec']]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Séances hebdomadaires de l'étudiant (cours de la section + TD/TP de son groupe), par jour */
function ast_seances(PDO $pdo, array $etu): array {
    $st = $pdo->prepare("SELECT s.jour, s.heurD, s.heurF, s.type_seance, m.Nom_mod
                         FROM seance s JOIN module m ON m.id_mod = s.id_mod
                         WHERE s.id_Sec = ? AND (s.id_groupe IS NULL OR s.id_groupe = ?)");
    $st->execute([$etu['id_Sec'], $etu['groupe']]);
    $by = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $by[$r['jour']][] = $r; }
    return $by;
}

/** Index des créneaux libres d'un jour donné (séances + examens du jour pris en compte) */
function ast_free_slots(DateTime $d, array $seances, array $exams): array {
    $jour = AST_JOURS[(int)$d->format('w')];
    if ($jour === 'Vendredi') return [];
    $free = [];
    foreach (AST_CRENEAUX as $i => $c) {
        $busy = false;
        foreach ($seances[$jour] ?? [] as $s) { if (ast_overlap($s['heurD'], $s['heurF'], $c[0], $c[1])) { $busy = true; break; } }
        foreach ($exams as $e) {
            if ($e['dateEx'] !== $d->format('Y-m-d')) continue;
            if (!$e['heurD'] || ast_overlap($e['heurD'], $e['heurF'], $c[0], $c[1])) { $busy = true; break; }
        }
        if (!$busy) $free[] = $i;
    }
    return $free;
}

/** Priorité de révision de chaque examen à venir */
function ast_priorites(array $modules, array $exams, DateTime $today): array {
    $out = [];
    foreach ($exams as $e) {
        $m = $modules[(int)$e['id_mod']] ?? null;
        if (!$m) continue;
        if ($e['type_exam'] === 'normal' && $m['has_ex']) continue;                         // déjà passé et noté
        if ($e['type_exam'] === 'rattrapage' && (!$m['has_ex'] || $m['moy'] === null || $m['moy'] >= 10)) continue; // inutile
        $d = (int)$today->diff(new DateTime($e['dateEx']))->days;
        $diff = $m['ca'] === null ? 0.5 : max(0.1, min(1.0, (12 - $m['ca']) / 8));          // difficulté estimée (0,1 à 1)
        $urg  = $d <= 2 ? 1.0 : ($d <= 7 ? 0.8 : ($d <= 14 ? 0.6 : ($d <= 30 ? 0.4 : 0.2))); // urgence
        $score = $m['coef'] * (0.5 + $diff) * $urg;
        $heures = max(2, round($m['coef'] * (1.5 + 2 * $diff) * 2) / 2);                    // heures conseillées
        $out[] = ['mod' => $m, 'exam' => $e, 'jours' => $d, 'diff' => $diff, 'urg' => $urg, 'score' => $score,
                  'niveau' => $score >= 3 ? 'haute' : ($score >= 1.5 ? 'moyenne' : 'faible'),
                  'heures' => $heures, 'nb_slots' => min(5, (int)ceil($heures / 1.5))];
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return $out;
}

/** Plan de révision : place les séances dans les créneaux libres, avant chaque examen */
function ast_plan(array $prios, array $seances, array $exams, DateTime $today, int $horizon = 14): array {
    $days = [];
    for ($i = 1; $i <= $horizon; $i++) {
        $d = (clone $today)->modify("+$i day");
        if ($d->format('w') != 5) $days[] = $d;
    }
    $used = []; $plan = [];
    foreach ($prios as $p) {
        $placed = 0;
        foreach ($days as $d) {
            if ($placed >= $p['nb_slots']) break;
            if ($d->format('Y-m-d') >= $p['exam']['dateEx']) break;                         // avant le jour de l'examen
            foreach (ast_free_slots($d, $seances, $exams) as $i) {
                $k = $d->format('Y-m-d') . '|' . $i;
                if (isset($used[$k])) continue;
                $used[$k] = true; $placed++;
                $plan[$d->format('Y-m-d')][] = ['slot' => $i, 'nom' => $p['mod']['nom'], 'niveau' => $p['niveau']];
                break;                                                                      // une séance par examen et par jour
            }
        }
    }
    ksort($plan);
    foreach ($plan as &$l) { usort($l, fn($a, $b) => $a['slot'] <=> $b['slot']); }
    return $plan;
}

/** Consultations à venir des profs de la section, comparées à l'emploi du temps de l'étudiant */
function ast_consultations(PDO $pdo, array $etu, array $seances, array $exams, int $horizon = 14): array {
    $st = $pdo->prepare("SELECT p.id_prof, u.nom, u.prenom, c.date_consult, c.heure_consult, c.bureau, c.email_contact,
                                GROUP_CONCAT(DISTINCT m.id_mod) AS mods
                         FROM prof p JOIN user u ON u.id_user = p.id_user
                         JOIN (SELECT id_prof, id_mod FROM seance WHERE id_Sec = ?
                               UNION SELECT en.id_prof, en.id_mod FROM enseignement en JOIN programme pr ON pr.id_mod = en.id_mod WHERE pr.id_sp = ?) pm ON pm.id_prof = p.id_prof
                         JOIN module m ON m.id_mod = pm.id_mod
                         JOIN consultation c ON c.id_prof = p.id_prof
                         WHERE c.date_consult >= CURDATE() AND c.date_consult <= DATE_ADD(CURDATE(), INTERVAL $horizon DAY)
                         GROUP BY p.id_prof, u.nom, u.prenom, c.date_consult, c.heure_consult, c.bureau, c.email_contact
                         ORDER BY c.date_consult");
    $st->execute([$etu['id_Sec'], $etu['id_sp']]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d = new DateTime($r['date_consult']);
        $jour = AST_JOURS[(int)$d->format('w')];
        $r['statut'] = 'inconnu'; $r['conflit'] = '';
        if (preg_match('/(\d{1,2}):(\d{2})\D+(\d{1,2}):(\d{2})/', $r['heure_consult'], $m)) {
            $h1 = sprintf('%02d:%02d', $m[1], $m[2]); $h2 = sprintf('%02d:%02d', $m[3], $m[4]);
            $r['statut'] = 'libre';
            foreach ($seances[$jour] ?? [] as $s) {
                if (ast_overlap($s['heurD'], $s['heurF'], $h1, $h2)) { $r['statut'] = 'conflit'; $r['conflit'] = $s['type_seance'] . ' ' . $s['Nom_mod']; break; }
            }
        }
        $r['jour'] = $jour; $r['mods'] = array_map('intval', explode(',', $r['mods']));
        $out[] = $r;
    }
    return $out;
}

/** Ressources conseillées pour les modules à renforcer (corrigés et TD d'abord) */
function ast_ressources(PDO $pdo, array $etu, array $mod_ids, int $max = 3): array {
    if (!$mod_ids) return [];
    $in = implode(',', array_map('intval', $mod_ids));
    $st = $pdo->prepare("SELECT r.id_module, r.titre, r.lien, r.type_res, r.date_ajout FROM ressource r
                         WHERE r.id_sect = ? AND r.id_module IN ($in)
                         ORDER BY FIELD(r.type_res,'examen_corrige','td','tp','cours','video','autre'), r.date_ajout DESC");
    $st->execute([$etu['id_Sec']]);
    $by = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { if (count($by[$r['id_module']] ?? []) < $max) $by[$r['id_module']][] = $r; }
    return $by;
}

/** Modules à renforcer : examen proche et prioritaire, ou moyenne / contrôle continu inférieur à 10 */
function ast_a_renforcer(array $modules, array $prios, int $max = 4): array {
    $ids = [];
    foreach ($prios as $p) { if ($p['niveau'] !== 'faible') $ids[$p['mod']['id']] = true; }
    foreach ($modules as $m) { if (($m['moy'] !== null && $m['moy'] < 10) || ($m['ca'] !== null && $m['ca'] < 10)) $ids[$m['id']] = true; }
    return array_slice(array_keys($ids), 0, $max);
}
