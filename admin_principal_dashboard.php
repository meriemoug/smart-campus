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

    // 2. Traitement de l'ajout d'un nouvel agent par l'Admin Principal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_agent'])) {
        $id_agent = trim($_POST['id_agent']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $post_Admin = trim($_POST['post_Admin']);

        if (!empty($id_agent) && !empty($nom) && !empty($prenom) && !empty($email) && !empty($password)) {
            // Vérifier si l'identifiant ou l'email existe déjà
            $stmt_check = $pdo->prepare("SELECT id_user FROM user WHERE id_user = ? OR email = ?");
            $stmt_check->execute([$id_agent, $email]);
            
            if ($stmt_check->rowCount() > 0) {
                $error = "Cet identifiant ou cet email existe déjà.";
            } else {
                // Hachage sécurisé du mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Transaction pour insérer dans 'user' et 'scolarite'
                $pdo->beginTransaction();

                // Insertion dans la table mère 'user' (avec role = 'scolarite')
                $stmt_user = $pdo->prepare("INSERT INTO user (id_user, nom, prenom, email, mdP, role) VALUES (?, ?, ?, ?, ?, 'scolarite')");
                $stmt_user->execute([$id_agent, $nom, $prenom, $email, $hashed_password]);

                // Insertion dans la table fille 'scolarite'
                $stmt_scol = $pdo->prepare("INSERT INTO scolarite (id_user, post_Admin) VALUES (?, ?)");
                $stmt_scol->execute([$id_agent, $post_Admin]);

                $pdo->commit();
                $message = "L'agent de scolarité a été ajouté avec succès !";
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires.";
        }
    }

    // 3. Récupérer la liste de tous les agents de scolarité (sauf l'admin principal lui-même si on veut, ou tous)
    $stmt_agents = $pdo->query("SELECT u.id_user, u.nom, u.prenom, u.email, s.post_Admin 
                                FROM scolarite s 
                                JOIN user u ON s.id_user = u.id_user");
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

        /* --- SIDEBAR --- */
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
            margin-bottom: 40px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .menu-item {
            background-color: transparent;
            color: var(--on-blue);
            padding: 12px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
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
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .btn-logout:hover {
            opacity: 0.9;
        }

        /* --- MAIN CONTENT --- */
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

        /* --- GRID LAYOUT --- */
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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

        /* --- FORMULAIRE --- */
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

        /* --- TABLEAU DES AGENTS --- */
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

    <!-- Sidebar Unifiée (Admin Principal & Agents de Scolarité) -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="logo-area">
            <span>🎓 Smart Campus</span>
        </div>
        <div class="menu">
            <?php if (!empty($_SESSION['is_super_admin'])): ?>
                <!-- 1. Visible uniquement par l'Admin Principal -->
                <a href="admin_agents.php" class="menu-item">👥 Agents de scolarité</a>
            <?php endif; ?>

            <!-- Éléments communs accessibles à l'Admin et aux Agents -->
            <a href="gestion_profs.php" class="menu-item">👨‍🏫 Professeurs</a>
            <a href="gestion_etudiants.php" class="menu-item">🎓 Étudiants</a>
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
        <!-- Top Navbar -->
        <div class="top-navbar">
            <h2>Espace Administrateur Principal</h2>
            <div class="user-profile">
                <span>Admin : <strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1)); ?></div>
            </div>
        </div>

        <!-- Body -->
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
                    <h3>Ajouter un nouvel agent de scolarité</h3>
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

                <!-- Liste des agents existants -->
                <div class="card">
                    <h3>Agents de scolarité enregistrés</h3>
                    <?php if (empty($agents)): ?>
                        <p style="color: var(--text-muted);">Aucun agent enregistré pour le moment.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Poste</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $ag): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ag['id_user']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($ag['nom'] . ' ' . $ag['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($ag['post_Admin']); ?></td>
                                            <td><?php echo htmlspecialchars($ag['email']); ?></td>
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