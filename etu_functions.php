<?php
// Fonctions communes à l'espace étudiant (notes et relevé)

/** Même règle de calcul que prof_gestion_notes.php (TD 20-40 %, TP 20-40 %, Exam 60 %) */
function moyenne_module($td, $tp, $exam): array {
    $has_td = ($td !== null && $td !== '');
    $has_tp = ($tp !== null && $tp !== '');
    $has_ex = ($exam !== null && $exam !== '');
    $ex = $has_ex ? (float)$exam : 0;
    $moy = null;
    if ($has_td && $has_tp)       { $moy = (float)$tp * 0.2 + (float)$td * 0.2 + $ex * 0.6; }
    elseif ($has_td)              { $moy = (float)$td * 0.4 + $ex * 0.6; }
    elseif ($has_tp)              { $moy = (float)$tp * 0.4 + $ex * 0.6; }
    elseif ($has_ex)              { $moy = (float)$exam; }
    return [$moy, $has_ex];   // [moyenne ou null, examen saisi ?]
}

/** Format d'affichage d'une note : 13,20 ou « — » si vide */
function fmt($v): string {
    return ($v === null || $v === '') ? '—' : number_format((float)$v, 2, ',', ' ');
}

/** Classe CSS d'une moyenne : ok (>= 10), ko (< 10) ou vide */
function cls_moy($v): string {
    return ($v === null || $v === '') ? '' : ((float)$v >= 10 ? 'ok' : 'ko');
}
/** Symbole d'une moyenne : ✔ ou ✖ (jamais la couleur seule) */
function ico_moy($v): string {
    return ($v === null || $v === '') ? '' : ((float)$v >= 10 ? '' : '');
}
