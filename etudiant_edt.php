<?php
// Emploi du temps de l'étudiant : cours de sa section + TD/TP de son groupe
$page_title  = 'Emploi du temps';
$page_active = 'etudiant_edt.php';
require 'etu_header.php';

// Mêmes jours et créneaux que la gestion côté admin (gestion_sections.php)
$jours = ['Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi'];
$creneaux = [['08:00','09:30'], ['09:40','11:10'], ['11:20','12:50'], ['13:00','14:30'], ['14:40','16:10'], ['16:20','17:50']];
$noms_jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$aujourdhui = $noms_jours[(int)date('w')];

$tous_groupes = isset($_GET['tous']) && $_GET['tous'] === '1';   // voir aussi les TD/TP des autres groupes
$grid = [];
$nb = 0;
$error = '';

try {
    $sql = "SELECT s.id_groupe, s.type_seance, s.jour, s.heurD, s.heurF,
                   m.Nom_mod, sal.nom_salle, u.nom, u.prenom
            FROM seance s
            JOIN module m   ON m.id_mod = s.id_mod
            JOIN salle sal  ON sal.id_salle = s.id_salle
            JOIN prof p     ON p.id_prof = s.id_prof
            JOIN user u     ON u.id_user = p.id_user
            WHERE s.id_Sec = ?";
    $params = [$etu['id_Sec']];
    if (!$tous_groupes) {
        $sql .= " AND (s.id_groupe IS NULL OR s.id_groupe = ?)";
        $params[] = $etu['groupe'];
    }
    $sql .= " ORDER BY s.heurD, s.type_seance";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cle = substr($r['heurD'], 0, 5) . ' - ' . substr($r['heurF'], 0, 5);
        $grid[$r['jour']][$cle][] = $r;
        $nb++;
    }
} catch (PDOException $e) {
    $error = "Impossible de charger l'emploi du temps pour le moment. Réessayez plus tard.";
}
?>
<style>
.edt-wrap{overflow-x:auto}
.edt{width:100%;border-collapse:collapse;min-width:820px;background:var(--card)}
.edt th,.edt td{border:1px solid var(--border);padding:8px;vertical-align:top;font-size:13px}
.edt th{background:#f4ebd0;color:var(--header);text-align:center}
.edt th.today{background:var(--accent);color:var(--on-accent)}
.edt td.hr{font-weight:600;background:#faf6e8;white-space:nowrap;text-align:center;width:110px}
.edt td.today{background:#fffaf0}
.sc{border-left:5px solid var(--header);background:#eef5f9;border-radius:6px;padding:7px 8px;margin-bottom:6px;line-height:1.5}
.sc.TD{border-color:#2e7d32;background:#eef7ee}.sc.TP{border-color:#8a5300;background:#fbf1e3}
.sc strong{display:block;font-size:13px}
.tp{display:inline-block;font-size:11px;font-weight:700;padding:1px 7px;border-radius:99px;color:#fff;background:var(--header);margin-bottom:3px}
.tp.TD{background:#2e7d32}.tp.TP{background:#8a5300}
.legend{display:flex;gap:14px;flex-wrap:wrap;margin:10px 0 16px;font-size:13px;align-items:center}
.toggle{margin-left:auto;font-weight:600;color:var(--menu)}
</style>

<!--<h3 class="section-title">Emploi du temps</h3> -->
<p class="muted" style="margin-bottom:6px">
  Section <strong><?php echo htmlspecialchars($etu['nom_sec'] ?? '-'); ?></strong> · Groupe <strong><?php echo (int)$etu['groupe']; ?></strong>
  · <?php echo htmlspecialchars($etu['NomSp'] ?? ''); ?>
</p>

<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="legend">
  <span><span class="tp">COURS</span> toute la section</span>
  <span><span class="tp TD">TD</span> votre groupe</span>
  <span><span class="tp TP">TP</span> votre groupe</span>
  <a class="toggle" href="etudiant_edt.php<?php echo $tous_groupes ? '' : '?tous=1'; ?>">
    <?php echo $tous_groupes ? '👤 Voir uniquement mon groupe' : '👥 Voir tous les groupes'; ?>
  </a>
</div>

<?php if (!$error && $nb === 0): ?>
  <div class="card"><p class="muted">L’emploi du temps de votre section n’a pas encore été publié par la scolarité.</p></div>
<?php else: ?>
<div class="card edt-wrap" style="padding:12px">
  <table class="edt">
    <thead>
      <tr>
        <th>Horaire</th>
        <?php foreach ($jours as $j): ?>
          <th class="<?php echo $j === $aujourdhui ? 'today' : ''; ?>"><?php echo $j; ?><?php echo $j === $aujourdhui ? ' · Aujourd’hui' : ''; ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($creneaux as $c): $h = $c[0] . ' - ' . $c[1]; ?>
      <tr>
        <td class="hr"><?php echo $h; ?></td>
        <?php foreach ($jours as $j): ?>
          <td class="<?php echo $j === $aujourdhui ? 'today' : ''; ?>">
            <?php foreach ($grid[$j][$h] ?? [] as $s): ?>
              <div class="sc <?php echo htmlspecialchars($s['type_seance']); ?>">
                <span class="tp <?php echo htmlspecialchars($s['type_seance']); ?>"><?php echo htmlspecialchars(strtoupper($s['type_seance'])); ?><?php echo $s['id_groupe'] ? ' · G' . (int)$s['id_groupe'] : ''; ?></span>
                <strong><?php echo htmlspecialchars($s['Nom_mod']); ?></strong>
                🏛️ <?php echo htmlspecialchars($s['nom_salle']); ?><br>
                👨‍🏫 <?php echo htmlspecialchars($s['nom'] . ' ' . $s['prenom']); ?>
              </div>
            <?php endforeach; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require 'etu_footer.php'; ?>
