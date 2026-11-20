<?php
// ===== En-tête commun de l'espace étudiant (session, BD, sidebar, navbar) =====
session_start();
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'etudiant') {
    header('Location: login.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=smart_campus;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Profil de l'étudiant connecté : section, groupe, spécialité
    $st = $pdo->prepare("SELECT e.id_Etu, e.id_sp, e.id_Sec, e.groupe, e.annee, s.nom_sec, sp.NomSp
                         FROM etudiant e
                         LEFT JOIN section s ON s.id_Sec = e.id_Sec
                         LEFT JOIN specialite sp ON sp.id_sp = e.id_sp
                         WHERE e.id_user = ?");
    $st->execute([$_SESSION['id_user']]);
    $etu = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données.");
}
if (!$etu) { die("Profil étudiant introuvable. Contactez la scolarité."); }

$menu = [
    'etudiant_dashboard.php' => '📢 Annonces',
    'etudiant_edt.php'       => '📅 Emploi du temps',
    'etudiant_notes.php'     => '📝 Mes notes',
    'etudiant_consultations.php' => '🕒 Consultations des profs',
    'etudiant_examens.php'   => '🗓️ Planning des examens',
    'etudiant_ressources.php'=> '📚 Ressources pédagogiques',
    'etudiant_releve.php'    => '🎓 Relevé de notes',
    'etudiant_assistant.php' => '🤖 Mon assistant',
    
];
$page_active = $page_active ?? '';
$page_title  = $page_title ?? 'Espace Étudiant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Campus - <?php echo htmlspecialchars($page_title); ?></title>
<style>
:root{--header:#1B5A80;--menu:#226B94;--on-blue:#FFFFFF;--accent:#F0B955;--on-accent:#2A1D00;
--bg:#F8F0D8;--card:#FFFFFF;--border:#E0C98A;--text:#16384D;--text-muted:#51606A;
--error:#A8162A;--on-error:#FFFFFF;--success:#2e7d32;--warning:#D9761C;--on-warning:#2A1500}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{background:var(--bg);color:var(--text);display:flex;height:100vh;overflow:hidden}
.sidebar{width:280px;background:var(--header);display:flex;flex-direction:column;justify-content:space-between;padding:20px;box-shadow:4px 0 10px rgba(0,0,0,.05);overflow-y:auto}
.logo-area{color:var(--on-blue);font-size:18px;font-weight:bold;margin-bottom:30px;padding-bottom:15px;border-bottom:1px solid rgba(255,255,255,.1)}
.menu{display:flex;flex-direction:column;gap:8px}
.menu-item{color:var(--on-blue);padding:10px 15px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;transition:background .2s;display:flex;align-items:center;gap:10px;min-height:44px}
.menu-item:hover,.menu-item.active{background:var(--accent);color:var(--on-accent);font-weight:600}
.btn-logout{background:var(--error);color:var(--on-error);padding:10px;border-radius:8px;text-align:center;text-decoration:none;font-weight:600;margin-top:20px}
.btn-logout:hover{opacity:.9}
.main-content{flex:1;display:flex;flex-direction:column;overflow-y:auto}
.top-navbar{background:var(--header);color:var(--on-blue);padding:15px 30px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 5px rgba(0,0,0,.05)}
.user-menu-container{position:relative;cursor:pointer}
.user-profile{display:flex;align-items:center;gap:12px}
.user-avatar{width:38px;height:38px;background:var(--accent);color:var(--on-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold}
.user-dropdown{display:none;position:absolute;right:0;top:50px;background:var(--card);border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.1);width:260px;z-index:1000;padding:15px;color:var(--text)}
.user-dropdown.active{display:block}
.dropdown-header{font-weight:bold;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border);font-size:13px}
.dropdown-item{display:block;width:100%;padding:8px 10px;font-size:13px;font-weight:600;text-decoration:none;color:var(--text);border-radius:4px;margin-bottom:5px}
.dropdown-item:hover{background:var(--bg)}
.dropdown-item.logout{background:var(--error);color:var(--on-error);text-align:center}
.content-body{padding:30px}
.section-title{color:var(--header);font-size:22px;margin-bottom:20px;font-weight:700}
.grid{display:grid;grid-template-columns:1.5fr 1fr;gap:30px}
@media(max-width:1024px){.grid{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:25px;box-shadow:0 4px 12px rgba(27,90,128,.04);margin-bottom:20px}
.card h3{color:var(--header);margin-bottom:16px;font-size:18px}
.alert-error{background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #f5c6cb}
.muted{color:var(--text-muted);font-size:14px}
:focus-visible{outline:3px solid var(--warning);outline-offset:2px}
/* Annonces */
.ann{display:flex;gap:14px;background:var(--card);border:1px solid var(--border);border-left:6px solid var(--accent);border-radius:10px;padding:14px;margin-bottom:14px}
.ann-icon{width:56px;height:56px;border-radius:8px;background:var(--accent);color:var(--on-accent);display:flex;align-items:center;justify-content:center;font-size:26px;flex:none}
.ann h4{font-size:16px;margin-bottom:4px}.ann p{font-size:14px;margin-bottom:6px;white-space:pre-line}
.badge{display:inline-block;font-size:12px;font-weight:700;padding:2px 8px;border-radius:99px;background:var(--accent);color:var(--on-accent);margin-left:6px}
.badge.warn{background:var(--warning);color:var(--on-warning)}
/* Séances du jour */
.item{padding:10px 0;border-bottom:1px solid var(--border);font-size:14px}.item:last-child{border:0}
.help-btn{position:fixed;left:300px;bottom:20px;width:44px;height:44px;border-radius:50%;background:var(--header);color:var(--on-blue);display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:18px;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.2)}
</style>
</head>
<body>
<div class="sidebar">
  <div>
    <div class="logo-area">🎓 Smart Campus </div>
    <nav class="menu" aria-label="Menu principal">
      <?php foreach ($menu as $file => $label): ?>
        <a href="<?php echo $file; ?>" class="menu-item <?php echo $file === $page_active ? 'active' : ''; ?>"><?php echo $label; ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

<div class="main-content">
  <div class="top-navbar">
    <h2>Espace Étudiant - <?php echo htmlspecialchars($page_title); ?></h2>

    <?php
// Récupérer les notifications non lues et la liste des notifications pour l'utilisateur connecté
$id_user_connecte = $_SESSION['id_user'];
$stmt_notifs = $pdo->prepare("SELECT * FROM notification WHERE id_user = ? ORDER BY date_creation DESC LIMIT 15");
$stmt_notifs->execute([$id_user_connecte]);
$notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
$stmt_count->execute([$id_user_connecte]);
$nb_non_lus = $stmt_count->fetchColumn();
?>

<!-- Conteneur des notifications -->
<div class="notif-menu-container" onclick="toggleNotifMenu(event)" style="position: relative; cursor: pointer; margin-right: -160px;">
    <div style="font-size: 20px; position: relative;">
        🔔
        <?php if ($nb_non_lus > 0): ?>
            <span style="position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background-color: #dc2626; border-radius: 50%; border: 2px solid var(--header);"></span>
        <?php endif; ?>
    </div>

    <!-- Dropdown des notifications -->
    <div id="notifDropdown" class="user-dropdown" style="width: 320px; max-height: 400px; overflow-y: auto; right: 0; left: auto;">
        <div class="dropdown-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Notifications</span>
            <?php if ($nb_non_lus > 0): ?>
                <span style="background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 10px; font-size: 11px;"><?= $nb_non_lus ?> non lue(s)</span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div style="padding: 15px; text-align: center; color: #64748b; font-size: 13px;">Aucune notification pour le moment.</div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <a href="marquer_lu.php?id=<?= $n['id_notif'] ?>&type=<?= $n['type'] ?>" class="dropdown-item" style="white-space: normal; border-bottom: 1px solid #f1f5f9; background: <?= $n['lu'] == 0 ? '#f8fafc' : 'white'; ?>; padding: 10px 15px;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 3px;"><?= date('d/m/Y H:i', strtotime($n['date_creation'])) ?></div>
                    <div style="font-size: 13px; color: var(--text); font-weight: <?= $n['lu'] == 0 ? 'bold' : 'normal'; ?>;"><?= htmlspecialchars($n['message']) ?></div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
    <div class="user-menu-container" onclick="toggleUserMenu(event)">
      <div class="user-profile">
        <span><strong><?php echo htmlspecialchars(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? '')); ?></strong></span>
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'] ?? 'E', 0, 1)); ?></div>
      </div>
      <div id="userDropdown" class="user-dropdown">
        <div class="dropdown-header">
          Connecté en tant que<br><span style="color:var(--menu)"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></span><br>
          <span class="muted">Section <?php echo htmlspecialchars($etu['nom_sec'] ?? '-'); ?> · Groupe <?php echo (int)$etu['groupe']; ?><br><?php echo htmlspecialchars($etu['NomSp'] ?? ''); ?></span>
        </div>
        <a href="modifier_infos.php" class="dropdown-item">✏️ Modifier mes informations</a>
        <a href="logout.php" class="dropdown-item logout">Se déconnecter</a>
      </div>
    </div>
    
  </div>
  <script>
    function toggleNotifMenu(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('notifDropdown');
    dropdown.classList.toggle('active');
    // Fermer l'autre menu utilisateur si ouvert
    const userDropdown = document.getElementById('userDropdown');
    if(userDropdown) userDropdown.classList.remove('active');
}

// Mettre à jour l'événement global de clic pour fermer le menu notif
window.addEventListener('click', function() {
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown && notifDropdown.classList.contains('active')) {
        notifDropdown.classList.remove('active');
    }
});
  </script>
  <div class="content-body">
