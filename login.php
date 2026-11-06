<?php
session_start();
// Connexion à la base de données (modifie les identifiants si besoin)
$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Recherche de l'utilisateur par email
            $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification du mot de passe (hashé ou en clair selon ton insertion)
            if ($user_data && password_verify($password, $user_data['mdP'])) {
                // Enregistrement des sessions
                $_SESSION['id_user'] = $user_data['id_user'];
                $_SESSION['nom'] = $user_data['nom'];
                $_SESSION['prenom'] = $user_data['prenom'];
                $_SESSION['role'] = $user_data['role'];

                // Redirection selon le rôle
                if ($user_data['role'] === 'etudiant') {
                    header('Location: etudiant_dashboard.php');
                } elseif ($user_data['role'] === 'prof') {
                    header('Location: prof_dashboard.php');
                } elseif ($user_data['role'] === 'scolarite') {
                    header('Location: scolarite_dashboard.php');
                }
                exit();
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion : " . $e->getMessage();
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
            --primary-blue: #1c4e70;      /* Bleu profond inspiré de ton interface */
            --accent-gold: #f4a261;       /* Or / Jaune chaleureux */
            --bg-cream: #fbf8f1;          /* Fond crème doux */
            --card-bg: #ffffff;           /* Blanc pur pour la carte */
            --text-dark: #2b2d42;         /* Texte principal sombre */
            --burgundy: #9b2226;          /* Bordeaux d'alerte / déconnexion */
            --border-color: #e3d5ca;      /* Couleur de bordure subtile */
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-cream);
            color: var(--text-dark);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background-color: var(--card-bg);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(28, 78, 112, 0.1);
            border: 1px solid var(--border-color);
            position: relative;
        }

        /* Liseré supérieur aux couleurs de l'app */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-blue), var(--accent-gold));
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: var(--primary-blue);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #6c757d;
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
            color: var(--text-dark);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background-color: #faf9f6;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-blue);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(28, 78, 112, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #153c56;
        }

        .alert-error {
            background-color: #fde8e8;
            color: var(--burgundy);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid #f8b4b4;
            text-align: center;
        }

        .login-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: #6c757d;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <h1>Smart Campus</h1>
            <p>Connectez-vous à votre espace</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Adresse Email</label>
                <input type="email" id="email" name="email" required placeholder="nom.prenom@usthb.dz">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-submit">Se connecter</button>
        </form>

        <div class="login-footer">
            &copy; 2026 USTHB - Smart Campus
        </div>
    </div>

</body>
</html>