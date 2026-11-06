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

    // 1. Traitement de l'ajout d'un étudiant avec vérification de l'année et de la spécialité
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_etudiant'])) {
        $id_etudiant = trim($_POST['id_etudiant'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $annee = intval($_POST['annee'] ?? 0);
        $id_section = trim($_POST['id_section'] ?? '');
        $id_spec = trim($_POST['id_spec'] ?? '');

        if (!empty($id_etudiant) && !empty($nom) && !empty($prenom) && !empty($email) && !empty($password) && !empty($annee) && !empty($id_section) && !empty($id_spec)) {
            
            // Vérifier l'année de la spécialité choisie
            $stmt_spec_check = $pdo->prepare("SELECT annee FROM specialite WHERE id_spec = ?");
            $stmt_spec_check->execute([$id_spec]);
            $spec_data = $stmt_spec_check->fetch(PDO::FETCH_ASSOC);

            if (!$spec_data) {
                $error = "La spécialité sélectionnée est invalide.";
            } else {
                $annee_spec = intval($spec_data['annee']);

                // Vérification : l'année de l'étudiant doit être >= à l'année de la spécialité
                if ($annee < $annee_spec) {
                    $error = "Erreur : L'année d'étude de l'étudiant ($annee) ne peut pas être inférieure à l'année de la spécialité requise ($annee_spec).";
                } else {
                    $stmt_check = $pdo->prepare("SELECT id_user FROM user WHERE id_user = ? OR email = ?");
                    $stmt_check->execute([$id_etudiant, $email]);
                    
                    if ($stmt_check->rowCount() > 0) {
                        $error = "Cet identifiant ou cet email existe déjà.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        $pdo->beginTransaction();

                        // Insertion dans la table mère 'user'
                        $stmt_user = $pdo->prepare("INSERT INTO user (id_user, nom, prenom, email, mdP, role) VALUES (?, ?, ?, ?, ?, 'etudiant')");
                        $stmt_user->execute([$id_etudiant, $nom, $prenom, $email, $hashed_password]);

                        // Insertion dans la table fille 'etudiant' avec section et spécialité
                        $stmt_etu = $pdo->prepare("INSERT INTO etudiant (id_user, annee, id_section, id_spec) VALUES (?, ?, ?, ?)");
                        $stmt_etu->execute([$id_etudiant, $annee, $id_section, $id_spec]);

                        $pdo->commit();
                        $message = "L'étudiant a été inscrit, affecté à sa section et à sa spécialité avec succès !";
                    }
                }
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires.";
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

    // 3. Récupérer la liste des sections
    $stmt_sections = $pdo->query("SELECT * FROM section");
    $sections = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);

    // 4. Récupérer la liste des spécialités
    $stmt_specs = $pdo->query("SELECT * FROM specialite");
    $specialites = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Gestion de la recherche avec jointures sur section et spécialité
    $search = trim($_GET['search'] ?? '');

    if (!empty($search)) {
        $stmt_etus = $pdo->prepare("SELECT u.id_user, u.nom, u.prenom, u.email, e.annee, sec.nom_section, sp.nom_spec 
                                    FROM etudiant e 
                                    JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
                                    LEFT JOIN section sec ON e.id_section = sec.id_section
                                    LEFT JOIN specialite sp ON e.id_spec = sp.id_spec
                                    WHERE u.nom LIKE ? OR u.prenom LIKE ?");
        $stmt_etus->execute(["%$search%", "%$search%"]);
    } else {
        $stmt_etus = $pdo->query("SELECT u.id_user, u.nom, u.prenom, u.email, e.annee, sec.nom_section, sp.nom_spec 
                                    FROM etudiant e 
                                    JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
                                    LEFT JOIN section sec ON e.id_section = sec.id_section
                                    LEFT JOIN specialite sp ON e.id_spec = sp.id_spec");
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
    </style>
</head>
<body>

    <!-- Sidebar Unifiée -->
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
                <a href="gestion_annonces_notes.php" class="menu-item">📢 Annonces & Notes</a>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Se déconnecter</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Gestion des Étudiants</h2>
            <div class="user-profile">
                <span>Utilisateur : <strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1)); ?></div>
            </div>
        </div>

        <div class="content-body">
            <h3 class="section-title">Annuaire des Étudiants, Sections & Spécialités</h3>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="admin-grid">
                <!-- Formulaire d'ajout d'étudiant avec Section et Spécialité -->
                <div class="card">
                    <h3>Inscrire un étudiant</h3>
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
                            <label for="annee">Année d'étude de l'étudiant (1 à 5)</label>
                            <input type="number" id="annee" name="annee" min="1" max="5" required placeholder="Ex: 3">
                        </div>
                        <div class="form-group">
                            <label for="id_section">Section</label>
                            <select id="id_section" name="id_section" required>
                                <option value="">-- Choisir une section --</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo htmlspecialchars($sec['id_section']); ?>">
                                        <?php echo htmlspecialchars($sec['nom_section'] ?? 'Section ' . $sec['id_section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="id_spec">Spécialité</label>
                            <select id="id_spec" name="id_spec" required>
                                <option value="">-- Choisir une spécialité --</option>
                                <?php foreach ($specialites as $sp): ?>
                                    <option value="<?php echo htmlspecialchars($sp['id_spec']); ?>">
                                        <?php echo htmlspecialchars($sp['nom_spec'] . ' (Année ' . $sp['annee'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="ajouter_etudiant" class="btn-submit">Inscrire l'étudiant</button>
                    </form>
                </div>

                <!-- Liste des étudiants avec affichage de la section et de la spécialité -->
                <div class="card">
                    <h3>Étudiants inscrits</h3>

                    <form action="gestion_etudiants.php" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Rechercher par nom ou prénom..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-search">Rechercher</button>
                        <?php if (!empty($search)): ?>
                            <a href="gestion_etudiants.php" class="btn-reset">Réinitialiser</a>
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
                                        <th>Section</th>
                                        <th>Spécialité</th>
                                        <th>Email</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etudiants as $etu): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($etu['id_user']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($etu['nom'] . ' ' . $etu['prenom']); ?></td>
                                            <td>Année <?php echo htmlspecialchars($etu['annee']); ?></td>
                                            <td><?php echo htmlspecialchars($etu['nom_section'] ?? 'Non assignée'); ?></td>
                                            <td><?php echo htmlspecialchars($etu['nom_spec'] ?? 'Non assignée'); ?></td>
                                            <td><?php echo htmlspecialchars($etu['email']); ?></td>
                                            <td>
                                                <form action="gestion_etudiants.php" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cet étudiant ?');" style="display:inline;">
                                                    <input type="hidden" name="id_etudiant_to_delete" value="<?php echo htmlspecialchars($etu['id_user']); ?>">
                                                    <button type="submit" name="supprimer_etudiant" class="btn-delete">Supprimer</button>
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

</body>
</html>