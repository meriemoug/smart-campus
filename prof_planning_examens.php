<?php
session_start();

// Vérification du rôle Enseignant
if (!isset($_SESSION['id_user']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['prof', 'professeur', 'enseignant'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$id_prof_session = $_SESSION['id_user'];
$nom_prof = $_SESSION['nom'] ?? 'Enseignant';
$prenom_prof = $_SESSION['prenom'] ?? '';

$error = '';
$examens = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Récupérer l'id_prof de la table 'prof'
    $stmt_p = $pdo->prepare("SELECT id_prof FROM prof WHERE id_user = ?");
    $stmt_p->execute([$id_prof_session]);
    $prof_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
    $id_prof = $prof_data['id_prof'] ?? null;

    if (!$id_prof) {
        throw new Exception("Profil enseignant introuvable dans la base de données.");
    }

    // 2. Récupérer directement les examens assignés à ce professeur
    $stmt_exams = $pdo->prepare("
        SELECT ex.*, m.nom_mod, sec.nom_sec, sp.NomSp, sp.annee, sl.nom_salle
        FROM exam ex
        JOIN module m ON ex.id_mod = m.id_mod
        JOIN section sec ON ex.id_sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        LEFT JOIN salle sl ON ex.id_salle = sl.id_salle
        WHERE ex.id_prof = ?
        ORDER BY ex.dateEx ASC, ex.heurD ASC
    ");
    $stmt_exams->execute([$id_prof]);
    $examens = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Erreur lors du chargement des examens : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Planning des Examens</title>
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
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; }
        .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { color: var(--on-blue); padding: 12px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }

        .user-info { color: var(--on-blue); font-size: 13px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; }
        .btn-logout { color: var(--accent); text-decoration: none; font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .content-body { padding: 30px; max-width: 1200px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 20px; font-weight: 700; margin-bottom: 20px; }

        table.exam-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 14px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; text-align: center; }
        td { text-align: center; }
        td:nth-child(2), td:nth-child(3) { text-align: left; }

        .badge-date { background: #e0f2fe; color: #0369a1; padding: 6px 10px; border-radius: 6px; font-weight: 600; font-size: 13px; }
        .badge-salle { background: #fef3c7; color: #92400e; padding: 6px 10px; border-radius: 6px; font-weight: 600; font-size: 13px; }
        .badge-type { background: #e2e8f0; color: #334155; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }

        .user-menu-container { position: relative; cursor: pointer; }
        .user-profile { display: flex; align-items: center; gap: 10px; background: rgba(255, 255, 255, 0.1); padding: 6px 12px; border-radius: 30px; }
        .user-profile span { color: var(--on-blue); font-size: 14px; }
        .user-avatar { width: 35px; height: 35px; background-color: var(--accent); color: var(--on-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 15px; }
        .user-dropdown { display: none; position: absolute; right: 0; top: 50px; background: white; color: var(--text); width: 240px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); border: 1px solid var(--border); z-index: 100; overflow: hidden; }
        .user-dropdown.show { display: block; }
        .dropdown-header { padding: 12px 15px; background: #f8fafc; border-bottom: 1px solid #eee; font-size: 13px; color: #64748b; }
        .dropdown-item { display: block; padding: 12px 15px; color: var(--text); text-decoration: none; font-size: 14px; }
        .dropdown-item:hover { background: #f1f5f9; }
        .dropdown-item.logout { color: #dc2626; border-top: 1px solid #eee; }

        .alert-error { background-color: #fef2f2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <!-- Sidebar Enseignant -->
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus | Prof</span></div>
            <div class="menu">
                <a href="prof_dashboard.php" class="menu-item"><span>📅</span> Consulter Emploi du temps</a>
                <a href="prof_gestion_notes.php" class="menu-item"><span>📊</span> Gestion des Notes</a>
                <a href="prof_horaires_consultation.php" class="menu-item"><span>🕒</span> Horaire de Consultation</a>
                <a href="prof_ressources_pedagogiques.php" class="menu-item"><span>📁</span> Gestion des Ressources</a>
                <a href="prof_annonces.php" class="menu-item"><span>📢</span> Annonces</a>
                <a href="prof_presence.php" class="menu-item "><span>📋</span> Liste de Présence</a>
                <a href="prof_planning_examens.php" class="menu-item active"><span>📋</span> Planning des Examens</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Enseignant - Planning des Examens</h2>
            
            <div class="user-menu-container" onclick="toggleUserMenu(event)">
                <div class="user-profile">
                    <span><strong><?php echo htmlspecialchars($prenom_prof . ' ' . $nom_prof); ?></strong></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($prenom_prof, 0, 1)); ?></div>
                </div>
                <div id="userDropdown" class="user-dropdown">
                    <div class="dropdown-header">
                        Connecté en tant que<br>
                        <span style="color: var(--menu); font-weight: 600;"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Enseignant'); ?></span>
                    </div>
                    <a href="modifier_infos.php" class="dropdown-item">✏️ Modifier mes informations</a>
                    <a href="logout.php" class="dropdown-item logout">Se déconnecter</a>
                </div>
            </div>
        </div>

        <div class="content-body">

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 class="page-title">Calendrier de mes Examens Surveillés / Planifiés</h3>

                <table class="exam-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Module</th>
                            <th>Section & Spécialité</th>
                            <th>Type</th>
                            <th>Salle</th>
                            <th>Horaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($examens)): ?>
                            <?php foreach ($examens as $ex): ?>
                                <tr>
                                    <td><span class="badge-date">📅 <?php echo date('d/m/Y', strtotime($ex['dateEx'])); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($ex['nom_mod']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(($ex['nom_sec'] ?? 'Section') . ' (' . ($ex['NomSp'] ?? 'N/A') . ' - ' . ($ex['annee'] ?? '') . ')'); ?></td>
                                    <td><span class="badge-type"><?php echo htmlspecialchars($ex['type_exam']); ?></span></td>
                                    <td><span class="badge-salle">🏛️ <?php echo htmlspecialchars($ex['nom_salle'] ?? 'Salle ' . $ex['id_salle']); ?></span></td>
                                    <td><strong>⏰ <?php echo date('H:i', strtotime($ex['heurD'])) . ' - ' . date('H:i', strtotime($ex['heurF'])); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #777; padding: 25px;">Aucun examen planifié pour vous pour le moment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        function toggleUserMenu(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        window.addEventListener('click', function() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>