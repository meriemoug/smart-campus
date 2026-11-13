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
$sections_uniques = [];
$modules_section = [];
$ressources = [];
$notifications = [];
$nb_non_lus = 0;

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

    // 1. Récupérer l'id_prof de la table 'prof'
    $stmt_p = $pdo->prepare("SELECT id_prof FROM prof WHERE id_user = ?");
    $stmt_p->execute([$id_prof_session]);
    $prof_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
    $id_prof = $prof_data['id_prof'] ?? null;

    if (!$id_prof) {
        throw new Exception("Profil enseignant introuvable dans la base de données.");
    }

    // 2. Récupérer les sections distinctes enseignées par ce professeur
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

    foreach ($sections_prof as $row) {
        $sections_uniques[$row['id_Sec']] = [
            'nom_sec' => $row['nom_sec'],
            'specialite' => $row['NomSp'] . ' (' . $row['annee'] . ')'
        ];
    }

    // Section sélectionnée pour le formulaire d'ajout
    $selected_section_form = $_POST['id_sect'] ?? '';
    // Section sélectionnée pour le filtre du tableau (GET)
    $filtre_section = $_GET['filtre_section'] ?? '';

    // Charger les modules pour le formulaire d'ajout si une section est choisie
    if (!empty($selected_section_form)) {
        $stmt_modules = $pdo->prepare("
            SELECT DISTINCT m.id_mod, m.nom_mod
            FROM seance s
            JOIN module m ON s.id_mod = m.id_mod
            WHERE s.id_prof = ? AND s.id_Sec = ?
            ORDER BY m.nom_mod ASC
        ");
        $stmt_modules->execute([$id_prof, $selected_section_form]);
        $modules_result = $stmt_modules->fetchAll(PDO::FETCH_ASSOC);

        foreach ($modules_result as $mod) {
            $modules_section[$mod['id_mod']] = $mod['nom_mod'];
        }
    }

    // 3. Traitement de la publication (Fichier ou Lien) + Notification
    if (isset($_POST['publier_ressource'])) {
        $titre = trim($_POST['titre'] ?? '');
        $id_module = $_POST['id_module'] ?? '';
        $id_sect = $_POST['id_sect'] ?? '';
        $type_res = $_POST['type_res'] ?? 'cours';
        $type_publication = $_POST['type_publication'] ?? 'fichier'; // 'fichier' ou 'lien'
        $lien_ressource = '';

        if (empty($titre) || empty($id_module) || empty($id_sect)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } else {
            if ($type_publication === 'lien') {
                $lien_ressource = trim($_POST['url_lien'] ?? '');
                if (empty($lien_ressource)) {
                    $error = "Veuillez entrer un lien URL valide.";
                }
            } else {
                // Gestion du fichier uploadé
                if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['fichier'];
                    $file_name = time() . '_' . basename($file['name']);
                    $upload_dir = 'uploads/';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $destination = $upload_dir . $file_name;
                    $allowed_exts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'png', 'jpg'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (in_array($file_ext, $allowed_exts)) {
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            $lien_ressource = $destination;
                        } else {
                            $error = "Erreur lors du transfert du fichier sur le serveur.";
                        }
                    } else {
                        $error = "Format de fichier non autorisé.";
                    }
                } else {
                    $error = "Veuillez sélectionner un fichier à télécharger.";
                }
            }

            // Si aucune erreur, insertion en base et envoi des notifications
            if (empty($error) && !empty($lien_ressource)) {
                $stmt_ins = $pdo->prepare("
                    INSERT INTO ressource (id_prof, id_sect, id_module, titre, lien, type_res) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$id_prof, $id_sect, $id_module, $titre, $lien_ressource, $type_res]);

                // --- NOTIFICATION : ENVOI AUX ÉTUDIANTS DE LA SECTION ---
                $stmt_m_info = $pdo->prepare("SELECT nom_mod FROM module WHERE id_mod = ?");
                $stmt_m_info->execute([$id_module]);
                $mod_nom = $stmt_m_info->fetchColumn();

                $stmt_etu_users = $pdo->prepare("SELECT u.id_user FROM user u JOIN etudiant e ON u.id_user = e.id_user WHERE e.id_Sec = ?");
                $stmt_etu_users->execute([$id_sect]);
                $etudiants_users = $stmt_etu_users->fetchAll(PDO::FETCH_COLUMN);

                // Message incluant le titre pour faciliter la suppression ultérieure
                $msg_etudiant = "Nouvelle ressource ($type_res) : \"$titre\" pour le module : $mod_nom.";
                
                // Utilisation des accents graves autour de `type` pour éviter les conflits SQL
                $stmt_notif = $pdo->prepare("INSERT INTO notification (id_user, `type`, message) VALUES (?, 'ressource', ?)");
                foreach ($etudiants_users as $uid_etu) {
                    $stmt_notif->execute([$uid_etu, $msg_etudiant]);
                }
                // --- FIN NOTIFICATION ---

                $message = "La ressource a été publiée et les étudiants de la section ont été notifiés avec succès !";
            }
        }
    }

    // 4. Traitement de la suppression d'une ressource (et de ses notifications associées)
    if (isset($_POST['supprimer_ressource'])) {
        $id_R = $_POST['id_R'] ?? '';
        
        $stmt_chk = $pdo->prepare("
            SELECT r.*, m.nom_mod 
            FROM ressource r
            JOIN module m ON r.id_module = m.id_mod
            WHERE r.id_R = ? AND r.id_prof = ?
        ");
        $stmt_chk->execute([$id_R, $id_prof]);
        $res_data = $stmt_chk->fetch(PDO::FETCH_ASSOC);

        if ($res_data) {
            $titre_res = $res_data['titre'];

            // Si c'est un fichier physique (et non un lien externe), on le supprime du serveur
            if (!preg_match('/^https?:\/\//', $res_data['lien']) && file_exists($res_data['lien'])) {
                unlink($res_data['lien']);
            }

            // Supprimer toutes les notifications liées à cette ressource précise
            $stmt_del_notif = $pdo->prepare("DELETE FROM notification WHERE `type` = 'ressource' AND message LIKE ?");
            $stmt_del_notif->execute(['%' . $titre_res . '%']);

            // Supprimer la ressource de la base de données
            $stmt_del = $pdo->prepare("DELETE FROM ressource WHERE id_R = ?");
            $stmt_del->execute([$id_R]);
            
            $message = "La ressource et ses notifications associées ont été supprimées avec succès.";
        }
    }

    // 5. Récupérer la liste des ressources publiées (avec filtre optionnel par section)
    if (!empty($filtre_section)) {
        $stmt_ressources = $pdo->prepare("
            SELECT r.*, m.nom_mod, sec.nom_sec 
            FROM ressource r
            JOIN module m ON r.id_module = m.id_mod
            JOIN section sec ON r.id_sect = sec.id_Sec
            WHERE r.id_prof = ? AND r.id_sect = ?
            ORDER BY r.date_ajout DESC
        ");
        $stmt_ressources->execute([$id_prof, $filtre_section]);
    } else {
        $stmt_ressources = $pdo->prepare("
            SELECT r.*, m.nom_mod, sec.nom_sec 
            FROM ressource r
            JOIN module m ON r.id_module = m.id_mod
            JOIN section sec ON r.id_sect = sec.id_Sec
            WHERE r.id_prof = ?
            ORDER BY r.date_ajout DESC
        ");
        $stmt_ressources->execute([$id_prof]);
    }
    $ressources = $stmt_ressources->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Ressources Pédagogiques</title>
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

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: span 2; }
        
        label { font-size: 13px; font-weight: 600; color: var(--text); }
        input[type="text"], select, input[type="file"], textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE;
        }
        textarea { resize: vertical; min-height: 80px; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--header); background: #fff; }

        .btn-save { background-color: var(--accent); color: var(--on-accent); border: none; padding: 12px 25px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { opacity: 0.9; }

        table.ressources-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 12px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; }
        td { vertical-align: middle; }

        .btn-delete { background-color: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-delete:hover { background-color: #b91c1c; }
        .btn-download { background-color: var(--header); color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-download:hover { background-color: var(--menu); }

        .filter-bar { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 20px; }

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

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
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
                <a href="prof_ressources_pedagogiques.php" class="menu-item active"><span>📁</span> Gestion des Ressources</a>
                <a href="prof_annonces.php" class="menu-item"><span>📢</span> Annonces</a>
                <a href="prof_presence.php" class="menu-item"><span>📋</span> Liste de Présence</a>
                <a href="prof_planning_examens.php" class="menu-item"><span>📋</span> Planning des Examens</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Enseignant - Gestion des Ressources Pédagogiques</h2>

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

            <!-- Formulaire de Publication -->
            <div class="card">
                <h3 class="page-title">Publier une nouvelle ressource ou un lien</h3>
                <form action="prof_ressources_pedagogiques.php" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="type_res">Type de ressource :</label>
                            <select name="type_res" id="type_res" required>
                                <option value="cours">Cours</option>
                                <option value="td">TD</option>
                                <option value="tp">TP</option>
                                <option value="examen_corrige">Examen Corrigé</option>
                                <option value="video">Vidéo</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="type_publication">Type de contenu :</label>
                            <select name="type_publication" id="type_publication" onchange="toggleTypePub(this.value)" required>
                                <option value="fichier">Fichier à télécharger</option>
                                <option value="lien">Lien URL externe</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="id_sect">Section cible :</label>
                            <select name="id_sect" id="id_sect" onchange="this.form.submit()" required>
                                <option value="">-- Choisir une section --</option>
                                <?php foreach ($sections_uniques as $id_sec => $sec): ?>
                                    <option value="<?php echo $id_sec; ?>" <?php echo ($selected_section_form == $id_sec) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sec['nom_sec'] . ' (' . $sec['specialite'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="id_module">Module :</label>
                            <select name="id_module" id="id_module" required>
                                <option value="">-- Choisir un module --</option>
                                <?php foreach ($modules_section as $id_m => $nom_m): ?>
                                    <option value="<?php echo $id_m; ?>">
                                        <?php echo htmlspecialchars($nom_m); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="titre">Titre et Description :</label>
                            <textarea id="titre" name="titre" placeholder="Entrez le titre et une description détaillée de la ressource..." required></textarea>
                        </div>

                        <!-- Champ Fichier -->
                        <div class="form-group full-width" id="groupe-fichier">
                            <label for="fichier">Fichier (PDF, Word, PPT, Archive...) :</label>
                            <input type="file" id="fichier" name="fichier" required>
                        </div>

                        <!-- Champ Lien URL (masqué par défaut) -->
                        <div class="form-group full-width" id="groupe-lien" style="display: none;">
                            <label for="url_lien">Lien URL (ex: https://youtube.com/... ou lien Drive) :</label>
                            <input type="text" id="url_lien" name="url_lien" placeholder="https://...">
                        </div>
                    </div>

                    <button type="submit" name="publier_ressource" class="btn-save">📤 Publier</button>
                </form>
            </div>

            <!-- Liste des ressources publiées avec FILTRE PAR SECTION -->
            <div class="card">
                <h3 class="page-title">Mes ressources publiées</h3>

                <!-- Barre de Filtrage -->
                <form method="GET" action="prof_ressources_pedagogiques.php" class="filter-bar">
                    <div class="form-group" style="flex: 1; max-width: 350px;">
                        <label for="filtre_section">Filtrer par section :</label>
                        <select name="filtre_section" id="filtre_section" onchange="this.form.submit()">
                            <option value="">-- Toutes les sections --</option>
                            <?php foreach ($sections_uniques as $id_sec => $sec): ?>
                                <option value="<?php echo $id_sec; ?>" <?php echo ($filtre_section == $id_sec) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sec['nom_sec'] . ' (' . $sec['specialite'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($filtre_section)): ?>
                        <a href="prof_ressources_pedagogiques.php" style="padding: 10px 15px; background: #e2e8f0; color: var(--text); border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; height: 42px; display: flex; align-items: center;">Réinitialiser le filtre</a>
                    <?php endif; ?>
                </form>

                <table class="ressources-table">
                    <thead>
                        <tr>
                            <th>Titre / Description</th>
                            <th>Type</th>
                            <th>Module</th>
                            <th>Section</th>
                            <th>Date</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($ressources)): ?>
                            <?php foreach ($ressources as $res): ?>
                                <tr>
                                    <td><strong><?php echo nl2br(htmlspecialchars($res['titre'])); ?></strong></td>
                                    <td><span style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-size: 12px; text-transform: uppercase;"><?php echo htmlspecialchars($res['type_res']); ?></span></td>
                                    <td><?php echo htmlspecialchars($res['nom_mod']); ?></td>
                                    <td><?php echo htmlspecialchars($res['nom_sec']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($res['date_ajout'])); ?></td>
                                    <td style="text-align: center; display: flex; gap: 8px; justify-content: center;">
                                        <a href="<?php echo htmlspecialchars($res['lien']); ?>" class="btn-download" target="_blank" <?php echo preg_match('/^https?:\/\//', $res['lien']) ? '' : 'download'; ?>>🔗 Accéder / Télécharger</a>
                                        <form action="prof_ressources_pedagogiques.php" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cette ressource ?');" style="margin: 0;">
                                            <input type="hidden" name="id_R" value="<?php echo $res['id_R']; ?>">
                                            <button type="submit" name="supprimer_ressource" class="btn-delete">🗑️ Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #777; padding: 20px;">Aucune ressource pédagogique trouvée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        function toggleTypePub(val) {
            const groupeFichier = document.getElementById('groupe-fichier');
            const groupeLien = document.getElementById('groupe-lien');
            const inputFichier = document.getElementById('fichier');
            const inputLien = document.getElementById('url_lien');

            if (val === 'lien') {
                groupeFichier.style.display = 'none';
                groupeLien.style.display = 'flex';
                inputFichier.removeAttribute('required');
                inputLien.setAttribute('required', 'required');
            } else {
                groupeFichier.style.display = 'flex';
                groupeLien.style.display = 'none';
                inputLien.removeAttribute('required');
                inputFichier.setAttribute('required', 'required');
            }
        }

        function toggleUserMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
            var notifDropdown = document.getElementById('notifDropdown');
            if (notifDropdown) notifDropdown.classList.remove('active');
        }

        function toggleNotifMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('notifDropdown');
            dropdown.classList.toggle('active');
            var userDropdown = document.getElementById('userDropdown');
            if (userDropdown) userDropdown.classList.remove('active');
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