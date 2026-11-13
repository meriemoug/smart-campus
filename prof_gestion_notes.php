<?php
session_start();

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
$notifications = [];
$nb_non_lus = 0;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les notifications et le compteur non lus
    $stmt_notifs = $pdo->prepare("SELECT * FROM notification WHERE id_user = ? ORDER BY date_creation DESC LIMIT 15");
    $stmt_notifs->execute([$id_prof_session]);
    $notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
    $stmt_count->execute([$id_prof_session]);
    $nb_non_lus = $stmt_count->fetchColumn();

    $stmt_p = $pdo->prepare("SELECT id_prof FROM prof WHERE id_user = ?");
    $stmt_p->execute([$id_prof_session]);
    $prof_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
    $id_prof = $prof_data['id_prof'] ?? null;

    if (!$id_prof) {
        throw new Exception("Profil enseignant introuvable dans la base de données.");
    }

    $stmt_sections = $pdo->prepare("
        SELECT DISTINCT sec.id_Sec, sec.nom_sec, sp.NomSp, sp.annee
        FROM seance s
        JOIN section sec ON s.id_Sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        WHERE s.id_prof = ?
        ORDER BY sec.nom_sec ASC
    ");
    $stmt_sections->execute([$id_prof]);
    foreach ($stmt_sections->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sections_uniques[$row['id_Sec']] = [
            'nom_sec' => $row['nom_sec'],
            'specialite' => $row['NomSp'] . ' (' . $row['annee'] . ')'
        ];
    }

    if (!empty($selected_section)) {
        $stmt_modules = $pdo->prepare("
            SELECT DISTINCT m.id_mod, m.nom_mod
            FROM enseignement en
            JOIN module m ON en.id_mod = m.id_mod
            WHERE en.id_prof = ? AND en.id_Sec = ?
            ORDER BY m.nom_mod ASC
        ");
        $stmt_modules->execute([$id_prof, $selected_section]);
        foreach ($stmt_modules->fetchAll(PDO::FETCH_ASSOC) as $mod) {
            $modules_section[$mod['id_mod']] = $mod['nom_mod'];
        }
    }

    // Enregistrement des notes (bloqué uniquement si l'étudiant a exclu = 1 dans la base)
    if (isset($_POST['enregistrer_notes']) && !empty($selected_section) && !empty($selected_module)) {
        $notes_saisies = $_POST['notes'] ?? [];

        $stmt_chk_exclus = $pdo->prepare("
            SELECT e.id_Etu, n.exclu 
            FROM etudiant e
            LEFT JOIN note n ON e.id_Etu = n.id_Etud AND n.id_Mod = ?
            WHERE e.id_Sec = ?
        ");
        $stmt_chk_exclus->execute([$selected_module, $selected_section]);
        $statuts_exclus = [];
        foreach ($stmt_chk_exclus->fetchAll(PDO::FETCH_ASSOC) as $st) {
            $statuts_exclus[$st['id_Etu']] = (isset($st['exclu']) && intval($st['exclu']) === 1);
        }

        foreach ($notes_saisies as $id_etud => $vals) {
            // Si la scolarité a validé l'exclusion, on ignore les modifications du prof
            if (isset($statuts_exclus[$id_etud]) && $statuts_exclus[$id_etud] === true) {
                continue; 
            }

            $td = (isset($vals['td']) && $vals['td'] !== '') ? floatval($vals['td']) : null;
            $tp = (isset($vals['tp']) && $vals['tp'] !== '') ? floatval($vals['tp']) : null;
            $exam = (isset($vals['exam']) && $vals['exam'] !== '') ? floatval($vals['exam']) : null;

            $stmt_chk = $pdo->prepare("SELECT * FROM note WHERE id_Etud = ? AND id_Mod = ?");
            $stmt_chk->execute([$id_etud, $selected_module]);

            if ($stmt_chk->rowCount() > 0) {
                $stmt_up = $pdo->prepare("UPDATE note SET TD = ?, TP = ?, Exam = ?, id_prof = ? WHERE id_Etud = ? AND id_Mod = ?");
                $stmt_up->execute([$td, $tp, $exam, $id_prof, $id_etud, $selected_module]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO note (id_Etud, id_Mod, id_prof, TD, TP, Exam) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->execute([$id_etud, $selected_module, $id_prof, $td, $tp, $exam]);
            }
        }
        $message = "Les notes ont été enregistrées avec succès !";
    }

    if (!empty($selected_section) && !empty($selected_module)) {
        $stmt_etus = $pdo->prepare("
            SELECT e.id_Etu, u.nom, u.prenom, e.groupe, 
                   n.TD, n.TP, n.Exam, n.exclu,
                   (
                       SELECT COUNT(*) 
                       FROM ligne_appel l 
                       JOIN liste_appel la ON l.id_liste = la.id_liste
                       JOIN seance s ON la.id_Seance = s.id_Seance
                       WHERE l.id_Etu = e.id_Etu 
                         AND s.id_mod = ? 
                         AND l.presence = 'absent' 
                         AND l.justification = 'non_justifiee'
                   ) AS absences_non_justifiees
            FROM etudiant e
            JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
            LEFT JOIN note n ON e.id_Etu = n.id_Etud AND n.id_Mod = ?
            WHERE e.id_Sec = ?
            ORDER BY u.nom ASC
        ");
        $stmt_etus->execute([$selected_module, $selected_module, $selected_section]);
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
            --error: #A8162A;
            --on-error: #FFFFFF;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; box-shadow: 4px 0 10px rgba(0,0,0,0.05); }
        .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { background-color: transparent; color: var(--on-blue); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-body { padding: 30px; max-width: 1300px; margin: 0 auto; width: 100%; }
        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 22px; font-weight: 700; margin-bottom: 20px; }
        .form-inline { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 250px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE; }
        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; height: 42px; }
        .btn-save { background-color: var(--accent); color: var(--on-accent); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
        
        /* Styles du menu utilisateur et notifications (Style Admin Dashboard) */
        .user-menu-container, .notif-menu-container { position: relative; display: inline-block; cursor: pointer; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; background-color: var(--accent); color: var(--on-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 15px; }
        
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

        table.notes-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 12px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; text-align: center; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; }
        td:nth-child(1) { text-align: left; }
        input[type="number"] { width: 85px; padding: 8px; border: 1px solid var(--border); border-radius: 6px; text-align: center; font-size: 14px; background-color: #fafafa; }
        input[type="number"]:disabled { background-color: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        .badge-elimine { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; margin-left: 8px; display: inline-block; text-transform: uppercase; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus | Prof</span></div>
            <div class="menu">
                <a href="prof_dashboard.php" class="menu-item"><span>📅</span> Consulter Emploi du temps</a>
                <a href="prof_gestion_notes.php" class="menu-item active"><span>📊</span> Gestion des Notes</a>
                <a href="prof_horaires_consultation.php" class="menu-item"><span>🕒</span> Horaire de Consultation</a>
                <a href="prof_ressources_pedagogiques.php" class="menu-item"><span>📁</span> Gestion des Ressources</a>
                <a href="prof_annonces.php" class="menu-item"><span>📢</span> Annonces</a>
                <a href="prof_presence.php" class="menu-item"><span>📋</span> Liste de Présence</a>
                <a href="prof_planning_examens.php" class="menu-item"><span>📋</span> Planning des Examens</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Enseignant - Saisie et Modification des Notes</h2>

            <div style="display: flex; align-items: center; gap: 15px;">
                <!-- Conteneur des notifications -->
                <div class="notif-menu-container" onclick="toggleNotifMenu(event)">
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
            
                <!-- Profil utilisateur cliquable (Style Admin Dashboard) -->
                <div class="user-menu-container" onclick="toggleUserMenu(event)">
                    <div class="user-profile">
                        <span><strong><?php echo htmlspecialchars($prenom_prof . ' ' . $nom_prof); ?></strong></span>
                        <div class="user-avatar"><?php echo strtoupper(substr($prenom_prof, 0, 1)); ?></div>
                    </div>

                    <div id="userDropdown" class="user-dropdown">
                        <div class="dropdown-header">
                            Connecté en tant que<br>
                            <span style="color: var(--menu);"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Enseignant'); ?></span>
                        </div>
                        <a href="modifier_infos.php" class="dropdown-item">✏️ Modifier mes informations</a>
                        <a href="logout.php" class="dropdown-item logout">Se déconnecter</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-body">
            <?php if (!empty($message)): ?><div class="alert-success"><?= htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="card">
                <h3 class="page-title">Sélectionner la Section et le Module</h3>
                <form action="prof_gestion_notes.php" method="GET" class="form-inline">
                    <div class="form-group">
                        <label for="id_section">Section attribuée :</label>
                        <select name="id_section" id="id_section" onchange="this.form.submit()" required>
                            <option value="">-- Choisir une section --</option>
                            <?php foreach ($sections_uniques as $id_sec => $sec): ?>
                                <option value="<?= $id_sec; ?>" <?= ($selected_section == $id_sec) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($sec['nom_sec'] . ' (' . $sec['specialite'] . ')'); ?>
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
                                <option value="<?= $id_m; ?>" <?= ($selected_module == $id_m) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($nom_m); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Afficher les étudiants</button>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($selected_section) && !empty($selected_module)): ?>
            <div class="card">
                <form action="prof_gestion_notes.php?id_section=<?= urlencode($selected_section); ?>&id_mod=<?= urlencode($selected_module); ?>" method="POST">
                    <input type="hidden" name="id_section" value="<?= htmlspecialchars($selected_section); ?>">
                    <input type="hidden" name="id_mod" value="<?= htmlspecialchars($selected_module); ?>">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 class="page-title" style="margin:0;">Notes des étudiants</h3>
                        <button type="submit" name="enregistrer_notes" class="btn-save">💾 Enregistrer les modifications</button>
                    </div>

                    <p style="font-size: 13px; color: #777; margin-bottom: 15px;">
                        ℹ️ <em>Règle : Un étudiant est éliminé et verrouillé à 0 uniquement si la scolarité a validé son exclusion depuis son espace.</em>
                    </p>

                    <table class="notes-table">
                        <thead>
                            <tr>
                                <th>Nom & Prénom</th>
                                <th>Groupe</th>
                                <th>Absences Non Just.</th>
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
                                        $nb_abs_nj = intval($etu['absences_non_justifiees']);
                                        $est_exclu = (isset($etu['exclu']) && intval($etu['exclu']) === 1);

                                        if ($est_exclu) {
                                            $td_val = 0;
                                            $tp_val = 0;
                                            $exam_val = 0;
                                            $moyenne = 0.00;
                                        } else {
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
                                        }
                                    ?>
                                    <tr>
                                        <td style="text-align: left;">
                                            <strong><?= htmlspecialchars($etu['nom'] . ' ' . $etu['prenom']); ?></strong>
                                            <?php if ($est_exclu): ?>
                                                <span class="badge-elimine">🚨 Éliminé(e) du module</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Gr. <?= htmlspecialchars($etu['groupe']); ?></td>
                                        <td>
                                            <span style="font-weight: bold; color: <?= ($nb_abs_nj >= 3) ? '#991b1b' : 'inherit'; ?>;">
                                                <?= $nb_abs_nj; ?> abs.
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="20" 
                                                   name="notes[<?= $etu['id_Etu']; ?>][td]" 
                                                   value="<?= ($td_val !== null) ? htmlspecialchars($td_val) : ''; ?>" 
                                                   placeholder="/20"
                                                   <?= $est_exclu ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="20" 
                                                   name="notes[<?= $etu['id_Etu']; ?>][tp]" 
                                                   value="<?= ($tp_val !== null) ? htmlspecialchars($tp_val) : ''; ?>" 
                                                   placeholder="/20"
                                                   <?= $est_exclu ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="20" 
                                                   name="notes[<?= $etu['id_Etu']; ?>][exam]" 
                                                   value="<?= ($exam_val !== null) ? htmlspecialchars($exam_val) : ''; ?>" 
                                                   placeholder="/20"
                                                   <?= $est_exclu ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <strong><?= ($moyenne !== null || $est_exclu) ? number_format($moyenne, 2) : '-'; ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align: center; color: #777; padding: 20px;">Aucun étudiant trouvé dans cette section.</td></tr>
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
            var dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
            var notifDropdown = document.getElementById('notifDropdown');
            if(notifDropdown) notifDropdown.classList.remove('active');
        }

        function toggleNotifMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('notifDropdown');
            dropdown.classList.toggle('active');
            var userDropdown = document.getElementById('userDropdown');
            if(userDropdown) userDropdown.classList.remove('active');
        }

        window.onclick = function(event) {
            var userDropdown = document.getElementById('userDropdown');
            if (userDropdown && userDropdown.classList.contains('active')) {
                userDropdown.classList.remove('active');
            }
            var notifDropdown = document.getElementById('notifDropdown');
            if (notifDropdown && notifDropdown.classList.contains('active')) {
                notifDropdown.classList.remove('active');
            }
        }
    </script>
</body>
</html>