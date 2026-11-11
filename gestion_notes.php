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

$message = '';
$error = '';
$etudiants = [];
$selected_specialite = $_GET['id_sp'] ?? $_POST['id_sp'] ?? '';
$selected_section = $_GET['id_section'] ?? $_POST['id_section'] ?? '';
$nom_section = '';
$nom_specialite = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Récupérer toutes les spécialités pour le premier sélecteur
    $stmt_specs = $pdo->query("SELECT id_sp, NomSp, annee FROM specialite ORDER BY NomSp ASC");
    $specialites = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

    // 2. Récupérer les sections en fonction de la spécialité sélectionnée (pour alimenter le second sélecteur)
    $sections = [];
    if (!empty($selected_specialite)) {
        $stmt_secs = $pdo->prepare("SELECT id_Sec, nom_sec FROM section WHERE id_sp = ? ORDER BY nom_sec ASC");
        $stmt_secs->execute([$selected_specialite]);
        $sections = $stmt_secs->fetchAll(PDO::FETCH_ASSOC);
    }

    // Si une section est sélectionnée, récupérer les infos de la section et de la spécialité pour l'affichage
    if (!empty($selected_section)) {
        $stmt_sec_info = $pdo->prepare("
            SELECT s.nom_sec, sp.NomSp, sp.annee 
            FROM section s 
            LEFT JOIN specialite sp ON s.id_sp = sp.id_sp 
            WHERE s.id_Sec = ?
        ");
        $stmt_sec_info->execute([$selected_section]);
        $sec_info = $stmt_sec_info->fetch(PDO::FETCH_ASSOC);
        
        if ($sec_info) {
            $nom_section = $sec_info['nom_sec'];
            $nom_specialite = $sec_info['NomSp'] . ' (' . $sec_info['annee'] . ')';
        }
    }

    // 3. Traitement du calcul & attribution de la décision commune pour la SECTION sélectionnée
    if (isset($_POST['calculer_section']) && !empty($selected_section)) {
        
        $stmt_etus_sec = $pdo->prepare("SELECT id_user FROM etudiant WHERE id_Sec = ?");
        $stmt_etus_sec->execute([$selected_section]);
        $etudiants_section = $stmt_etus_sec->fetchAll(PDO::FETCH_ASSOC);

        foreach ($etudiants_section as $etu) {
            $id_user = $etu['id_user'];

            // --- Calcul S1 ---
            $stmt_notes_s1 = $pdo->prepare("
                SELECT n.valeur_note, en.coefficient 
                FROM note n 
                JOIN enseignement en ON n.id_enseignement = en.id_enseignement 
                WHERE n.id_user = ? AND n.semestre = 'S1'
            ");
            $stmt_notes_s1->execute([$id_user]);
            $notes_s1 = $stmt_notes_s1->fetchAll(PDO::FETCH_ASSOC);

            $p_s1 = 0; $c_s1 = 0;
            foreach ($notes_s1 as $n) { $p_s1 += ($n['valeur_note'] * $n['coefficient']); $c_s1 += $n['coefficient']; }
            $moyenne_s1 = ($c_s1 > 0) ? ($p_s1 / $c_s1) : null;

            // --- Calcul S2 ---
            $stmt_notes_s2 = $pdo->prepare("
                SELECT n.valeur_note, en.coefficient 
                FROM note n 
                JOIN enseignement en ON n.id_enseignement = en.id_enseignement 
                WHERE n.id_user = ? AND n.semestre = 'S2'
            ");
            $stmt_notes_s2->execute([$id_user]);
            $notes_s2 = $stmt_notes_s2->fetchAll(PDO::FETCH_ASSOC);

            $p_s2 = 0; $c_s2 = 0;
            foreach ($notes_s2 as $n) { $p_s2 += ($n['valeur_note'] * $n['coefficient']); $c_s2 += $n['coefficient']; }
            $moyenne_s2 = ($c_s2 > 0) ? ($p_s2 / $c_s2) : null;

            // --- Moyenne Finale & Décision ---
            $moyenne_finale = null;
            $decision = 'En cours';

            if ($moyenne_s1 !== null && $moyenne_s2 !== null) {
                $moyenne_finale = ($moyenne_s1 + $moyenne_s2) / 2;
                $decision = ($moyenne_finale >= 10) ? 'Admis' : 'Ajourné';
            } elseif ($moyenne_s1 !== null) {
                $moyenne_finale = $moyenne_s1;
            }

            // --- Enregistrement dans resultat_etudiant ---
            $stmt_check = $pdo->prepare("SELECT id_resultat FROM resultat_etudiant WHERE id_user = ?");
            $stmt_check->execute([$id_user]);

            if ($stmt_check->rowCount() > 0) {
                $stmt_up = $pdo->prepare("UPDATE resultat_etudiant SET moyenne_s1 = ?, moyenne_s2 = ?, moyenne_finale = ?, decision = ? WHERE id_user = ?");
                $stmt_up->execute([$moyenne_s1, $moyenne_s2, $moyenne_finale, $decision, $id_user]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO resultat_etudiant (id_user, moyenne_s1, moyenne_s2, moyenne_finale, decision) VALUES (?, ?, ?, ?, ?)");
                $stmt_ins->execute([$id_user, $moyenne_s1, $moyenne_s2, $moyenne_finale, $decision]);
            }
        }
        $message = "Les moyennes et décisions de la section ont été validées et mises à jour !";
    }

    // 4. Récupérer les étudiants et résultats de la section sélectionnée
    if (!empty($selected_section)) {
        $stmt_resultats = $pdo->prepare("
            SELECT e.id_user, u.nom, u.prenom, e.annee, e.groupe, r.moyenne_s1, r.moyenne_s2, r.moyenne_finale, r.decision
            FROM etudiant e
            JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
            LEFT JOIN resultat_etudiant r ON e.id_user = r.id_user
            WHERE e.id_Sec = ?
            ORDER BY u.nom ASC
        ");
        $stmt_resultats->execute([$selected_section]);
        $etudiants = $stmt_resultats->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Notes & Moyennes par Spécialité et Section</title>
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
            --success-bg: #d4edda;
            --success-text: #155724;
            --danger-bg: #f8d7da;
            --danger-text: #721c24;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; }
        .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { color: var(--on-blue); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .content-body { padding: 30px; max-width: 1100px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .section-title { color: var(--header); font-size: 20px; margin-bottom: 15px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }

        .form-inline { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 250px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE; }
        
        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; height: 42px; }
        .btn-submit:hover { background-color: var(--menu); }

        .btn-calcul { background-color: var(--accent); color: var(--on-accent); border: none; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 12px 15px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; }

        .badge-admis { background-color: var(--success-bg); color: var(--success-text); padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-ajourne { background-color: var(--danger-bg); color: var(--danger-text); padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-cours { background-color: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }

        .alert-success { background-color: var(--success-bg); color: var(--success-text); padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: var(--danger-bg); color: var(--danger-text); padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        .menu-dropdown { position: relative; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .submenu { display: none; flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px; margin-bottom: 4px; border-left: 2px solid rgba(255, 255, 255, 0.2); }
        .submenu-item { color: rgba(255, 255, 255, 0.85); padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .submenu-item:hover, .submenu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }
        
        .section-info-badge { font-size: 14px; font-weight: normal; color: #51606A; background: #f1f5f9; padding: 4px 10px; border-radius: 4px; }

        .user-menu-container { position: relative; display: inline-block; cursor: pointer; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; background-color: var(--accent); color: var(--on-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
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
        .btn-logout { background-color: var(--error); color: var(--on-error); padding: 10px; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; }

        .user-dropdown.active { display: block; }
        .dropdown-header { font-weight: bold; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .dropdown-item { display: block; width: 100%; padding: 8px 10px; text-align: left; background: none; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; color: var(--text); margin-bottom: 5px; }
        .dropdown-item:hover { background-color: var(--bg); }
        .dropdown-item.logout { background-color: var(--error); color: var(--on-error); text-align: center; }
        .dropdown-item.logout:hover { opacity: 0.9; }



        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background-color: var(--menu); }
        .btn-edit { background-color: var(--menu); color: var(--on-blue); padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-delete { background-color: var(--error); color: var(--on-error); border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; }

    </style>
</head>
<body>

    <!-- Sidebar -->
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
                        <span id="arrow" style="font-size: 11px;">▾</span>
                    </a>
                    <div class="submenu" id="submenuNotes">
                        <a href="gestion_notes.php" class="submenu-item active">📊 Notes & Moyennes</a>
                        <a href="gestion_absences.php" class="submenu-item">🚫 Absences</a>
                        <a href="gestion_annonces.php" class="submenu-item">📢 Annonces</a>
                    </div>
                </div> 
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Notes et Moyennes</h2>
            <div class="user-menu-container" onclick="toggleUserMenu(event)">
                <div class="user-profile">
                    <span><strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1)); ?></div>
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

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- 1. Choix de la Spécialité puis de la Section -->
            <div class="card">
                <h3 class="section-title">Filtrer par Spécialité et Section</h3>
                <form action="gestion_notes.php" method="GET" class="form-inline">
                    
                    <!-- Sélecteur Spécialité -->
                    <div class="form-group">
                        <label for="id_sp">Spécialité & Année :</label>
                        <select name="id_sp" id="id_sp" onchange="this.form.submit()" required>
                            <option value="">-- Choisir une spécialité --</option>
                            <?php foreach ($specialites as $sp): ?>
                                <option value="<?php echo $sp['id_sp']; ?>" <?php echo ($selected_specialite == $sp['id_sp']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sp['NomSp'] . ' (' . $sp['annee'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sélecteur Section (filtré selon la spécialité choisie) -->
                    <div class="form-group">
                        <label for="id_section">Section académique :</label>
                        <select name="id_section" id="id_section" <?php echo empty($selected_specialite) ? 'disabled' : ''; ?> required>
                            <option value="">-- Choisir une section --</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo $sec['id_Sec']; ?>" <?php echo ($selected_section == $sec['id_Sec']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sec['nom_sec']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Afficher les étudiants</button>
                </form>
            </div>

            <!-- 2. Résultat & Calcul de la décision pour cette section -->
            <?php if (!empty($selected_section)): ?>
                <div class="card">
                    <div class="section-title">
                        <div>
                            <span>Section : <strong><?php echo htmlspecialchars($nom_section); ?></strong></span>
                            <span class="section-info-badge">Spécialité : <?php echo htmlspecialchars($nom_specialite); ?></span>
                        </div>
                        <form action="gestion_notes.php?id_sp=<?php echo urlencode($selected_specialite); ?>&id_section=<?php echo urlencode($selected_section); ?>" method="POST">
                            <input type="hidden" name="id_section" value="<?php echo htmlspecialchars($selected_section); ?>">
                            <button type="submit" name="calculer_section" class="btn-calcul">⚙️ Calculer & Délibérer la Section</button>
                        </form>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID Étudiant</th>
                                <th>Nom & Prénom</th>
                                <th>Groupe</th>
                                <th>Moy. S1</th>
                                <th>Moy. S2</th>
                                <th>Moyenne Finale</th>
                                <th>Décision Commune</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($etudiants)): ?>
                                <?php foreach ($etudiants as $res): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($res['id_user']); ?></td>
                                        <td><?php echo htmlspecialchars($res['nom'] . ' ' . $res['prenom']); ?></td>
                                        <td>Gr. <?php echo htmlspecialchars($res['groupe']); ?></td>
                                        <td><?php echo ($res['moyenne_s1'] !== null) ? number_format($res['moyenne_s1'], 2) : '-'; ?></td>
                                        <td><?php echo ($res['moyenne_s2'] !== null) ? number_format($res['moyenne_s2'], 2) : '-'; ?></td>
                                        <td><strong><?php echo ($res['moyenne_finale'] !== null) ? number_format($res['moyenne_finale'], 2) : '-'; ?></strong></td>
                                        <td>
                                            <?php if ($res['decision'] === 'Admis'): ?>
                                                <span class="badge-admis">Admis</span>
                                            <?php elseif ($res['decision'] === 'Ajourné'): ?>
                                                <span class="badge-ajourne">Ajourné</span>
                                            <?php else: ?>
                                                <span class="badge-cours">En cours</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #777;">Aucun étudiant trouvé dans cette section.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
      function toggleSubmenu() {
          var submenu = document.getElementById('submenuNotes');
          var arrow = document.getElementById('arrow');
          if (submenu.style.display === 'none' || submenu.style.display === '') {
              submenu.style.display = 'flex';
              arrow.textContent = '▴';
          } else {
              submenu.style.display = 'none';
              arrow.textContent = '▾';
          }
      }

      // Gestion du menu déroulant de l'avatar utilisateur
        function toggleUserMenu(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }

        window.onclick = function(event) {
            var dropdown = document.getElementById('userDropdown');
            if (dropdown && dropdown.classList.contains('active')) {
                dropdown.classList.remove('active');
            }
        }
    </script>
</body>
</html>