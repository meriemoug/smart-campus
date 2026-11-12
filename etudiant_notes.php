<?php
// Notes de l'étudiant : modules de sa spécialité (table programme), par semestre
$page_title  = 'Mes notes';
$page_active = 'etudiant_notes.php';
require 'etu_header.php';
require_once 'etu_functions.php';

$semestre = (($_GET['s'] ?? '1') === '2') ? 2 : 1;   // semestre affiché (S1 par défaut)
$par_sem = [1 => [], 2 => []];
$error = '';

try {
    // Modules du programme de la spécialité de l'étudiant + ses notes éventuelles
    $st = $pdo->prepare("SELECT p.semestre, p.coefficient, m.Nom_mod, n.TD, n.TP, n.Exam
                         FROM programme p
                         JOIN module m ON m.id_mod = p.id_mod
                         LEFT JOIN note n ON n.id_Mod = p.id_mod AND n.id_Etud = ?
                         WHERE p.id_sp = ?
                         ORDER BY p.semestre, m.Nom_mod");
    $st->execute([$etu['id_Etu'], $etu['id_sp']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        [$r['moy'], $r['has_ex']] = moyenne_module($r['TD'], $r['TP'], $r['Exam']);
        $par_sem[(int)$r['semestre']][] = $r;
    }
} catch (PDOException $e) {
    $error = "Impossible de charger vos notes pour le moment. Réessayez plus tard.";
}

// Résumé du semestre affiché : moyenne pondérée par les coefficients
$mods = $par_sem[$semestre];
$somme = $coefs = 0; $complet = true;
foreach ($mods as $m) {
    if ($m['moy'] === null) { $complet = false; continue; }
    if (!$m['has_ex']) { $complet = false; }
    $somme += $m['moy'] * $m['coefficient'];
    $coefs += $m['coefficient'];
}
$moy_sem = $coefs > 0 ? $somme / $coefs : null;
?>
<style>
.tabs{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.tab{padding:10px 18px;border-radius:8px;border:2px solid var(--header);color:var(--header);font-weight:600;font-size:14px;text-decoration:none;background:var(--card);min-height:44px;display:inline-flex;align-items:center}
.tab:hover{background:var(--bg)}.tab.active{background:var(--header);color:var(--on-blue)}
.tbl-wrap{overflow-x:auto}
.nt{width:100%;border-collapse:collapse;min-width:640px;font-size:14px}
.nt th,.nt td{padding:12px;border-bottom:1px solid var(--border);text-align:center}
.nt th{background:#f4ebd0;color:var(--header);font-weight:600}
.nt th:first-child,.nt td:first-child{text-align:left}
.moy{font-weight:700}.moy.ok{color:var(--success)}.moy.ko{color:var(--error)}
.pill{display:inline-block;font-size:11px;font-weight:700;padding:1px 8px;border-radius:99px;background:var(--warning);color:var(--on-warning);margin-left:6px}
.resume{display:flex;gap:30px;flex-wrap:wrap;align-items:center}
.resume .val{font-size:30px;font-weight:700;color:var(--header)}
.resume .val.ok{color:var(--success)}.resume .val.ko{color:var(--error)}
.verdict{font-size:13px;font-weight:600;margin-top:2px}.verdict.ok{color:var(--success)}.verdict.ko{color:var(--error)}
</style>


<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="tabs">
  <a href="etudiant_notes.php?s=1" class="tab <?php echo $semestre === 1 ? 'active' : ''; ?>">Semestre 1 (<?php echo count($par_sem[1]); ?> modules)</a>
  <a href="etudiant_notes.php?s=2" class="tab <?php echo $semestre === 2 ? 'active' : ''; ?>">Semestre 2 (<?php echo count($par_sem[2]); ?> modules)</a>
</div>

<?php if (!$error && empty($mods)): ?>
  <div class="card"><p class="muted">Aucun module n’est défini pour le semestre <?php echo $semestre; ?> dans le programme de votre spécialité.</p></div>
<?php elseif (!$error): ?>
  <div class="card">
    <div class="resume">
      <div>
        <div class="muted">Moyenne du semestre <?php echo $semestre; ?></div>
        <div class="val <?php echo cls_moy($moy_sem); ?>"><?php echo ico_moy($moy_sem) . fmt($moy_sem); ?> <span style="font-size:16px">/ 20</span>
          <?php if ($moy_sem !== null && !$complet): ?><span class="pill">Provisoire</span><?php endif; ?></div>
        <?php if ($moy_sem !== null): ?>
          <div class="verdict <?php echo cls_moy($moy_sem); ?>"><?php echo $moy_sem >= 10 ? '' : ''; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="nt">
        <thead><tr><th>Module</th><th>Coef.</th><th>TD</th><th>TP</th><th>Examen</th><th>Moyenne du module</th></tr></thead>
        <tbody>
        <?php foreach ($mods as $m): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($m['Nom_mod']); ?></strong></td>
            <td><?php echo rtrim(rtrim(number_format((float)$m['coefficient'], 1, ',', ''), '0'), ','); ?></td>
            <td><?php echo fmt($m['TD']); ?></td>
            <td><?php echo fmt($m['TP']); ?></td>
            <td><?php echo fmt($m['Exam']); ?></td>
            <td>
              <?php if ($m['moy'] === null): ?>
                <span class="muted">En attente</span>
              <?php else: ?>
                <span class="moy <?php echo $m['moy'] >= 10 ? 'ok' : 'ko'; ?>"><?php echo $m['moy'] >= 10 ? '' : ''; ?> <?php echo fmt($m['moy']); ?></span>
                <?php if (!$m['has_ex']): ?><span class="pill">Provisoire</span><?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require 'etu_footer.php'; ?>
