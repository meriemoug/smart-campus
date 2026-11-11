<?php
session_start(); // Démarrer la session pour y accéder

// 1. Vider toutes les variables de session
$_SESSION = array();

// 2. Supprimer le cookie de session si nécessaire (pour plus de sécurité)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Détruire complètement la session sur le serveur
session_destroy();

// 4. Rediriger l'utilisateur vers la page de connexion
header("Location: login.php");
exit();
?>