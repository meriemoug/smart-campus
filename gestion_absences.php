<?php
session_start();

// Vérification du rôle Scolarité / Admin
if (!isset($_SESSION['id_user']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['scolarite', 'admin', 'agent'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$nom_agent = $_SESSION['nom'] ?? 'Scolarité';
$prenom_agent = $_SESSION['prenom'] ?? '';

$sections_uniques = [];
$etudiants_exclus = [];
$toutes_absences = [];
$selected_sec = $_GET['id_sec'] ?? '';
$tab = $_GET['tab'] ?? 'exclus'; // Onglet par défaut : Étudiants exclus
$nom_section_courante = '';
$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Récupérer toutes les sections pour les filtres
    $stmt_sec = $pdo->query("
        SELECT sec.id_Sec, sec.nom_sec, sp.NomSp, sp.annee 
        FROM section sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        ORDER BY sec.nom_sec ASC
    ");
    $sections_uniques = $stmt_sec->fetchAll(PDO::FETCH_ASSOC);

    // 2. Traitement du changement de statut d'une justification par la scolarité
    if (isset($_POST['modifier_justification'])) {
        $id_ligne = $_POST['id_ligne'];
        $nouveau_statut = $_POST['justification'];

        $stmt_upd = $pdo->prepare("UPDATE ligne_appel SET justification = ? WHERE id_liste = ? AND id_Etu = ?");
        // Note: id_ligne est un tableau ou combiné, adaptons selon la structure (id_liste et id_Etu transmis en inputs cachés)
    }

    if (isset($_POST['update_justif'])) {
        $id_liste = $_POST['id_liste'];
        $id_Etu = $_POST['id_Etu'];
        $nouveau_statut = $_POST['justification'];

        $stmt_upd = $pdo->prepare("UPDATE ligne_appel SET justification = ? WHERE id_liste = ? AND id_Etu = ?");
        $stmt_upd->execute([$nouveau_statut, $id_liste, $id_Etu]);
        $message = "Le statut de justification a été mis à jour avec succès.";
        $tab = 'suivi';
        $selected_sec = $_POST['current_sec'] ?? '';
    }

    // 3. Si une section est sélectionnée, récupérer les données
    if (!empty($selected_sec)) {
        foreach ($sections_uniques as $s) {
            if ($s['id_Sec'] == $selected_sec) {
                $nom_section_courante = $s['nom_sec'] . ' (' . $s['NomSp'] . ' - ' . $s['annee'] . ')';
                break;
            }
        }

        // A. Requête pour les étudiants exclus (>=3 non justifiées OU >=5 total)
        $stmt_exclus = $pdo->prepare("
            SELECT et.id_Etu, et.id_Etu, u.nom, u.prenom, et.groupe,
                   SUM(CASE WHEN l.justification = 'non_justifiee' THEN 1 ELSE 0 END) AS total_non_justifiees,
                   COUNT(l.id_Etu) AS total_absences
            FROM etudiant et
            JOIN user u ON et.id_user = u.id_user
            JOIN ligne_appel l ON et.id_Etu = l.id_Etu
            WHERE et.id_Sec = ? AND l.presence = 'absent'
            GROUP BY et.id_Etu, et.id_Etu, u.nom, u.prenom, et.groupe
            HAVING total_non_justifiees >= 3 OR total_absences >= 5
            ORDER BY total_non_justifiees DESC, total_absences DESC, u.nom ASC
        ");
        $stmt_exclus->execute([$selected_sec]);
        $etudiants_exclus = $stmt_exclus->fetchAll(PDO::FETCH_ASSOC);

        // B. Requête pour le suivi global des absences de la section
        $stmt_suivi = $pdo->prepare("
            SELECT la.id_liste, la.date_appel, m.nom_mod, s.type_seance, s.id_groupe,
                   et.id_Etu, et.id_Etu, u.nom, u.prenom, l.presence, l.justification
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }

        /* Sidebar personnalisée */
        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; overflow-y: auto; }
        .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { color: var(--on-blue); padding: 12px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }

        .menu-dropdown { display: flex; flex-direction: column; }
        .submenu { display: flex; flex-direction: column; padding-left: 15px; margin-top: 5px; gap: 5px; border-left: 2px solid rgba(255, 255, 255, 0.2); margin-left: 10px; }
        .submenu-item { color: var(--on-blue); padding: 10px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 400; display: flex; align-items: center; gap: 10px; transition: 0.2s; opacity: 0.85; }
        .submenu-item:hover, .submenu-item.active { background-color: var(--accent); color: var(--on-accent); opacity: 1; font-weight: 600; }

        .user-info { color: var(--on-blue); font-size: 13px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; }
        .btn-logout { color: var(--accent); text-decoration: none; font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .content-body { padding: 30px; max-width: 1200px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 20px; font-weight: 700; margin-bottom: 20px; }

        .tabs-nav { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
        .tab-btn { background: none; border: none; padding: 10px 20px; font-size: 15px; font-weight: 600; cursor: pointer; color: var(--text); border-radius: 6px; transition: 0.2s; text-decoration: none; }
        .tab-btn.active { background-color: var(--header); color: var(--on-blue); }

        .form-group { display: flex; flex-direction: column; gap: 6px; max-width: 400px; margin-bottom: 20px; }
        label { font-size: 13px; font-weight: 600; color: var(--text); }
        select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE; }
        select:focus { outline: none; border-color: var(--header); background: #fff; }

        table.data-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 14px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; text-align: center; }
        td { text-align: center; }
        td:nth-child(2), td:nth-child(3) { text-align: left; }

        .badge-danger { background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .badge-count { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .badge-justifiee { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-non-justifiee { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

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
    </style>
</head>
<body>

    <!-- Sidebar Scolarité / Admin -->
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
                <a href="gestion_etudiants.php" class="menu-item ">🎓 Étudiants</a>
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
                        <a href="gestion_absences.php" class="submenu-item active">🚫 Absences</a>
                        <a href="gestion_annonces.php" class="submenu-item">📢 Annonces</a>
                    </div>
                </div> 
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Gestion des Absences & Exclusions</h2>
            
            <div class="user-menu-container" onclick="toggleUserMenu(event)">
                <div class="user-profile">
                    <span><strong><?php echo htmlspecialchars($prenom_agent . ' ' . $nom_agent); ?></strong></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($prenom_agent, 0, 1)); ?></div>
                </div>
                <div id="userDropdown" class="user-dropdown">
                    <div class="dropdown-header">
                        Connecté en tant que<br>
                        <span style="color: var(--menu); font-weight: 600;"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Agent'); ?></span>
                    </div>
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

            <div class="card">
                <h3 class="page-title">Filtrer par Section</h3>
                
                <!-- Formulaire de sélection de la section -->
                <form method="GET" action="gestion_absences.php">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                    <div class="form-group">
                        <label for="id_sec">Sélectionner une section :</label>
                        <select name="id_sec" id="id_sec" onchange="this.form.submit()" required>
                            <option value="">-- Choisir une section --</option>
                            <?php foreach ($sections_uniques as $sec): ?>
                                <option value="<?php echo $sec['id_Sec']; ?>" <?php echo ($selected_sec == $sec['id_Sec']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sec['nom_sec'] . ' (' . $sec['NomSp'] . ' - ' . $sec['annee'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if (!empty($selected_sec)): ?>
                    <!-- Navigation par onglets -->
                    <div class="tabs-nav" style="margin-top: 25px;">
                        <a href="gestion_absences.php?id_sec=<?php echo $selected_sec; ?>&tab=exclus" class="tab-btn <?php echo ($tab == 'exclus') ? 'active' : ''; ?>">🚨 Étudiants Exclus</a>
                        <a href="gestion_absences.php?id_sec=<?php echo $selected_sec; ?>&tab=suivi" class="tab-btn <?php echo ($tab == 'suivi') ? 'active' : ''; ?>">📋 Suivi & Justification des Absences</a>
                    </div>

                    <h4 style="margin: 20px 0 15px 0; color: var(--header);">Section active : <?php echo htmlspecialchars($nom_section_courante); ?></h4>
                    
                    <!-- ONGLET 1 : ETUDIANTS EXCLUS -->
                    <?php if ($tab == 'exclus'): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>N° Étudiant</th>
                                    <th>Nom & Prénom</th>
                                    <th>Groupe</th>
                                    <th>Absences Non Justifiées</th>
                                    <th>Total Absences</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($etudiants_exclus)): ?>
                                    <?php foreach ($etudiants_exclus as $etud): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($etud['num_etudiant'] ?? 'N/A'); ?></td>
                                            <td><strong><?php echo htmlspecialchars($etud['nom'] . ' ' . $etud['prenom']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($etud['id_groupe']); ?></td>
                                            <td><span class="badge-count"><?php echo $etud['total_non_justifiee']; ?></span></td>
                                            <td><span class="badge-count"><?php echo $etud['total_absences']; ?></span></td>
                                            <td><span class="badge-danger">🚨 Exclu(e)</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #777; padding: 25px;">Aucun étudiant exclu dans cette section pour le moment (critère : $\ge 3$ non justifiées ou $\ge 5$ total).</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <!-- ONGLET 2 : SUIVI ET JUSTIFICATION DES ABSENCES -->
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
                                            <td>📅 <?php echo date('d/m/Y', strtotime($abs['date_appel'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($abs['nom_mod']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($abs['type_seance'] . ' - G' . $abs['id_groupe']); ?></td>
                                            <td><?php echo htmlspecialchars($abs['nom'] . ' ' . $abs['prenom']); ?></td>
                                            <td>
                                                <?php if ($abs['justification'] == 'justifiee'): ?>
                                                    <span class="badge-justifiee">Justifiée</span>
                                                <?php else: ?>
                                                    <span class="badge-non-justifiee">Non justifiée</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="gestion_absences.php" style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                    <input type="hidden" name="id_liste" value="<?php echo $abs['id_liste']; ?>">
                                                    <input type="hidden" name="id_Etu" value="<?php echo $abs['id_Etu']; ?>">
                                                    <input type="hidden" name="current_sec" value="<?php echo $selected_sec; ?>">
                                                    
                                                    <select name="justification" style="padding: 6px; font-size: 13px; width: auto;">
                                                        <option value="non_justifiee" <?php echo ($abs['justification'] == 'non_justifiee') ? 'selected' : ''; ?>>Non justifiée</option>
                                                        <option value="justifiee" <?php echo ($abs['justification'] == 'justifiee') ? 'selected' : ''; ?>>Justifiée</option>
                                                    </select>
                                                    
                                                    <button type="submit" name="update_justif" style="background: var(--header); color: white; border: none; padding: 6px 12px; border-radius: 4px; font-weight: 600; cursor: pointer;">Modifier</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #777; padding: 25px;">Aucune absence enregistrée pour cette section.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <p style="color: #777; text-align: center; margin-top: 20px;">Veuillez sélectionner une section ci-dessus pour afficher les données d'absences.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        function toggleSubmenu() {
            const submenu = document.getElementById('submenuNotes');
            const arrow = document.getElementById('arrow');
            
            if (submenu.style.display === 'none' || submenu.style.display === '') {
                submenu.style.display = 'flex';
                arrow.textContent = '▴';
            } else {
                submenu.style.display = 'none';
                arrow.textContent = '▾';
            }
        }

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