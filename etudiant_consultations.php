<?php
// Consultations : profs de la section de l'étudiant, avec leurs horaires de consultation et leur bureau
$page_title  = 'Consultations des profs';
$page_active = 'etudiant_consultations.php';
require 'etu_header.php';

$jours_fr = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$profs = [];
$error = '';

try {
    // Profs de la section : ceux qui ont des séances avec elle + les responsables des modules du programme.
    // On ne garde que les consultations à venir (une date dépassée est ignorée).
    $st = $pdo->prepare("SELECT p.id_prof, p.grade, u.nom, u.prenom, u.email AS email_compte,
                                GROUP_CONCAT(DISTINCT m.Nom_mod ORDER BY m.Nom_mod SEPARATOR ', ') AS modules,
                                c.date_consult, c.heure_consult, c.bureau, c.email_contact, c.note
                         FROM prof p
                         JOIN user u ON u.id_user = p.id_user
                         JOIN (SELECT id_prof, id_mod FROM seance WHERE id_Sec = ?
                               UNION
                               SELECT en.id_prof, en.id_mod FROM enseignement en
                               JOIN programme pr ON pr.id_mod = en.id_mod WHERE pr.id_sp = ?) pm ON pm.id_prof = p.id_prof
                         JOIN module m ON m.id_mod = pm.id_mod
                         LEFT JOIN consultation c ON c.id_prof = p.id_prof AND c.date_consult >= CURDATE()
                         GROUP BY p.id_prof, p.grade, u.nom, u.prenom, u.email,
                                  c.date_consult, c.heure_consult, c.bureau, c.email_contact, c.note
                         ORDER BY (c.date_consult IS NULL), c.date_consult, u.nom");
    $st->execute([$etu['id_Sec'], $etu['id_sp']]);
    $profs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Impossible de charger les consultations pour le moment. Réessayez plus tard.";
}
$aujourdhui = new DateTime('today');
?>
<style>
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px}
.pcard{background:var(--card);border:1px solid var(--border);border-left:6px solid var(--accent);border-radius:10px;padding:18px}
.pcard.none{border-left-color:var(--border)}
.pcard h4{font-size:17px;color:var(--header);margin-bottom:2px}
.pcard .grade{font-size:13px;color:var(--text-muted);margin-bottom:10px}
.mods{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.mod{font-size:12px;font-weight:600;padding:2px 9px;border-radius:99px;background:#e8eef2;color:var(--text)}
.row{font-size:14px;padding:5px 0;border-top:1px solid var(--border)}
.pill{display:inline-block;font-size:11px;font-weight:700;padding:1px 8px;border-radius:99px;background:var(--accent);color:var(--on-accent);margin-left:6px}
.pill.today{background:var(--warning);color:var(--on-warning)}
.note{font-size:13px;background:#fffaf0;border:1px solid var(--border);border-radius:6px;padding:8px;margin-top:8px}
.pcard a{color:var(--menu);font-weight:600}
</style>

<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$error && empty($profs)): ?>
  <div class="card"><p class="muted">Aucun enseignant n’est encore rattaché à votre section.</p></div>
<?php else: ?>
<div class="pgrid">
  <?php foreach ($profs as $p): $has = !empty($p['date_consult']);
        if ($has) { $d = new DateTime($p['date_consult']); $j = (int)$aujourdhui->diff($d)->days; } ?>
    <article class="pcard <?php echo $has ? '' : 'none'; ?>">
      <h4>👨‍🏫 Pr. <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?></h4>
      <div class="grade"><?php echo htmlspecialchars($p['grade'] ?: 'Enseignant'); ?></div>
      <div class="mods"><?php foreach (explode(', ', $p['modules']) as $mn): ?><span class="mod"><?php echo htmlspecialchars($mn); ?></span><?php endforeach; ?></div>

      <?php if ($has): ?>
        <div class="row">📅 <strong><?php echo $jours_fr[(int)$d->format('w')] . ' ' . $d->format('d/m/Y'); ?></strong>
          <?php if ($j === 0): ?><span class="pill today">Aujourd’hui</span><?php elseif ($j === 1): ?><span class="pill">Demain</span><?php endif; ?></div>
        <div class="row">🕒 <?php echo htmlspecialchars($p['heure_consult']); ?></div>
        <div class="row">🏛️ <?php echo htmlspecialchars($p['bureau']); ?></div>
        <div class="row">✉️ <a href="mailto:<?php echo htmlspecialchars($p['email_contact']); ?>"><?php echo htmlspecialchars($p['email_contact']); ?></a></div>
        <?php if (!empty($p['note'])): ?><div class="note">💬 <?php echo htmlspecialchars($p['note']); ?></div><?php endif; ?>
      <?php else: ?>
        <div class="row"><span class="muted">Aucun horaire de consultation programmé pour le moment.</span></div>
        <?php if (!empty($p['email_compte'])): ?>
          <div class="row">✉️ <a href="mailto:<?php echo htmlspecialchars($p['email_compte']); ?>"><?php echo htmlspecialchars($p['email_compte']); ?></a></div>
        <?php endif; ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require 'etu_footer.php'; ?>
