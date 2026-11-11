<?php
session_start();



$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $id_user = $_SESSION['id_user'];

    // 1. Traitement de la modification du profil
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_profil'])) {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ancien_mdp = $_POST['ancien_mdp'] ?? '';
        $nouveau_mdp = $_POST['nouveau_mdp'] ?? '';

        if (!empty($nom) && !empty($prenom) && !empty($email)) {
            
            // Vérifier si l'email appartient déjà à un autre utilisateur
            $stmt_check = $pdo->prepare("SELECT id_user, mdP FROM user WHERE email = ? AND id_user != ?");
            $stmt_check->execute([$email, $id_user]);
            
            if ($stmt_check->rowCount() > 0) {
                $error = "Cette adresse email est déjà utilisée par un autre compte.";
            } else {
                // Récupérer le mot de passe actuel stocké
                $stmt_user = $pdo->prepare("SELECT mdP FROM user WHERE id_user = ?");
                $stmt_user->execute([$id_user]);
                $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

                // Construction de la requête de mise à jour de base
                if (!empty($nouveau_mdp)) {
                    // Si l'utilisateur veut changer de mot de passe, on vérifie l'ancien
                    if (empty($ancien_mdp) || !password_verify($ancien_mdp, $user_data['mdP'])) {
                        $error = "L'ancien mot de passe est incorrect.";
                    } else {
                        $hashed_password = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
                        $stmt_update = $pdo->prepare("UPDATE user SET nom = ?, prenom = ?, email = ?, mdP = ? WHERE id_user = ?");
                        $stmt_update->execute([$nom, $prenom, $email, $hashed_password, $id_user]);
                        $message = "Vos informations et votre mot de passe ont été mis à jour avec succès !";
                    }
                } else {
                    // Mise à jour sans changer le mot de passe
                    $stmt_update = $pdo->prepare("UPDATE user SET nom = ?, prenom = ?, email = ? WHERE id_user = ?");
                    $stmt_update->execute([$nom, $prenom, $email, $id_user]);
                    $message = "Vos informations ont été mises à jour avec succès !";
                }

                // Mettre à jour les variables de session si la modification a réussi
                if (empty($error)) {
                    $_SESSION['nom'] = $nom;
                    $_SESSION['prenom'] = $prenom;
                    $_SESSION['email'] = $email;
                }
            }
        } else {
            $error = "Veuillez remplir les champs obligatoires (Nom, Prénom, Email).";
        }
    }

    // 2. Récupérer les informations actuelles fraîches de la base de données
    $stmt_get = $pdo->prepare("SELECT * FROM user WHERE id_user = ?");
    $stmt_get->execute([$id_user]);
    $current_user = $stmt_get->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Modifier mes informations</title>
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
            max-width: 700px;
            margin: 0 auto;
            width: 100%;
        }

        .section-title {
            color: var(--header);
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(27,90,128,0.04);
        }

        .form-group {
            margin-bottom: 20px;
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
            padding: 12px;
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
            padding: 12px 20px;
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

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--menu);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }

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
            <h2>Espace Paramètres du Compte</h2>
        </div>

        <div class="content-body">
            <a href="gestion_etudiants.php" class="back-link">← Retourner à l'espace de gestion</a>
            
            <h3 class="section-title">Modifier mes informations personnelles</h3>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <form action="modifier_infos.php" method="POST">
                    
                    <div class="form-group">
                        <label for="id_user">Identifiant unique (Non modifiable)</label>
                        <input type="text" id="id_user" value="<?php echo htmlspecialchars($current_user['id_user'] ?? ''); ?>" disabled style="background-color: #e9ecef; cursor: not-allowed;">
                    </div>

                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($current_user['nom'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($current_user['prenom'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" required>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border); margin: 25px 0;">

                    <h4 style="color: var(--header); margin-bottom: 15px; font-size: 15px;">Changer de mot de passe (Facultatif)</h4>
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">Laissez ces champs vides si vous ne souhaitez pas modifier votre mot de passe.</p>

                    <div class="form-group">
                        <label for="ancien_mdp">Ancien mot de passe</label>
                        <input type="password" id="ancien_mdp" name="ancien_mdp" placeholder="Laisser vide si inchangé">
                    </div>

                    <div class="form-group">
                        <label for="nouveau_mdp">Nouveau mot de passe</label>
                        <input type="password" id="nouveau_mdp" name="nouveau_mdp" placeholder="Nouveau mot de passe sécurisé">
                    </div>

                    <button type="submit" name="modifier_profil" class="btn-submit">Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>

    <script>
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
    <script>

</body>
</html>