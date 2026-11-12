<?php
// Relevé de notes : modules, moyennes par semestre, moyenne finale et décision
$page_title  = 'Relevé de notes';
$page_active = 'etudiant_releve.php';
require 'etu_header.php';
require_once 'etu_functions.php';

$par_sem = [1 => [], 2 => []];
$annees = [];          // une ligne de résultat par année académique
$res = null;
$annee_sel = null;
$error = '';

try {
    // Tous les résultats de l'étudiant, avec la spécialité et la section de CHAQUE année
    $st = $pdo->prepare("SELECT r.*, sp.NomSp, s.nom_sec
                         FROM resultat_etudiant r
                         LEFT JOIN specialite sp ON sp.id_sp = r.id_sp
                         LEFT JOIN section s ON s.id_Sec = r.id_Sec
                         WHERE r.id_user = ?
                         ORDER BY r.annee_academique DESC, r.id_resultat DESC");
    $st->execute([$_SESSION['id_user']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $annees[$r['annee_academique']] ??= $r; }

    // Année affichée : celle du filtre, sinon la plus récente
    $annee_sel = $_GET['annee'] ?? array_key_first($annees);
    if (!isset($annees[$annee_sel])) { $annee_sel = array_key_first($annees); }
    $res = $annee_sel !== null ? $annees[$annee_sel] : null;

    $est_courante = ($annee_sel === null) || ($annee_sel === array_key_first($annees));

    if ($est_courante) {
        // Année en cours : modules du programme + notes actuelles (table note)
        $id_sp_annee = $res['id_sp'] ?? $etu['id_sp'];
        $st = $pdo->prepare("SELECT p.semestre, p.coefficient, m.Nom_mod, n.TD, n.TP, n.Exam
                             FROM programme p
                             JOIN module m ON m.id_mod = p.id_mod
                             LEFT JOIN note n ON n.id_Mod = p.id_mod AND n.id_Etud = ?
                             WHERE p.id_sp = ?
                             ORDER BY p.semestre, m.Nom_mod");
        $st->execute([$etu['id_Etu'], $id_sp_annee]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            [$r['moy'], $r['has_ex']] = moyenne_module($r['TD'], $r['TP'], $r['Exam']);
            $par_sem[(int)$r['semestre']][] = $r;
        }
    } else {
        // Année précédente : détail archivé au moment de la délibération (table resultat_module)
        $st = $pdo->prepare("SELECT semestre, coefficient, nom_mod AS Nom_mod, td AS TD, tp AS TP, exam AS Exam, moyenne
                             FROM resultat_module
                             WHERE id_user = ? AND annee_academique = ?
                             ORDER BY semestre, nom_mod");
        $st->execute([$_SESSION['id_user'], $annee_sel]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['moy']    = ($r['moyenne'] !== null) ? (float)$r['moyenne'] : null;
            $r['has_ex'] = ($r['Exam'] !== null);
            $par_sem[(int)$r['semestre']][] = $r;
        }
    }
} catch (PDOException $e) {
    $error = "Impossible de charger votre relevé pour le moment. Réessayez plus tard.";
}

// Infos affichées en en-tête (celles de l'année choisie)
$nom_sp  = $res['NomSp']   ?? ($etu['NomSp'] ?? '');
$nom_sec = $res['nom_sec'] ?? ($etu['nom_sec'] ?? '-');
$est_courante = ($annee_sel === null) || ($annee_sel === array_key_first($annees));

// Classe visuelle de la décision (le texte reste toujours affiché)
$decision = trim($res['decision'] ?? 'En cours');
$d = mb_strtolower($decision);
if (preg_match('/ajourn|non admis|redoubl|exclu/u', $d))      { $d_class = 'ko';   $d_icon = ''; }
elseif (strpos($d, 'rattrap') !== false)                      { $d_class = 'warn'; $d_icon = ''; }
elseif (strpos($d, 'admis') !== false)                        { $d_class = 'ok';   $d_icon = ''; }
else                                                          { $d_class = 'wait'; $d_icon = '⏳'; }
?>
<style>
.rl-head{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;align-items:flex-start}
.rl-head h3{margin-bottom:6px}
.btn-print{background:var(--header);color:var(--on-blue);border:0;border-radius:8px;padding:10px 16px;font-weight:600;font-size:14px;cursor:pointer;min-height:44px}
.btn-print:hover{background:var(--menu)}
.years{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.yr{border:2px solid var(--header);background:var(--card);color:var(--header);font-weight:600;font-size:14px;padding:8px 16px;border-radius:99px;text-decoration:none;min-height:40px;display:inline-flex;align-items:center}
.yr:hover{background:var(--bg)}.yr.active{background:var(--header);color:var(--on-blue)}
.tbl-wrap{overflow-x:auto}
.nt{width:100%;border-collapse:collapse;font-size:14px}
.nt th,.nt td{padding:11px 12px;border-bottom:1px solid var(--border);text-align:center}
.nt th{background:#f4ebd0;color:var(--header);font-weight:600}
.nt th:first-child,.nt td:first-child{text-align:left}
.moy{font-weight:700}.moy.ok{color:var(--success)}.moy.ko{color:var(--error)}
.sem-foot{display:flex;gap:28px;flex-wrap:wrap;margin-top:14px;font-size:14px}
.sem-foot strong{font-size:18px;color:var(--header)}
.sem-foot strong.ok,.big.ok{color:var(--success)}.sem-foot strong.ko,.big.ko{color:var(--error)}
.final{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:700px){.final{grid-template-columns:1fr}}
.big{font-size:34px;font-weight:700;color:var(--header)}
.dec{display:inline-block;font-size:18px;font-weight:700;padding:8px 18px;border-radius:99px;background:#e8eef2;color:var(--text)}
.dec.ok{background:var(--success);color:#fff}.dec.ko{background:var(--error);color:var(--on-error)}.dec.warn{background:var(--warning);color:var(--on-warning)}
@media print{
  .sidebar,.top-navbar,.help-btn,.no-print{display:none!important}
  body{display:block;height:auto;overflow:visible;background:#fff}
  .main-content{overflow:visible}.content-body{padding:0}.card{box-shadow:none;break-inside:avoid}
}
</style>

<?php if (count($annees) > 0): ?>
<div class="years no-print" role="group" aria-label="Choisir l'année académique">
  <?php foreach ($annees as $a => $r): ?>
    <a class="yr <?php echo $a === $annee_sel ? 'active' : ''; ?>" href="etudiant_releve.php?annee=<?php echo urlencode($a); ?>"><?php echo htmlspecialchars($a); ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="rl-head">
  <div>
    <p class="muted">
      <?php echo htmlspecialchars($nom_sp); ?> · Section <?php echo htmlspecialchars($nom_sec); ?>
      <?php if ($est_courante): ?> · Groupe <?php echo (int)$etu['groupe']; ?><?php endif; ?>
      <?php if ($annee_sel !== null): ?> · Année académique <?php echo htmlspecialchars($annee_sel); ?><?php endif; ?><br>
      Édité le <?php echo date('d/m/Y'); ?>
    </p>
  </div>
  <button class="btn-print no-print" onclick="window.print()">🖨️ Imprimer le relevé</button>
</div>

<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$error): ?>
  <?php foreach ([1, 2] as $s):
      $mods = $par_sem[$s];
      $moy_s = $res['moyenne_s' . $s] ?? null;
      $rat_s = $res['rattrapage_s' . $s] ?? null; ?>
    <div class="card" style="margin-top:20px">
      <h3>Semestre <?php echo $s; ?></h3>
      <?php if (empty($mods)): ?>
        <p class="muted"><?php echo $est_courante ? 'Aucun module défini pour ce semestre.' : 'Le détail des modules de cette année n’a pas été archivé.'; ?></p>
      <?php else: ?>
        <div class="tbl-wrap"><table class="nt">
          <thead><tr><th>Module</th><th>Coef.</th><th>Moyenne du module</th></tr></thead>
          <tbody>
          <?php foreach ($mods as $m): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($m['Nom_mod']); ?></strong></td>
              <td><?php echo rtrim(rtrim(number_format((float)$m['coefficient'], 1, ',', ''), '0'), ','); ?></td>
              <td><?php if ($m['moy'] === null): ?><span class="muted">En attente</span>
                  <?php else: ?><span class="moy <?php echo $m['moy'] >= 10 ? 'ok' : 'ko'; ?>"><?php echo $m['moy'] >= 10 ? '' : ''; ?> <?php echo fmt($m['moy']); ?></span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
      <div class="sem-foot">
        <div>Moyenne du semestre <?php echo $s; ?> : <strong class="<?php echo cls_moy($moy_s); ?>"><?php echo $moy_s !== null ? ico_moy($moy_s) . fmt($moy_s) . ' / 20' : 'Non publiée'; ?></strong></div>
        <?php if ($rat_s !== null): ?><div>Après rattrapage : <strong class="<?php echo cls_moy($rat_s); ?>"><?php echo ico_moy($rat_s) . fmt($rat_s); ?> / 20</strong></div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card final">
    <div>
      <div class="muted">Moyenne finale</div>
      <div class="big <?php echo cls_moy($res['moyenne_finale'] ?? null); ?>"><?php echo isset($res['moyenne_finale']) ? ico_moy($res['moyenne_finale']) . fmt($res['moyenne_finale']) . ' <span style="font-size:18px">/ 20</span>' : '—'; ?></div>
    </div>
    <div>
      <div class="muted" style="margin-bottom:8px">Décision</div>
      <span class="dec <?php echo $d_class; ?>"><?php echo $d_icon . ' ' . htmlspecialchars($decision); ?></span>
    </div>
  </div>
  <?php if (!$res): ?>
    <p class="muted">Vos résultats officiels n’ont pas encore été publiés par la scolarité.</p>
  <?php endif; ?>
<?php endif; ?>

<?php require 'etu_footer.php'; ?>
