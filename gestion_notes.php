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
$annee_academique = $_GET['annee_academique'] ?? $_POST['annee_academique'] ?? '2026/2027';
$nom_section = '';
$nom_specialite = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Récupérer toutes les spécialités
    $stmt_specs = $pdo->query("SELECT id_sp, NomSp, annee FROM specialite ORDER BY NomSp ASC");
    $specialites = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer toutes les sections avec leurs spécialités pour l'orientation
    $stmt_all_secs = $pdo->query("
        SELECT sec.id_Sec, sec.nom_sec, sp.id_sp, sp.NomSp, sp.annee 
        FROM section sec 
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp 
        ORDER BY sec.nom_sec ASC
    ");
    $toutes_sections = $stmt_all_secs->fetchAll(PDO::FETCH_ASSOC);

    // 2. Récupérer les sections en fonction de la spécialité sélectionnée pour le filtre principal
    $sections = [];
    if (!empty($selected_specialite)) {
        $stmt_secs = $pdo->prepare("SELECT id_Sec, nom_sec FROM section WHERE id_sp = ? ORDER BY nom_sec ASC");
        $stmt_secs->execute([$selected_specialite]);
        $sections = $stmt_secs->fetchAll(PDO::FETCH_ASSOC);
    }

    // Infos de la section et spécialité courante
    if (!empty($selected_section)) {
        $stmt_sec_info = $pdo->prepare("
            SELECT s.nom_sec, sp.NomSp, sp.annee, sp.id_sp 
            FROM section s 
            LEFT JOIN specialite sp ON s.id_sp = sp.id_sp 
            WHERE s.id_Sec = ?
        ");
        $stmt_sec_info->execute([$selected_section]);
        $sec_info = $stmt_sec_info->fetch(PDO::FETCH_ASSOC);
        
        if ($sec_info) {
            $nom_section = $sec_info['nom_sec'];
            $nom_specialite = $sec_info['NomSp'] . ' (Niveau ' . $sec_info['annee'] . ')';
        }
    }

    // 3. Traitement de l'orientation collective des étudiants ADMIS (Contrainte stricte : niveau cible supérieur)
    if (isset($_POST['orienter_admis']) && !empty($selected_section)) {
        $nouvelle_section_cible = $_POST['nouvelle_section_cible'] ?? '';
        $annee_actuelle_debut = intval(substr($annee_academique, 0, 4));
        $nouvelle_annee_acad = ($annee_actuelle_debut + 1) . '/' . ($annee_actuelle_debut + 2);

        if (!empty($nouvelle_section_cible) && !empty($sec_info)) {
            // Récupérer les infos de la section cible
            $stmt_cible_info = $pdo->prepare("
                SELECT sec.id_Sec, sec.id_sp, sp.annee AS niveau_cible, sp.NomSp 
                FROM section sec 
                LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp 
                WHERE sec.id_Sec = ?
            ");
            $stmt_cible_info->execute([$nouvelle_section_cible]);
            $cible_info = $stmt_cible_info->fetch(PDO::FETCH_ASSOC);

            if ($cible_info) {
                $id_sp_cible = $cible_info['id_sp'];
                $niveau_actuel = intval($sec_info['annee'] ?? 1);
                $niveau_cible = intval($cible_info['niveau_cible'] ?? 1);

                // Vérification que le niveau cible est strictement supérieur au niveau actuel
                if ($niveau_cible > $niveau_actuel) {
                    // Récupérer UNIQUEMENT les étudiants dont la décision est STRICTEMENT 'Admis'
                    $stmt_admis = $pdo->prepare("
                        SELECT e.id_user 
                        FROM etudiant e
                        JOIN resultat_etudiant r ON e.id_user = r.id_user 
                             AND r.annee_academique = ? 
                             AND r.id_Sec = ?
                        WHERE e.id_Sec = ? 
                          AND r.decision = 'Admis'
                    ");
                    $stmt_admis->execute([$annee_academique, $selected_section, $selected_section]);
                    $admis_list = $stmt_admis->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($admis_list)) {
                        $pdo->beginTransaction();

                        foreach ($admis_list as $id_user) {
                            // A. Mettre à jour la section, la spécialité et l'année dans la table etudiant
                            $stmt_update_etu = $pdo->prepare("
                                UPDATE etudiant 
                                SET id_Sec = ?, id_sp = ?, annee = ? 
                                WHERE id_user = ?
                            ");
                            $stmt_update_etu->execute([$nouvelle_section_cible, $id_sp_cible, $niveau_cible, $id_user]);

                            // B. Créer une nouvelle ligne de résultat vierge pour la nouvelle année académique et la nouvelle section
                            $stmt_insert_res = $pdo->prepare("
                                INSERT INTO resultat_etudiant (id_user, annee_academique, id_sp, id_Sec, decision) 
                                VALUES (?, ?, ?, ?, 'En cours')
                            ");
                            $stmt_insert_res->execute([$id_user, $nouvelle_annee_acad, $id_sp_cible, $nouvelle_section_cible]);
                        }

                        $pdo->commit();
                        $message = "Les étudiants admis ont été orientés avec succès vers la nouvelle section pour l'année $nouvelle_annee_acad !";
                    } else {
                        $error = "Erreur : Aucun étudiant avec la décision 'Admis' n'a été trouvé pour cette année dans cette section.";
                    }
                } else {
                    $error = "Erreur d'orientation : Le niveau de la section de destination doit être strictement supérieur au niveau actuel.";
                }
            } else {
                $error = "Section de destination introuvable.";
            }
        } else {
            $error = "Veuillez sélectionner une section de destination valide.";
        }
    }

    // 4. Traitement du calcul & attribution des moyennes S1, S2, Finale et de la Décision
    if (isset($_POST['calculer_section']) && !empty($selected_section)) {
        
        $stmt_etus_sec = $pdo->prepare("SELECT id_Etu, id_user, id_sp FROM etudiant WHERE id_Sec = ?");
        $stmt_etus_sec->execute([$selected_section]);
        $etudiants_section = $stmt_etus_sec->fetchAll(PDO::FETCH_ASSOC);

        // Fonction de calcul de moyenne semestrielle filtrée par spécialité
        $calculerMoyenneSemestre = function($pdo, $id_etudiant, $id_sp_etudiant, $semestre_num) {
            $stmt_notes = $pdo->prepare("
                SELECT n.Exam, n.TD, n.TP, p.coefficient 
                FROM note n
                JOIN programme p ON n.id_Mod = p.id_mod
                WHERE n.id_Etud = ? AND p.semestre = ? AND p.id_sp = ?
            ");
            $stmt_notes->execute([$id_etudiant, $semestre_num, $id_sp_etudiant]);
            $notes_modules = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

            $points_totaux = 0;
            $coefficients_totaux = 0;

            foreach ($notes_modules as $mod) {
                $exam = $mod['Exam'];
                $td   = $mod['TD'];
                $tp   = $mod['TP'];
                $coef = floatval($mod['coefficient']);

                if ($exam === null && $td === null && $tp === null) {
                    continue; // Ignorer les modules sans notes
                }

                $exam_val = ($exam !== null) ? floatval($exam) : 0;
                $td_val   = ($td !== null) ? floatval($td) : null;
                $tp_val   = ($tp !== null) ? floatval($tp) : null;

                $moy_module = 0;
                if ($tp_val !== null && $td_val !== null) {
                    $moy_module = ($tp_val * 0.2) + ($td_val * 0.2) + ($exam_val * 0.6); 
                } elseif ($tp_val !== null) {
                    $moy_module = ($tp_val * 0.4) + ($exam_val * 0.6);
                } elseif ($td_val !== null) {
                    $moy_module = ($td_val * 0.4) + ($exam_val * 0.6);
                } else {
                    $moy_module = $exam_val;
                }

                $points_totaux += ($moy_module * $coef);
                $coefficients_totaux += $coef;
            }

            return ($coefficients_totaux > 0) ? ($points_totaux / $coefficients_totaux) : null;
        };

        foreach ($etudiants_section as $etu) {
            $id_etudiant = $etu['id_Etu'];
            $id_user = $etu['id_user'];
            $id_sp_etudiant = $etu['id_sp'];

            $moyenne_s1 = $calculerMoyenneSemestre($pdo, $id_etudiant, $id_sp_etudiant, 1);
            $moyenne_s2 = $calculerMoyenneSemestre($pdo, $id_etudiant, $id_sp_etudiant, 2);

            // Récupérer les rattrapages éventuels saisis pour cette année
            $stmt_rat = $pdo->prepare("SELECT rattrapage_s1, rattrapage_s2 FROM resultat_etudiant WHERE id_user = ? AND annee_academique = ? AND id_Sec = ?");
            $stmt_rat->execute([$id_user, $annee_academique, $selected_section]);
            $rat_data = $stmt_rat->fetch(PDO::FETCH_ASSOC);

            $rattrapage_s1 = $rat_data['rattrapage_s1'] ?? null;
            $rattrapage_s2 = $rat_data['rattrapage_s2'] ?? null;

            $eff_s1 = ($rattrapage_s1 !== null) ? $rattrapage_s1 : $moyenne_s1;
            $eff_s2 = ($rattrapage_s2 !== null) ? $rattrapage_s2 : $moyenne_s2;

            $moyenne_finale = null;
            $decision = 'En cours';

            if ($eff_s1 !== null && $eff_s2 !== null) {
                $moyenne_finale = ($eff_s1 + $eff_s2) / 2;
                $decision = ($moyenne_finale >= 10) ? 'Admis' : 'Ajourné';
            } elseif ($eff_s1 !== null) {
                $moyenne_finale = $eff_s1;
            } elseif ($eff_s2 !== null) {
                $moyenne_finale = $eff_s2;
            }

            // Insertion ou Mise à jour (UPSERT) dans resultat_etudiant pour l'année académique et la section courante
            $stmt_check = $pdo->prepare("SELECT id_resultat FROM resultat_etudiant WHERE id_user = ? AND annee_academique = ? AND id_Sec = ?");
            $stmt_check->execute([$id_user, $annee_academique, $selected_section]);

            if ($stmt_check->rowCount() > 0) {
                $stmt_up = $pdo->prepare("
                    UPDATE resultat_etudiant 
                    SET moyenne_s1 = ?, moyenne_s2 = ?, moyenne_finale = ?, decision = ?, id_sp = ? 
                    WHERE id_user = ? AND annee_academique = ? AND id_Sec = ?
                ");
                $stmt_up->execute([$moyenne_s1, $moyenne_s2, $moyenne_finale, $decision, $id_sp_etudiant, $id_user, $annee_academique, $selected_section]);
            } else {
                $stmt_ins = $pdo->prepare("
                    INSERT INTO resultat_etudiant (id_user, annee_academique, id_sp, id_Sec, moyenne_s1, moyenne_s2, moyenne_finale, decision) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$id_user, $annee_academique, $id_sp_etudiant, $selected_section, $moyenne_s1, $moyenne_s2, $moyenne_finale, $decision]);
            }

            // --- Archivage du détail des modules pour cette année académique ---
            $stmt_mods = $pdo->prepare("
                SELECT p.id_mod, p.semestre, p.coefficient, m.Nom_mod, n.TD, n.TP, n.Exam
                FROM programme p
                JOIN module m ON m.id_mod = p.id_mod
                LEFT JOIN note n ON n.id_Mod = p.id_mod AND n.id_Etud = ?
                WHERE p.id_sp = ?
            ");
            $stmt_mods->execute([$id_etudiant, $id_sp_etudiant]);

            $stmt_arch = $pdo->prepare("
                INSERT INTO resultat_module (id_user, annee_academique, id_mod, nom_mod, semestre, coefficient, td, tp, exam, moyenne)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE nom_mod = VALUES(nom_mod), semestre = VALUES(semestre),
                    coefficient = VALUES(coefficient), td = VALUES(td), tp = VALUES(tp),
                    exam = VALUES(exam), moyenne = VALUES(moyenne)
            ");

            foreach ($stmt_mods->fetchAll(PDO::FETCH_ASSOC) as $mod_arch) {
                $a_td = ($mod_arch['TD'] !== null)   ? (float)$mod_arch['TD']   : null;
                $a_tp = ($mod_arch['TP'] !== null)   ? (float)$mod_arch['TP']   : null;
                $a_ex = ($mod_arch['Exam'] !== null) ? (float)$mod_arch['Exam'] : null;
                $a_moy = null;
                if ($a_td !== null || $a_tp !== null || $a_ex !== null) {
                    $exv = ($a_ex !== null) ? $a_ex : 0;
                    if ($a_td !== null && $a_tp !== null) { $a_moy = $a_tp * 0.2 + $a_td * 0.2 + $exv * 0.6; }
                    elseif ($a_tp !== null)               { $a_moy = $a_tp * 0.4 + $exv * 0.6; }
                    elseif ($a_td !== null)               { $a_moy = $a_td * 0.4 + $exv * 0.6; }
                    else                                  { $a_moy = $exv; }
                }
                $stmt_arch->execute([$id_user, $annee_academique, $mod_arch['id_mod'], $mod_arch['Nom_mod'],
                                     $mod_arch['semestre'], $mod_arch['coefficient'], $a_td, $a_tp, $a_ex, $a_moy]);
            }
        }
        $message = "Les moyennes, la moyenne finale et les décisions ont été calculées et enregistrées avec succès !";
    }

    // 5. Récupérer les résultats pour l'affichage du tableau filtré par année académique et section exacte
    if (!empty($selected_section)) {
        $stmt_resultats = $pdo->prepare("
            SELECT e.id_user, u.nom, u.prenom, e.annee, e.groupe, 
                   r.moyenne_s1, r.rattrapage_s1, r.moyenne_s2, r.rattrapage_s2, r.moyenne_finale, r.decision
            FROM etudiant e
            JOIN user u ON e.id_user COLLATE utf8mb4_unicode_ci = u.id_user COLLATE utf8mb4_unicode_ci
            LEFT JOIN resultat_etudiant r ON e.id_user = r.id_user 
                 AND r.annee_academique = ? 
                 AND r.id_Sec = ?
            WHERE e.id_Sec = ?
            ORDER BY u.nom ASC
        ");
        $stmt_resultats->execute([$annee_academique, $selected_section, $selected_section]);
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
    <title>Smart Campus - Notes & Moyennes</title>
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
        .content-body { padding: 30px; max-width: 1350px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .section-title { color: var(--header); font-size: 20px; margin-bottom: 15px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }

        .form-inline { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 220px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE; }
        
        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; height: 42px; }
        .btn-submit:hover { background-color: var(--menu); }

        .btn-calcul { background-color: var(--accent); color: var(--on-accent); border: none; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-orient { background-color: var(--header); color: white; border: none; padding: 8px 15px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 12px 10px; text-align: left; font-size: 13px; border-bottom: 1px solid #eee; text-align: center; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; }
        td:nth-child(2) { text-align: left; }

        .badge-admis { background-color: var(--success-bg); color: var(--success-text); padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-ajourne { background-color: var(--danger-bg); color: var(--danger-text); padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-cours { background-color: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }

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
        .user-dropdown.active { display: block; }
        .dropdown-header { font-weight: bold; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .dropdown-item { display: block; width: 100%; padding: 8px 10px; text-align: left; background: none; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; color: var(--text); margin-bottom: 5px; }
        .dropdown-item:hover { background-color: var(--bg); }
        .dropdown-item.logout { background-color: #dc2626; color: white; text-align: center; }

        .alert-success { background-color: var(--success-bg); color: var(--success-text); padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: var(--danger-bg); color: var(--danger-text); padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus</span></div>
            <div class="menu">
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
                    <div class="submenu" id="submenuNotes" style="display: flex;">
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
            <h2>Espace Scolarité - Notes et Moyennes</h2>


                <?php
// Récupérer les notifications non lues et la liste des notifications pour l'utilisateur connecté
$id_user_connecte = $_SESSION['id_user'];
$stmt_notifs = $pdo->prepare("SELECT * FROM notification WHERE id_user = ? ORDER BY date_creation DESC LIMIT 15");
$stmt_notifs->execute([$id_user_connecte]);
$notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
$stmt_count->execute([$id_user_connecte]);
$nb_non_lus = $stmt_count->fetchColumn();
?>

<!-- Conteneur des notifications -->
<div class="notif-menu-container" onclick="toggleNotifMenu(event)" style="position: relative; cursor: pointer; margin-right: -160px;">
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

            <!-- Profil utilisateur cliquable avec menu déroulant -->
            <div class="user-menu-container" onclick="toggleUserMenu(event)">
                <div class="user-profile">
                    <span><strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1)); ?></div>
                </div>

                <!-- Dropdown affiché au clic -->
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

            <!-- 1. Formulaire de filtre -->
            <div class="card">
                <h3 class="section-title">Filtrer par Année Académique, Spécialité et Section</h3>
                <form action="gestion_notes.php" method="GET" class="form-inline">
                    
                    <div class="form-group">
                        <label for="annee_academique">Année Académique :</label>
                        <input type="text" name="annee_academique" id="annee_academique" value="<?php echo htmlspecialchars($annee_academique); ?>" placeholder="Ex: 2026/2027" required>
                    </div>

                    <div class="form-group">
                        <label for="id_sp">Spécialité & Année :</label>
                        <select name="id_sp" id="id_sp" onchange="this.form.submit()" required>
                            <option value="">-- Choisir une spécialité --</option>
                            <?php foreach ($specialites as $sp): ?>
                                <option value="<?php echo $sp['id_sp']; ?>" <?php echo ($selected_specialite == $sp['id_sp']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sp['NomSp'] . ' (Niveau ' . $sp['annee'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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

            <!-- 2. Tableau des résultats, délibération et orientation collective -->
            <?php if (!empty($selected_section)): ?>
                <div class="card">
                    <div class="section-title" style="margin-bottom: 20px;">
                        <div>
                            <span>Section : <strong><?php echo htmlspecialchars($nom_section); ?></strong> (Année : <?php echo htmlspecialchars($annee_academique); ?>)</span>
                            <span class="section-info-badge">Spécialité : <?php echo htmlspecialchars($nom_specialite); ?></span>
                        </div>
                        <form action="gestion_notes.php?id_sp=<?php echo urlencode($selected_specialite); ?>&id_section=<?php echo urlencode($selected_section); ?>&annee_academique=<?php echo urlencode($annee_academique); ?>" method="POST" style="display:inline;">
                            <input type="hidden" name="id_section" value="<?php echo htmlspecialchars($selected_section); ?>">
                            <input type="hidden" name="annee_academique" value="<?php echo htmlspecialchars($annee_academique); ?>">
                            <button type="submit" name="calculer_section" class="btn-calcul">⚙️ Calculer S1, S2 & Délibérer</button>
                        </form>
                    </div>

                    <!-- Bloc d'orientation collective pour les étudiants Admis -->
                    <div style="background: #fdf8e2; border: 1px solid var(--border); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="color: var(--header); margin-bottom: 10px; font-size: 15px;">🚀 Orientation collective des étudiants ADMIS (Vers l'année supérieure)</h4>
                        <form action="gestion_notes.php?id_sp=<?php echo urlencode($selected_specialite); ?>&id_section=<?php echo urlencode($selected_section); ?>&annee_academique=<?php echo urlencode($annee_academique); ?>" method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                            <input type="hidden" name="id_section" value="<?php echo htmlspecialchars($selected_section); ?>">
                            <div style="flex: 1; min-width: 320px;">
                                <label for="nouvelle_section_cible" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px;">Section de destination (Niveau supérieur requis) :</label>
                                <select name="nouvelle_section_cible" id="nouvelle_section_cible" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #fff;" required>
                                    <option value="">-- Choisir la section de destination --</option>
                                    <?php foreach ($toutes_sections as $sec): ?>
                                        <option value="<?php echo $sec['id_Sec']; ?>">
                                            <?php echo htmlspecialchars($sec['nom_sec'] . ' — ' . $sec['NomSp'] . ' (Niveau ' . $sec['annee'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="orienter_admis" class="btn-orient" onclick="return confirm('Confirmez-vous l\'orientation des étudiants admis vers cette nouvelle section ?');">Valider l'orientation</button>
                        </form>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom & Prénom</th>
                                <th>Gr.</th>
                                <th>Moy. S1</th>
                                <th>Ratt. S1</th>
                                <th>Moy. S2</th>
                                <th>Ratt. S2</th>
                                <th>Moyenne Finale</th>
                                <th>Décision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($etudiants)): ?>
                                <?php foreach ($etudiants as $res): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($res['id_user']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($res['nom'] . ' ' . $res['prenom']); ?></strong></td>
                                        <td>Gr. <?php echo htmlspecialchars($res['groupe'] ?? 'N/A'); ?></td>
                                        <td><?php echo ($res['moyenne_s1'] !== null) ? number_format($res['moyenne_s1'], 2) : '-'; ?></td>
                                        <td><?php echo ($res['rattrapage_s1'] !== null) ? number_format($res['rattrapage_s1'], 2) : '-'; ?></td>
                                        <td><?php echo ($res['moyenne_s2'] !== null) ? number_format($res['moyenne_s2'], 2) : '-'; ?></td>
                                        <td><?php echo ($res['rattrapage_s2'] !== null) ? number_format($res['rattrapage_s2'], 2) : '-'; ?></td>
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
                                    <td colspan="9" style="text-align: center; color: #777;">Aucun étudiant trouvé dans cette section pour l'année <?php echo htmlspecialchars($annee_academique); ?>.</td>
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

      function toggleNotifMenu(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('notifDropdown');
    dropdown.classList.toggle('active');
    // Fermer l'autre menu utilisateur si ouvert
    const userDropdown = document.getElementById('userDropdown');
    if(userDropdown) userDropdown.classList.remove('active');
}

// Mettre à jour l'événement global de clic pour fermer le menu notif
window.addEventListener('click', function() {
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown && notifDropdown.classList.contains('active')) {
        notifDropdown.classList.remove('active');
    }
});
    </script>
</body>
</html>