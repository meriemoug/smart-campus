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

    // 1. Traitement de l'ajout d'un professeur avec son département
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_prof'])) {
        $id_prof = trim($_POST['id_prof']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']?? NULL);
        $password = trim($_POST['password']);
        $grade = trim($_POST['grade']);
        $id_departement = !empty($_POST['id_Dep']) ? trim($_POST['id_Dep']) : null;

        if (!empty($id_prof) && !empty($nom) && !empty($prenom) && !empty($email) && !empty($password)) {
            $stmt_check = $pdo->prepare("SELECT id_user FROM user WHERE id_user = ? OR email = ?");
            $stmt_check->execute([$id_prof, $email]);
            
            if ($stmt_check->rowCount() > 0) {
                $error = "Cet identifiant ou cet email existe déjà.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                // Insertion dans la table mère 'user'
                $stmt_user = $pdo->prepare("INSERT INTO user (id_user, nom, prenom, email, mdP, role) VALUES (?, ?, ?, ?, ?, 'prof')");
                $stmt_user->execute([$id_prof, $nom, $prenom, $email, $hashed_password]);

                // Insertion dans la table fille 'prof' avec id_departement
                $stmt_prof = $pdo->prepare("INSERT INTO prof (id_user, grade, id_Dep) VALUES (?, ?, ?)");
                $stmt_prof->execute([$id_prof, $grade, $id_departement]);

                $pdo->commit();
                $message = "Le professeur a été enregistré avec succès !";
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires.";
        }
    }

    // 2. Traitement de la suppression d'un professeur
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_prof'])) {
        $id_prof_to_delete = trim($_POST['id_prof_to_delete']);

        $pdo->beginTransaction();

        $stmt_del_prof = $pdo->prepare("DELETE FROM prof WHERE id_user = ?");
        $stmt_del_prof->execute([$id_prof_to_delete]);

        $stmt_del_user = $pdo->prepare("DELETE FROM user WHERE id_user = ?");
        $stmt_del_user->execute([$id_prof_to_delete]);

        $pdo->commit();
        $message = "Le professeur a été supprimé avec succès.";
    }

    // 3. Récupérer la liste des départements pour le menu déroulant
    $stmt_dept = $pdo->query("SELECT * FROM departement");
    $departements = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);

    // 4. Gestion de la recherche par nom ou prénom avec jointure sur le département
    $search = trim($_GET['search'] ?? '');

    if (!empty($search)) {
        $stmt_profs = $pdo->prepare("SELECT u.id_user, u.nom, u.prenom, u.email, p.grade, d.Nom_Dep 
                                   FROM prof p 
                                   JOIN user u ON p.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci 
                                   LEFT JOIN departement d ON p.id_Dep = d.id_dept
                                   WHERE u.nom LIKE ? OR u.prenom LIKE ?");
        $stmt_profs->execute(["%$search%", "%$search%"]);
    } else {
        $stmt_profs = $pdo->query("SELECT u.id_user, u.nom, u.prenom, u.email, p.grade, d.Nom_Dep 
                                   FROM prof p 
                                   JOIN user u ON p.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
                                   LEFT JOIN departement d ON p.id_Dep = d.id_Dep");
    }
    $professeurs = $stmt_profs->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Smart Campus - Gestion des Professeurs</title>
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
                <a href="gestion_profs.php" class="menu-item active">👨‍🏫 Professeurs</a>
                <a href="gestion_etudiants.php" class="menu-item">🎓 Étudiants</a>
                <a href="gestion_sections.php" class="menu-item">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item">📚 Spécialités & Départements</a>
                <a href="gestion_examens.php" class="menu-item">📅 Plannings d'examens</a>
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
            <h3 class="section-title">Annuaire des Professeurs</h3>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="admin-grid">
                <!-- Formulaire d'ajout de professeur -->
                <div class="card">
                    <h3>Ajouter un professeur</h3>
                    <form action="gestion_profs.php" method="POST">
                        <div class="form-group">
                            <label for="id_prof">Identifiant unique (ex: PROF001)</label>
                            <input type="text" id="id_prof" name="id_prof" required placeholder="PROF001">
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
                            <input type="email" id="email" name="email"  placeholder="prof@usthb.dz">
                        </div>
                        <div class="form-group">
                            <label for="password">Mot de passe temporaire</label>
                            <input type="password" id="password" name="password" required placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label for="grade">Grade / Titre</label>
                            <input type="text" id="grade" name="grade"  placeholder="Ex: Professeur, MCA, MAB">
                        </div>
                        <div class="form-group">
                            <label for="id_departement">Département de rattachement (Optionnel)</label>
                            <select id="id_departement" name="id_departement">
                                <option value="">-- Aucun département --</option>
                                <?php foreach ($departements as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['id_Dep']); ?>">
                                        <?php echo htmlspecialchars($dept['Nom_Dep'] ?? 'Département ' . $dept['id_dept']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="ajouter_prof" class="btn-submit">Enregistrer le professeur</button>
                    </form>
                </div>

                <!-- Liste des professeurs -->
                <div class="card">
                    <h3>Professeurs enregistrés</h3>

                    <form action="gestion_profs.php" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Rechercher par nom ou prénom..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-search">Rechercher</button>
                        <?php if (!empty($search)): ?>
                            <a href="gestion_profs.php" class="btn-reset">Réinitialiser</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($professeurs)): ?>
                        <p style="color: var(--text-muted);">Aucun professeur trouvé.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Grade</th>
                                        <th>Département</th>
                                        <th>Email</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($professeurs as $prof): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($prof['id_user']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($prof['nom'] . ' ' . $prof['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($prof['grade']); ?></td>
                                            <td><?php echo htmlspecialchars($prof['Nom_Dep'] ?? 'Non rattaché'); ?></td>
                                            <td><?php echo htmlspecialchars($prof['email']); ?></td>
                                            <td>
                                                <form action="gestion_profs.php" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer ce professeur ?');" style="display:inline;">
                                                    <input type="hidden" name="id_prof_to_delete" value="<?php echo htmlspecialchars($prof['id_user']); ?>">
                                                    <button type="submit" name="supprimer_prof" class="btn-delete">Supprimer</button>
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