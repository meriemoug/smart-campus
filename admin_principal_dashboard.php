<?php
session_start();

// 1. Sécurité : Vérifier si l'utilisateur est connecté, s'il est de type 'scolarite' et s'il est Admin Principal
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'scolarite' || empty($_SESSION['is_super_admin'])) {
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

    // 2. Traitement de l'ajout d'un nouvel agent
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_agent'])) {
        $id_agent = trim($_POST['id_agent']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $post_Admin = trim($_POST['post_Admin']);

        if (!empty($id_agent) && !empty($nom) && !empty($prenom) && !empty($email) && !empty($password)) {
            $stmt_check = $pdo->prepare("SELECT id_user FROM user WHERE id_user = ? OR email = ?");
            $stmt_check->execute([$id_agent, $email]);
            
            if ($stmt_check->rowCount() > 0) {
                $error = "Cet identifiant ou cet email existe déjà.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $stmt_user = $pdo->prepare("INSERT INTO user (id_user, nom, prenom, email, mdP, role) VALUES (?, ?, ?, ?, ?, 'scolarite')");
                $stmt_user->execute([$id_agent, $nom, $prenom, $email, $hashed_password]);

                $stmt_scol = $pdo->prepare("INSERT INTO scolarite (id_user, post_Admin) VALUES (?, ?)");
                $stmt_scol->execute([$id_agent, $post_Admin]);

                $pdo->commit();
                $message = "L'agent de scolarité a été ajouté avec succès !";
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires.";
        }
    }

    // 3. Traitement de la suppression d'un agent
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_agent'])) {
        $id_agent_to_delete = trim($_POST['id_agent_to_delete']);

        if ($id_agent_to_delete === $_SESSION['id_user']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte administrateur.";
        } else {
            $pdo->beginTransaction();

            $stmt_del_scol = $pdo->prepare("DELETE FROM scolarite WHERE id_user = ?");
            $stmt_del_scol->execute([$id_agent_to_delete]);

            $stmt_del_user = $pdo->prepare("DELETE FROM user WHERE id_user = ?");
            $stmt_del_user->execute([$id_agent_to_delete]);

            $pdo->commit();
            $message = "L'agent a été supprimé avec succès.";
        }
    }

    // 4. Gestion de la recherche par nom ou prénom
    $search = trim($_GET['search'] ?? '');

    if (!empty($search)) {
        $stmt_agents = $pdo->prepare("SELECT u.id_user, u.nom, u.prenom, u.email, s.post_Admin 
                                    FROM scolarite s 
                                    JOIN user u ON s.id_user = u.id_user 
                                    WHERE u.nom LIKE ? OR u.prenom LIKE ?");
        $stmt_agents->execute(["%$search%", "%$search%"]);
    } else {
        $stmt_agents = $pdo->query("SELECT u.id_user, u.nom, u.prenom, u.email, s.post_Admin 
                                    FROM scolarite s 
                                    JOIN user u ON s.id_user = u.id_user");
    }
    $agents = $stmt_agents->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Smart Campus - Administration Principale</title>
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
    </style>
</head>
<body>

    <!-- Sidebar Unifiée (7 Éléments) -->
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area">
                <span>🛡️ Smart Campus (Admin)</span>
            </div>
            <div class="menu">
                <a href="admin_principal_dashboard.php" class="menu-item active">👥 Agents de scolarité</a>
                <a href="gestion_profs.php" class="menu-item">👨‍🏫 Professeurs</a>
                <a href="gestion_etudiants.php" class="menu-item">🎓 Étudiants</a>
                <a href="gestion_sections.php" class="menu-item">🏢 Sections</a>
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
            <h3 class="section-title">Gestion de l'équipe de scolarité</h3>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="admin-grid">
                <!-- Formulaire d'ajout d'agent -->
                <div class="card">
                    <h3>Ajouter un nouvel agent</h3>
                    <form action="admin_principal_dashboard.php" method="POST">
                        <div class="form-group">
                            <label for="id_agent">Identifiant unique (ex: SCOL002)</label>
                            <input type="text" id="id_agent" name="id_agent" required placeholder="SCOL002">
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
                            <input type="email" id="email" name="email" required placeholder="agent@usthb.dz">
                        </div>
                        <div class="form-group">
                            <label for="password">Mot de passe temporaire</label>
                            <input type="password" id="password" name="password" required placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label for="post_Admin">Poste / Rôle administratif</label>
                            <input type="text" id="post_Admin" name="post_Admin" required placeholder="Ex: Agent Scolarité - Licence">
                        </div>
                        <button type="submit" name="ajouter_agent" class="btn-submit">Créer le compte agent</button>
                    </form>
                </div>

                <!-- Liste des agents avec barre de recherche -->
                <div class="card">
                    <h3>Agents enregistrés</h3>

                    <!-- Barre de recherche -->
                    <form action="admin_principal_dashboard.php" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Rechercher par nom ou prénom..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-search">Rechercher</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_principal_dashboard.php" class="btn-reset">Réinitialiser</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($agents)): ?>
                        <p style="color: var(--text-muted);">Aucun agent trouvé.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Poste</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $ag): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ag['id_user']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($ag['nom'] . ' ' . $ag['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($ag['post_Admin']); ?></td>
                                            <td>
                                                <?php if ($ag['id_user'] !== $_SESSION['id_user']): ?>
                                                    <form action="admin_principal_dashboard.php" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cet agent ?');" style="display:inline;">
                                                        <input type="hidden" name="id_agent_to_delete" value="<?php echo htmlspecialchars($ag['id_user']); ?>">
                                                        <button type="submit" name="supprimer_agent" class="btn-delete">Supprimer</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">Compte Actif</span>
                                                <?php endif; ?>
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