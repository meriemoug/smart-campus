<?php
// Assistant intelligent proactif : plan de révision, simulateur de note, ressources conseillées
$page_title  = 'Mon assistant';
$page_active = 'etudiant_assistant.php';
require 'etu_header.php';
require_once 'etu_functions.php';
require_once 'etu_assistant.php';

$today = new DateTime('today');
$error = '';
$modules = $exams = $seances = $prios = $plan = $ress = [];
$renf = [];
try {
    $modules = ast_modules($pdo, $etu);
    $exams   = ast_exams($pdo, $etu);
    $seances = ast_seances($pdo, $etu);
    $prios   = ast_priorites($modules, $exams, $today);
    $plan    = ast_plan($prios, $seances, $exams, $today);
    $renf    = ast_a_renforcer($modules, $prios);
    $ress    = ast_ressources($pdo, $etu, $renf);
} catch (PDOException $e) {
    $error = "L’assistant n’a pas pu charger vos données pour le moment. Réessayez plus tard.";
}

// Simulateur : pour chaque semestre, modules figés (examen noté) et modules simulables (examen manquant)
$sims = [];
foreach ([1, 2] as $s) {
    $fixed = $coefs = $sa = $sw = 0; $rows = [];
    foreach ($modules as $m) {
        if ($m['sem'] !== $s) continue;
        $coefs += $m['coef'];
        if ($m['has_ex']) { $fixed += $m['moy'] * $m['coef']; }
        else { $rows[] = $m; $sa += $m['coef'] * $m['a']; $sw += $m['coef'] * $m['w']; }
    }
    if ($rows) { $sims[$s] = ['fixed' => $fixed, 'coefs' => $coefs, 'rows' => $rows, 'need' => ($sw > 0 ? (10 * $coefs - $fixed - $sa) / $sw : null)]; }
}
$premier_sem = $sims ? array_key_first($sims) : null;

function ast_date_fr(DateTime $d): string { return AST_JOURS[(int)$d->format('w')] . ' ' . $d->format('d/m'); }
function ast_dans(int $j): string { return $j === 0 ? "aujourd’hui" : "dans $j jour" . ($j > 1 ? 's' : ''); }
function ast_lien_sur(string $l): bool { return (bool)preg_match('#^https?://#i', $l) || !preg_match('#^[a-z][a-z0-9+.\-]*:#i', $l); }
$types_res = ['cours' => 'Cours', 'td' => 'TD', 'tp' => 'TP', 'examen_corrige' => 'Examen corrigé', 'video' => 'Vidéo', 'autre' => 'Autre'];
?>
<style>
.perso{margin-bottom:18px;font-size:14px}.perso summary{cursor:pointer;font-weight:600;color:var(--menu)}
.perso label{display:inline-flex;gap:6px;align-items:center;margin:8px 18px 0 0}
.resume{background:#fffaf0;border:1px solid var(--border);border-left:6px solid var(--accent);border-radius:8px;padding:12px 14px;margin-bottom:12px;font-size:15px;line-height:1.5}
.row{padding:12px 0;border-top:1px solid var(--border);font-size:14px}.row:first-of-type{border-top:0}
.lvl{display:inline-block;font-size:12px;font-weight:700;padding:2px 10px;border-radius:99px;background:var(--warning);color:var(--on-warning)}
.chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:4px}
.chip{font-size:12px;font-weight:600;padding:3px 10px;border-radius:99px;background:#e8eef2;color:var(--text)}
.chip.rev{background:var(--accent);color:var(--on-accent)}
.ok{color:var(--success);font-weight:700}.ko{color:var(--error);font-weight:700}
.tabs{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.tab{padding:10px 18px;border-radius:8px;border:2px solid var(--header);color:var(--header);font-weight:600;font-size:14px;background:var(--card);cursor:pointer;min-height:44px;font-family:inherit}
.tab:hover{background:var(--bg)}.tab.active{background:var(--header);color:var(--on-blue)}
.sim{border:2px solid var(--border);border-radius:12px;padding:16px;margin-bottom:6px}
.sim h4{margin:0 0 12px;color:var(--header);font-size:17px}
.tbl{width:100%;border-collapse:collapse;font-size:14px}.tbl th,.tbl td{padding:10px;border-bottom:1px solid var(--border);text-align:center}
.tbl th{background:#f4ebd0;color:var(--header)}.tbl th:first-child,.tbl td:first-child{text-align:left}
.tbl input{width:80px;padding:6px;border:1px solid var(--border);border-radius:6px;font-size:14px;text-align:center}
.quick{margin-bottom:10px;font-size:14px}
.quick button{border:2px solid var(--header);background:var(--card);color:var(--header);font-weight:600;border-radius:99px;padding:5px 12px;cursor:pointer;margin-right:6px;font-family:inherit}
.proj{font-size:16px;margin-top:12px}.tw{overflow-x:auto}
.res a{color:var(--menu);font-weight:600}
</style>

<h3 class="section-title">🤖 Mon assistant</h3>
<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<details class="perso">
  <summary>⚙️ Personnaliser mon assistant</summary>
  <?php foreach (['rev' => 'Plan de révision', 'sim' => 'Simulateur de note', 'res' => 'Ressources conseillées'] as $k => $l): ?>
    <label><input type="checkbox" data-w="<?php echo $k; ?>" checked> <?php echo $l; ?></label>
  <?php endforeach; ?>
</details>

<!-- ===== 1. Plan de révision ===== -->
<div class="card" data-widget="rev">
  <h3>📚 Plan de révision</h3>
  <?php if (!$prios): ?>
    <p class="muted">Aucun examen à préparer pour le moment.</p>
  <?php else:
      $noms = array_map(fn($p) => '<strong>' . htmlspecialchars($p['mod']['nom']) . '</strong>', $prios); ?>
    <div class="resume">
      📌 À réviser en priorité : <?php echo $noms[0]; ?> (examen <?php echo ast_dans($prios[0]['jours']); ?>)<?php
        if (count($noms) > 1) echo ', puis ' . implode(', puis ', array_slice($noms, 1, 3)); ?>.
    </div>
    <?php foreach ($prios as $p): ?>
      <div class="row">
        <strong><?php echo htmlspecialchars($p['mod']['nom']); ?></strong>
        <?php if ($p['exam']['type_exam'] === 'rattrapage'): ?><span class="lvl">Rattrapage</span><?php endif; ?><br>
        <span class="muted">Examen le <?php echo date('d/m/Y', strtotime($p['exam']['dateEx'])); ?> (<?php echo ast_dans($p['jours']); ?>) · coefficient <?php echo fmt($p['mod']['coef']); ?></span>
      </div>
    <?php endforeach; ?>
    <h4 style="margin:16px 0 6px;color:var(--header)">Planning proposé</h4>
    <?php if (!$plan): ?><p class="muted">Aucun créneau libre trouvé avant les examens dans les 14 prochains jours.</p>
    <?php else: foreach ($plan as $date => $items): ?>
      <div class="row"><strong><?php echo ast_date_fr(new DateTime($date)); ?></strong>
        <div class="chips"><?php foreach ($items as $it): $c = AST_CRENEAUX[$it['slot']]; ?>
          <span class="chip rev"><?php echo $c[0] . ' – ' . $c[1] . ' · ' . htmlspecialchars($it['nom']); ?></span>
        <?php endforeach; ?></div></div>
    <?php endforeach; endif; ?>
  <?php endif; ?>
</div>

<!-- ===== 2. Simulateur ===== -->
<div class="card" data-widget="sim">
  <h3>🎯 Simulateur de note</h3>
  <?php if (!$sims): ?>
    <p class="muted">Tous vos examens sont déjà notés : il n’y a plus rien à simuler.</p>
  <?php else: ?>
    <div class="tabs" role="tablist">
      <?php foreach ($sims as $s => $sm): ?>
        <button type="button" class="tab <?php echo $s === $premier_sem ? 'active' : ''; ?>" data-s="<?php echo $s; ?>">Semestre <?php echo $s; ?></button>
      <?php endforeach; ?>
    </div>
    <?php foreach ($sims as $s => $sm): ?>
      <div class="sim" data-sem="<?php echo $s; ?>" data-fixed="<?php echo $sm['fixed']; ?>" data-coefs="<?php echo $sm['coefs']; ?>" <?php echo $s === $premier_sem ? '' : 'style="display:none"'; ?>>
        <h4>Semestre <?php echo $s; ?></h4>
        <div class="quick">Hypothèse pour tous les examens :
          <button type="button" data-v="8">8</button><button type="button" data-v="10">10</button><button type="button" data-v="12">12</button><button type="button" data-v="14">14</button></div>
        <div class="tw"><table class="tbl">
          <thead><tr><th>Module</th><th>Contrôle continu</th><th>Examen nécessaire pour 10</th><th>Si j’obtiens…</th><th>Moyenne du module</th></tr></thead>
          <tbody>
          <?php foreach ($sm['rows'] as $m): $need = ($m['w'] > 0) ? (10 - $m['a']) / $m['w'] : null; ?>
            <tr data-coef="<?php echo $m['coef']; ?>" data-a="<?php echo $m['a']; ?>" data-w="<?php echo $m['w']; ?>">
              <td><strong><?php echo htmlspecialchars($m['nom']); ?></strong> <span class="muted">(coef. <?php echo fmt($m['coef']); ?>)</span></td>
              <td><?php echo $m['ca'] !== null ? fmt($m['ca']) : '—'; ?></td>
              <td><?php if ($need <= 0): ?><span class="ok"> Déjà validé</span><?php elseif ($need > 20): ?><span class="ko"> Rattrapage nécessaire</span><?php else: ?><strong><?php echo fmt($need); ?> / 20</strong><?php endif; ?></td>
              <td><input type="number" min="0" max="20" step="0.25" value="10" aria-label="Note d’examen supposée pour <?php echo htmlspecialchars($m['nom']); ?>"></td>
              <td class="mm">—</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="proj">Moyenne projetée du semestre <?php echo $s; ?> : <strong class="pj">—</strong></div>
        <?php if ($sm['need'] !== null): ?>
          <p class="muted" style="margin-top:6px">
            <?php if ($sm['need'] <= 0): ?>Vous validez déjà ce semestre, quelles que soient vos notes d’examen.
            <?php elseif ($sm['need'] > 20): ?>Même avec 20 partout, la moyenne de 10 n’est pas atteignable : prévoyez le rattrapage.
            <?php else: ?>Pour valider le semestre, il faut en moyenne <strong><?php echo fmt($sm['need']); ?> / 20</strong> aux examens restants.<?php endif; ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ===== 3. Ressources conseillées ===== -->
<div class="card res" data-widget="res">
  <h3>⭐ Ressources conseillées</h3>
  <?php if (!$renf): ?>
    <p class="muted">Aucun module à renforcer pour le moment. Bravo !</p>
  <?php else: foreach ($renf as $id): $m = $modules[$id]; ?>
    <div class="row">
      <strong><?php echo htmlspecialchars($m['nom']); ?></strong>
      <?php if (empty($ress[$id])): ?><div class="muted">Aucune ressource publiée pour ce module.</div>
      <?php else: foreach ($ress[$id] as $r): ?>
        <div style="margin-top:4px"><span class="chip"><?php echo $types_res[$r['type_res']] ?? 'Autre'; ?></span>
          <?php if (ast_lien_sur($r['lien'])): ?><a href="<?php echo htmlspecialchars($r['lien']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['titre']); ?></a>
          <?php else: echo htmlspecialchars($r['titre']); endif; ?></div>
      <?php endforeach; endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>
function fmtN(v){return v.toFixed(2).replace('.',',');}
// Simulateur : recalcul en direct
document.querySelectorAll('.sim').forEach(function(box){
  function calc(){
    var tot=parseFloat(box.dataset.fixed), co=parseFloat(box.dataset.coefs);
    box.querySelectorAll('tr[data-coef]').forEach(function(tr){
      var e=parseFloat(tr.querySelector('input').value.replace(',','.')); if(isNaN(e)) e=0; e=Math.max(0,Math.min(20,e));
      var m=parseFloat(tr.dataset.a)+parseFloat(tr.dataset.w)*e;
      tr.querySelector('.mm').innerHTML='<span class="'+(m>=10?'ok':'ko')+'">'+(m>=10?' ':' ')+fmtN(m)+'</span>';
      tot+=m*parseFloat(tr.dataset.coef);
    });
    var avg=tot/co, p=box.querySelector('.pj');
    p.className='pj '+(avg>=10?'ok':'ko'); p.textContent=(avg>=10?' ':' ')+fmtN(avg)+' / 20';
  }
  box.querySelectorAll('input').forEach(function(i){i.addEventListener('input',calc);});
  box.querySelectorAll('.quick button').forEach(function(b){b.onclick=function(){box.querySelectorAll('input').forEach(function(i){i.value=b.dataset.v;});calc();};});
  calc();
});
// Onglets Semestre 1 / Semestre 2
document.querySelectorAll('.tabs .tab').forEach(function(t){
  t.onclick=function(){
    document.querySelectorAll('.tabs .tab').forEach(function(x){x.classList.toggle('active',x===t);});
    document.querySelectorAll('.sim').forEach(function(b){b.style.display=(b.dataset.sem===t.dataset.s)?'':'none';});
  };
});
// Personnalisation : masquer / afficher chaque bloc (mémorisé sur cet appareil)
(function(){
  var KEY='ast_hidden', hid=[];
  try{hid=JSON.parse(localStorage.getItem(KEY)||'[]');}catch(e){}
  function apply(){document.querySelectorAll('[data-widget]').forEach(function(w){w.style.display=hid.indexOf(w.dataset.widget)>-1?'none':'';});
    document.querySelectorAll('.perso input').forEach(function(i){i.checked=hid.indexOf(i.dataset.w)===-1;});}
  document.querySelectorAll('.perso input').forEach(function(i){i.onchange=function(){
    hid=hid.filter(function(x){return x!==i.dataset.w;}); if(!i.checked) hid.push(i.dataset.w);
    try{localStorage.setItem(KEY,JSON.stringify(hid));}catch(e){} apply();};});
  apply();
})();
</script>

<?php require 'etu_footer.php'; ?>
