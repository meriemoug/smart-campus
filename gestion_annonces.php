<?php
session_start();
// Accès réservé à la scolarité (principal et agents)
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'scolarite') {
    header('Location: login.php');
    exit();
}

$message = $error = '';
// La scolarité peut écrire à tout le monde, y compris à elle-même
$cibles = ['tous' => 'Tous', 'etudiants' => 'Étudiants', 'profs' => 'Professeurs', 'scolarite' => 'Scolarité'];
$vue  = (($_GET['vue'] ?? 'recues') === 'gestion') ? 'gestion' : 'recues';   // par défaut : annonces reçues
$edit = null;
$annonces = $recues = $sections = [];
$search = trim($_GET['search'] ?? '');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=smart_campus;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ---- Ajout / modification ----
    if (isset($_POST['ajouter_annonce']) || isset($_POST['modifier_annonce'])) {
        $titre = trim($_POST['titre'] ?? '');
        $det   = trim($_POST['det'] ?? '');
        $cible = $_POST['cible'] ?? 'tous';
        $id_sec = ($_POST['id_sec'] ?? '') === '' ? null : (int)$_POST['id_sec'];
        if ($cible !== 'etudiants') { $id_sec = null; } // la section n'a de sens que pour les étudiants

        if ($titre === '' || $det === '') {
            $error = "Le titre et le contenu sont obligatoires.";
        } elseif (mb_strlen($titre) > 150) {
            $error = "Le titre ne doit pas dépasser 150 caractères.";
        } elseif (!isset($cibles[$cible])) {
            $error = "Destinataire invalide.";
        } elseif (isset($_POST['ajouter_annonce'])) {
            $st = $pdo->prepare("INSERT INTO annonce (id_user, titre, det, Date_Pub, cible, id_Sec) VALUES (?, ?, ?, CURDATE(), ?, ?)");
            $st->execute([$_SESSION['id_user'], $titre, $det, $cible, $id_sec]);
            $message = "Annonce publiée avec succès.";
        } else {
            $st = $pdo->prepare("UPDATE annonce SET titre = ?, det = ?, cible = ?, id_Sec = ? WHERE id_Anm = ? AND id_user = ?");
            $st->execute([$titre, $det, $cible, $id_sec, (int)$_POST['id_anm'], $_SESSION['id_user']]);
            $message = "Annonce modifiée avec succès.";
        }
    }

    // ---- Suppression (uniquement ses propres annonces) ----
    if (isset($_POST['supprimer_annonce'])) {
        $st = $pdo->prepare("DELETE FROM annonce WHERE id_Anm = ? AND id_user = ?");
        $st->execute([(int)$_POST['id_anm_to_delete'], $_SESSION['id_user']]);
        $message = "Annonce supprimée.";
    }

    if ($vue === 'gestion') {
        // Mode édition : pré-remplir le formulaire
        if (isset($_GET['edit'])) {
            $st = $pdo->prepare("SELECT * FROM annonce WHERE id_Anm = ? AND id_user = ?");
            $st->execute([(int)$_GET['edit'], $_SESSION['id_user']]);
            $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $sections = $pdo->query("SELECT id_Sec, nom_sec FROM section ORDER BY nom_sec")->fetchAll(PDO::FETCH_ASSOC);

        // Mes annonces envoyées (+ recherche par titre)
        $st = $pdo->prepare("SELECT a.id_Anm, a.titre, a.det, a.Date_Pub, a.cible, s.nom_sec
                             FROM annonce a
                             LEFT JOIN section s ON s.id_Sec = a.id_Sec
                             WHERE a.id_user = ? AND a.titre LIKE ?
                             ORDER BY a.Date_Pub DESC, a.id_Anm DESC");
        $st->execute([$_SESSION['id_user'], '%' . $search . '%']);
        $annonces = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Annonces reçues : destinées à la scolarité, hors celles que j'ai moi-même écrites
        $st = $pdo->prepare("SELECT a.titre, a.det, a.Date_Pub, a.image, u.nom, u.prenom, u.role
                             FROM annonce a
                             LEFT JOIN user u ON u.id_user = a.id_user
                             WHERE a.cible IN ('tous','scolarite') AND (a.id_user IS NULL OR a.id_user <> ?)
                             ORDER BY a.Date_Pub DESC, a.id_Anm DESC");
        $st->execute([$_SESSION['id_user']]);
        $recues = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Erreur lors de l'opération sur la base de données.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Campus - Gestion des annonces</title>
    <style>
        :root {
            --header: #1B5A80;
            --menu: #226B94;
            --on-blue: #FFFFFF;
            --accent: #F0B955;
            --on-accent: #2A1D00;
            --bg: #F8F0D8;
            --card: #FFFFFF;
            --border: #E0C98A;
            --text: #16384D;
            --text-muted: #51606A;
            --error: #A8162A;
            --on-error: #FFFFFF;
            --success: #2e7d32;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 280px;
            background-color: var(--header);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar-top .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--on-blue);
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu-item {
            background-color: transparent;
            color: var(--on-blue);
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-item:hover, .menu-item.active {
            background-color: var(--accent);
            color: var(--on-accent);
            font-weight: 600;
        }

        .btn-logout {
            background-color: var(--error);
            color: var(--on-error);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .btn-logout:hover {
            opacity: 0.9;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .top-navbar {
            background-color: var(--header);
            color: var(--on-blue);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background-color: var(--accent);
            color: var(--on-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .content-body {
            padding: 30px;
        }

        .section-title {
            color: var(--header);
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(27,90,128,0.04);
        }

        .card h3 {
            color: var(--header);
            margin-bottom: 20px;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background-color: #FAFCFE;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--menu);
            box-shadow: 0 0 0 3px rgba(34, 107, 148, 0.15);
        }

        /* Style de la barre de recherche */
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-form input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background-color: #FAFCFE;
        }

        .search-form input:focus {
            outline: none;
            border-color: var(--menu);
            box-shadow: 0 0 0 3px rgba(34, 107, 148, 0.15);
        }

        .btn-search {
            background-color: var(--menu);
            color: var(--on-blue);
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-search:hover {
            background-color: var(--header);
        }

        .btn-reset {
            background-color: #e2e8f0;
            color: var(--text);
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: background 0.2s;
        }

        .btn-reset:hover {
            background-color: #cbd5e1;
        }

        .btn-submit {
            background-color: var(--header);
            color: var(--on-blue);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: var(--menu);
        }

        .btn-delete {
            background-color: var(--error);
            color: var(--on-error);
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-delete:hover {
            opacity: 0.85;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background-color: #f4ebd0;
            color: var(--header);
            font-weight: 600;
        }

        td {
            color: var(--text);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* Style du profil utilisateur cliquable */
        .user-menu-container { position: relative; display: inline-block; cursor: pointer; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; background-color: var(--accent); color: var(--on-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
        /* Dropdown du profil */
        .user-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 240px;
            z-index: 1000;
            padding: 15px;
            color: var(--text);
        }
        .user-dropdown.active { display: block; }
        .dropdown-header { font-weight: bold; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .dropdown-item { display: block; width: 100%; padding: 8px 10px; text-align: left; background: none; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; color: var(--text); margin-bottom: 5px; }
        .dropdown-item:hover { background-color: var(--bg); }
        .dropdown-item.logout { background-color: var(--error); color: var(--on-error); text-align: center; }
        .dropdown-item.logout:hover { opacity: 0.9; }

        .menu-dropdown {
    position: relative;
}

.dropdown-toggle {
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Le sous-menu */
.submenu {
    flex-direction: column;
    gap: 4px;
    padding-left: 15px;
    margin-top: 4px;
    margin-bottom: 4px;
    border-left: 2px solid rgba(255, 255, 255, 0.2);
}

.submenu-item {
    color: rgba(255, 255, 255, 0.85);
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 400;
    transition: background 0.2s, color 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.submenu-item:hover, .submenu-item.active {
    background-color: var(--accent);
    color: var(--on-accent);
    font-weight: 600;
}
        /* ---- Ajouts spécifiques à la page Annonces ---- */
        .form-group textarea, .form-group select { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; font-size:14px; background-color:#FAFCFE; font-family:inherit; }
        .form-group textarea { min-height:110px; resize:vertical; }
        .form-group textarea:focus, .form-group select:focus { outline:none; border-color:var(--menu); box-shadow:0 0 0 3px rgba(34,107,148,.15); }
        .hint { font-size:12px; color:var(--text-muted); margin-top:4px; }
        .badge { display:inline-block; font-size:12px; font-weight:700; padding:2px 8px; border-radius:99px; background:var(--accent); color:var(--on-accent); }
        .btn-edit { background:var(--menu); color:var(--on-blue); padding:6px 12px; border-radius:4px; font-size:13px; font-weight:600; text-decoration:none; display:inline-block; }
        .btn-edit:hover { background:var(--header); }
        .actions { display:flex; gap:8px; align-items:center; }
        .btn-cancel { display:block; text-align:center; margin-top:10px; font-size:13px; color:var(--text-muted); }
        .tabs { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .tab { padding:10px 18px; border-radius:8px; border:2px solid var(--header); color:var(--header); font-weight:600; font-size:14px; text-decoration:none; background:var(--card); }
        .tab:hover { background:var(--bg); }
        .tab.active { background:var(--header); color:var(--on-blue); }
        .ann { display:flex; gap:14px; border:1px solid var(--border); border-left:6px solid var(--accent); border-radius:10px; padding:14px; margin-bottom:14px; }
        .ann-icon, .ann-img { width:56px; height:56px; border-radius:8px; flex:none; }
        .ann-icon { background:var(--accent); color:var(--on-accent); display:flex; align-items:center; justify-content:center; font-size:26px; }
        .ann-img { object-fit:cover; }
        .ann h4 { font-size:16px; margin-bottom:4px; } .ann p { font-size:14px; margin-bottom:6px; white-space:pre-line; }
    </style>
</head>
<body>

   <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area">
                <span>🎓 Smart Campus</span>
            </div>
            <div class="menu">
                <?php if (!empty($_SESSION['is_super_admin'])): ?>
                    <a href="admin_principal_dashboard.php" class="menu-item">👥 Agents de scolarité</a>
                <?php endif; ?>
                <a href="gestion_profs.php" class="menu-item">👨‍🏫 Professeurs</a>
                <a href="gestion_etudiants.php" class="menu-item ">🎓 Étudiants</a>
                <a href="gestion_sections.php" class="menu-item">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item">📚 Spécialités & Départements</a>
                <a href="gestion_examens.php" class="menu-item">📅 Plannings d'examens</a>
                <div class="menu-dropdown">
                    <!-- Le lien principal qui déclenche l'affichage au clic -->
                    <a href="javascript:void(0);" class="menu-item dropdown-toggle" onclick="toggleSubmenu()">
                        <span>📢 Notes, Absences & Annonces</span> 
                        <span id="arrow" style="font-size: 11px;">▾</span>
                    </a>
                    
                    <!-- Le sous-menu masqué par défaut grâce à l'attribut style="display: none;" -->
                    <div class="submenu" id="submenuNotes" style="display: none;">
                        <a href="gestion_notes.php" class="submenu-item">📊 Notes & Moyennes</a>
                        <a href="gestion_absences.php" class="submenu-item ">🚫 Absences</a>
                        <a href="gestion_annonces.php" class="submenu-item active">📢 Annonces</a>
                    </div>
                </div> 
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Gestion des Annonces</h2>

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
                    <span><strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1)); ?></div>
                </div>
                <div id="userDropdown" class="user-dropdown">
                    <div class="dropdown-header">
                        Connecté en tant que<br>
                        <span style="color: var(--menu);"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Scolarité'); ?></span>
                    </div>
                    <a href="modifier_infos.php" class="dropdown-item">✏️ Modifier mes informations</a>
                    <a href="logout.php" class="dropdown-item logout">Se déconnecter</a>
                </div>
            </div>
        </div>

        <div class="content-body">
            

            <div class="tabs">
                <a href="gestion_annonces.php?vue=gestion" class="tab <?php echo $vue === 'gestion' ? 'active' : ''; ?>">✍️ Gérer mes annonces</a>
                <a href="gestion_annonces.php?vue=recues" class="tab <?php echo $vue === 'recues' ? 'active' : ''; ?>">📥 Annonces reçues</a>
            </div>

            <?php if (!empty($message)): ?><div class="alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($vue === 'recues'): ?>
            <!-- ===== Annonces reçues ===== -->
            <div class="card">
                <h3>Annonces reçues</h3>
                <p class="hint" style="margin-bottom:16px">Annonces envoyées à la scolarité par les professeurs et par les autres agents.</p>
                <?php if (empty($recues)): ?>
                    <p style="color: var(--text-muted);">Aucune annonce reçue pour le moment.</p>
                <?php else: foreach ($recues as $r):
                    if (($r['role'] ?? '') === 'prof') { $icone = '👨‍🏫'; $exp = 'Enseignant : Pr. ' . $r['nom'] . ' ' . $r['prenom']; }
                    else { $icone = '🏛️'; $exp = 'Scolarité : ' . ($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''); } ?>
                    <article class="ann">
                        <?php if (!empty($r['image'])): ?>
                            <img class="ann-img" src="<?php echo htmlspecialchars($r['image']); ?>" alt="">
                        <?php else: ?>
                          <!--  <div class="ann-icon" aria-hidden="true"><?php echo $icone; ?></div> -->
                        <?php endif; ?>
                        <div>
                            <h4><?php echo htmlspecialchars($r['titre']); ?></h4>
                            <p><?php echo htmlspecialchars($r['det']); ?></p>
                            <small style="color:var(--text-muted)"><?php echo htmlspecialchars($exp); ?> · Publié le <?php echo date('d/m/Y', strtotime($r['Date_Pub'])); ?></small>
                        </div>
                    </article>
                <?php endforeach; endif; ?>
            </div>

<?php else: ?>
            <!-- ===== Gestion de mes annonces ===== -->
            <div class="admin-grid">
                <div class="card">
                    <h3><?php echo $edit ? "Modifier l'annonce" : "Publier une annonce"; ?></h3>
                    <form action="gestion_annonces.php?vue=gestion" method="POST">
                        <?php if ($edit): ?><input type="hidden" name="id_anm" value="<?php echo (int)$edit['id_Anm']; ?>"><?php endif; ?>
                        <div class="form-group">
                            <label for="titre">Titre</label>
                            <input type="text" id="titre" name="titre" maxlength="150" required placeholder="Ex: Planning des examens publié" value="<?php echo htmlspecialchars($edit['titre'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="det">Contenu</label>
                            <textarea id="det" name="det" required placeholder="Détails de l'annonce..."><?php echo htmlspecialchars($edit['det'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="cible">Destinataires</label>
                            <select id="cible" name="cible" onchange="majSection()">
                                <?php foreach ($cibles as $k => $l): ?>
                                    <option value="<?php echo $k; ?>" <?php echo (($edit['cible'] ?? 'tous') === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="blocSection">
                            <label for="id_sec">Section concernée</label>
                            <select id="id_sec" name="id_sec">
                                <option value="">Toutes les sections</option>
                                <?php foreach ($sections as $sc): ?>
                                    <option value="<?php echo (int)$sc['id_Sec']; ?>" <?php echo ((string)($edit['id_Sec'] ?? '') === (string)$sc['id_Sec']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sc['nom_sec']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="hint">Laissez « Toutes les sections » pour une annonce destinée à tous les étudiants.</div>
                        </div>
                        <button type="submit" name="<?php echo $edit ? 'modifier_annonce' : 'ajouter_annonce'; ?>" class="btn-submit">
                            <?php echo $edit ? "Enregistrer les modifications" : "Publier l'annonce"; ?>
                        </button>
                        <?php if ($edit): ?><a href="gestion_annonces.php?vue=gestion" class="btn-cancel">Annuler la modification</a><?php endif; ?>
                    </form>
                </div>

                <div class="card">
                    <h3>Mes annonces publiées</h3>
                    <form action="gestion_annonces.php" method="GET" class="search-form">
                        <input type="hidden" name="vue" value="gestion">
                        <input type="text" name="search" placeholder="Rechercher par titre..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-search">Rechercher</button>
                        <?php if (!empty($search)): ?><a href="gestion_annonces.php?vue=gestion" class="btn-reset">Réinitialiser</a><?php endif; ?>
                    </form>
                    <?php if (empty($annonces)): ?>
                        <p style="color: var(--text-muted);">Vous n'avez publié aucune annonce.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead><tr><th>Date</th><th>Annonce</th><th>Pour</th><th>Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($annonces as $a):
                                    $pour = $cibles[$a['cible']] . ($a['nom_sec'] ? ' · ' . $a['nom_sec'] : ''); ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($a['Date_Pub'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($a['titre']); ?></strong><br>
                                            <span style="font-size:12px;color:var(--text-muted)"><?php echo htmlspecialchars(mb_strimwidth($a['det'], 0, 70, '…')); ?></span></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($pour); ?></span></td>
                                        <td><div class="actions">
                                            <a href="gestion_annonces.php?vue=gestion&edit=<?php echo (int)$a['id_Anm']; ?>" class="btn-edit">Modifier</a>
                                            <form action="gestion_annonces.php?vue=gestion" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cette annonce ?');">
                                                <input type="hidden" name="id_anm_to_delete" value="<?php echo (int)$a['id_Anm']; ?>">
                                                <button type="submit" name="supprimer_annonce" class="btn-delete">Supprimer</button>
                                            </form>
                                        </div></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
<?php endif; ?>
        </div>
    </div>

    <script>
        function toggleUserMenu(event) { event.stopPropagation(); document.getElementById('userDropdown').classList.toggle('active'); }
        window.onclick = function () { var d = document.getElementById('userDropdown'); if (d && d.classList.contains('active')) d.classList.remove('active'); };
        function toggleSubmenu() {
            var s = document.getElementById('submenuNotes'), a = document.getElementById('arrow');
            var ouvert = s.style.display === 'flex';
            s.style.display = ouvert ? 'none' : 'flex';
            a.textContent = ouvert ? '▾' : '▴';
        }
        // Une annonce destinée aux profs n'a pas de section
        function majSection() {
            var c = document.getElementById('cible'); if (!c) return;
            document.getElementById('blocSection').style.display = (c.value === 'etudiants') ? 'block' : 'none';
        }
        majSection();

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
</body>
</html>
