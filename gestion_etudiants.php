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
            
            $stmt_sec_check = $pdo->prepare("SELECT nombre_groupes FROM section WHERE c = ?");
            $stmt_sec_check->execute([$id_section]);
            $section_data = $stmt_sec_check->fetch(PDO::FETCH_ASSOC);

            // Vérifier l'année de la spécialité choisie
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
                        $stmt_etu = $pdo->prepare("INSERT INTO etudiant (id_user, annee, e.id_Sec, groupe, id_sp) VALUES (?, ?, ?, ?, ?)");
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

    // 2. Traitement de la suppression d'un étudiant
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

    // 3. Récupérer toutes les sections
    $stmt_sections = $pdo->query("SELECT * FROM section ORDER BY nom_sec ASC");
    $sections = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);

    // 4. Récupérer la liste des spécialités
    $stmt_specs = $pdo->query("SELECT * FROM specialite ORDER BY NomSp ASC");
    $specialites = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Gestion de la recherche
    $search = trim($_GET['search'] ?? '');

    if (!empty($search)) {
        $stmt_etus = $pdo->prepare("SELECT u.id_user, u.nom, u.prenom, u.email, e.annee, e.groupe, sec.nom_sec, sp.NomSp as nom_spec
                                    FROM etudiant e 
                                    JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
                                    LEFT JOIN section sec ON e.id_Sec = sec.id_Sec
                                    LEFT JOIN specialite sp ON e.id_sp = sp.id_sp
                                    WHERE u.nom LIKE ? OR u.prenom LIKE ? OR u.id_user LIKE ?
                                    ORDER BY u.nom ASC");
        $stmt_etus->execute(["%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt_etus = $pdo->query("SELECT u.id_user, u.nom, u.prenom, u.email, e.annee, e.groupe, sec.nom_sec, sp.NomSp as nom_spec
                                    FROM etudiant e 
                                    JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
                                    LEFT JOIN section sec ON e.id_Sec = sec.id_Sec
                                    LEFT JOIN specialite sp ON e.id_sp = sp.id_sp
                                    ORDER BY u.nom ASC");
    }
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

        /* Disposition côte à côte (Formulaire à gauche, Liste à droite) */
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
            font-size: 13px;
        }

        th, td {
            padding: 10px 8px;
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
                <a href="gestion_annonces_notes.php" class="menu-item">📢 Notes, Absences & Annonces</a>
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

            <!-- GRID CÔTE À CÔTE : Formulaire à gauche, Liste à droite -->
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
                        
                        <!-- Spécialité -->
                        <div class="form-group">
                            <label for="id_spec">Spécialité</label>
                            <select id="id_spec" name="id_spec" onchange="surChangementSpecialite()" required>
                                <option value="">-- Choisir une spécialité --</option>
                                <?php foreach ($specialites as $sp): ?>
                                    <option value="<?php echo htmlspecialchars($sp['id_sp']); ?>" data-annee="<?php echo htmlspecialchars($sp['annee']); ?>">
                                        <?php echo htmlspecialchars($sp['NomSp'] . ' (Année ' . $sp['annee'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Année d'étude (Automatique) -->
                        <div class="form-group">
                            <label for="annee">Année d'étude (Automatique)</label>
                            <input type="number" id="annee" name="annee" readonly style="background-color: #eee;" required placeholder="Auto">
                        </div>

                        <!-- Section (Filtrée dynamiquement selon la spécialité) -->
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

                        <!-- Groupe -->
                        <div class="form-group">
                            <label for="groupe">Numéro du groupe</label>
                            <input type="number" id="groupe" name="groupe" min="1" required placeholder="Ex: 1">
                        </div>

                        <button type="submit" name="ajouter_etudiant" class="btn-submit">Inscrire l'étudiant</button>
                    </form>
                </div>

                <!-- 2. LISTE DES ÉTUDIANTS (SANS LA COLONNE EMAIL) -->
                <div class="card">
                    <h3>Étudiants inscrits</h3>

                    <form action="gestion_etudiants.php" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Rechercher nom/prénom..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-search">Rechercher</button>
                        <?php if (!empty($search)): ?>
                            <a href="gestion_etudiants.php" class="btn-reset">X</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($etudiants)): ?>
                        <p style="color: var(--text-muted);">Aucun étudiant trouvé.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Année</th>
                                        <th>Section / Groupe</th>
                                        <th>Spécialité</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etudiants as $etu): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($etu['id_user']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($etu['nom'] . ' ' . $etu['prenom']); ?></td>
                                            <td>Année <?php echo htmlspecialchars($etu['annee']); ?></td>
                                            <td><?php echo htmlspecialchars(($etu['nom_sec'] ?? 'Section') . ' - G.' . ($etu['groupe'] ?? 1)); ?></td>
                                            <td><?php echo htmlspecialchars($etu['nom_spec'] ?? 'Non assignée'); ?></td>
                                            <td>
                                                <form action="gestion_etudiants.php" method="POST" onsubmit="return confirm('Supprimer cet étudiant ?');" style="display:inline;">
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

    <script>
        // Fonction de filtrage dynamique des sections selon la spécialité
        function surChangementSpecialite() {
            var selectSp = document.getElementById('id_spec');
            var idSpChoisi = selectSp.value;
            var optionChoisie = selectSp.options[selectSp.selectedIndex];

            // 1. Remplissage automatique de l'année
            var anneeInput = document.getElementById('annee');
            var annee = optionChoisie.getAttribute('data-annee');
            if (annee) {
                anneeInput.value = annee;
            } else {
                anneeInput.value = '';
            }

            // 2. Filtrage des sections rattachées à cette spécialité
            var selectSec = document.getElementById('id_section');
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
            } else {
                optionsSec[0].text = '-- Aucune section pour cette spécialité --';
                selectSec.disabled = true;
            }
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
    </script>

</body>
</html>