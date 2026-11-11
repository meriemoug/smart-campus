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

    // 1. Ajout d'une nouvelle section (avec vérification d'unicité)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_section'])) {
        $nom_sec = trim($_POST['nom_sec'] ?? '');
        $id_sp = trim($_POST['id_sp'] ?? '');
        $nombre_groupes = intval($_POST['nombre_groupes'] ?? 4);

        if (!empty($nom_sec) && !empty($id_sp)) {
            $stmt_check = $pdo->prepare("SELECT id_Sec FROM section WHERE nom_sec = ? AND id_sp = ?");
            $stmt_check->execute([$nom_sec, $id_sp]);

            if ($stmt_check->rowCount() > 0) {
                $error = "Erreur : La section '$nom_sec' existe déjà pour cette spécialité !";
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO section (nom_sec, id_sp, nombre_groupes) VALUES (?, ?, ?)");
                $stmt_ins->execute([$nom_sec, $id_sp, $nombre_groupes]);
                $message = "La section a été créée avec succès !";
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires.";
        }
    }

    // 2. Modification du professeur responsable d'un module
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_prof_module'])) {
        $id_mod_target = intval($_POST['id_mod_target'] ?? 0);
        $id_prof_target = intval($_POST['id_prof_target'] ?? 0);

        if ($id_mod_target > 0 && $id_prof_target > 0) {
            $stmt_ens = $pdo->prepare("INSERT INTO enseignement (id_prof, id_mod) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE id_prof = VALUES(id_prof)");
            $stmt_ens->execute([$id_prof_target, $id_mod_target]);
            $message = "Le professeur responsable du module a été mis à jour avec succès !";
        } else {
            $error = "Veuillez sélectionner un professeur valide.";
        }
    }

    // 3. Enregistrement d'une séance (AVEC GESTION DES GROUPES ET CONFLITS SALLE/SECTION)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_seance'])) {
        $id_Sec = intval($_POST['id_Sec'] ?? 0);
        $jour = trim($_POST['jour'] ?? '');
        $heurD = trim($_POST['heurD'] ?? '');
        $heurF = trim($_POST['heurF'] ?? '');
        $id_mod = intval($_POST['id_mod'] ?? 0);
        $type_seance = trim($_POST['type_seance'] ?? 'cours');
        $id_salle = intval($_POST['id_salle'] ?? 0);
        $id_prof = intval($_POST['id_prof'] ?? 0);
        $id_groupe = !empty($_POST['id_groupe']) ? intval($_POST['id_groupe']) : null;

        $stmt_sec_info = $pdo->prepare("SELECT nombre_groupes FROM section WHERE id_Sec = ?");
        $stmt_sec_info->execute([$id_Sec]);
        $sec_info = $stmt_sec_info->fetch(PDO::FETCH_ASSOC);

        if ($type_seance !== 'cours' && (empty($id_groupe) || $id_groupe < 1 || $id_groupe > $sec_info['nombre_groupes'])) {
            $error = "Erreur : Pour un " . strtoupper($type_seance) . ", le groupe doit être compris entre 1 et " . $sec_info['nombre_groupes'] . ".";
        } elseif ($id_Sec > 0 && !empty($jour) && !empty($heurD) && !empty($heurF) && $id_mod > 0 && $id_salle > 0 && $id_prof > 0) {
            
            // CONTRÔLE 1 : Vérifier si la salle est déjà occupée par n'importe quel cours/groupe sur ce créneau
            $stmt_conflict_room = $pdo->prepare("SELECT id_Seance FROM seance WHERE id_salle = ? AND jour = ? AND (heurD < ? AND heurF > ?)");
            $stmt_conflict_room->execute([$id_salle, $jour, $heurF, $heurD]);

            // CONTRÔLE 2 : Vérifier les conflits de la section/groupe
            if ($type_seance === 'cours') {
                // Un cours bloque toute la section
                $stmt_conflict_sec = $pdo->prepare("SELECT id_Seance FROM seance WHERE id_Sec = ? AND jour = ? AND (heurD < ? AND heurF > ?)");
                $stmt_conflict_sec->execute([$id_Sec, $jour, $heurF, $heurD]);
            } else {
                // Un TD/TP bloque uniquement ce même groupe OU un cours global de la section
                $stmt_conflict_sec = $pdo->prepare("SELECT id_Seance FROM seance WHERE id_Sec = ? AND jour = ? AND (heurD < ? AND heurF > ?) AND (id_groupe IS NULL OR id_groupe = ?)");
                $stmt_conflict_sec->execute([$id_Sec, $jour, $heurF, $heurD, $id_groupe]);
            }

            if ($stmt_conflict_room->rowCount() > 0) {
                $error = "Erreur : Cette salle est déjà occupée sur ce créneau horaire par un autre cours !";
            } elseif ($stmt_conflict_sec->rowCount() > 0) {
                $error = "Erreur : Conflit d'horaire ! Cette section (ou ce groupe) a déjà une séance programmée sur ce créneau.";
            } else {
                $stmt_ins_s = $pdo->prepare("INSERT INTO seance (id_Sec, id_salle, id_prof, id_mod, type_seance, jour, heurD, heurF, id_groupe) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ins_s->execute([$id_Sec, $id_salle, $id_prof, $id_mod, $type_seance, $jour, $heurD, $heurF, ($type_seance === 'cours' ? null : $id_groupe)]);
                $message = "Séance ajoutée avec succès à l'emploi du temps !";
            }
        } else {
            $error = "Veuillez remplir tous les champs du formulaire.";
        }
    }

    // 4. Suppression d'une séance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_seance'])) {
        $id_Seance_del = intval($_POST['id_Seance_to_delete'] ?? 0);
        $pdo->prepare("DELETE FROM seance WHERE id_Seance = ?")->execute([$id_Seance_del]);
        $message = "Séance retirée de l'emploi du temps.";
    }

    // Données globales
    $specialites = $pdo->query("SELECT * FROM specialite ORDER BY NomSp ASC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT p.id_prof, u.nom, u.prenom FROM prof p JOIN user u ON p.id_user = u.id_user ORDER BY u.nom ASC")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM salle ORDER BY typeSalle, nom_salle")->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer TOUTES les séances de l'université pour vérifier la disponibilité des salles en temps réel côté JS
    $toutes_seances = $pdo->query("SELECT id_salle, jour, heurD, heurF FROM seance")->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ens_all = $pdo->query("SELECT id_mod, id_prof FROM enseignement");
    $default_profs = [];
    while ($row = $stmt_ens_all->fetch(PDO::FETCH_ASSOC)) {
        $default_profs[$row['id_mod']] = $row['id_prof'];
    }

    $filter_sp = trim($_GET['filter_sp'] ?? '');
    $filter_annee = trim($_GET['filter_annee'] ?? '');

    $sql_sec = "SELECT sec.*, sp.NomSp, sp.annee FROM section sec LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp WHERE 1=1";
    $params_sec = [];

    if (!empty($filter_sp)) {
        $sql_sec .= " AND sec.id_sp = ?";
        $params_sec[] = $filter_sp;
    }
    if (!empty($filter_annee)) {
        $sql_sec .= " AND sp.annee = ?";
        $params_sec[] = $filter_annee;
    }

    $stmt_sec = $pdo->prepare($sql_sec);
    $stmt_sec->execute($params_sec);
    $sections = $stmt_sec->fetchAll(PDO::FETCH_ASSOC);

    $id_sec_selected = isset($_GET['id_sec_selected']) ? intval($_GET['id_sec_selected']) : null;
    $selected_section = null;
    $modules_section = [];
    $seances_grid = [];

    if ($id_sec_selected) {
        $stmt_sel = $pdo->prepare("SELECT sec.*, sp.NomSp, sp.annee, sp.id_sp FROM section sec LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp WHERE sec.id_Sec = ?");
        $stmt_sel->execute([$id_sec_selected]);
        $selected_section = $stmt_sel->fetch(PDO::FETCH_ASSOC);

        if ($selected_section) {
            $stmt_mods = $pdo->prepare("SELECT m.id_mod, m.Nom_mod, p.semestre, p.coefficient, e.id_prof, u.nom as prof_nom, u.prenom as prof_prenom
                                       FROM programme p 
                                       JOIN module m ON p.id_mod = m.id_mod 
                                       LEFT JOIN enseignement e ON m.id_mod = e.id_mod
                                       LEFT JOIN prof pr ON e.id_prof = pr.id_prof
                                       LEFT JOIN user u ON pr.id_user = u.id_user
                                       WHERE p.id_sp = ?
                                       ORDER BY p.semestre ASC, m.Nom_mod ASC");
            $stmt_mods->execute([$selected_section['id_sp']]);
            $modules_section = $stmt_mods->fetchAll(PDO::FETCH_ASSOC);

            $stmt_seances = $pdo->prepare("SELECT s.*, m.Nom_mod, sal.nom_salle, u.nom as prof_nom, u.prenom as prof_prenom, sec_cible.nom_sec as section_nom 
                                           FROM seance s 
                                           JOIN module m ON s.id_mod = m.id_mod 
                                           JOIN salle sal ON s.id_salle = sal.id_salle 
                                           JOIN prof p ON s.id_prof = p.id_prof
                                           JOIN user u ON p.id_user = u.id_user
                                           JOIN section sec_cible ON s.id_Sec = sec_cible.id_Sec
                                           WHERE s.id_Sec = ?");
            $stmt_seances->execute([$id_sec_selected]);
            $raw_seances = $stmt_seances->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($raw_seances as $rs) {
                $creneau_key = substr($rs['heurD'], 0, 5) . ' - ' . substr($rs['heurF'], 0, 5);
                $seances_grid[$rs['jour']][$creneau_key][] = $rs;
            }
        }
    }

    $jours = ['Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi'];
    $creneaux = [
        ['08:00', '09:30'],
        ['09:40', '11:10'],
        ['11:20', '12:50'],
        ['13:00', '14:30'],
        ['14:40', '16:10'],
        ['16:20', '17:50']
    ];

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Sections</title>
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

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; }
        .sidebar-top .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { color: var(--on-blue); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }
        .btn-logout { background-color: var(--error); color: var(--on-error); padding: 10px; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; background-color: var(--accent); color: var(--on-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .content-body { padding: 30px; }
        .section-title { color: var(--header); font-size: 22px; margin-bottom: 20px; font-weight: 700; }

        .admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        @media (max-width: 1024px) { .admin-grid { grid-template-columns: 1fr; } }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); }
        .card h3 { color: var(--header); margin-bottom: 15px; font-size: 18px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background-color: #FAFCFE; }

        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background-color: var(--menu); }
        .btn-edit { background-color: var(--menu); color: var(--on-blue); padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-delete { background-color: var(--error); color: var(--on-error); border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background-color: #f4ebd0; color: var(--header); font-weight: 600; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        .edt-table { border: 1px solid var(--border); }
        .edt-table th, .edt-table td { border: 1px solid var(--border); text-align: center; vertical-align: top; padding: 8px; font-size: 12px; }
        .edt-cell { cursor: pointer; min-height: 70px; background-color: #fafbfc; transition: background 0.2s; }
        .edt-cell:hover { background-color: #f0f7ff; }
        .seance-card { background: var(--header); color: #fff; padding: 6px; border-radius: 4px; margin-bottom: 4px; text-align: left; font-size: 11px; }
        .seance-card.cours { background-color: #1B5A80; }
        .seance-card.TD { background-color: #2e7d32; }
        .seance-card.TP { background-color: #c67c00; }

        #modalSeance { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 10px; width: 450px; max-width: 90%; }
    
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
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus</span></div>
            <div class="menu">
                <?php if (!empty($_SESSION['is_super_admin'])): ?>
                    <a href="admin_principal_dashboard.php" class="menu-item">👥 Agents de scolarité</a>
                <?php endif; ?>
                <a href="gestion_profs.php" class="menu-item">👨‍🏫 Professeurs</a>
                <a href="gestion_etudiants.php" class="menu-item">🎓 Étudiants</a>
                <a href="gestion_sections.php" class="menu-item active">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item">📚 Spécialités & Départements</a>
                <a href="gestion_examens.php" class="menu-item">📅 Plannings d'examens</a>
                                <a href="gestion_annonces_notes.php" class="menu-item">📢 Notes, Absences & Annonces</a>            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Gestion des Sections</h2>
            
            <!-- Profil utilisateur cliquable avec menu déroulant -->
            <div class="user-menu-container" onclick="toggleUserMenu(event)">
                <div class="user-profile">
                    <span><strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1)); ?></div>
                </div>

                <!-- Dropdown affiché au clic -->
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
            <h3 class="section-title">Gestion des Sections et de la Planification</h3>

            <?php if (!empty($message)): ?><div class="alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="admin-grid">
                <div class="card">
                    <h3>Ajouter une section</h3>
                    <form action="gestion_sections.php" method="POST">
                        <div class="form-group">
                            <label for="nom_sec">Nom de la section</label>
                            <input type="text" id="nom_sec" name="nom_sec" required placeholder="Ex: Section A">
                        </div>
                        <div class="form-group">
                            <label for="id_sp">Spécialité</label>
                            <select id="id_sp" name="id_sp" required>
                                <option value="">-- Choisir une spécialité --</option>
                                <?php foreach ($specialites as $sp): ?>
                                    <option value="<?php echo $sp['id_sp']; ?>"><?php echo htmlspecialchars($sp['NomSp'] . ' (Année ' . $sp['annee'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nombre_groupes">Nombre de groupes</label>
                            <input type="number" id="nombre_groupes" name="nombre_groupes" min="1" max="10" value="4" required>
                        </div>
                        <button type="submit" name="ajouter_section" class="btn-submit">Créer la section</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Rechercher / Filtrer les sections</h3>
                    <form action="gestion_sections.php" method="GET">
                        <div class="form-group">
                            <label for="filter_sp">Filtrer par Spécialité</label>
                            <select id="filter_sp" name="filter_sp">
                                <option value="">-- Toutes les spécialités --</option>
                                <?php foreach ($specialites as $sp): ?>
                                    <option value="<?php echo $sp['id_sp']; ?>" <?php echo ($filter_sp == $sp['id_sp']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sp['NomSp']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_annee">Filtrer par Année</label>
                            <input type="number" id="filter_annee" name="filter_annee" min="1" max="5" value="<?php echo htmlspecialchars($filter_annee); ?>" placeholder="Ex: 2">
                        </div>
                        <button type="submit" class="btn-submit" style="background-color: var(--menu);">Filtrer</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h3>Sections enregistrées</h3>
                <?php if (empty($sections)): ?>
                    <p style="color: var(--text-muted);">Aucune section trouvée.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom de la Section</th>
                                <th>Spécialité</th>
                                <th>Année</th>
                                <th>Nb Groupes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sections as $sec): ?>
                                <tr style="<?php echo ($selected_section && $selected_section['id_Sec'] == $sec['id_Sec']) ? 'background-color: #fdf8e9;' : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($sec['id_Sec']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sec['nom_sec']); ?></td>
                                    <td><?php echo htmlspecialchars($sec['NomSp']); ?></td>
                                    <td>Année <?php echo htmlspecialchars($sec['annee']); ?></td>
                                    <td><?php echo htmlspecialchars($sec['nombre_groupes']); ?> groupe(s)</td>
                                    <td>
                                        <a href="gestion_sections.php?id_sec_selected=<?php echo $sec['id_Sec']; ?>&filter_sp=<?php echo $filter_sp; ?>&filter_annee=<?php echo $filter_annee; ?>" class="btn-edit">
                                            ⚙️ Gérer l'emploi du temps
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ($selected_section): ?>
                <div class="card" style="border: 2px solid var(--accent); background-color: #fffdf9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--header);">
                            ⚙️ Section : <span style="color: var(--menu);"><?php echo htmlspecialchars($selected_section['nom_sec']); ?></span> (<?php echo htmlspecialchars($selected_section['NomSp']); ?>)
                        </h3>
                        <a href="gestion_sections.php" style="color: var(--text-muted); text-decoration: none; font-size: 13px;">❌ Fermer</a>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <button type="button" onclick="toggleModulesProfs()" id="btnToggleMP" class="btn-submit" style="background-color: var(--menu); width: auto;">
                            📋 Afficher les modules et professeurs de cette section
                        </button>
                    </div>

                    <div id="blocModulesProfs" class="card" style="display: none; border: 1px solid var(--header); background: #fff; margin-bottom: 25px;">
                        <h4 style="color: var(--header); margin-bottom: 15px;">📖 Modules & Professeurs Responsables</h4>
                        <?php if (empty($modules_section)): ?>
                            <p style="color: var(--text-muted);">Aucun module trouvé dans le programme de cette spécialité.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Semestre</th>
                                        <th>Coefficient</th>
                                        <th>Professeur responsable</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules_section as $ms): ?>
                                        <tr>
                                            <form action="gestion_sections.php?id_sec_selected=<?php echo $selected_section['id_Sec']; ?>" method="POST">
                                                <input type="hidden" name="id_mod_target" value="<?php echo $ms['id_mod']; ?>">
                                                <td><strong><?php echo htmlspecialchars($ms['Nom_mod']); ?></strong></td>
                                                <td>S<?php echo htmlspecialchars($ms['semestre']); ?></td>
                                                <td>Coeff <?php echo htmlspecialchars($ms['coefficient']); ?></td>
                                                <td>
                                                    <select name="id_prof_target" required style="padding: 6px; border-radius: 4px; border: 1px solid var(--border); width: 100%; background: #fff;">
                                                        <option value="">-- Choisir un professeur --</option>
                                                        <?php foreach ($profs as $p): ?>
                                                            <option value="<?php echo $p['id_prof']; ?>" <?php echo ($ms['id_prof'] == $p['id_prof']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="submit" name="modifier_prof_module" class="btn-submit" style="width: auto; padding: 6px 12px; font-size: 12px; background-color: var(--menu);">
                                                        💾 Enregistrer
                                                    </button>
                                                </td>
                                            </form>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <h4 style="margin-bottom: 10px; color: var(--header);">📅 Emploi du temps (Cliquez sur une case pour ajouter une séance)</h4>
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 15px;">Horaires : 08h00 à 17h50 (Séances de 1h30 + 10 min de pause inter-séances).</p>

                    <div style="overflow-x: auto;">
                        <table class="edt-table">
                            <thead>
                                <tr>
                                    <th>Horaire</th>
                                    <?php foreach ($jours as $j): ?>
                                        <th><?php echo $j; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($creneaux as $c): 
                                    $horaire_str = $c[0] . ' - ' . $c[1];
                                ?>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa; min-width: 110px;"><?php echo $horaire_str; ?></td>
                                        <?php foreach ($jours as $j): ?>
                                            <td class="edt-cell" onclick="ouvrirModalSeance('<?php echo $j; ?>', '<?php echo $c[0]; ?>', '<?php echo $c[1]; ?>')">
                                                <?php 
                                                if (isset($seances_grid[$j][$horaire_str])) {
                                                    foreach ($seances_grid[$j][$horaire_str] as $s) {
                                                        echo '<div class="seance-card ' . htmlspecialchars($s['type_seance']) . '">';
                                                        echo '<strong>' . htmlspecialchars($s['Nom_mod']) . '</strong> (' . htmlspecialchars(strtoupper($s['type_seance'])) . ')<br>';
                                                        echo '🏛️ ' . htmlspecialchars($s['nom_salle']) . '<br>';
                                                        echo '👨‍🏫 ' . htmlspecialchars($s['prof_nom'] . ' ' . $s['prof_prenom']);
                                                        if ($s['id_groupe']) {
                                                            echo '<br>👥 Groupe ' . htmlspecialchars($s['id_groupe']);
                                                        }
                                                        echo '<form method="POST" action="gestion_sections.php?id_sec_selected=' . $selected_section['id_Sec'] . '" style="text-align:right; margin-top:3px;">
                                                                <input type="hidden" name="id_Seance_to_delete" value="' . $s['id_Seance'] . '">
                                                                <button type="submit" name="supprimer_seance" class="btn-delete" onclick="event.stopPropagation(); return confirm(\'Supprimer cette séance ?\');">❌ Supprimer</button>
                                                              </form>';
                                                        echo '</div>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if ($selected_section): ?>
    <div id="modalSeance">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; color: var(--header);">Planifier une séance</h3>
            <form action="gestion_sections.php?id_sec_selected=<?php echo $selected_section['id_Sec']; ?>" method="POST">
                <input type="hidden" name="id_Sec" value="<?php echo $selected_section['id_Sec']; ?>">
                
                <div class="form-group">
                    <label>Jour & Créneau</label>
                    <input type="text" id="modal_jour_horaire_display" readonly style="background-color: #eee;">
                    <input type="hidden" id="modal_jour" name="jour">
                    <input type="hidden" id="heurD" name="heurD">
                    <input type="hidden" id="heurF" name="heurF">
                </div>

                <div class="form-group">
                    <label for="modal_id_mod">Module</label>
                    <select id="modal_id_mod" name="id_mod" onchange="autoSelectProf()" required>
                        <option value="">-- Choisir un module --</option>
                        <?php foreach ($modules_section as $ms): ?>
                            <option value="<?php echo $ms['id_mod']; ?>"><?php echo htmlspecialchars($ms['Nom_mod'] . ' (Semestre ' . $ms['semestre'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_type_seance">Type de séance</label>
                    <select id="modal_type_seance" name="type_seance" onchange="filtrerSallesEtGroupe()" required>
                        <option value="cours">Cours</option>
                        <option value="TD">TD</option>
                        <option value="TP">TP</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_id_salle">Salle libre disponible</label>
                    <select id="modal_id_salle" name="id_salle" required>
                        <option value="">-- Sélectionner une salle --</option>
                        <?php foreach ($salles as $sal): ?>
                            <option value="<?php echo $sal['id_salle']; ?>" data-id="<?php echo $sal['id_salle']; ?>" data-type="<?php echo $sal['typeSalle']; ?>">
                                <?php echo htmlspecialchars($sal['nom_salle'] . ' (' . $sal['typeSalle'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_id_prof">Professeur</label>
                    <select id="modal_id_prof" name="id_prof" required>
                        <option value="">-- Sélectionner un professeur --</option>
                        <?php foreach ($profs as $p): ?>
                            <option value="<?php echo $p['id_prof']; ?>">
                                <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="groupe_container" style="display:none;">
                    <label for="modal_id_groupe">Numéro du groupe (Max : <?php echo $selected_section['nombre_groupes']; ?>)</label>
                    <input type="number" id="modal_id_groupe" name="id_groupe" min="1" max="<?php echo $selected_section['nombre_groupes']; ?>" placeholder="Ex: 1">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="ajouter_seance" class="btn-submit">Valider et planifier</button>
                    <button type="button" onclick="fermerModalSeance()" class="btn-submit" style="background-color: #6c757d;">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        var defaultProfs = <?php echo json_encode($default_profs); ?>;
        var toutesSeances = <?php echo json_encode($toutes_seances); ?>;
        var currentJour = '';
        var currentHeurD = '';
        var currentHeurF = '';

        function toggleModulesProfs() {
            var bloc = document.getElementById('blocModulesProfs');
            var btn = document.getElementById('btnToggleMP');
            if (bloc.style.display === 'none') {
                bloc.style.display = 'block';
                btn.textContent = '❌ Masquer les modules et professeurs';
                btn.style.backgroundColor = '#6c757d';
            } else {
                bloc.style.display = 'none';
                btn.textContent = '📋 Afficher les modules et professeurs de cette section';
                btn.style.backgroundColor = 'var(--menu)';
            }
        }

        function ouvrirModalSeance(jour, heurD, heurF) {
            currentJour = jour;
            currentHeurD = heurD + ':00';
            currentHeurF = heurF + ':00';

            document.getElementById('modal_jour').value = jour;
            document.getElementById('heurD').value = currentHeurD;
            document.getElementById('heurF').value = currentHeurF;
            document.getElementById('modal_jour_horaire_display').value = jour + ' | ' + heurD + ' - ' + heurF;
            document.getElementById('modalSeance').style.display = 'flex';
            filtrerSallesEtGroupe();
        }

        function fermerModalSeance() {
            document.getElementById('modalSeance').style.display = 'none';
        }

        function autoSelectProf() {
            var modId = document.getElementById('modal_id_mod').value;
            var profSelect = document.getElementById('modal_id_prof');
            if (defaultProfs[modId]) {
                profSelect.value = defaultProfs[modId];
            }
        }

        function filtrerSallesEtGroupe() {
            var typeSeance = document.getElementById('modal_type_seance').value; // 'cours', 'TD', 'TP'
            var salleSelect = document.getElementById('modal_id_salle');
            var options = salleSelect.options;

            // 1. Identifier les IDs des salles occupées sur ce créneau horaire précis (toute l'université)
            var sallesOccupees = [];
            toutesSeances.forEach(function(s) {
                if (s.jour === currentJour) {
                    // Vérification du chevauchement d'horaires
                    if (s.heurD < currentHeurF && s.heurF > currentHeurD) {
                        sallesOccupees.push(parseInt(s.id_salle));
                    }
                }
            });

            // 2. Parcourir et filtrer les options de salles
            for (var i = 0; i < options.length; i++) {
                var opt = options[i];
                var optType = opt.getAttribute('data-type'); // 'cours', 'salle_TD', 'labo', etc.
                var optId = parseInt(opt.getAttribute('data-id'));
                
                if (!optType) continue; // Ignorer l'option par défaut "-- Sélectionner --"

                var matchType = false;

                // Condition stricte selon le type de séance
                if (typeSeance === 'cours' && optType === 'cours') {
                    matchType = true;
                } else if (typeSeance === 'TD' && optType === 'TD') {
                    matchType = true;
                } else if (typeSeance === 'TP' && optType === 'TP' ) {
                    matchType = true;
                }

                // La salle s'affiche UNIQUEMENT si elle correspond au type ET qu'elle n'est PAS occupée
                if (matchType && !sallesOccupees.includes(optId)) {
                    opt.style.display = 'block';
                    opt.disabled = false;
                } else {
                    opt.style.display = 'none'; // Cachée totalement
                    opt.disabled = true;
                }
            }
            salleSelect.value = ''; // Réinitialiser la sélection

            // Gestion de l'affichage du champ groupe pour les TD et TP
            var groupeContainer = document.getElementById('groupe_container');
            var groupeInput = document.getElementById('modal_id_groupe');
            if (typeSeance === 'TD' || typeSeance === 'TP') {
                groupeContainer.style.display = 'block';
                groupeInput.required = true;
            } else {
                groupeContainer.style.display = 'none';
                groupeInput.required = false;
                groupeInput.value = '';
            }
        }
        
        // Gestion du menu déroulant de l'avatar utilisateur
        function toggleUserMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }

        // Fermer le menu si on clique ailleurs sur la page
        window.onclick = function(event) {
            var dropdown = document.getElementById('userDropdown');
            if (dropdown && dropdown.classList.contains('active')) {
                dropdown.classList.remove('active');
            }
        }
    
    </script>
</body>
</html>