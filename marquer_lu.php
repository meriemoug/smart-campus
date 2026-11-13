<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$id_notif = intval($_GET['id'] ?? 0);
$type_notif = strtolower(trim($_GET['type'] ?? ''));
$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($id_notif > 0) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Marquer la notification comme lue
        $stmt = $pdo->prepare("UPDATE notification SET lu = 1 WHERE id_notif = ? AND id_user = ?");
        $stmt->execute([$id_notif, $_SESSION['id_user']]);

    } catch (PDOException $e) {
        // Gestion silencieuse ou log d'erreur
    }
}

// 2. Redirection intelligente selon le rôle et le type de notification
if (in_array($role, ['prof', 'professeur', 'enseignant'])) {
    switch ($type_notif) {
        case 'annonce':
        case 'annonces':
            header('Location: prof_annonces.php');
            exit();
        case 'note':
        case 'notes':
            header('Location: prof_gestion_notes.php');
            exit();
        case 'ressource':
        case 'ressources':
            header('Location: prof_ressources_pedagogiques.php');
            exit();
        case 'absence':
        case 'absences':
            header('Location: prof_presence.php');
            exit();
        case 'examen':
        case 'examens':
            header('Location: prof_planning_examens.php');
            exit();
        default:
            header('Location: prof_dashboard.php');
            exit();
    }
} 
elseif (in_array($role, ['etudiant', 'etu', 'student'])) {
    switch ($type_notif) {
        case 'edt':
        case 'emploi':
        case 'emploi_du_temps':
        case 'seance':
        case 'seances':
            header('Location: etudiant_edt.php');
            exit();
        case 'ressource':
        case '':
            header('Location: etudiant_ressources.php');
            exit();
        case 'note':
        case 'notes':
            header('Location: etudiant_notes.php');
            exit();
        case 'examen':
        case 'examens':
            header('Location: etudiant_examens.php');
            exit();
        default:
            header('Location: etudiant_dashboard.php');
            exit();
    }
} 
else {
    if ($role === 'scolarite') {
        header('Location: gestion_annonces.php');
        exit();
    } else {
        header('Location: admin_principal_dashboard.php');
        exit();
    }
}