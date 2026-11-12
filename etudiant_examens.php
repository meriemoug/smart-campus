<?php
// Planning des examens de la section de l'étudiant
$page_title  = 'Planning des examens';
$page_active = 'etudiant_examens.php';
require 'etu_header.php';

$jours_fr = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$a_venir = $passes = [];
$error = '';
$aujourdhui = new DateTime('today');

try {
    $st = $pdo->prepare("SELECT x.dateEx, x.heurD, x.heurF, x.type_exam, m.Nom_mod, sa.nom_salle, u.nom, u.prenom
                         FROM exam x
                         JOIN module m ON m.id_mod = x.id_mod
                         LEFT JOIN salle sa ON sa.id_salle = x.id_salle
                         LEFT JOIN prof p ON p.id_prof = x.id_prof
                         LEFT JOIN user u ON u.id_user = p.id_user
                         WHERE x.id_sec = ?
                         ORDER BY x.dateEx, x.heurD");
    $st->execute([$etu['id_Sec']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
        if (new DateTime($e['dateEx']) >= $aujourdhui) { $a_venir[] = $e; } else { $passes[] = $e; }
    }
} catch (PDOException $e) {
    $error = "Impossible de charger le planning pour le moment. Réessayez plus tard.";
}

// Affiche une ligne du tableau
function ligne_examen(array $e, DateTime $aujourdhui, array $jours_fr, bool $avec_delai): void {
    $d = new DateTime($e['dateEx']);
    $j = (int)$aujourdhui->diff($d)->format('%r%a');
    $rattrapage = $e['type_exam'] === 'rattrapage';
    ?>
    <tr>
      <td><strong><?php echo $jours_fr[(int)$d->format('w')]; ?></strong><br><?php echo $d->format('d/m/Y'); ?></td>
      <td><?php echo $e['heurD'] ? substr($e['heurD'],0,5) . ' – ' . substr($e['heurF'],0,5) : '<span class="muted">À confirmer</span>'; ?></td>
      <td><strong><?php echo htmlspecialchars($e['Nom_mod']); ?></strong></td>
      <td><span class="tp <?php echo $rattrapage ? 'rat' : ''; ?>"><?php echo $rattrapage ? 'Rattrapage' : 'Examen'; ?></span></td>
      <td> <?php echo $e['nom_salle'] ? htmlspecialchars($e['nom_salle']) : '<span class="muted">À confirmer</span>'; ?></td>
      <td><?php echo $e['nom'] ? htmlspecialchars($e['nom'] . ' ' . $e['prenom']) : '—'; ?></td>
      <?php if ($avec_delai): ?>
      <td><span class="delai <?php echo $j <= 7 ? 'proche' : ''; ?>"><?php echo $j === 0 ? 'Aujourd’hui' : ($j === 1 ? 'Demain' : "Dans $j jours"); ?></span></td>
      <?php endif; ?>
    </tr>
    <?php
}
?>
<style>
.tbl-wrap{overflow-x:auto}
.ex{width:100%;border-collapse:collapse;min-width:760px;font-size:14px}
.ex th,.ex td{padding:12px;text-align:left;border-bottom:1px solid var(--border);vertical-align:top}
.ex th{background:#f4ebd0;color:var(--header);font-weight:600}
.tp{display:inline-block;font-size:12px;font-weight:700;padding:2px 9px;border-radius:99px;background:var(--header);color:var(--on-blue)}
.tp.rat{background:var(--warning);color:var(--on-warning)}
.delai{display:inline-block;font-size:12px;font-weight:700;padding:2px 9px;border-radius:99px;background:#e8eef2;color:var(--text)}
.delai.proche{background:var(--accent);color:var(--on-accent)}
details summary{cursor:pointer;font-weight:600;color:var(--menu);padding:6px 0}
</style>

<h3 class="section-title">Planning des examens</h3>
<p class="muted" style="margin-bottom:16px">Section <strong><?php echo htmlspecialchars($etu['nom_sec'] ?? '-'); ?></strong> · <?php echo htmlspecialchars($etu['NomSp'] ?? ''); ?></p>

<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$error && empty($a_venir) && empty($passes)): ?>
  <div class="card"><p class="muted">Le planning des examens de votre section n’a pas encore été publié par la scolarité.</p></div>
<?php elseif (!$error): ?>
  <div class="card">
    <h3>🗓️ Examens à venir</h3>
    <?php if (empty($a_venir)): ?>
      <p class="muted">Aucun examen à venir.</p>
    <?php else: ?>
      <div class="tbl-wrap"><table class="ex">
        <thead><tr><th>Date</th><th>Horaire</th><th>Module</th><th>Type</th><th>Salle</th><th>Enseignant</th><th>Délai</th></tr></thead>
        <tbody><?php foreach ($a_venir as $e) ligne_examen($e, $aujourdhui, $jours_fr, true); ?></tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <?php if (!empty($passes)): ?>
  <div class="card">
    <details>
      <summary>Examens passés (<?php echo count($passes); ?>)</summary>
      <div class="tbl-wrap"><table class="ex">
        <thead><tr><th>Date</th><th>Horaire</th><th>Module</th><th>Type</th><th>Salle</th><th>Enseignant</th></tr></thead>
        <tbody><?php foreach (array_reverse($passes) as $e) ligne_examen($e, $aujourdhui, $jours_fr, false); ?></tbody>
      </table></div>
    </details>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php require 'etu_footer.php'; ?>
