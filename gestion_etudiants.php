<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'scolarite') {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Traitement de l'ajout d'un étudiant
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_etudiant'])) {
        $id_etudiant = trim($_POST['id_etudiant'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $id_section = trim($_POST['id_section'] ?? '');
        $id_spec = trim($_POST['id_spec'] ?? '');
        $groupe = trim($_POST['groupe'] ?? '');

        if (!empty($id_etudiant) && !empty($nom) && !empty($prenom) && !empty($email) && !empty($password) && !empty($id_section) && !empty($id_spec) && !empty($groupe)) {
            
            $stmt_sec_check = $pdo->prepare("SELECT nombre_groupes FROM section WHERE id_Sec = ?");
            $stmt_sec_check->execute([$id_section]);
            $section_data = $stmt_sec_check->fetch(PDO::FETCH_ASSOC);

            $stmt_spec_check = $pdo->prepare("SELECT annee FROM specialite WHERE id_sp = ?");
            $stmt_spec_check->execute([$id_spec]);
            $spec_data = $stmt_spec_check->fetch(PDO::FETCH_ASSOC);

            if (!$section_data) {
                $error = "La section sélectionnée est invalide.";
            } elseif (!$spec_data) {
                $error = "La spécialité sélectionnée est invalide.";
            } else {
                $max_groupes = intval($section_data['nombre_groupes']);
                $annee_spec = intval($spec_data['annee']);
                $groupe_int = intval($groupe);

                if ($groupe_int < 1 || $groupe_int > $max_groupes) {
                    $error = "Erreur : Le groupe saisi ($groupe) est invalide. Cette section ne contient que $max_groupes groupe(s).";
                } else {
                    $stmt_check = $pdo->prepare("SELECT id_user FROM user WHERE id_user = ? OR email = ?");
                    $stmt_check->execute([$id_etudiant, $email]);
                    
                    if ($stmt_check->rowCount() > 0) {
                        $error = "Cet identifiant ou cet email existe déjà.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        $pdo->beginTransaction();

                        // Insertion dans 'user'
                        $stmt_user = $pdo->prepare("INSERT INTO user (id_user, nom, prenom, email, mdP, role) VALUES (?, ?, ?, ?, ?, 'etudiant')");
                        $stmt_user->execute([$id_etudiant, $nom, $prenom, $email, $hashed_password]);

                        // Insertion dans 'etudiant'
                        $stmt_etu = $pdo->prepare("INSERT INTO etudiant (id_user, annee, id_Sec, groupe, id_sp) VALUES (?, ?, ?, ?, ?)");
                        $stmt_etu->execute([$id_etudiant, $annee_spec, $id_section, $groupe_int, $id_spec]);

                        $pdo->commit();
                        $message = "L'étudiant a été inscrit, affecté à sa section (Groupe $groupe) et à sa spécialité avec succès !";
                    }
                }
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires, y compris le groupe.";
        }
    }

    // 2. Traitement de la modification d'un étudiant existant
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_etudiant'])) {
        $id_etu_mod = trim($_POST['id_etu_mod'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $id_spec = trim($_POST['id_spec'] ?? '');
        $id_section = trim($_POST['id_section'] ?? '');
        $groupe = trim($_POST['groupe'] ?? '');
        $nouveau_mdp = trim($_POST['password'] ?? '');

        if (!empty($id_etu_mod) && !empty($nom) && !empty($prenom) && !empty($email) && !empty($id_spec) && !empty($id_section) && !empty($groupe)) {
            $stmt_check_email = $pdo->prepare("SELECT id_user FROM user WHERE email = ? AND id_user != ?");
            $stmt_check_email->execute([$email, $id_etu_mod]);

            if ($stmt_check_email->rowCount() > 0) {
                $error = "Cette adresse email est déjà utilisée par un autre compte.";
            } else {
                $stmt_sec_check = $pdo->prepare("SELECT nombre_groupes FROM section WHERE id_Sec = ?");
                $stmt_sec_check->execute([$id_section]);
                $section_data = $stmt_sec_check->fetch(PDO::FETCH_ASSOC);

                $stmt_spec_check = $pdo->prepare("SELECT annee FROM specialite WHERE id_sp = ?");
                $stmt_spec_check->execute([$id_spec]);
                $spec_data = $stmt_spec_check->fetch(PDO::FETCH_ASSOC);

                if (!$section_data || !$spec_data) {
                    $error = "La section ou la spécialité sélectionnée est invalide.";
                } else {
                    $max_groupes = intval($section_data['nombre_groupes']);
                    $annee_spec = intval($spec_data['annee']);
                    $groupe_int = intval($groupe);

                    if ($groupe_int < 1 || $groupe_int > $max_groupes) {
                        $error = "Erreur : Le groupe saisi ($groupe) est invalide. Cette section ne contient que $max_groupes groupe(s).";
                    } else {
                        $pdo->beginTransaction();

                        if (!empty($nouveau_mdp)) {
                            $hashed_password = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
                            $stmt_up_user = $pdo->prepare("UPDATE user SET nom = ?, prenom = ?, email = ?, mdP = ? WHERE id_user = ?");
                            $stmt_up_user->execute([$nom, $prenom, $email, $hashed_password, $id_etu_mod]);
                        } else {
                            $stmt_up_user = $pdo->prepare("UPDATE user SET nom = ?, prenom = ?, email = ? WHERE id_user = ?");
                            $stmt_up_user->execute([$nom, $prenom, $email, $id_etu_mod]);
                        }

                        $stmt_up_etu = $pdo->prepare("UPDATE etudiant SET annee = ?, id_Sec = ?, groupe = ?, id_sp = ? WHERE id_user = ?");
                        $stmt_up_etu->execute([$annee_spec, $id_section, $groupe_int, $id_spec, $id_etu_mod]);

                        $pdo->commit();
                        $message = "Les informations de l'étudiant ont été mises à jour avec succès !";
                    }
                }
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires pour la modification.";
        }
    }

    // 3. Traitement de la suppression d'un étudiant
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_etudiant'])) {
        $id_etudiant_to_delete = trim($_POST['id_etudiant_to_delete'] ?? '');

        $pdo->beginTransaction();

        $stmt_del_etu = $pdo->prepare("DELETE FROM etudiant WHERE id_user = ?");
        $stmt_del_etu->execute([$id_etudiant_to_delete]);

        $stmt_del_user = $pdo->prepare("DELETE FROM user WHERE id_user = ?");
        $stmt_del_user->execute([$id_etudiant_to_delete]);

        $pdo->commit();
        $message = "L'étudiant a été supprimé avec succès.";
    }

    // 4. Récupérer toutes les sections
    $stmt_sections = $pdo->query("SELECT * FROM section ORDER BY nom_sec ASC");
    $sections = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);

    // 5. Récupérer la liste des spécialités
    $stmt_specs = $pdo->query("SELECT * FROM specialite ORDER BY NomSp ASC");
    $specialites = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

    // 6. Gestion des filtres et de la recherche
    $spec_filter = trim($_GET['spec'] ?? '');
    $sec_filter = trim($_GET['sec'] ?? '');
    $groupe_filter = trim($_GET['groupe'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $sql = "SELECT u.id_user, u.nom, u.prenom, u.email, e.annee, e.groupe, e.id_Sec, e.id_sp, sec.nom_sec, sp.NomSp as nom_spec
            FROM etudiant e 
            JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
            LEFT JOIN section sec ON e.id_Sec = sec.id_Sec
            LEFT JOIN specialite sp ON e.id_sp = sp.id_sp
            WHERE 1=1";
    $params = [];

    if (!empty($spec_filter)) {
        $sql .= " AND e.id_sp = ?";
        $params[] = $spec_filter;
    }
    if (!empty($sec_filter)) {
        $sql .= " AND e.id_Sec = ?";
        $params[] = $sec_filter;
    }
    if (!empty($groupe_filter)) {
        $sql .= " AND e.groupe = ?";
        $params[] = $groupe_filter;
    }
    if (!empty($search)) {
        $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.id_user LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY u.nom ASC";

    $stmt_etus = $pdo->prepare($sql);
    $stmt_etus->execute($params);
    $etudiants = $stmt_etus->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Étudiants</title>
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
            grid-template-columns: 1fr 1.4fr;
            gap: 25px;
            align-items: start;
        }

        @media (max-width: 1200px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background-color: #FAFCFE;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--menu);
            box-shadow: 0 0 0 3px rgba(34, 107, 148, 0.15);
        }

        /* Styles pour les boutons en bulle (Pills) */
        .filter-container {
            background-color: #FAFCFE;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .filter-group-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--header);
            min-width: 85px;
        }

        .filter-pill {
            background-color: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filter-pill:hover {
            border-color: var(--menu);
            color: var(--menu);
            background-color: #f0f7fc;
        }

        .filter-pill.active {
            background-color: var(--header);
            color: var(--on-blue);
            border-color: var(--header);
            font-weight: 600;
        }

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
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: var(--menu);
        }

        .btn-edit {
            background-color: var(--menu);
            color: var(--on-blue);
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }

        .btn-delete {
            background-color: var(--error);
            color: var(--on-error);
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-top: 10px;
        }

        .btn-delete:hover {
            opacity: 0.85;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
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

        #modalEdit { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 10px; width: 480px; max-width: 90%; max-height: 90vh; overflow-y: auto; }

        .user-menu-container { position: relative; display: inline-block; cursor: pointer; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; background-color: var(--accent); color: var(--on-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
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

        .menu-dropdown { position: relative; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .submenu { flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px; margin-bottom: 4px; border-left: 2px solid rgba(255, 255, 255, 0.2); }
        .submenu-item { color: rgba(255, 255, 255, 0.85); padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 400; transition: background 0.2s, color 0.2s; display: flex; align-items: center; gap: 8px; }
        .submenu-item:hover, .submenu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }
    </style>
</head>
<body>

    <!-- Sidebar -->
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
                <a href="gestion_etudiants.php" class="menu-item active">🎓 Étudiants</a>
                <a href="gestion_sections.php" class="menu-item">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item">📚 Spécialités & Départements</a>
                <a href="gestion_examens.php" class="menu-item">📅 Plannings d'examens</a>
                <div class="menu-dropdown">
                    <a href="javascript:void(0);" class="menu-item dropdown-toggle" onclick="toggleSubmenu()">
                        <span>📢 Notes, Absences & Annonces</span> 
                        <span id="arrow" style="font-size: 11px;">▾</span>
                    </a>
                    <div class="submenu" id="submenuNotes" style="display: none;">
                        <a href="gestion_notes.php" class="submenu-item">📊 Notes & Moyennes</a>
                        <a href="gestion_absences.php" class="submenu-item">🚫 Absences</a>
                        <a href="gestion_annonces.php" class="submenu-item">📢 Annonces</a>
                    </div>
                </div> 
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Gestion des Étudiants</h2>
            
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
            <h3 class="section-title">Annuaire des Étudiants, Sections, Groupes & Spécialités</h3>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="admin-grid">
                
                <!-- 1. FORMULAIRE D'INSCRIPTION -->
                <div class="card">
                    <h3>Inscrire un nouvel étudiant</h3>
                    <form action="gestion_etudiants.php" method="POST">
                        <div class="form-group">
                            <label for="id_etudiant">Identifiant unique (ex: ETU001)</label>
                            <input type="text" id="id_etudiant" name="id_etudiant" required placeholder="ETU001">
                        </div>
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" required placeholder="Nom de famille">
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" required placeholder="Prénom">
                        </div>
                        <div class="form-group">
                            <label for="email">Adresse Email</label>
                            <input type="email" id="email" name="email" required placeholder="etudiant@usthb.dz">
                        </div>
                        <div class="form-group">
                            <label for="password">Mot de passe temporaire</label>
                            <input type="password" id="password" name="password" required placeholder="••••••••">
                        </div>
                        
                        <div class="form-group">
                            <label for="id_spec">Spécialité</label>
                            <select id="id_spec" name="id_spec" onchange="surChangementSpecialite('id_spec', 'annee', 'id_section')" required>
                                <option value="">-- Choisir une spécialité --</option>
                                <?php foreach ($specialites as $sp): ?>
                                    <option value="<?php echo htmlspecialchars($sp['id_sp']); ?>" data-annee="<?php echo htmlspecialchars($sp['annee']); ?>">
                                        <?php echo htmlspecialchars($sp['NomSp'] . ' (Année ' . $sp['annee'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="annee">Année d'étude</label>
                            <input type="number" id="annee" name="annee" readonly style="background-color: #eee;" required placeholder="Auto">
                        </div>

                        <div class="form-group">
                            <label for="id_section">Section</label>
                            <select id="id_section" name="id_section" required disabled>
                                <option value="">-- Choisir d'abord une spécialité --</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo htmlspecialchars($sec['id_Sec']); ?>" data-idsp="<?php echo htmlspecialchars($sec['id_sp']); ?>" data-maxgroupes="<?php echo htmlspecialchars($sec['nombre_groupes'] ?? 4); ?>">
                                        <?php echo htmlspecialchars($sec['nom_sec'] ?? 'Section ' . $sec['id_Sec']); ?> (Max groupes : <?php echo htmlspecialchars($sec['nombre_groupes'] ?? 4); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="groupe">Numéro du groupe</label>
                            <input type="number" id="groupe" name="groupe" min="1" required placeholder="Ex: 1">
                        </div>

                        <button type="submit" name="ajouter_etudiant" class="btn-submit">Inscrire l'étudiant</button>
                    </form>
                </div>

                <!-- 2. LISTE DES ÉTUDIANTS AVEC FILTRAGE PAR BULLES -->
                <div class="card">
                    <h3>Étudiants inscrits</h3>

                    <!-- Filtres en boutons bulle (Pills) en cascade -->
                    <div class="filter-container">
                        <!-- 1. Ligne Spécialités -->
                        <div class="filter-group-row">
                            <span class="filter-label">Spécialité :</span>
                            <a href="gestion_etudiants.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" 
                               class="filter-pill <?php echo empty($spec_filter) ? 'active' : ''; ?>">Toutes</a>
                            <?php foreach ($specialites as $sp): ?>
                                <a href="gestion_etudiants.php?spec=<?php echo $sp['id_sp']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-pill <?php echo ($spec_filter == $sp['id_sp']) ? 'active' : ''; ?>">
                                    📚 <?php echo htmlspecialchars($sp['NomSp']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- 2. Ligne Sections (affichée si une spécialité est sélectionnée) -->
                        <?php if (!empty($spec_filter)): 
                            $filtered_sections = array_filter($sections, function($sec) use ($spec_filter) {
                                return $sec['id_sp'] == $spec_filter;
                            });
                        ?>
                        <div class="filter-group-row">
                            <span class="filter-label">Section :</span>
                            <a href="gestion_etudiants.php?spec=<?php echo $spec_filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="filter-pill <?php echo empty($sec_filter) ? 'active' : ''; ?>">Toutes</a>
                            <?php foreach ($filtered_sections as $sec): ?>
                                <a href="gestion_etudiants.php?spec=<?php echo $spec_filter; ?>&sec=<?php echo $sec['id_Sec']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-pill <?php echo ($sec_filter == $sec['id_Sec']) ? 'active' : ''; ?>">
                                    🏢 <?php echo htmlspecialchars($sec['nom_sec']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- 3. Ligne Groupes (affichée si une section est sélectionnée) -->
                        <?php if (!empty($sec_filter)): 
                            $selected_sec_data = null;
                            foreach ($sections as $sec) {
                                if ($sec['id_Sec'] == $sec_filter) {
                                    $selected_sec_data = $sec;
                                    break;
                                }
                            }
                            $max_g = $selected_sec_data ? intval($selected_sec_data['nombre_groupes'] ?? 4) : 4;
                        ?>
                        <div class="filter-group-row">
                            <span class="filter-label">Groupe :</span>
                            <a href="gestion_etudiants.php?spec=<?php echo $spec_filter; ?>&sec=<?php echo $sec_filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="filter-pill <?php echo empty($groupe_filter) ? 'active' : ''; ?>">Tous</a>
                            <?php for ($g = 1; $g <= $max_g; $g++): ?>
                                <a href="gestion_etudiants.php?spec=<?php echo $spec_filter; ?>&sec=<?php echo $sec_filter; ?>&groupe=<?php echo $g; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-pill <?php echo ($groupe_filter == $g) ? 'active' : ''; ?>">
                                    👥 G. <?php echo $g; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Barre de recherche combinée avec les filtres -->
                    <form action="gestion_etudiants.php" method="GET" class="search-form">
                        <?php if (!empty($spec_filter)): ?><input type="hidden" name="spec" value="<?php echo htmlspecialchars($spec_filter); ?>"><?php endif; ?>
                        <?php if (!empty($sec_filter)): ?><input type="hidden" name="sec" value="<?php echo htmlspecialchars($sec_filter); ?>"><?php endif; ?>
                        <?php if (!empty($groupe_filter)): ?><input type="hidden" name="groupe" value="<?php echo htmlspecialchars($groupe_filter); ?>"><?php endif; ?>
                        
                        <input type="text" name="search" placeholder="Rechercher par nom, prénom ou ID..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-search">Rechercher</button>
                        <?php if (!empty($search) || !empty($spec_filter) || !empty($sec_filter) || !empty($groupe_filter)): ?>
                            <a href="gestion_etudiants.php" class="btn-reset" title="Réinitialiser tous les filtres">Réinitialiser</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($etudiants)): ?>
                        <p style="color: var(--text-muted);">Aucun étudiant trouvé pour ces critères.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom & Prénom</th>
                                        <th>Année</th>
                                        <th>Spécialité</th>
                                        <th>Section / Groupe</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etudiants as $etu): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($etu['nom'] . ' ' . $etu['prenom']); ?></td>
                                            <td>Année <?php echo htmlspecialchars($etu['annee']); ?></td>
                                            <td><?php echo htmlspecialchars($etu['nom_spec'] ?? 'Non assignée'); ?></td>
                                            <td><?php echo htmlspecialchars(($etu['nom_sec'] ?? 'Section') . ' - G.' . ($etu['groupe'] ?? 1)); ?></td>
                                            
                                            <td style="display: flex; align-items: center; gap: 5px;">
                                                <!-- Bouton Modifier -->
                                                <button type="button" class="btn-edit" onclick="ouvrirModalEdit(
                                                    '<?php echo htmlspecialchars($etu['id_user']); ?>',
                                                    '<?php echo addslashes($etu['nom']); ?>',
                                                    '<?php echo addslashes($etu['prenom']); ?>',
                                                    '<?php echo addslashes($etu['email']); ?>',
                                                    '<?php echo htmlspecialchars($etu['id_sp'] ?? ''); ?>',
                                                    '<?php echo htmlspecialchars($etu['id_Sec'] ?? ''); ?>',
                                                    '<?php echo htmlspecialchars($etu['groupe'] ?? 1); ?>'
                                                )">Modif</button>

                                                <!-- Bouton Supprimer -->
                                                <form action="gestion_etudiants.php" method="POST" onsubmit="return confirm('Supprimer cet étudiant ?');" style="display:inline; margin: 0;">
                                                    <input type="hidden" name="id_etudiant_to_delete" value="<?php echo htmlspecialchars($etu['id_user']); ?>">
                                                    <button type="submit" name="supprimer_etudiant" class="btn-delete">Suppr</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal de Modification d'un Étudiant -->
    <div id="modalEdit">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; color: var(--header);">Modifier l'étudiant</h3>
            <form action="gestion_etudiants.php" method="POST">
                <input type="hidden" id="edit_id_etu" name="id_etu_mod">
                
                <div class="form-group">
                    <label for="edit_nom">Nom</label>
                    <input type="text" id="edit_nom" name="nom" required>
                </div>
                <div class="form-group">
                    <label for="edit_prenom">Prénom</label>
                    <input type="text" id="edit_prenom" name="prenom" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Adresse Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_id_spec">Spécialité</label>
                    <select id="edit_id_spec" name="id_spec" onchange="surChangementSpecialite('edit_id_spec', 'edit_annee', 'edit_id_section')" required>
                        <option value="">-- Choisir une spécialité --</option>
                        <?php foreach ($specialites as $sp): ?>
                            <option value="<?php echo htmlspecialchars($sp['id_sp']); ?>" data-annee="<?php echo htmlspecialchars($sp['annee']); ?>">
                                <?php echo htmlspecialchars($sp['NomSp'] . ' (Année ' . $sp['annee'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_annee">Année d'étude (Automatique)</label>
                    <input type="number" id="edit_annee" name="annee" readonly style="background-color: #eee;" required>
                </div>

                <div class="form-group">
                    <label for="edit_id_section">Section</label>
                    <select id="edit_id_section" name="id_section" required>
                        <option value="">-- Choisir une section --</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo htmlspecialchars($sec['id_Sec']); ?>" data-idsp="<?php echo htmlspecialchars($sec['id_sp']); ?>" data-maxgroupes="<?php echo htmlspecialchars($sec['nombre_groupes'] ?? 4); ?>">
                                <?php echo htmlspecialchars($sec['nom_sec'] ?? 'Section ' . $sec['id_Sec']); ?> (Max groupes : <?php echo htmlspecialchars($sec['nombre_groupes'] ?? 4); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_groupe">Numéro du groupe</label>
                    <input type="number" id="edit_groupe" name="groupe" min="1" required>
                </div>

                <div class="form-group">
                    <label for="edit_password">Nouveau mot de passe </label>
                    <input type="password" id="edit_password" name="password" placeholder="Laisser vide pour garder l'actuel">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="modifier_etudiant" class="btn-submit" style="margin-top: 0;">Enregistrer</button>
                    <button type="button" onclick="fermerModalEdit()" class="btn-submit" style="background-color: #6c757d; margin-top: 0;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fonction générique de filtrage dynamique des sections selon la spécialité
        function surChangementSpecialite(idSelectSp, idInputAnnee, idSelectSec, sectionPreselectionnee = '') {
            var selectSp = document.getElementById(idSelectSp);
            var idSpChoisi = selectSp.value;
            var optionChoisie = selectSp.options[selectSp.selectedIndex];

            var anneeInput = document.getElementById(idInputAnnee);
            var annee = optionChoisie ? optionChoisie.getAttribute('data-annee') : '';
            anneeInput.value = annee ? annee : '';

            var selectSec = document.getElementById(idSelectSec);
            var optionsSec = selectSec.options;
            var nbSectionsTrouvees = 0;

            if (!idSpChoisi) {
                selectSec.disabled = true;
                selectSec.value = '';
                optionsSec[0].text = '-- Choisir d\'abord une spécialité --';
                return;
            }

            selectSec.disabled = false;
            selectSec.value = '';

            for (var i = 1; i < optionsSec.length; i++) {
                var opt = optionsSec[i];
                var spDeLaSection = opt.getAttribute('data-idsp');

                if (spDeLaSection === idSpChoisi) {
                    opt.style.display = 'block';
                    nbSectionsTrouvees++;
                } else {
                    opt.style.display = 'none';
                }
            }

            if (nbSectionsTrouvees > 0) {
                optionsSec[0].text = '-- Choisir une section (' + nbSectionsTrouvees + ' dispo) --';
                if (sectionPreselectionnee) {
                    selectSec.value = sectionPreselectionnee;
                }
            } else {
                optionsSec[0].text = '-- Aucune section pour cette spécialité --';
                selectSec.disabled = true;
            }
        }

        // Gestion du Modal de modification
        function ouvrirModalEdit(id, nom, prenom, email, id_sp, id_sec, groupe) {
            document.getElementById('edit_id_etu').value = id;
            document.getElementById('edit_nom').value = nom;
            document.getElementById('edit_prenom').value = prenom;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_id_spec').value = id_sp;
            document.getElementById('edit_groupe').value = groupe;
            document.getElementById('edit_password').value = '';

            surChangementSpecialite('edit_id_spec', 'edit_annee', 'edit_id_section', id_sec);

            document.getElementById('modalEdit').style.display = 'flex';
        }

        function fermerModalEdit() {
            document.getElementById('modalEdit').style.display = 'none';
        }

        // Gestion du menu déroulant de l'avatar utilisateur
        function toggleUserMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }

        window.onclick = function(event) {
            var dropdown = document.getElementById('userDropdown');
            if (dropdown && dropdown.classList.contains('active')) {
                dropdown.classList.remove('active');
            }
        }

        function toggleSubmenu() {
            var submenu = document.getElementById('submenuNotes');
            var arrow = document.getElementById('arrow');
            
            if (submenu.style.display === 'none' || submenu.style.display === '') {
                submenu.style.display = 'flex';
                arrow.textContent = '▴';
            } else {
                submenu.style.display = 'none';
                arrow.textContent = '▾';
            }
        }
    </script>

</body>
</html>