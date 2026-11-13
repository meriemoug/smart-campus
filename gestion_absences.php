<?php
session_start();

if (!isset($_SESSION['id_user']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['scolarite', 'admin', 'agent'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$sections_uniques = [];
$etudiants_exclus_modules = [];
$toutes_absences = [];
$selected_sec = $_GET['id_sec'] ?? '';
$tab = $_GET['tab'] ?? 'exclus_modules';
$nom_section_courante = '';
$message = '';
$error = '';

$SEUIL_EXCLUSION = 3;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Récupérer toutes les sections
    $stmt_sec = $pdo->query("
        SELECT sec.id_Sec, sec.nom_sec, sp.NomSp, sp.annee 
        FROM section sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        ORDER BY sec.nom_sec ASC
    ");
    $sections_uniques = $stmt_sec->fetchAll(PDO::FETCH_ASSOC);

    // 2. Traitement : Valider l'exclusion par la Scolarité (exclu = 1)
    if (isset($_POST['appliquer_exclusion_module'])) {
        $id_Etu = $_POST['id_Etu'];
        $id_Mod = $_POST['id_Mod'];
        $selected_sec = $_POST['current_sec'] ?? '';

        $stmt_chk_n = $pdo->prepare("SELECT * FROM note WHERE id_Etud = ? AND id_Mod = ?");
        $stmt_chk_n->execute([$id_Etu, $id_Mod]);

        if ($stmt_chk_n->rowCount() > 0) {
            $stmt_up_n = $pdo->prepare("UPDATE note SET TD = 0, TP = 0, Exam = 0, exclu = 1 WHERE id_Etud = ? AND id_Mod = ?");
            $stmt_up_n->execute([$id_Etu, $id_Mod]);
        } else {
            $stmt_ins_n = $pdo->prepare("INSERT INTO note (id_Etud, id_Mod, TD, TP, Exam, exclu) VALUES (?, ?, 0, 0, 0, 1)");
            $stmt_ins_n->execute([$id_Etu, $id_Mod]);
        }
        $message = "L'exclusion de l'étudiant pour ce module a été validée avec succès.";
        $tab = 'exclus_modules';
    }

    // 3. Traitement : Annuler l'exclusion par la Scolarité (exclu = 0)
    if (isset($_POST['annuler_exclusion_module'])) {
        $id_Etu = $_POST['id_Etu'];
        $id_Mod = $_POST['id_Mod'];
        $selected_sec = $_POST['current_sec'] ?? '';

        $stmt_up_n = $pdo->prepare("UPDATE note SET exclu = 0 WHERE id_Etud = ? AND id_Mod = ?");
        $stmt_up_n->execute([$id_Etu, $id_Mod]);
        $message = "L'exclusion de l'étudiant pour ce module a été annulée avec succès.";
        $tab = 'exclus_modules';
    }

    // 4. Mise à jour de justification
    if (isset($_POST['update_justif'])) {
        $id_liste = $_POST['id_liste'];
        $id_Etu = $_POST['id_Etu'];
        $nouveau_statut = $_POST['justification'];
        $selected_sec = $_POST['current_sec'] ?? '';

        $stmt_upd = $pdo->prepare("UPDATE ligne_appel SET justification = ? WHERE id_liste = ? AND id_Etu = ?");
        $stmt_upd->execute([$nouveau_statut, $id_liste, $id_Etu]);
        $message = "Le statut de justification a été mis à jour avec succès.";
        $tab = 'suivi';
    }

    // 5. Données de la section sélectionnée
    if (!empty($selected_sec)) {
        foreach ($sections_uniques as $s) {
            if ($s['id_Sec'] == $selected_sec) {
                $nom_section_courante = $s['nom_sec'] . ' (' . $s['NomSp'] . ' - ' . $s['annee'] . ')';
                break;
            }
        }

        // Étudiants atteignant le seuil d'absences par module
        $stmt_exclus = $pdo->prepare("
            SELECT e.id_Etu, e.groupe, u.nom, u.prenom, m.id_mod, m.nom_mod,
                   COUNT(l.id_Etu) AS abs_non_justifiees,
                   (SELECT COALESCE(n.exclu, 0) FROM note n WHERE n.id_Etud = e.id_Etu AND n.id_Mod = m.id_mod) AS deja_exclu
            FROM etudiant e
            JOIN user u ON e.id_user = u.id_user
            JOIN ligne_appel l ON e.id_Etu = l.id_Etu
            JOIN liste_appel la ON l.id_liste = la.id_liste
            JOIN seance s ON la.id_Seance = s.id_Seance
            JOIN module m ON s.id_mod = m.id_mod
            WHERE e.id_Sec = ? AND l.presence = 'absent' AND l.justification = 'non_justifiee'
            GROUP BY e.id_Etu, e.groupe, u.nom, u.prenom, m.id_mod, m.nom_mod
            HAVING abs_non_justifiees >= ?
            ORDER BY m.nom_mod ASC, u.nom ASC
        ");
        $stmt_exclus->execute([$selected_sec, $SEUIL_EXCLUSION]);
        $etudiants_exclus_modules = $stmt_exclus->fetchAll(PDO::FETCH_ASSOC);

        // Suivi global
        $stmt_suivi = $pdo->prepare("
            SELECT la.id_liste, la.date_appel, m.nom_mod, s.type_seance, s.id_groupe,
                   et.id_Etu, u.nom, u.prenom, l.presence, l.justification
            FROM ligne_appel l
            JOIN etudiant et ON l.id_Etu = et.id_Etu
            JOIN user u ON et.id_user = u.id_user
            JOIN liste_appel la ON l.id_liste = la.id_liste
            JOIN seance s ON la.id_Seance = s.id_Seance
            JOIN module m ON s.id_mod = m.id_mod
            WHERE et.id_Sec = ? AND l.presence = 'absent'
            ORDER BY la.date_appel DESC, u.nom ASC
        ");
        $stmt_suivi->execute([$selected_sec]);
        $toutes_absences = $stmt_suivi->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Smart Campus - Gestion des Absences & Exclusions</title>
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
        
        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; overflow-y: auto; box-shadow: 4px 0 10px rgba(0,0,0,0.05); }
        .logo-area { display: flex; align-items: center; gap: 12px; color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { background-color: transparent; color: var(--on-blue); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }
        
        .menu-dropdown { position: relative; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .submenu { display: flex; flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px; margin-bottom: 4px; border-left: 2px solid rgba(255, 255, 255, 0.2); margin-left: 10px; }
        .submenu-item { color: rgba(255, 255, 255, 0.85); padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 400; transition: background 0.2s, color 0.2s; display: flex; align-items: center; gap: 8px; }
        .submenu-item:hover, .submenu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
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

        .content-body { padding: 30px; max-width: 1200px; margin: 0 auto; width: 100%; }
        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 20px; font-weight: 700; margin-bottom: 20px; }
        .tabs-nav { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
        .tab-btn { background: none; border: none; padding: 10px 20px; font-size: 15px; font-weight: 600; cursor: pointer; color: var(--text); border-radius: 6px; text-decoration: none; }
        .tab-btn.active { background-color: var(--header); color: var(--on-blue); }
        .form-group { display: flex; flex-direction: column; gap: 6px; max-width: 400px; margin-bottom: 20px; }
        label { font-size: 13px; font-weight: 600; color: var(--text); }
        select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 14px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; text-align: center; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; }
        td:nth-child(2), td:nth-child(3) { text-align: left; }
        .badge-danger { background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; text-transform: uppercase; border: none; cursor: pointer; }
        .badge-count { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .badge-justifiee { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-non-justifiee { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .btn-action { background: var(--header); color: white; border: none; padding: 6px 12px; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus</span></div>
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
                    <a href="javascript:void(0);" class="menu-item dropdown-toggle" onclick="toggleSubmenu()">
                        <span>📢 Notes, Absences & Annonces</span> 
                        <span id="arrow" style="font-size: 11px;">▴</span>
                    </a>
                    <div class="submenu" id="submenuNotes" style="display: flex;">
                        <a href="gestion_notes.php" class="submenu-item">📊 Notes & Moyennes</a>
                        <a href="gestion_absences.php" class="submenu-item active">🚫 Absences</a>
                        <a href="gestion_annonces.php" class="submenu-item">📢 Annonces</a>
                    </div>
                </div> 
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Gestion des Absences & Exclusions par Module</h2>
            
            <div class="user-menu-container" onclick="toggleUserMenu(event)">
                <div class="user-profile">
                    <span><strong><?php echo htmlspecialchars(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? 'Scolarité')); ?></strong></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'] ?? 'S', 0, 1)); ?></div>
                </div>
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
            <?php if (!empty($message)): ?><div class="alert-success"><?= htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="card">
                <h3 class="page-title">Filtrer par Section</h3>
                <form method="GET" action="gestion_absences.php">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab); ?>">
                    <div class="form-group">
                        <label for="id_sec">Sélectionner une section :</label>
                        <select name="id_sec" id="id_sec" onchange="this.form.submit()" required>
                            <option value="">-- Choisir une section --</option>
                            <?php foreach ($sections_uniques as $sec): ?>
                                <option value="<?= $sec['id_Sec']; ?>" <?= ($selected_sec == $sec['id_Sec']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($sec['nom_sec'] . ' (' . $sec['NomSp'] . ' - ' . $sec['annee'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if (!empty($selected_sec)): ?>
                    <div class="tabs-nav" style="margin-top: 25px;">
                        <a href="gestion_absences.php?id_sec=<?= $selected_sec; ?>&tab=exclus_modules" class="tab-btn <?= ($tab == 'exclus_modules') ? 'active' : ''; ?>">🚨 Exclusions par Module (≥ <?= $SEUIL_EXCLUSION ?> abs.)</a>
                        <a href="gestion_absences.php?id_sec=<?= $selected_sec; ?>&tab=suivi" class="tab-btn <?= ($tab == 'suivi') ? 'active' : ''; ?>">📋 Suivi & Justification des Absences</a>
                    </div>

                    <h4 style="margin: 20px 0 15px 0; color: var(--header);">Section active : <?= htmlspecialchars($nom_section_courante); ?></h4>
                    
                    <?php if ($tab == 'exclus_modules'): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>N° Étudiant</th>
                                    <th>Nom & Prénom</th>
                                    <th>Groupe</th>
                                    <th>Module Concerné</th>
                                    <th>Absences Non Justifiées</th>
                                    <th>Statut / Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($etudiants_exclus_modules)): ?>
                                    <?php foreach ($etudiants_exclus_modules as $etud): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($etud['id_Etu']); ?></td>
                                            <td><strong><?= htmlspecialchars($etud['nom'] . ' ' . $etud['prenom']); ?></strong></td>
                                            <td>Gr. <?= htmlspecialchars($etud['groupe']); ?></td>
                                            <td><strong><?= htmlspecialchars($etud['nom_mod']); ?></strong></td>
                                            <td><span class="badge-count"><?= $etud['abs_non_justifiees']; ?> abs.</span></td>
                                            <td>
                                                <?php if ($etud['deja_exclu'] == 1): ?>
                                                    <span style="color: #dc2626; font-weight: bold; margin-right: 10px;">Éliminé</span>
                                                    <form method="POST" action="gestion_absences.php" style="display:inline-block; margin:0;" onsubmit="return confirm('Voulez-vous vraiment annuler l\'exclusion de cet étudiant pour le module <?= htmlspecialchars($etud['nom_mod']) ?> ?');">
                                                        <input type="hidden" name="id_Etu" value="<?= $etud['id_Etu']; ?>">
                                                        <input type="hidden" name="id_Mod" value="<?= $etud['id_mod']; ?>">
                                                        <input type="hidden" name="current_sec" value="<?= $selected_sec; ?>">
                                                        <button type="submit" name="annuler_exclusion_module" style="background: #fee2e2; color: #991b1b; border: 1px solid #f87171; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer;">Annuler l'exclusion</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="gestion_absences.php" style="margin:0;" onsubmit="return confirm('Valider l\'exclusion de cet étudiant du module <?= htmlspecialchars($etud['nom_mod']) ?> ? Sa moyenne sera fixée à 0.');">
                                                        <input type="hidden" name="id_Etu" value="<?= $etud['id_Etu']; ?>">
                                                        <input type="hidden" name="id_Mod" value="<?= $etud['id_mod']; ?>">
                                                        <input type="hidden" name="current_sec" value="<?= $selected_sec; ?>">
                                                        <button type="submit" name="appliquer_exclusion_module" class="badge-danger">Appliquer l'exclusion</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align: center; color: #777; padding: 25px;">Aucun étudiant n'a atteint le seuil d'exclusion dans les modules de cette section.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ($tab == 'suivi'): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Module</th>
                                    <th>Type & Groupe</th>
                                    <th>Étudiant</th>
                                    <th>Justification actuelle</th>
                                    <th>Action / Modifier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($toutes_absences)): ?>
                                    <?php foreach ($toutes_absences as $abs): ?>
                                        <tr>
                                            <td>📅 <?= date('d/m/Y', strtotime($abs['date_appel'])); ?></td>
                                            <td><strong><?= htmlspecialchars($abs['nom_mod']); ?></strong></td>
                                            <td><?= htmlspecialchars($abs['type_seance'] . ' - G' . $abs['id_groupe']); ?></td>
                                            <td><?= htmlspecialchars($abs['nom'] . ' ' . $abs['prenom']); ?></td>
                                            <td>
                                                <?php if ($abs['justification'] == 'justifiee'): ?>
                                                    <span class="badge-justifiee">Justifiée</span>
                                                <?php else: ?>
                                                    <span class="badge-non-justifiee">Non justifiée</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="gestion_absences.php" style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                    <input type="hidden" name="id_liste" value="<?= $abs['id_liste']; ?>">
                                                    <input type="hidden" name="id_Etu" value="<?= $abs['id_Etu']; ?>">
                                                    <input type="hidden" name="current_sec" value="<?= $selected_sec; ?>">
                                                    <select name="justification" style="padding: 6px; font-size: 13px; width: auto;">
                                                        <option value="non_justifiee" <?= ($abs['justification'] == 'non_justifiee') ? 'selected' : ''; ?>>Non justifiée</option>
                                                        <option value="justifiee" <?= ($abs['justification'] == 'justifiee') ? 'selected' : ''; ?>>Justifiée</option>
                                                    </select>
                                                    <button type="submit" name="update_justif" class="btn-action">Modifier</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align: center; color: #777; padding: 25px;">Aucune absence enregistrée pour cette section.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleUserMenu(event) { event.stopPropagation(); document.getElementById('userDropdown').classList.toggle('active'); }
        window.onclick = function () { var d = document.getElementById('userDropdown'); if (d && d.classList.contains('active')) d.classList.remove('active'); };
        function toggleSubmenu() {
            var s = document.getElementById('submenuNotes'), a = document.getElementById('arrow');
            var ouvert = s.style.display === 'flex';
            s.style.display = ouvert ? 'none' : 'flex';
            a.textContent = ouvert ? '▾' : '▴';
        }
    </script>
</body>
</html>