<?php
session_start();

// Vérification du rôle Enseignant
if (!isset($_SESSION['id_user']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['prof', 'professeur', 'enseignant'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$id_prof = $_SESSION['id_user'];
$nom_prof = $_SESSION['nom'] ?? 'Enseignant';
$prenom_prof = $_SESSION['prenom'] ?? '';

$emploi_du_temps = [];
$error = '';
$notifications = [];
$nb_non_lus = 0;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les notifications et le compteur non lus
    $stmt_notifs = $pdo->prepare("SELECT * FROM notification WHERE id_user = ? ORDER BY date_creation DESC LIMIT 15");
    $stmt_notifs->execute([$id_prof]);
    $notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
    $stmt_count->execute([$id_prof]);
    $nb_non_lus = $stmt_count->fetchColumn();

    // Requête pour récupérer l'emploi du temps du professeur
    $stmt = $pdo->prepare("
        SELECT s.jour, 
               CONCAT(TIME_FORMAT(s.heurD, '%H:%i'), ' - ', TIME_FORMAT(s.heurF, '%H:%i')) AS horaire_str, 
               s.type_seance, 
               s.id_groupe AS groupe,
               COALESCE(m.nom_mod, CONCAT('Module #', s.id_mod)) AS module, 
               sec.nom_sec AS section_nom,
               sp.NomSp AS specialite_nom,
               sp.annee AS specialite_annee,
               COALESCE(sal.nom_salle, CONCAT('Salle #', s.id_salle)) AS salle
        FROM seance s
        JOIN prof p ON s.id_prof = p.id_prof
        LEFT JOIN module m ON s.id_mod = m.id_mod
        LEFT JOIN section sec ON s.id_Sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        LEFT JOIN salle sal ON s.id_salle = sal.id_salle
        WHERE p.id_user = ?
        ORDER BY FIELD(s.jour, 'Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'), s.heurD ASC
    ");
    $stmt->execute([$id_prof]);
    $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($seances as $seance) {
        $emploi_du_temps[$seance['jour']][$seance['horaire_str']][] = $seance;
    }

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}

$jours = ['Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Emploi du Temps Professeur</title>
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
            --error: #A8162A;
            --on-error: #FFFFFF;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }

        /* Sidebar Enseignant */
        .sidebar {
            width: 280px; 
            background-color: var(--header); 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            padding: 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.05);
        }
        .logo-area { 
            color: var(--on-blue); 
            font-size: 18px; 
            font-weight: bold; 
            margin-bottom: 30px; 
            padding-bottom: 15px; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
        }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { 
            background-color: transparent;
            color: var(--on-blue); 
            padding: 10px 15px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            transition: background 0.2s;
        }
        .menu-item:hover, .menu-item.active { 
            background-color: var(--accent); 
            color: var(--on-accent); 
            font-weight: 600; 
        }

        /* Contenu Principal */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-body { padding: 30px; max-width: 1300px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 22px; font-weight: 700; margin-bottom: 20px; }

        /* --- STYLES DU PROFIL UTILISATEUR ET NOTIFICATIONS (Style Admin Dashboard) --- */
        .user-menu-container, .notif-menu-container {
            position: relative;
            display: inline-block;
            cursor: pointer;
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
            font-size: 15px;
        }
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
        .user-dropdown.active {
            display: block;
        }
        .dropdown-header {
            font-weight: bold;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .dropdown-item {
            display: block;
            width: 100%;
            padding: 8px 10px;
            text-align: left;
            background: none;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: var(--text);
            margin-bottom: 5px;
        }
        .dropdown-item:hover {
            background-color: var(--bg);
        }
        .dropdown-item.logout {
            background-color: var(--error);
            color: var(--on-error);
            text-align: center;
        }
        .dropdown-item.logout:hover {
            opacity: 0.9;
        }

        /* Tableau Emploi du Temps */
        table.edt-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        table.edt-table th, table.edt-table td {
            border: 1px solid #e2e8f0;
            padding: 10px;
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
        }
        table.edt-table th {
            background-color: var(--header);
            color: var(--on-blue);
            font-weight: 600;
        }

        .seance-card {
            background-color: #f8fafc;
            border-left: 4px solid var(--header);
            padding: 8px;
            border-radius: 4px;
            text-align: left;
            margin-bottom: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .seance-module { font-weight: 700; color: var(--header); font-size: 13px; }
        .seance-details { font-size: 12px; color: #475569; margin-top: 3px; }
        .badge-type {
            display: inline-block;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 4px;
            background-color: var(--accent);
            color: var(--on-accent);
            text-transform: uppercase;
            margin-top: 3px;
        }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <!-- Sidebar Enseignant -->
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus | Prof</span></div>
            <div class="menu">
              <a href="prof_emploi_du_temps.php" class="menu-item active"><span>📅</span> Consulter Emploi du temps</a>
              <a href="prof_gestion_notes.php" class="menu-item"><span>📊</span> Gestion des Notes</a>
              <a href="prof_horaires_consultation.php" class="menu-item"><span>🕒</span> Horaire de Consultation</a>
              <a href="prof_ressources_pedagogiques.php" class="menu-item"><span>📁</span> Gestion des Ressources</a>
              <a href="prof_annonces.php" class="menu-item"><span>📢</span> Annonces</a>
              <a href="prof_presence.php" class="menu-item "><span>📋</span> Liste de Présence</a>
              <a href="prof_planning_examens.php" class="menu-item"><span>📋</span> Planning des Examens</a>
          </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Enseignant - Emploi du Temps</h2>

            <div style="display: flex; align-items: center; gap: 15px;">
                <!-- Conteneur des notifications -->
                <div class="notif-menu-container" onclick="toggleNotifMenu(event)">
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

                <!-- Profil utilisateur cliquable (Style Admin Dashboard) -->
                <div class="user-menu-container" onclick="toggleUserMenu(event)">
                    <div class="user-profile">
                        <span><strong><?php echo htmlspecialchars($prenom_prof . ' ' . $nom_prof); ?></strong></span>
                        <div class="user-avatar"><?php echo strtoupper(substr($prenom_prof, 0, 1)); ?></div>
                    </div>

                    <div id="userDropdown" class="user-dropdown">
                        <div class="dropdown-header">
                            Connecté en tant que<br>
                            <span style="color: var(--menu);"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Enseignant'); ?></span>
                        </div>
                        <a href="modifier_infos.php" class="dropdown-item">✏️ Modifier mes informations</a>
                        <a href="logout.php" class="dropdown-item logout">Se déconnecter</a>
                    </div>
                </div>
            </div>

        </div>

        <div class="content-body">

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2 class="page-title">Mon Emploi du Temps </h2>
                <p style="font-size: 13px; color: #51606A; margin-bottom: 20px;">
                    Planning hebdomadaire de vos enseignements (Cours, TD et TP).
                </p>

                <table class="edt-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Horaire / Jour</th>
                            <?php foreach ($jours as $jour): ?>
                                <th><?php echo $jour; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $tous_horaires = [];
                        foreach ($emploi_du_temps as $j => $plages) {
                            foreach ($plages as $h => $seances_tab) {
                                if (!in_array($h, $tous_horaires)) {
                                    $tous_horaires[] = $h;
                                }
                            }
                        }
                        sort($tous_horaires);

                        if (!empty($tous_horaires)):
                            foreach ($tous_horaires as $horaire): 
                        ?>
                            <tr>
                                <td style="font-weight: 600; background-color: #f1f5f9;"><?php echo $horaire; ?></td>
                                <?php foreach ($jours as $jour): ?>
                                    <td>
                                        <?php if (isset($emploi_du_temps[$jour][$horaire])): ?>
                                            <?php foreach ($emploi_du_temps[$jour][$horaire] as $seance): ?>
                                                <div class="seance-card">
                                                    <div class="seance-module"><?php echo htmlspecialchars($seance['module']); ?></div>
                                                    <div class="seance-details">
                                                        🏛️ <strong><?php echo htmlspecialchars($seance['section_nom'] ?? 'Section'); ?></strong>
                                                        <?php if (!empty($seance['specialite_nom'])): ?>
                                                            <br><span style="color: #1B5A80; font-weight: 600;">📚 <?php echo htmlspecialchars($seance['specialite_nom']); ?> (<?php echo htmlspecialchars($seance['specialite_annee']); ?>)</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($seance['groupe'])): ?>
                                                            <br>(Gr. <?php echo htmlspecialchars($seance['groupe']); ?>)
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="seance-details">📍 Salle : <?php echo htmlspecialchars($seance['salle']); ?></div>
                                                    <span class="badge-type"><?php echo htmlspecialchars($seance['type_seance']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1; font-size: 12px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #777; padding: 20px;">Aucune séance enregistrée dans votre emploi du temps.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Script JavaScript -->
    <script>
        function toggleUserMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
            var notifDropdown = document.getElementById('notifDropdown');
            if(notifDropdown) notifDropdown.classList.remove('active');
        }

        function toggleNotifMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('notifDropdown');
            dropdown.classList.toggle('active');
            var userDropdown = document.getElementById('userDropdown');
            if(userDropdown) userDropdown.classList.remove('active');
        }

        window.onclick = function(event) {
            var userDropdown = document.getElementById('userDropdown');
            if (userDropdown && userDropdown.classList.contains('active')) {
                userDropdown.classList.remove('active');
            }
            var notifDropdown = document.getElementById('notifDropdown');
            if (notifDropdown && notifDropdown.classList.contains('active')) {
                notifDropdown.classList.remove('active');
            }
        }
    </script>
</body>
</html>