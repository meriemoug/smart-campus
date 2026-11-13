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
        $email = trim($_POST['email'] ?? NULL);
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

    // 3. Traitement de la modification d'un agent existant
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_agent'])) {
        $id_agent_mod = trim($_POST['id_agent_mod']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $post_Admin = trim($_POST['post_Admin']);
        $nouveau_mdp = trim($_POST['password'] ?? '');

        if (!empty($id_agent_mod) && !empty($nom) && !empty($prenom) && !empty($email)) {
            // Vérifier si l'email appartient à un autre utilisateur
            $stmt_check_email = $pdo->prepare("SELECT id_user FROM user WHERE email = ? AND id_user != ?");
            $stmt_check_email->execute([$email, $id_agent_mod]);

            if ($stmt_check_email->rowCount() > 0) {
                $error = "Cette adresse email est déjà utilisée par un autre compte.";
            } else {
                $pdo->beginTransaction();

                // Mettre à jour la table user (avec ou sans nouveau mot de passe)
                if (!empty($nouveau_mdp)) {
                    $hashed_password = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
                    $stmt_up_user = $pdo->prepare("UPDATE user SET nom = ?, prenom = ?, email = ?, mdP = ? WHERE id_user = ?");
                    $stmt_up_user->execute([$nom, $prenom, $email, $hashed_password, $id_agent_mod]);
                } else {
                    $stmt_up_user = $pdo->prepare("UPDATE user SET nom = ?, prenom = ?, email = ? WHERE id_user = ?");
                    $stmt_up_user->execute([$nom, $prenom, $email, $id_agent_mod]);
                }

                // Mettre à jour le poste dans la table scolarite
                $stmt_up_scol = $pdo->prepare("UPDATE scolarite SET post_Admin = ? WHERE id_user = ?");
                $stmt_up_scol->execute([$post_Admin, $id_agent_mod]);

                $pdo->commit();
                $message = "Les informations de l'agent ont été mises à jour avec succès !";
            }
        } else {
            $error = "Veuillez remplir les champs obligatoires pour la modification.";
        }
    }

    // 4. Traitement de la suppression d'un agent
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

    // 5. Gestion de la recherche par nom ou prénom
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
            margin-top:10px;
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
            margin-top:10px;
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

        /* Modal de modification */
        #modalEdit { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 10px; width: 450px; max-width: 90%; }

        /* Style du profil utilisateur cliquable */
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

        .menu-dropdown { position: relative; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .submenu { flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px; margin-bottom: 4px; border-left: 2px solid rgba(255, 255, 255, 0.2); }
        .submenu-item { color: rgba(255, 255, 255, 0.85); padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .submenu-item:hover, .submenu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }
    </style>
</head>
<body>

    <!-- Sidebar Unifiée -->
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
            <h2>Espace Administration - Gestion des Agents</h2>

                <?php
// Récupérer les notifications non lues et la liste des notifications pour l'utilisateur connecté
$id_user_connecte = $_SESSION['id_user'];
$stmt_notifs = $pdo->prepare("SELECT * FROM notification WHERE id_user = ? ORDER BY date_creation DESC LIMIT 15");
$stmt_notifs->execute([$id_user_connecte]);
$notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
$stmt_count->execute([$id_user_connecte]);
$nb_non_lus = $stmt_count->fetchColumn();
?>

<!-- Conteneur des notifications -->
<div class="notif-menu-container" onclick="toggleNotifMenu(event)" style="position: relative; cursor: pointer; margin-right: -160px;">
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

                <!-- Liste des agents avec barre de recherche et bouton modifier -->
                <div class="card">
                    <h3>Agents enregistrés</h3>

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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $ag): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ag['id_user']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($ag['nom'] . ' ' . $ag['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($ag['post_Admin']); ?></td>
                                            <td style="display: flex; gap: 5px;">
                                                <!-- Bouton de modification (ouvre le modal avec les données de l'agent) -->
                                                <button type="button" class="btn-edit" onclick="ouvrirModalEdit('<?php echo $ag['id_user']; ?>', '<?php echo addslashes($ag['nom']); ?>', '<?php echo addslashes($ag['prenom']); ?>', '<?php echo addslashes($ag['email']); ?>', '<?php echo addslashes($ag['post_Admin']); ?>')">
                                                    Modifier
                                                </button>

                                                <?php if ($ag['id_user'] !== $_SESSION['id_user']): ?>
                                                    <form action="admin_principal_dashboard.php" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cet agent ?');" style="display:inline;">
                                                        <input type="hidden" name="id_agent_to_delete" value="<?php echo htmlspecialchars($ag['id_user']); ?>">
                                                        <button type="submit" name="supprimer_agent" class="btn-delete">Supprimer</button>
                                                    </form>
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

    <!-- Modal de Modification d'un agent -->
    <div id="modalEdit">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; color: var(--header);">Modifier l'agent</h3>
            <form action="admin_principal_dashboard.php" method="POST">
                <input type="hidden" id="edit_id_agent" name="id_agent_mod">
                
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
                    <label for="edit_post">Poste / Rôle administratif</label>
                    <input type="text" id="edit_post" name="post_Admin" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">Nouveau mot de passe (Laisser vide si inchangé)</label>
                    <input type="password" id="edit_password" name="password" placeholder="Laisser vide pour garder l'actuel">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="modifier_agent" class="btn-submit">Enregistrer</button>
                    <button type="button" onclick="fermerModalEdit()" class="btn-submit" style="background-color: #6c757d;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

        // Fonctions pour gérer le Modal de modification
        function ouvrirModalEdit(id, nom, prenom, email, poste) {
            document.getElementById('edit_id_agent').value = id;
            document.getElementById('edit_nom').value = nom;
            document.getElementById('edit_prenom').value = prenom;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_post').value = poste;
            document.getElementById('edit_password').value = ''; // Vider le champ MDP
            document.getElementById('modalEdit').style.display = 'flex';
        }

        function fermerModalEdit() {
            document.getElementById('modalEdit').style.display = 'none';
        }

        function toggleNotifMenu(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('notifDropdown');
    dropdown.classList.toggle('active');
    // Fermer l'autre menu utilisateur si ouvert
    const userDropdown = document.getElementById('userDropdown');
    if(userDropdown) userDropdown.classList.remove('active');
}

// Mettre à jour l'événement global de clic pour fermer le menu notif
window.addEventListener('click', function() {
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown && notifDropdown.classList.contains('active')) {
        notifDropdown.classList.remove('active');
    }
});
    </script>
</body>
</html>