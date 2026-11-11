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

    // 1. Enregistrement ou mise à jour de l'examen pour un module et une section
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_examen'])) {
        $id_sec = intval($_POST['id_sec'] ?? 0);
        $id_mod = intval($_POST['id_mod'] ?? 0);
        $id_prof = intval($_POST['id_prof'] ?? 0);
        $dateEx = trim($_POST['dateEx'] ?? '');
        $heurD = trim($_POST['heurD'] ?? '');
        $heurF = trim($_POST['heurF'] ?? '');
        $id_salle = intval($_POST['id_salle'] ?? 0);
        $type_exam = trim($_POST['type_exam'] ?? 'normal');

        if ($id_sec > 0 && $id_mod > 0 && $id_prof > 0 && !empty($dateEx) && !empty($heurD) && !empty($heurF)) {
            // Insertion ou mise à jour si un examen existe déjà pour cette section et ce module
            $stmt_ins = $pdo->prepare("INSERT INTO exam (id_mod, id_sec, id_prof, id_salle, type_exam, dateEx, heurD, heurF) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE 
                                       id_prof = VALUES(id_prof), id_salle = VALUES(id_salle), type_exam = VALUES(type_exam), 
                                       dateEx = VALUES(dateEx), heurD = VALUES(heurD), heurF = VALUES(heurF)");
            $stmt_ins->execute([$id_mod, $id_sec, $id_prof, ($id_salle > 0 ? $id_salle : null), $type_exam, $dateEx, $heurD, $heurF]);
            $message = "Planning de l'examen enregistré avec succès !";
        } else {
            $error = "Veuillez remplir tous les champs obligatoires pour programmer l'examen.";
        }
    }

    // 2. Suppression d'un examen programmé
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_examen'])) {
        $id_exam_del = intval($_POST['id_exam_to_delete'] ?? 0);
        $pdo->prepare("DELETE FROM exam WHERE id_exam = ?")->execute([$id_exam_del]);
        $message = "L'examen a été retiré du planning.";
    }

    // Récupération des sections et salles pour les listes déroulantes
    $sections = $pdo->query("SELECT sec.*, sp.NomSp, sp.annee FROM section sec JOIN specialite sp ON sec.id_sp = sp.id_sp ORDER BY sec.nom_sec ASC")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM salle ORDER BY typeSalle, nom_salle")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT p.id_prof, u.nom, u.prenom FROM prof p JOIN user u ON p.id_user = u.id_user ORDER BY u.nom ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Section sélectionnée
    $id_sec_selected = isset($_GET['id_sec_selected']) ? intval($_GET['id_sec_selected']) : null;
    $selected_section = null;
    $modules_section = [];
    $examens_section = [];

    if ($id_sec_selected) {
        $stmt_sel = $pdo->prepare("SELECT sec.*, sp.NomSp, sp.annee, sp.id_sp FROM section sec JOIN specialite sp ON sec.id_sp = sp.id_sp WHERE sec.id_Sec = ?");
        $stmt_sel->execute([$id_sec_selected]);
        $selected_section = $stmt_sel->fetch(PDO::FETCH_ASSOC);

        if ($selected_section) {
            // Récupérer les modules de la spécialité avec le prof responsable par défaut (via enseignement)
            $stmt_mods = $pdo->prepare("SELECT m.id_mod, m.Nom_mod, p.semestre, p.coefficient, e.id_prof 
                                       FROM programme p 
                                       JOIN module m ON p.id_mod = m.id_mod 
                                       LEFT JOIN enseignement e ON m.id_mod = e.id_mod
                                       WHERE p.id_sp = ?");
            $stmt_mods->execute([$selected_section['id_sp']]);
            $modules_section = $stmt_mods->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les examens déjà programmés pour cette section
            $stmt_exams = $pdo->prepare("SELECT e.*, m.Nom_mod, sal.nom_salle, u.nom as prof_nom, u.prenom as prof_prenom 
                                         FROM exam e 
                                         JOIN module m ON e.id_mod = m.id_mod 
                                         LEFT JOIN salle sal ON e.id_salle = sal.id_salle 
                                         JOIN prof p ON e.id_prof = p.id_prof 
                                         JOIN user u ON p.id_user = u.id_user 
                                         WHERE e.id_sec = ?");
            $stmt_exams->execute([$id_sec_selected]);
            $raw_exams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);
            
            $examens_section = [];
            foreach ($raw_exams as $ex) {
                $examens_section[$ex['id_mod']] = $ex;
            }
        }
    }

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Plannings d'Examens</title>
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

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); }
        .card h3 { color: var(--header); margin-bottom: 15px; font-size: 18px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background-color: #FAFCFE; }

        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background-color: var(--menu); }
        .btn-edit { background-color: var(--menu); color: var(--on-blue); padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-delete { background-color: var(--error); color: var(--on-error); border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { background-color: #f4ebd0; color: var(--header); font-weight: 600; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }


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
                <a href="gestion_sections.php" class="menu-item">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item">📚 Spécialités & Départements</a>
                <a href="gestion_examens.php" class="menu-item active">📅 Plannings d'examens</a>
                <div class="menu-dropdown">
                    <!-- Le lien principal qui déclenche l'affichage au clic -->
                    <a href="javascript:void(0);" class="menu-item dropdown-toggle" onclick="toggleSubmenu()">
                        <span>📢 Notes, Absences & Annonces</span> 
                        <span id="arrow" style="font-size: 11px;">▾</span>
                    </a>
                    
                    <!-- Le sous-menu masqué par défaut grâce à l'attribut style="display: none;" -->
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
            <h3 class="section-title">Planification des Examens par Section</h3>

            <?php if (!empty($message)): ?><div class="alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <!-- Choix de la section -->
            <div class="card">
                <h3>1. Sélectionner une section</h3>
                <form action="gestion_examens.php" method="GET">
                    <div style="display: flex; gap: 15px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label for="id_sec_selected">Section</label>
                            <select id="id_sec_selected" name="id_sec_selected" required>
                                <option value="">-- Choisir une section --</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec['id_Sec']; ?>" <?php echo ($id_sec_selected == $sec['id_Sec']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sec['nom_sec'] . ' - ' . $sec['NomSp'] . ' (Année ' . $sec['annee'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn-submit" style="height: 42px; background-color: var(--menu);">Charger les modules</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TABLEAU DE PLANIFICATION DES EXAMENS -->
            <?php if ($selected_section): ?>
                <div class="card" style="border: 2px solid var(--accent); background-color: #fffdf9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--header);">
                            📅 Planning d'examens pour : <span style="color: var(--menu);"><?php echo htmlspecialchars($selected_section['nom_sec']); ?></span> (<?php echo htmlspecialchars($selected_section['NomSp']); ?>)
                        </h3>
                        <a href="gestion_examens.php" style="color: var(--text-muted); text-decoration: none; font-size: 13px;">❌ Fermer</a>
                    </div>

                    <?php if (empty($modules_section)): ?>
                        <p style="color: var(--text-muted);">Aucun module trouvé pour cette spécialité.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Professeur Surveillant / Responsable</th>
                                    <th>Type d'Examen</th>
                                    <th>Date</th>
                                    <th>Heure Début & Fin</th>
                                    <th>Salle</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules_section as $ms): 
                                    $ex_actuel = $examens_section[$ms['id_mod']] ?? null;
                                ?>
                                    <tr>
                                        <form action="gestion_examens.php?id_sec_selected=<?php echo $selected_section['id_Sec']; ?>" method="POST">
                                            <input type="hidden" name="id_sec" value="<?php echo $selected_section['id_Sec']; ?>">
                                            <input type="hidden" name="id_mod" value="<?php echo $ms['id_mod']; ?>">

                                            <td><strong><?php echo htmlspecialchars($ms['Nom_mod']); ?></strong></td>
                                            
                                            <!-- Professeur -->
                                            <td>
                                                <select name="id_prof" required style="padding: 6px; border-radius: 4px; border: 1px solid var(--border); width: 100%; background: #fff;">
                                                    <option value="">-- Prof --</option>
                                                    <?php foreach ($profs as $p): ?>
                                                        <option value="<?php echo $p['id_prof']; ?>" 
                                                            <?php echo ($ex_actuel && $ex_actuel['id_prof'] == $p['id_prof']) || (!$ex_actuel && $ms['id_prof'] == $p['id_prof']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>

                                            <!-- Type d'examen -->
                                            <td>
                                                <select name="type_exam" required style="padding: 6px; border-radius: 4px; border: 1px solid var(--border);">
                                                    <option value="normal" <?php echo ($ex_actuel && $ex_actuel['type_exam'] === 'normal') ? 'selected' : ''; ?>>Normal</option>
                                                    <option value="rattrapage" <?php echo ($ex_actuel && $ex_actuel['type_exam'] === 'rattrapage') ? 'selected' : ''; ?>>Rattrapage</option>
                                                </select>
                                            </td>

                                            <!-- Date -->
                                            <td>
                                                <input type="date" name="dateEx" value="<?php echo $ex_actuel ? htmlspecialchars($ex_actuel['dateEx']) : ''; ?>" required style="padding: 6px; border-radius: 4px; border: 1px solid var(--border);">
                                            </td>

                                            <!-- Heure Début et Fin (l'une en dessous de l'autre) -->
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                                    <div>
                                                        <label style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">Début :</label>
                                                        <input type="time" name="heurD" value="<?php echo $ex_actuel ? htmlspecialchars($ex_actuel['heurD']) : ''; ?>" required style="padding: 6px; border-radius: 4px; border: 1px solid var(--border); width: 130px;">
                                                    </div>
                                                    <div>
                                                        <label style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">Fin :</label>
                                                        <input type="time" name="heurF" value="<?php echo $ex_actuel ? htmlspecialchars($ex_actuel['heurF']) : ''; ?>" required style="padding: 6px; border-radius: 4px; border: 1px solid var(--border); width: 130px;">
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Salle -->
                                            <td>
                                                <select name="id_salle" required style="padding: 6px; border-radius: 4px; border: 1px solid var(--border);">
                                                    <option value="">-- Salle --</option>
                                                    <?php foreach ($salles as $sal): ?>
                                                        <option value="<?php echo $sal['id_salle']; ?>" <?php echo ($ex_actuel && $ex_actuel['id_salle'] == $sal['id_salle']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($sal['nom_salle'] . ' (' . $sal['typeSalle'] . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>

                                            <!-- Actions -->
                                            <!-- Actions (Enregistrer et Supprimer l'un en dessous de l'autre) -->
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                                    <button type="submit" name="enregistrer_examen" class="btn-submit" style="width: 100%; padding: 6px 12px; font-size: 12px; background-color: var(--menu);">
                                                        💾 Enregistrer
                                                    </button>
                                                </form>
                                                    <?php if ($ex_actuel): ?>
                                                        <form action="gestion_examens.php?id_sec_selected=<?php echo $selected_section['id_Sec']; ?>" method="POST" onsubmit="return confirm('Supprimer cet examen du planning ?');">
                                                            <input type="hidden" name="id_exam_to_delete" value="<?php echo $ex_actuel['id_exam']; ?>">
                                                            <button type="submit" name="supprimer_examen" class="btn-delete" style="width: 100%; padding: 6px 12px; font-size: 12px; text-align: center;">
                                                                ❌ Supprimer
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
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

        function toggleSubmenu() {
    var submenu = document.getElementById('submenuNotes');
    var arrow = document.getElementById('arrow');
    
    if (submenu.style.display === 'none' || submenu.style.display === '') {
        submenu.style.display = 'flex'; // Affiche le sous-menu
        arrow.textContent = '▴';       // Change la flèche vers le haut
    } else {
        submenu.style.display = 'none'; // Masque le sous-menu
        arrow.textContent = '▾';       // Change la flèche vers le bas
    }
}
    </script>

</body>
</html>