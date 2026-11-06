<?php
session_start();

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_user = trim($_POST['id_user']);
    $password = $_POST['password'];

    if (!empty($id_user) && !empty($password)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 1. Recherche de l'utilisateur dans la table mère
            $stmt = $pdo->prepare("SELECT * FROM user WHERE id_user = ?");
            $stmt->execute([$id_user]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Vérification de l'existence et du mot de passe
            if ($user_data && password_verify($password, $user_data['mdP'])) {
                
                // Stockage des informations de base en Session
                $_SESSION['id_user'] = $user_data['id_user'];
                $_SESSION['nom']     = $user_data['nom'];
                $_SESSION['prenom']  = $user_data['prenom'];
                $_SESSION['role']    = $user_data['role'];

                // 3. Redirection dynamique selon le Rôle
                switch ($user_data['role']) {
                    case 'etudiant':
                        header('Location: etudiant_dashboard.php');
                        exit();

                    case 'prof':
                        header('Location: prof_dashboard.php');
                        exit();

                    case 'scolarite':
                        // Vérification du poste administratif dans la table scolarité
                        $stmt_scol = $pdo->prepare("SELECT post_Admin FROM scolarite WHERE id_user = ?");
                        $stmt_scol->execute([$user_data['id_user']]);
                        $scol_data = $stmt_scol->fetch(PDO::FETCH_ASSOC);

                        $post_admin = strtolower($scol_data['post_Admin'] ?? '');

                        // Si c'est l'Admin Principal -> Tableau de bord Super Admin avec gestion des agents
                        if (str_contains($post_admin, 'principal') || str_contains($post_admin, 'superadmin')) {
                            $_SESSION['is_super_admin'] = true;
                            header('Location: admin_principal_dashboard.php');
                        } else {
                            // Agent de la scolarité classique
                            $_SESSION['is_super_admin'] = false;
                            header('Location: admin_principal_dashboard.php');
                        }
                        exit();

                    default:
                        $error = "Rôle utilisateur non reconnu.";
                        break;
                }
            } else {
                $error = "Identifiant ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Connexion</title>
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
            --warning: #D9761C;
            --on-warning: #2A1500;
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
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background-color: var(--card);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(27, 90, 128, 0.1);
            border: 1px solid var(--border);
            position: relative;
        }

        /* Liseré supérieur aux couleurs de ta palette (header + accent) */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--header), var(--accent));
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: var(--header);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            color: var(--text);
            transition: all 0.3s ease;
            background-color: #FAFCFE;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--menu);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(34, 107, 148, 0.15);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: var(--header);
            color: var(--on-blue);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-submit:hover {
            background-color: var(--menu);
        }

        .alert-error {
            background-color: var(--error);
            color: var(--on-error);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .login-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <h1>Smart Campus</h1>
            <p>Connexion avec l'identifiant de la scolarité</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="id_user">Identifiant </label>
                <input type="text" id="id_user" name="id_user" required placeholder="Ex: ETU2026001">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-submit">Se connecter</button>
        </form>

        <div class="login-footer">
            &copy; 2026  - Smart Campus
        </div>
    </div>

</body>
</html>