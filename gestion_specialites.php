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

    // 1. Traitement de l'ajout d'un département
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_departement'])) {
        $nom_dep = trim($_POST['nom_dep'] ?? '');
        if (!empty($nom_dep)) {
            $stmt = $pdo->prepare("INSERT INTO departement (Nom_Dep) VALUES (?)");
            $stmt->execute([$nom_dep]);
            $message = "Le département a été créé avec succès !";
        } else {
            $error = "Veuillez entrer un nom de département valide.";
        }
    }

    // 2. Traitement de l'ajout d'une spécialité
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_specialite'])) {
        $nom_specialite = trim($_POST['nom_specialite'] ?? '');
        $annee = trim($_POST['annee'] ?? '');
        $id_dep = trim($_POST['id_dep'] ?? '');

        if (!empty($nom_specialite) && !empty($annee) && !empty($id_dep)) {
            $stmt = $pdo->prepare("INSERT INTO specialite (NomSp, annee, id_Dep) VALUES (?, ?, ?)");
            $stmt->execute([$nom_specialite, $annee, $id_dep]);
            $message = "La spécialité a été ajoutée avec succès !";
        } else {
            $error = "Veuillez remplir tous les champs pour la spécialité.";
        }
    }

    // 3. Traitement de l'ajout d'un nouveau module au catalogue général
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_module_catalogue'])) {
        $nom_mod = trim($_POST['nom_mod'] ?? '');
        if (!empty($nom_mod)) {
            $stmt = $pdo->prepare("INSERT INTO module (Nom_mod) VALUES (?)");
            $stmt->execute([$nom_mod]);
            $message = "Le module a été ajouté au catalogue général avec succès !";
        } else {
            $error = "Veuillez saisir un nom de module valide.";
        }
    }

    // 4. Traitement de l'affectation d'un module à la spécialité sélectionnée
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_module_programme'])) {
        $id_spec_target = trim($_POST['id_spec_target'] ?? '');
        $id_mod = trim($_POST['id_mod'] ?? '');
        $semestre = intval($_POST['semestre'] ?? 1);
        $coefficient = floatval($_POST['coefficient'] ?? 1.0);

        if (!empty($id_spec_target) && !empty($id_mod)) {
            $stmt = $pdo->prepare("INSERT INTO programme (id_sp, id_mod, semestre, coefficient) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_spec_target, $id_mod, $semestre, $coefficient]);
            $message = "Module rattaché à la spécialité avec succès !";
        } else {
            $error = "Veuillez sélectionner un module à ajouter.";
        }
    }

    // 5. Traitement des suppressions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_specialite'])) {
        $id_del = $_POST['id_spec_to_delete'] ?? '';
        
        // Nettoyage des liaisons dans la table programme
        $stmt_clean = $pdo->prepare("DELETE FROM programme WHERE id_sp= ?");
        $stmt_clean->execute([$id_del]);

        // Suppression de la spécialité
        $stmt_spec = $pdo->prepare("DELETE FROM specialite WHERE id_sp = ?");
        $stmt_spec->execute([$id_del]);
        
        $message = "La spécialité a été supprimée avec succès.";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirer_module_programme'])) {
        $id_prog_del = $_POST['id_prog_to_delete'] ?? '';
        $pdo->prepare("DELETE FROM programme WHERE id_prog = ?")->execute([$id_prog_del]);
        $message = "Le module a été retiré de la spécialité.";
    }

    // Récupération des données globales
    $departements = $pdo->query("SELECT id_Dep, Nom_Dep FROM departement")->fetchAll(PDO::FETCH_ASSOC);
    $specialites = $pdo->query("SELECT s.*, d.Nom_Dep FROM specialite s JOIN departement d ON s.id_Dep = d.id_Dep")->fetchAll(PDO::FETCH_ASSOC);
    $modules_catalogue = $pdo->query("SELECT * FROM module ORDER BY Nom_mod ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Détection de la spécialité sélectionnée pour édition
    $id_spec_edit = isset($_GET['id_spec_edit']) ? intval($_GET['id_spec_edit']) : null;
    $selected_specialite = null;
    $programmes_spec = [];

    if ($id_spec_edit) {
        // Infos sur la spécialité choisie
        $stmt_sel = $pdo->prepare("SELECT s.*, d.Nom_Dep FROM specialite s JOIN departement d ON s.id_Dep = d.id_Dep WHERE s.id_sp = ?");
        $stmt_sel->execute([$id_spec_edit]);
        $selected_specialite = $stmt_sel->fetch(PDO::FETCH_ASSOC);

        if ($selected_specialite) {
            // Récupérer uniquement les modules associés à cette spécialité
            $stmt_prog = $pdo->prepare("SELECT p.*, m.Nom_mod 
                                        FROM programme p 
                                        JOIN module m ON p.id_mod = m.id_mod 
                                        WHERE p.id_sp = ?
                                        ORDER BY p.semestre ASC");
            $stmt_prog->execute([$id_spec_edit]);
            $programmes_spec = $stmt_prog->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Smart Campus - Spécialités & Programmes</title>
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
        .btn-edit:hover { background-color: var(--header); }

        .btn-delete { background-color: var(--error); color: var(--on-error); border: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background-color: #f4ebd0; color: var(--header); font-weight: 600; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        
        .badge-semestre { background-color: var(--accent); color: var(--on-accent); padding: 3px 8px; border-radius: 12px; font-weight: 600; font-size: 12px; }
    
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
                <a href="gestion_sections.php" class="menu-item">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item active">📚 Spécialités & Départements</a>
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
            <h3 class="section-title">Structure Académique</h3>

            <?php if (!empty($message)): ?><div class="alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <!-- Formulaires d'ajout (Département et Spécialité) -->
            <div class="admin-grid">
                <div class="card">
                    <h3>Créer un département</h3>
                    <form action="gestion_specialites.php" method="POST">
                        <div class="form-group">
                            <label for="nom_dep">Nom du département</label>
                            <input type="text" id="nom_dep" name="nom_dep" required placeholder="Ex: Informatique">
                        </div>
                        <button type="submit" name="ajouter_departement" class="btn-submit">Créer le département</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Créer une spécialité</h3>
                    <form action="gestion_specialites.php" method="POST">
                        <div class="form-group">
                            <label for="nom_specialite">Intitulé de la spécialité</label>
                            <input type="text" id="nom_specialite" name="nom_specialite" required placeholder="Ex: Master 2 - GL">
                        </div>
                        <div class="form-group">
                            <label for="annee">Année d'étude (1 à 5)</label>
                            <input type="number" id="annee" name="annee" min="1" max="5" required placeholder="Ex: 5">
                        </div>
                        <div class="form-group">
                            <label for="id_dep">Département de rattachement</label>
                            <select id="id_dep" name="id_dep" required>
                                <option value="">-- Choisir un département --</option>
                                <?php foreach ($departements as $d): ?>
                                    <option value="<?php echo $d['id_Dep']; ?>"><?php echo htmlspecialchars($d['Nom_Dep']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="ajouter_specialite" class="btn-submit">Créer la spécialité</button>
                    </form>
                </div>
            </div>

            <!-- Liste des Spécialités -->
            <div class="card">
                <h3>Spécialités enregistrées</h3>
                <?php if (empty($specialites)): ?>
                    <p style="color: var(--text-muted);">Aucune spécialité enregistrée pour le moment.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Spécialité</th>
                                <th>Année</th>
                                <th>Département</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($specialites as $spec): ?>
                                <tr style="<?php echo ($selected_specialite && $selected_specialite['id_sp'] == $spec['id_sp']) ? 'background-color: #fdf8e9;' : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($spec['id_sp']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($spec['NomSp']); ?></td>
                                    <td>Année <?php echo htmlspecialchars($spec['annee']); ?></td>
                                    <td><?php echo htmlspecialchars($spec['Nom_Dep']); ?></td>
                                    <td>
                                        <a href="gestion_specialites.php?id_spec_edit=<?php echo $spec['id_sp']; ?>" class="btn-edit">
                                            📖 Gérer les modules
                                        </a>
                                        <form action="gestion_specialites.php" method="POST" onsubmit="return confirm('Voulez-vous supprimer cette spécialité ?');" style="display:inline;">
                                            <input type="hidden" name="id_spec_to_delete" value="<?php echo htmlspecialchars($spec['id_sp']); ?>">
                                            <button type="submit" name="supprimer_specialite" class="btn-delete">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- BLOC DYNAMIQUE : S'affiche UNIQUEMENT si une spécialité est sélectionnée -->
            <?php if ($selected_specialite): ?>
                <div class="card" style="border: 2px solid var(--accent); background-color: #fffdf9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--header);">
                            📚 Programme pédagogique : <span style="color: var(--menu);"><?php echo htmlspecialchars($selected_specialite['NomSp']); ?></span> (Année <?php echo htmlspecialchars($selected_specialite['annee']); ?>)
                        </h3>
                        <a href="gestion_specialites.php" style="color: var(--text-muted); text-decoration: none; font-size: 13px;">❌ Fermer l'édition</a>
                    </div>

                    <!-- Formulaire pour ajouter un module au programme de CETTE spécialité -->
                    <form action="gestion_specialites.php?id_spec_edit=<?php echo $selected_specialite['id_sp']; ?>" method="POST" style="margin-bottom: 25px; padding: 15px; background: #fff; border-radius: 8px; border: 1px solid var(--border);">
                        <input type="hidden" name="id_spec_target" value="<?php echo $selected_specialite['id_sp']; ?>">
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px; align-items: end;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="id_mod">Choisir un module (Catalogue)</label>
                                <select id="id_mod" name="id_mod" required>
                                    <option value="">-- Sélectionner un module --</option>
                                    <?php foreach ($modules_catalogue as $mc): ?>
                                        <option value="<?php echo $mc['id_mod']; ?>"><?php echo htmlspecialchars($mc['Nom_mod']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="semestre">Semestre</label>
                                <select id="semestre" name="semestre" required>
                                    <option value="1">Semestre 1 (S1)</option>
                                    <option value="2">Semestre 2 (S2)</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="coefficient">Coefficient</label>
                                <input type="number" step="0.5" id="coefficient" name="coefficient" min="1" max="10" value="1.0" required>
                            </div>
                            <div>
                                <button type="submit" name="ajouter_module_programme" class="btn-submit">Ajouter au programme</button>
                            </div>
                        </div>
                    </form>

                    <!-- Formulaire secondaire pour ajouter rapidement un tout nouveau module au catalogue si absent -->
                    <details style="margin-bottom: 20px; color: var(--text-muted); font-size: 13px;">
                        <summary style="cursor: pointer; font-weight: 600;">➕ Le module souhaité n'est pas dans la liste ? Ajoutez-le au catalogue général.</summary>
                        <form action="gestion_specialites.php?id_spec_edit=<?php echo $selected_specialite['id_sp']; ?>" method="POST" style="display: flex; gap: 10px; margin-top: 10px;">
                            <input type="text" name="nom_mod" placeholder="Nom du nouveau module (ex: Compilation)" required style="padding: 8px; border-radius: 4px; border: 1px solid var(--border); flex: 1;">
                            <button type="submit" name="ajouter_module_catalogue" class="btn-submit" style="width: auto;">Créer dans le catalogue</button>
                        </form>
                    </details>

                    <!-- Tableau des modules et coefficients de cette spécialité -->
                    <h4>Modules affectés à <?php echo htmlspecialchars($selected_specialite['NomSp']); ?></h4>
                    <?php if (empty($programmes_spec)): ?>
                        <p style="color: var(--text-muted); margin-top: 10px;">Aucun module n'a encore été associé à cette spécialité.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom du module</th>
                                    <th>Semestre</th>
                                    <th>Coefficient</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programmes_spec as $ps): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ps['Nom_mod']); ?></strong></td>
                                        <td><span class="badge-semestre">S<?php echo htmlspecialchars($ps['semestre']); ?></span></td>
                                        <td><strong>Coeff <?php echo htmlspecialchars($ps['coefficient']); ?></strong></td>
                                        <td>
                                            <form action="gestion_specialites.php?id_spec_edit=<?php echo $selected_specialite['id_sp']; ?>" method="POST" onsubmit="return confirm('Retirer ce module du programme de cette spécialité ?');" style="display:inline;">
                                                <input type="hidden" name="id_prog_to_delete" value="<?php echo htmlspecialchars($ps['id_prog']); ?>">
                                                <button type="submit" name="retirer_module_programme" class="btn-delete">Retirer</button>
                                            </form>
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
    </script>

</body>
</html>