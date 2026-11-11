<?php
// Page d'accueil étudiant : annonces + résumé du jour (base de l'assistant intelligent)
$page_title  = 'Annonces';
$page_active = 'etudiant_dashboard.php';
require 'etu_header.php';

$jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$aujourdhui = $jours[(int)date('w')];
$annonces = $seances = [];
$exam = null;
$error = '';

try {
    // Annonces destinées aux étudiants, générales ou de leur section
    $st = $pdo->prepare("SELECT a.titre, a.det, a.Date_Pub, u.nom, u.prenom, u.role
                         FROM annonce a
                         LEFT JOIN user u ON u.id_user = a.id_user
                         WHERE a.cible IN ('tous','etudiants') AND (a.id_Sec IS NULL OR a.id_Sec = ?)
                         ORDER BY a.Date_Pub DESC, a.id_Anm DESC");
    $st->execute([$etu['id_Sec']]);
    $annonces = $st->fetchAll(PDO::FETCH_ASSOC);

    // Séances d'aujourd'hui (cours de la section + TD/TP de son groupe)
    $st = $pdo->prepare("SELECT s.type_seance, s.heurD, s.heurF, m.Nom_mod, sa.nom_salle
                         FROM seance s
                         JOIN module m ON m.id_mod = s.id_mod
                         JOIN salle sa ON sa.id_salle = s.id_salle
                         WHERE s.id_Sec = ? AND s.jour = ? AND (s.id_groupe IS NULL OR s.id_groupe = ?)
                         ORDER BY s.heurD");
    $st->execute([$etu['id_Sec'], $aujourdhui, $etu['groupe']]);
    $seances = $st->fetchAll(PDO::FETCH_ASSOC);

    // Prochain examen de la section
    $st = $pdo->prepare("SELECT x.dateEx, x.heurD, x.heurF, x.type_exam, m.Nom_mod, sa.nom_salle
                         FROM exam x
                         JOIN module m ON m.id_mod = x.id_mod
                         LEFT JOIN salle sa ON sa.id_salle = x.id_salle
                         WHERE x.id_sec = ? AND x.dateEx >= CURDATE()
                         ORDER BY x.dateEx, x.heurD LIMIT 1");
    $st->execute([$etu['id_Sec']]);
    $exam = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Impossible de charger vos informations pour le moment. Réessayez plus tard.";
}
?>
<?php if ($error): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<h3 class="section-title">Bonjour <?php echo htmlspecialchars($_SESSION['prenom'] ?? ''); ?> 👋</h3>

<div class="grid">
  <!-- Annonces -->
  <div>
    <h3 class="section-title" style="font-size:18px">Annonces</h3>
    <?php if (empty($annonces)): ?>
      <div class="card"><p class="muted">Aucune annonce pour le moment.</p></div>
    <?php else: foreach ($annonces as $a):
        $nouveau = (time() - strtotime($a['Date_Pub'])) <= 3 * 86400; 
        if (($a['role'] ?? '') === 'prof') {
            $icone  = '👨‍🏫';
            $auteur = 'Enseignant : Pr. ' . $a['nom'] . ' ' . $a['prenom'];
        } elseif (($a['role'] ?? '') === 'scolarite') {
            $icone  = '🏛️';
            $auteur = 'Scolarité';
        } else {
            $icone  = '📢';
            $auteur = 'Administration';
        }
        ?>
      <article class="ann">
        <!--<div class="ann-icon" aria-hidden="true"><?php echo $icone; ?></div>-->
        <div>
          <h4><?php echo htmlspecialchars($a['titre']); ?><?php if ($nouveau): ?><span class="badge">Nouveau</span><?php endif; ?></h4>
          <p><?php echo htmlspecialchars($a['det']); ?></p>
          <small class="muted"><?php echo htmlspecialchars($auteur); ?> · Publié le <?php echo date('d/m/Y', strtotime($a['Date_Pub'])); ?></small>
        </div>
      </article>
    <?php endforeach; endif; ?>
  </div>

  <!-- Résumé du jour -->
  <div>
    <div class="card">
      <h3>📅 Aujourd’hui · <?php echo $aujourdhui; ?></h3>
      <?php if (empty($seances)): ?>
        <p class="muted">Aucune séance prévue aujourd’hui.</p>
      <?php else: foreach ($seances as $s): ?>
        <div class="item">
          <strong><?php echo htmlspecialchars($s['Nom_mod']); ?></strong>
          <span class="badge"><?php echo htmlspecialchars($s['type_seance']); ?></span><br>
          <span class="muted"><?php echo substr($s['heurD'],0,5); ?> – <?php echo substr($s['heurF'],0,5); ?> · <?php echo htmlspecialchars($s['nom_salle']); ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <h3>🗓️ Prochain examen</h3>
      <?php if (!$exam): ?>
        <p class="muted">Aucun examen programmé.</p>
      <?php else:
        $j = (new DateTime('today'))->diff(new DateTime($exam['dateEx']))->days; ?>
        <strong><?php echo htmlspecialchars($exam['Nom_mod']); ?></strong>
        <span class="badge warn"><?php echo $j === 0 ? "Aujourd’hui" : "Dans $j jour" . ($j > 1 ? 's' : ''); ?></span><br>
        <span class="muted">
          <?php echo date('d/m/Y', strtotime($exam['dateEx'])); ?>
          <?php if ($exam['heurD']): ?> · <?php echo substr($exam['heurD'],0,5); ?> – <?php echo substr($exam['heurF'],0,5); ?><?php endif; ?>
          · <?php echo htmlspecialchars($exam['nom_salle'] ?? 'Salle à confirmer'); ?>
          (<?php echo htmlspecialchars($exam['type_exam']); ?>)
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require 'etu_footer.php'; ?>
