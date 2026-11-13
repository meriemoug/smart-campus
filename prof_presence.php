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

$id_prof_session = $_SESSION['id_user'];
$nom_prof = $_SESSION['nom'] ?? 'Enseignant';
$prenom_prof = $_SESSION['prenom'] ?? '';

$message = '';
$error = '';
$seances_prof = [];
$etudiants_groupe = [];
$anciennes_listes = [];
$details_liste = [];
$sections_prof = [];
$notifications = [];
$nb_non_lus = 0;

$tab = $_GET['tab'] ?? 'ajouter';

// Date choisie (par défaut, la date du jour)
$date_selectionnee = $_POST['date_appel'] ?? $_GET['date_appel'] ?? date('Y-m-d');

// Filtres de consultation
$filter_date = trim($_GET['filter_date'] ?? '');
$filter_sec = trim($_GET['filter_sec'] ?? '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les notifications et le compteur non lus
    $stmt_notifs = $pdo->prepare("SELECT * FROM notification WHERE id_user = ? ORDER BY date_creation DESC LIMIT 15");
    $stmt_notifs->execute([$id_prof_session]);
    $notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
    $stmt_count->execute([$id_prof_session]);
    $nb_non_lus = $stmt_count->fetchColumn();

    // 1. Récupérer l'id_prof
    $stmt_p = $pdo->prepare("SELECT id_prof FROM prof WHERE id_user = ?");
    $stmt_p->execute([$id_prof_session]);
    $prof_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
    $id_prof = $prof_data['id_prof'] ?? null;

    if (!$id_prof) {
        throw new Exception("Profil enseignant introuvable.");
    }

    // Récupérer toutes les sections distinctes enseignées par ce professeur (utiles pour le filtre et l'affichage)
    $stmt_sections = $pdo->prepare("
        SELECT DISTINCT sec.id_Sec, sec.nom_sec, sp.NomSp, sp.annee
        FROM seance s
        JOIN section sec ON s.id_Sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        WHERE s.id_prof = ?
        ORDER BY sec.nom_sec ASC
    ");
    $stmt_sections->execute([$id_prof]);
    $sections_prof = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);

    // Convertir la date choisie en nom de jour en français
    $jours_fr = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $num_jour_semaine = date('w', strtotime($date_selectionnee));
    $jour_correspondant = $jours_fr[$num_jour_semaine];

    // 2. Récupérer uniquement les séances TD et TP pour ce jour de la semaine et ce prof
    $stmt_seances = $pdo->prepare("
        SELECT s.id_Seance, s.jour, s.heurD, s.heurF, s.type_seance, s.id_groupe, 
               sec.id_Sec, sec.nom_sec, m.id_mod, m.nom_mod, sp.NomSp, sp.annee
        FROM seance s
        JOIN section sec ON s.id_Sec = sec.id_Sec
        JOIN module m ON s.id_mod = m.id_mod
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        WHERE s.id_prof = ? 
          AND s.type_seance IN ('TD', 'TP') 
          AND s.jour = ?
        ORDER BY s.heurD ASC
    ");
    $stmt_seances->execute([$id_prof, $jour_correspondant]);
    $seances_prof = $stmt_seances->fetchAll(PDO::FETCH_ASSOC);

    // 3. Charger les étudiants d'une séance spécifique lors de la sélection
    $selected_id_seance = $_POST['id_Seance'] ?? $_GET['id_Seance'] ?? '';
    $selected_seance_info = null;

    if (!empty($selected_id_seance)) {
        foreach ($seances_prof as $s) {
            if ($s['id_Seance'] == $selected_id_seance) {
                $selected_seance_info = $s;
                break;
            }
        }

        if ($selected_seance_info && isset($_POST['charger_etudiants'])) {
            $stmt_etud = $pdo->prepare("
                SELECT et.*, u.nom, u.prenom 
                FROM etudiant et
                JOIN user u ON et.id_user = u.id_user
                WHERE et.id_Sec = ? AND et.groupe = ?
                ORDER BY u.nom ASC, u.prenom ASC
            ");
            $stmt_etud->execute([$selected_seance_info['id_Sec'], $selected_seance_info['id_groupe']]);
            $etudiants_groupe = $stmt_etud->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 4. Enregistrement d'une nouvelle feuille d'appel (avec contrainte d'unicité)
    if (isset($_POST['enregistrer_presence'])) {
        $id_Seance = $_POST['id_Seance'];
        $date_appel = $_POST['date_appel'];
        $presences = $_POST['presence'] ?? [];
        $justifications = $_POST['justification'] ?? [];

        // Vérifier si une liste existe déjà pour cette séance et cette date
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM liste_appel WHERE id_Seance = ? AND date_appel = ?");
        $stmt_check->execute([$id_Seance, $date_appel]);
        if ($stmt_check->fetchColumn() > 0) {
            $error = "Une liste de présence existe déjà pour cette séance à la date du " . date('d/m/Y', strtotime($date_appel)) . ".";
            $tab = 'ajouter';
        } else {
            // Insérer dans `liste_appel`
            $stmt_ins_l = $pdo->prepare("
                INSERT INTO liste_appel (id_Seance, id_prof, date_appel) 
                VALUES (?, ?, ?)
            ");
            $stmt_ins_l->execute([$id_Seance, $id_prof, $date_appel]);
            $id_liste = $pdo->lastInsertId();

            // Insérer dans `ligne_appel`
            $stmt_ins_g = $pdo->prepare("
                INSERT INTO ligne_appel (id_liste, id_Etu, presence, justification) 
                VALUES (?, ?, ?, ?)
            ");

            $absents_ids = [];
            foreach ($presences as $id_Etu => $presence) {
                $justification = ($presence === 'absent') ? ($justifications[$id_Etu] ?? 'non_justifiee') : 'non_justifiee';
                $stmt_ins_g->execute([$id_liste, $id_Etu, $presence, $justification]);

                if ($presence === 'absent') {
                    $absents_ids[] = $id_Etu;
                }
            }

            // --- NOTIFICATION : ENVOI AUX ÉTUDIANTS MARQUÉS ABSENTS ---
            if (!empty($absents_ids)) {
                $stmt_m_info = $pdo->prepare("
                    SELECT m.nom_mod 
                    FROM seance s 
                    JOIN module m ON s.id_mod = m.id_mod 
                    WHERE s.id_Seance = ?
                ");
                $stmt_m_info->execute([$id_Seance]);
                $mod_nom = $stmt_m_info->fetchColumn();

                $placeholders = implode(',', array_fill(0, count($absents_ids), '?'));
                $stmt_u_abs = $pdo->prepare("SELECT id_user FROM etudiant WHERE id_Etu IN ($placeholders)");
                $stmt_u_abs->execute($absents_ids);
                $users_absents = $stmt_u_abs->fetchAll(PDO::FETCH_COLUMN);

                $msg_absence = "Vous avez été marqué(e) absent(e) pour le module : $mod_nom (Date : " . date('d/m/Y', strtotime($date_appel)) . ").";
                $stmt_notif = $pdo->prepare("INSERT INTO notification (id_user, type, message) VALUES (?, 'absence', ?)");
                
                foreach ($users_absents as $uid_etu) {
                    $stmt_notif->execute([$uid_etu, $msg_absence]);
                }
            }
            // --- FIN NOTIFICATION ---

            $message = "La liste de présence a été enregistrée et les étudiants absents ont été notifiés avec succès !";
            $tab = 'consulter';
        }
    }

    // 5. Modification d'une ancienne feuille d'appel (Seule la justification des absents est modifiable)
    if (isset($_POST['modifier_presence'])) {
        $id_liste = $_POST['id_liste'];
        $nouvelles_justifs = $_POST['justification'] ?? [];

        foreach ($nouvelles_justifs as $id_Etu => $justification) {
            $stmt_upd = $pdo->prepare("
                UPDATE ligne_appel 
                SET justification = ? 
                WHERE id_liste = ? AND id_Etu = ?
            ");
            $stmt_upd->execute([$justification, $id_liste, $id_Etu]);
        }
        $message = "La liste de présence a été mise à jour avec succès !";
        $tab = 'consulter';
    }

    // 6. Suppression d'une feuille de présence
    if (isset($_POST['supprimer_liste'])) {
        $id_liste_del = $_POST['id_liste'];

        // Supprimer d'abord les lignes d'appel associées
        $stmt_del_lignes = $pdo->prepare("DELETE FROM ligne_appel WHERE id_liste = ?");
        $stmt_del_lignes->execute([$id_liste_del]);

        // Supprimer la liste d'appel principale
        $stmt_del_liste = $pdo->prepare("DELETE FROM liste_appel WHERE id_liste = ? AND id_prof = ?");
        $stmt_del_liste->execute([$id_liste_del, $id_prof]);

        $message = "La feuille de présence a été supprimée avec succès.";
        $tab = 'consulter';
    }

    // 7. Récupérer les anciennes listes d'appel avec application des filtres de date et de section
    $sql_anciennes = "
        SELECT la.*, s.type_seance, s.id_groupe, m.nom_mod, sec.id_Sec, sec.nom_sec, sp.NomSp, sp.annee
        FROM liste_appel la
        JOIN seance s ON la.id_Seance = s.id_Seance
        JOIN module m ON s.id_mod = m.id_mod
        JOIN section sec ON s.id_Sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        WHERE la.id_prof = ?
    ";
    $params_anciennes = [$id_prof];

    if (!empty($filter_date)) {
        $sql_anciennes .= " AND la.date_appel = ?";
        $params_anciennes[] = $filter_date;
    }
    if (!empty($filter_sec)) {
        $sql_anciennes .= " AND sec.id_Sec = ?";
        $params_anciennes[] = $filter_sec;
    }

    $sql_anciennes .= " ORDER BY la.date_appel DESC";

    $stmt_anciennes = $pdo->prepare($sql_anciennes);
    $stmt_anciennes->execute($params_anciennes);
    $anciennes_listes = $stmt_anciennes->fetchAll(PDO::FETCH_ASSOC);

    // Si on consulte/modifie une liste spécifique
    $edit_liste_id = $_GET['voir'] ?? null;
    if ($edit_liste_id) {
        $stmt_det = $pdo->prepare("
            SELECT la.*, u.nom, u.prenom, et.id_Etu, l.id_Etu, l.presence, l.justification, l.motif
            FROM ligne_appel l
            JOIN etudiant et ON l.id_Etu = et.id_Etu
            JOIN user u ON et.id_user = u.id_user
            JOIN liste_appel la ON l.id_liste = la.id_liste
            WHERE l.id_liste = ?
            ORDER BY u.nom ASC
        ");
        $stmt_det->execute([$edit_liste_id]);
        $details_liste = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Présences</title>
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

        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; box-shadow: 4px 0 10px rgba(0,0,0,0.05); }
        .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { background-color: transparent; color: var(--on-blue); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-body { padding: 30px; max-width: 1200px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 20px; font-weight: 700; margin-bottom: 20px; }

        .tabs-nav { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
        .tab-btn { background: none; border: none; padding: 10px 20px; font-size: 15px; font-weight: 600; cursor: pointer; color: var(--text); border-radius: 6px; transition: 0.2s; text-decoration: none; }
        .tab-btn.active { background-color: var(--header); color: var(--on-blue); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        
        label { font-size: 13px; font-weight: 600; color: var(--text); }
        input[type="date"], select {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE;
        }
        input:focus, select:focus { outline: none; border-color: var(--header); background: #fff; }

        .btn-save { background-color: var(--accent); color: var(--on-accent); border: none; padding: 12px 25px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { opacity: 0.9; }
        .btn-action { background-color: var(--header); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-delete { background-color: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }

        table.presence-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 12px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; }
        
        .radio-group { display: flex; gap: 15px; align-items: center; }
        .radio-group label { font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 5px; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        /* Styles du profil utilisateur et des notifications */
        .user-menu-container, .notif-menu-container { position: relative; display: inline-block; cursor: pointer; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; background-color: var(--accent); color: var(--on-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 15px; }
        
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
        .dropdown-header { font-weight: bold; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); font-size: 13px; color: #64748b; }
        .dropdown-item { display: block; width: 100%; padding: 8px 10px; text-align: left; background: none; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; color: var(--text); margin-bottom: 5px; }
        .dropdown-item:hover { background-color: var(--bg); }
        .dropdown-item.logout { background-color: var(--error); color: var(--on-error); text-align: center; }
        .dropdown-item.logout:hover { opacity: 0.9; }
    </style>
</head>
<body>

    <!-- Sidebar Enseignant -->
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus | Prof</span></div>
            <div class="menu">
                <a href="prof_dashboard.php" class="menu-item"><span>📅</span> Consulter Emploi du temps</a>
                <a href="prof_gestion_notes.php" class="menu-item"><span>📊</span> Gestion des Notes</a>
                <a href="prof_horaires_consultation.php" class="menu-item"><span>🕒</span> Horaire de Consultation</a>
                <a href="prof_ressources_pedagogiques.php" class="menu-item"><span>📁</span> Gestion des Ressources</a>
                <a href="prof_annonces.php" class="menu-item"><span>📢</span> Annonces</a>
                <a href="prof_presence.php" class="menu-item active"><span>📋</span> Liste de Présence</a>
                <a href="prof_planning_examens.php" class="menu-item"><span>📋</span> Planning des Examens</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Enseignant - Gestion des Présences</h2>

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
            
                <!-- Profil utilisateur cliquable -->
                <div class="user-menu-container" onclick="toggleUserMenu(event)">
                    <div class="user-profile">
                        <span><strong><?php echo htmlspecialchars($prenom_prof . ' ' . $nom_prof); ?></strong></span>
                        <div class="user-avatar"><?php echo strtoupper(substr($prenom_prof, 0, 1)); ?></div>
                    </div>
                    <div id="userDropdown" class="user-dropdown">
                        <div class="dropdown-header">
                            Connecté en tant que<br>
                            <span style="color: var(--menu); font-weight: 600;"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Enseignant'); ?></span>
                        </div>
                        <a href="modifier_infos.php" class="dropdown-item">✏️ Modifier mes informations</a>
                        <a href="logout.php" class="dropdown-item logout">Se déconnecter</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-body">

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Navigation par onglets -->
            <div class="tabs-nav">
                <a href="prof_presence.php?tab=ajouter" class="tab-btn <?php echo ($tab == 'ajouter' && !$edit_liste_id) ? 'active' : ''; ?>">➕ Nouvelle Feuille de Présence</a>
                <a href="prof_presence.php?tab=consulter" class="tab-btn <?php echo ($tab == 'consulter' || $edit_liste_id) ? 'active' : ''; ?>">📂 Consulter / Modifier les Listes</a>
            </div>

            <!-- ONGLET 1 : AJOUTER UNE PRESENCE -->
            <?php if ($tab == 'ajouter' && !$edit_liste_id): ?>
                <div class="card">
                    <h3 class="page-title">Sélectionner la date et la séance (TD ou TP uniquement)</h3>
                    
                    <form action="prof_presence.php?tab=ajouter" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="date_appel">1. Choisir la date :</label>
                                <input type="date" name="date_appel" id="date_appel" value="<?php echo htmlspecialchars($date_selectionnee); ?>" onchange="this.form.submit()" required>
                            </div>

                            <div class="form-group">
                                <label for="id_Seance">2. Séance programmée (Jour : <strong><?php echo $jour_correspondant; ?></strong>) :</label>
                                <select name="id_Seance" id="id_Seance" required>
                                    <option value="">-- Choisir une séance --</option>
                                    <?php if (!empty($seances_prof)): ?>
                                        <?php foreach ($seances_prof as $s): ?>
                                            <option value="<?php echo $s['id_Seance']; ?>" <?php echo ($selected_id_seance == $s['id_Seance']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s['nom_mod'] . ' - Section ' . $s['nom_sec'] . ' (' . $s['NomSp'] . ' - ' . $s['annee'] . ') | Type: ' . $s['type_seance'] . ' | Groupe: ' . $s['id_groupe'] . ' (' . $s['heurD'] . ' - ' . $s['heurF'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Aucune séance de TD ou TP ce jour-là.</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="charger_etudiants" class="btn-action">🔍 Afficher la liste des étudiants</button>
                    </form>
                </div>

                <!-- Tableau de pointage si les étudiants sont chargés -->
                <?php if (!empty($etudiants_groupe)): ?>
                    <div class="card">
                        <h3 class="page-title">Pointage des Présences (<?php echo htmlspecialchars($selected_seance_info['nom_mod'] . ' - Groupe ' . $selected_seance_info['id_groupe']); ?>)</h3>
                        <form action="prof_presence.php" method="POST">
                            <input type="hidden" name="id_Seance" value="<?php echo $selected_id_seance; ?>">
                            <input type="hidden" name="date_appel" value="<?php echo htmlspecialchars($date_selectionnee); ?>">

                            <table class="presence-table">
                                <thead>
                                    <tr>
                                        <th>N° Étudiant</th>
                                        <th>Nom & Prénom</th>
                                        <th>Présence</th>
                                        <th>Justification (si absent)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etudiants_groupe as $etud): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($etud['num_etudiant'] ?? 'N/A'); ?></td>
                                            <td><strong><?php echo htmlspecialchars($etud['nom'] . ' ' . $etud['prenom']); ?></strong></td>
                                            <td>
                                                <div class="radio-group">
                                                    <label><input type="radio" name="presence[<?php echo $etud['id_Etu']; ?>]" value="present" checked onchange="toggleJustification(<?php echo $etud['id_Etu']; ?>)"> Présent</label>
                                                    <label><input type="radio" name="presence[<?php echo $etud['id_Etu']; ?>]" value="absent" onchange="toggleJustification(<?php echo $etud['id_Etu']; ?>)"> Absent</label>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="radio-group" id="justif-group-<?php echo $etud['id_Etu']; ?>">
                                                    <label><input type="radio" name="justification[<?php echo $etud['id_Etu']; ?>]" value="non_justifiee" checked disabled> Non justifiée</label>
                                                    <label><input type="radio" name="justification[<?php echo $etud['id_Etu']; ?>]" value="justifiee" disabled> Justifiée</label>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div style="margin-top: 20px;">
                                <button type="submit" name="enregistrer_presence" class="btn-save">💾 Enregistrer la feuille de présence</button>
                            </div>
                        </form>
                    </div>
                <?php elseif (isset($_POST['charger_etudiants'])): ?>
                    <div class="card">
                        <p style="color: #777; text-align: center;">Aucun étudiant trouvé pour ce groupe dans cette section.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ONGLET 2 : CONSULTER ET MODIFIER LES ANCIENNES LISTES -->
            <?php if ($tab == 'consulter' && !$edit_liste_id): ?>
                <div class="card">
                    <h3 class="page-title">Historique des feuilles de présence</h3>

                    <!-- Formulaire de filtrage par date et par section -->
                    <form action="prof_presence.php" method="GET" class="form-grid" style="align-items: flex-end; margin-bottom: 20px; background: #fdfae7; padding: 15px; border-radius: 8px; border: 1px solid var(--border);">
                        <input type="hidden" name="tab" value="consulter">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="filter_date">Filtrer par date :</label>
                            <input type="date" name="filter_date" id="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="filter_sec">Filtrer par section :</label>
                            <select name="filter_sec" id="filter_sec">
                                <option value="">-- Toutes les sections --</option>
                                <?php foreach ($sections_prof as $sec): ?>
                                    <option value="<?php echo $sec['id_Sec']; ?>" <?php echo ($filter_sec == $sec['id_Sec']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sec['nom_sec'] . ' (' . $sec['NomSp'] . ' - ' . $sec['annee'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px; grid-column: span 2;">
                            <button type="submit" class="btn-action">🔍 Filtrer</button>
                            <a href="prof_presence.php?tab=consulter" style="background: #64748b; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center;">Réinitialiser</a>
                        </div>
                    </form>

                    <table class="presence-table">
                        <thead>
                            <tr>
                                <th>Date de l'appel</th>
                                <th>Module</th>
                                <th>Section</th>
                                <th>Type</th>
                                <th>Groupe</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($anciennes_listes)): ?>
                                <?php foreach ($anciennes_listes as $liste): ?>
                                    <tr>
                                        <td>📅 <?php echo date('d/m/Y', strtotime($liste['date_appel'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($liste['nom_mod']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($liste['nom_sec'] . ' (' . $liste['NomSp'] . ' - ' . $liste['annee'] . ')'); ?></td>
                                        <td><span style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;"><?php echo htmlspecialchars($liste['type_seance']); ?></span></td>
                                        <td><?php echo htmlspecialchars($liste['id_groupe']); ?></td>
                                        <td style="text-align: center; display: flex; justify-content: center; gap: 8px;">
                                            <a href="prof_presence.php?voir=<?php echo $liste['id_liste']; ?>" style="background: var(--header); color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600;">👁️ Consulter / Modifier</a>
                                            <form action="prof_presence.php?tab=consulter" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cette feuille de présence ?');" style="margin: 0;">
                                                <input type="hidden" name="id_liste" value="<?php echo $liste['id_liste']; ?>">
                                                <button type="submit" name="supprimer_liste" class="btn-delete">🗑️ Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #777; padding: 20px;">Aucune feuille de présence trouvée avec ces critères.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- VUE DE DETAIL ET MODIFICATION D'UNE LISTE SPECIFIQUE -->
            <?php if ($edit_liste_id && !empty($details_liste)): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 class="page-title" style="margin-bottom: 0;">Modifier la feuille de présence (Appel du <?php echo date('d/m/Y', strtotime($details_liste[0]['date_appel'])); ?>)</h3>
                        <a href="prof_presence.php?tab=consulter" style="color: var(--header); text-decoration: none; font-weight: 600;">⬅️ Retour à la liste</a>
                    </div>

                    <form action="prof_presence.php" method="POST">
                        <input type="hidden" name="id_liste" value="<?php echo $edit_liste_id; ?>">

                        <table class="presence-table">
                            <thead>
                                <tr>
                                    <th>N° Étudiant</th>
                                    <th>Nom & Prénom</th>
                                    <th>Statut de présence</th>
                                    <th>Justification (Modifiable si absent)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($details_liste as $det): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($det['num_etudiant'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($det['nom'] . ' ' . $det['prenom']); ?></strong></td>
                                        <td>
                                            <?php if ($det['presence'] == 'present'): ?>
                                                <span style="color: #155724; background: #d4edda; padding: 4px 10px; border-radius: 4px; font-weight: 600;">Présent</span>
                                            <?php else: ?>
                                                <span style="color: #721c24; background: #f8d7da; padding: 4px 10px; border-radius: 4px; font-weight: 600;">Absent</span>
                                            <?php endif; ?>
                                            <input type="hidden" name="presence[<?php echo $det['id_Etu']; ?>]" value="<?php echo $det['presence']; ?>">
                                        </td>
                                        <td>
                                            <?php if ($det['presence'] == 'absent'): ?>
                                                <div class="radio-group">
                                                    <label><input type="radio" name="justification[<?php echo $det['id_Etu']; ?>]" value="non_justifiee" <?php echo ($det['justification'] == 'non_justifiee') ? 'checked' : ''; ?>> Non justifiée</label>
                                                    <label><input type="radio" name="justification[<?php echo $det['id_Etu']; ?>]" value="justifiee" <?php echo ($det['justification'] == 'justifiee') ? 'checked' : ''; ?>> Justifiée</label>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-style: italic;">Non applicable (Présent)</span>
                                                <input type="hidden" name="justification[<?php echo $det['id_Etu']; ?>]" value="non_justifiee">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="margin-top: 20px;">
                            <button type="submit" name="modifier_presence" class="btn-save">💾 Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>

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

        function toggleJustification(etudiantId) {
            const radioAbsent = document.querySelector(`input[name="presence[${etudiantId}]"][value="absent"]`);
            const justifContainer = document.getElementById(`justif-group-${etudiantId}`);
            const inputsJustif = justifContainer.querySelectorAll('input[type="radio"]');

            if (radioAbsent.checked) {
                inputsJustif.forEach(input => input.disabled = false);
            } else {
                inputsJustif.forEach(input => input.disabled = true);
            }
        }
    </script>
</body>
</html>