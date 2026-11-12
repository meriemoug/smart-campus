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

$message = '';
$error = '';
$etudiants = [];
$selected_section = $_GET['id_section'] ?? $_POST['id_section'] ?? '';
$selected_module = $_GET['id_mod'] ?? $_POST['id_mod'] ?? '';
$modules_section = [];
$sections_uniques = [];

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

    // 2. Récupérer toutes les sections distinctes enseignées par ce professeur
    $stmt_sections = $pdo->prepare("
        SELECT DISTINCT sec.id_Sec, sec.nom_sec, sp.NomSp, sp.annee
        FROM seance s
        JOIN section sec ON s.id_Sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        WHERE s.id_prof = ?
        ORDER BY sec.nom_sec ASC
    ");
    $stmt_sections->execute([$id_prof]);
    $sections_prof = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sections_prof as $row) {
        $sections_uniques[$row['id_Sec']] = [
            'nom_sec' => $row['nom_sec'],
            'specialite' => $row['NomSp'] . ' (' . $row['annee'] . ')'
        ];
    }

    // 3. Si une section est sélectionnée, récupérer UNIQUEMENT les modules enseignés par ce prof pour CETTE section
    if (!empty($selected_section)) {
        $stmt_modules = $pdo->prepare("
            SELECT DISTINCT m.id_mod, m.nom_mod
            FROM enseignement en
            JOIN module m ON en.id_mod = m.id_mod
            WHERE en.id_prof = ?
            ORDER BY m.nom_mod ASC
        ");
        $stmt_modules->execute([$id_prof]);
        $modules_result = $stmt_modules->fetchAll(PDO::FETCH_ASSOC);

        foreach ($modules_result as $mod) {
            $modules_section[$mod['id_mod']] = $mod['nom_mod'];
        }
    }

    // 4. Enregistrement / Modification des notes en utilisant id_Etud
    if (isset($_POST['enregistrer_notes']) && !empty($selected_section) && !empty($selected_module)) {
        $notes_saisies = $_POST['notes'] ?? [];

        foreach ($notes_saisies as $id_etud => $vals) {
            $td = (isset($vals['td']) && $vals['td'] !== '') ? floatval($vals['td']) : null;
            $tp = (isset($vals['tp']) && $vals['tp'] !== '') ? floatval($vals['tp']) : null;
            $exam = (isset($vals['exam']) && $vals['exam'] !== '') ? floatval($vals['exam']) : null;

            // Vérifier si la note existe déjà pour cet id_Etud et ce module
            $stmt_chk = $pdo->prepare("SELECT * FROM note WHERE id_Etud = ? AND id_Mod = ?");
            $stmt_chk->execute([$id_etud, $selected_module]);

            if ($stmt_chk->rowCount() > 0) {
                // Mise à jour
                $stmt_up = $pdo->prepare("
                    UPDATE note 
                    SET TD = ?, TP = ?, Exam = ?, id_prof = ? 
                    WHERE id_Etud = ? AND id_Mod = ?
                ");
                $stmt_up->execute([$td, $tp, $exam, $id_prof, $id_etud, $selected_module]);
            } else {
                // Insertion
                $stmt_ins = $pdo->prepare("
                    INSERT INTO note (id_Etud, id_Mod, id_prof, TD, TP, Exam) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$id_etud, $selected_module, $id_prof, $td, $tp, $exam]);
            }
        }
        $message = "Les notes ont été enregistrées et mises à jour avec succès !";
    }

    // 5. Récupérer les étudiants de la section et leurs notes
    if (!empty($selected_section) && !empty($selected_module)) {
        $stmt_etus = $pdo->prepare("
            SELECT e.id_Etu, u.nom, u.prenom, e.groupe, 
                   n.TD, n.TP, n.Exam
            FROM etudiant e
            JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
            LEFT JOIN note n ON e.id_Etu = n.id_Etud AND n.id_Mod = ?
            WHERE e.id_Sec = ?
            ORDER BY u.nom ASC
        ");
        $stmt_etus->execute([$selected_module, $selected_section]);
        $etudiants = $stmt_etus->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Notes</title>
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
        .content-body { padding: 30px; max-width: 1300px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 22px; font-weight: 700; margin-bottom: 20px; }

        .form-inline { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 250px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE; }

        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; height: 42px; }
        .btn-submit:hover { background-color: var(--menu); }
        .btn-save { background-color: var(--accent); color: var(--on-accent); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }

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

        table.notes-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 12px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; text-align: center; }
        td { text-align: center; }
        td:nth-child(1) { text-align: left; }

        input[type="number"] { width: 85px; padding: 8px; border: 1px solid var(--border); border-radius: 6px; text-align: center; font-size: 14px; background-color: #fafafa; }
        input[type="number"]:focus { outline: none; border-color: var(--header); background-color: #fff; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <!-- Sidebar Enseignant -->
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus | Prof</span></div>
            <div class="menu">
                <a href="prof_dashboard.php" class="menu-item"><span>📅</span> Consulter Emploi du temps</a>
                <a href="prof_gestion_notes.php" class="menu-item active"><span>📊</span> Gestion des Notes</a>
                <a href="prof_horaires_consultation.php" class="menu-item"><span>🕒</span> Horaire de Consultation</a>
                <a href="prof_ressources_pedagogiques.php" class="menu-item"><span>📁</span> Gestion des Ressources</a>
                <a href="prof_annonces.php" class="menu-item"><span>📢</span> Annonces</a>
                <a href="prof_presence.php" class="menu-item "><span>📋</span> Liste de Présence</a>
                <a href="prof_planning_examens.php" class="menu-item"><span>📋</span> Planning des Examens</a>
            </div>
        </div>

    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Enseignant - Saisie et Modification des Notes</h2>
            
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

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- 1. Sélection de la Section et du Module -->
            <div class="card">
                <h3 class="page-title">Sélectionner la Section et le Module</h3>
                <form action="prof_gestion_notes.php" method="GET" class="form-inline">
                    <div class="form-group">
                        <label for="id_section">Section attribuée :</label>
                        <select name="id_section" id="id_section" onchange="this.form.submit()" required>
                            <option value="">-- Choisir une section --</option>
                            <?php foreach ($sections_uniques as $id_sec => $sec): ?>
                                <option value="<?php echo $id_sec; ?>" <?php echo ($selected_section == $id_sec) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sec['nom_sec'] . ' (' . $sec['specialite'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!empty($selected_section)): ?>
                    <div class="form-group">
                        <label for="id_mod">Module :</label>
                        <select name="id_mod" id="id_mod" required>
                            <option value="">-- Choisir un module --</option>
                            <?php foreach ($modules_section as $id_m => $nom_m): ?>
                                <option value="<?php echo $id_m; ?>" <?php echo ($selected_module == $id_m) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($nom_m); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Afficher les étudiants</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- 2. Saisie et Calcul de la Moyenne -->
            <?php if (!empty($selected_section) && !empty($selected_module)): ?>
            <div class="card">
                <form action="prof_gestion_notes.php?id_section=<?php echo urlencode($selected_section); ?>&id_mod=<?php echo urlencode($selected_module); ?>" method="POST">
                    <input type="hidden" name="id_section" value="<?php echo htmlspecialchars($selected_section); ?>">
                    <input type="hidden" name="id_mod" value="<?php echo htmlspecialchars($selected_module); ?>">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 class="page-title" style="margin:0;">Notes des étudiants </h3>
                        <button type="submit" name="enregistrer_notes" class="btn-save">💾 Enregistrer les modifications</button>
                    </div>

                    <table class="notes-table">
                        <thead>
                            <tr>
                                <th>Nom & Prénom</th>
                                <th>Groupe</th>
                                <th>Note TD</th>
                                <th>Note TP</th>
                                <th>Note Examen</th>
                                <th>Moyenne Module</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($etudiants)): ?>
                                <?php foreach ($etudiants as $etu): ?>
                                    <?php
                                        $td_val = $etu['TD'];
                                        $tp_val = $etu['TP'];
                                        $exam_val = $etu['Exam'];
                                        
                                        $moyenne = null;

                                        $has_td = ($td_val !== null && $td_val !== '');
                                        $has_tp = ($tp_val !== null && $tp_val !== '');
                                        $has_exam = ($exam_val !== null && $exam_val !== '');

                                        if ($has_td && $has_tp) {
                                            $exam_note = $has_exam ? floatval($exam_val) : 0;
                                            $moyenne = (floatval($tp_val) * 0.2) + (floatval($td_val) * 0.2) + ($exam_note * 0.6);
                                        } elseif ($has_td && !$has_tp) {
                                            $exam_note = $has_exam ? floatval($exam_val) : 0;
                                            $moyenne = (floatval($td_val) * 0.4) + ($exam_note * 0.6);
                                        } elseif (!$has_td && $has_tp) {
                                            $exam_note = $has_exam ? floatval($exam_val) : 0;
                                            $moyenne = (floatval($tp_val) * 0.4) + ($exam_note * 0.6);
                                        } elseif (!$has_td && !$has_tp && $has_exam) {
                                            $moyenne = floatval($exam_val);
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($etu['nom'] . ' ' . $etu['prenom']); ?></td>
                                        <td>Gr. <?php echo htmlspecialchars($etu['groupe']); ?></td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="20" 
                                                   name="notes[<?php echo $etu['id_Etu']; ?>][td]" 
                                                   value="<?php echo ($td_val !== null) ? htmlspecialchars($td_val) : ''; ?>" 
                                                   placeholder="/20">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="20" 
                                                   name="notes[<?php echo $etu['id_Etu']; ?>][tp]" 
                                                   value="<?php echo ($tp_val !== null) ? htmlspecialchars($tp_val) : ''; ?>" 
                                                   placeholder="/20">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="20" 
                                                   name="notes[<?php echo $etu['id_Etu']; ?>][exam]" 
                                                   value="<?php echo ($exam_val !== null) ? htmlspecialchars($exam_val) : ''; ?>" 
                                                   placeholder="/20">
                                        </td>
                                        <td>
                                            <strong><?php echo ($moyenne !== null && ($has_td || $has_tp || $has_exam)) ? number_format($moyenne, 2) : '-'; ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #777; padding: 20px;">Aucun étudiant trouvé dans cette section.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
            <?php endif; ?>

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