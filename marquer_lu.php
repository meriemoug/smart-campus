<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit();
}

$id_notif = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'autre';

if ($id_notif) {
    $host = 'localhost'; $db = 'smart_campus'; $user = 'root'; $pass = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        
        // Marquer comme lu
        $stmt = $pdo->prepare("UPDATE notification SET lu = 1 WHERE id_notif = ? AND id_user = ?");
        $stmt->execute([$id_notif, $_SESSION['id_user']]);
    } catch (Exception $e) {
        // Gérer l'erreur silencieusement ou rediriger
    }
}

// Redirection intelligente selon le rôle et le type de notification
$role = strtolower($_SESSION['role'] ?? '');
if ($role === 'prof' || $role === 'professeur' || $role === 'enseignant') {
    if ($type === 'seance') header('Location: prof_dashboard.php');
    elseif ($type === 'examen') header('Location: prof_planning_examens.php');
    else header('Location: prof_dashboard.php');
} elseif ($role === 'scolarite' || $role === 'admin' || $role === 'agent') {
    header('Location: gestion_annonces.php');
} else {
    // Étudiant par défaut
    if ($type === 'absence' || $type === 'exclusion') header('Location: etudiant_notes.php'); // ou espace étudiant
    elseif ($type === 'examen') header('Location: etudiant_examens.php');
    elseif ($type === 'seance') header('Location: etudiant_edt.php');
    else header('Location: etudiant_dashboard.php');
}
exit();