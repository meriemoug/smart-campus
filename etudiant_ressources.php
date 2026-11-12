<?php
// Ressources pédagogiques : par module, publiées par les profs pour la section de l'étudiant
$page_title  = 'Ressources pédagogiques';
$page_active = 'etudiant_ressources.php';
require 'etu_header.php';

$types = ['cours' => ['Cours','📘'], 'td' => ['TD','📝'], 'tp' => ['TP','🧪'],
          'examen_corrige' => ['Examen corrigé','✅'], 'video' => ['Vidéo','🎬'], 'autre' => ['Autre','📎']];
$par_mod = [];
$error = '';

try {
    // 1) Tous les modules du programme (même ceux sans ressource, pour dire « rien de publié »)
    $st = $pdo->prepare("SELECT m.id_mod, m.Nom_mod FROM programme p JOIN module m ON m.id_mod = p.id_mod
                         WHERE p.id_sp = ? ORDER BY p.semestre, m.Nom_mod");
    $st->execute([$etu['id_sp']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) { $par_mod[$m['id_mod']] = ['nom' => $m['Nom_mod'], 'res' => []]; }

    // 2) Ressources publiées pour la section
    $st = $pdo->prepare("SELECT r.id_module, r.titre, r.lien, r.type_res, r.date_ajout, m.Nom_mod, u.nom, u.prenom
                         FROM ressource r
                         JOIN module m ON m.id_mod = r.id_module
                         LEFT JOIN prof p ON p.id_prof = r.id_prof
                         LEFT JOIN user u ON u.id_user = p.id_user
                         WHERE r.id_sect = ?
                         ORDER BY m.Nom_mod, r.date_ajout DESC");
    $st->execute([$etu['id_Sec']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $par_mod[$r['id_module']] ??= ['nom' => $r['Nom_mod'], 'res' => []];
        $par_mod[$r['id_module']]['res'][] = $r;
    }
} catch (PDOException $e) {
    $error = "Impossible de charger les ressources pour le moment. Réessayez plus tard.";
}
$total = array_sum(array_map(fn($m) => count($m['res']), $par_mod));
?>
<style>
.filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.f{border:2px solid var(--header);background:var(--card);color:var(--header);font-weight:600;font-size:13px;padding:8px 14px;border-radius:99px;cursor:pointer;min-height:40px;font-family:inherit}
.f.active{background:var(--header);color:var(--on-blue)}
.mcard h3{display:flex;justify-content:space-between;align-items:center;gap:10px}
.cnt{font-size:12px;font-weight:700;background:var(--accent);color:var(--on-accent);border-radius:99px;padding:2px 10px}
.res{display:flex;gap:12px;align-items:center;padding:12px 0;border-top:1px solid var(--border)}
.res .ic{font-size:24px;width:40px;text-align:center;flex:none}
.res .info{flex:1}.res .info strong{display:block;font-size:15px}
.tp{display:inline-block;font-size:11px;font-weight:700;padding:1px 8px;border-radius:99px;background:var(--header);color:var(--on-blue);margin-right:6px}
.new{display:inline-block;font-size:11px;font-weight:700;padding:1px 8px;border-radius:99px;background:var(--accent);color:var(--on-accent)}
.btn-open{background:var(--header);color:var(--on-blue);text-decoration:none;font-weight:600;font-size:13px;padding:9px 14px;border-radius:8px;white-space:nowrap;min-height:40px;display:inline-flex;align-items:center}
.btn-open:hover{background:var(--menu)}
</style>

<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$error && empty($par_mod)): ?>
  <div class="card"><p class="muted">Aucun module n’est défini pour votre spécialité.</p></div>
<?php elseif (!$error): ?>
  <div class="filters" role="group" aria-label="Filtrer par type">
    <button class="f active" data-t="all">Tout</button>
    <?php foreach ($types as $k => $t): ?><button class="f" data-t="<?php echo $k; ?>"><?php echo $t[1] . ' ' . $t[0]; ?></button><?php endforeach; ?>
  </div>

  <?php foreach ($par_mod as $m): ?>
    <div class="card mcard">
      <h3>📚 <?php echo htmlspecialchars($m['nom']); ?><span class="cnt"><?php echo count($m['res']); ?></span></h3>
      <?php if (empty($m['res'])): ?>
        <p class="muted empty">Aucune ressource publiée pour ce module.</p>
      <?php else: foreach ($m['res'] as $r):
          $t = $types[$r['type_res']] ?? $types['autre'];
          $externe = (bool)preg_match('#^https?://#i', $r['lien']);
          $sur = $externe || !preg_match('#^[a-z][a-z0-9+.\-]*:#i', $r['lien']);   // refuse javascript:, data:, etc.
          $nouveau = $r['date_ajout'] && (time() - strtotime($r['date_ajout'])) <= 7 * 86400; ?>
        <div class="res" data-type="<?php echo htmlspecialchars($r['type_res']); ?>">
          <div class="ic" aria-hidden="true"><?php echo $t[1]; ?></div>
          <div class="info">
            <strong><?php echo htmlspecialchars($r['titre']); ?> <?php if ($nouveau): ?><span class="new">Nouveau</span><?php endif; ?></strong>
            <span class="tp"><?php echo $t[0]; ?></span>
            <span class="muted"><?php echo $r['nom'] ? 'Pr. ' . htmlspecialchars($r['nom'] . ' ' . $r['prenom']) . ' · ' : ''; ?><?php echo $r['date_ajout'] ? date('d/m/Y', strtotime($r['date_ajout'])) : ''; ?></span>
          </div>
          <?php if ($sur): ?>
            <a class="btn-open" href="<?php echo htmlspecialchars($r['lien']); ?>" target="_blank" rel="noopener" <?php echo $externe ? '' : 'download'; ?>><?php echo $externe ? '🔗 Ouvrir' : '⬇️ Télécharger'; ?></a>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php endforeach; ?>

<script>
// Filtre par type de ressource
document.querySelectorAll('.f').forEach(function (b) {
  b.onclick = function () {
    var t = b.dataset.t;
    document.querySelectorAll('.f').forEach(function (x) { x.classList.toggle('active', x === b); });
    document.querySelectorAll('.res').forEach(function (r) { r.style.display = (t === 'all' || r.dataset.type === t) ? '' : 'none'; });
    document.querySelectorAll('.mcard').forEach(function (c) {
      var vis = Array.prototype.some.call(c.querySelectorAll('.res'), function (r) { return r.style.display !== 'none'; });
      c.style.display = (t === 'all' || vis) ? '' : 'none';
    });
  };
});
</script>
<?php endif; ?>

<?php require 'etu_footer.php'; ?>
