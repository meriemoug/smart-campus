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

$id_user_session = $_SESSION['id_user'];
$nom_prof = $_SESSION['nom'] ?? 'Enseignant';
$prenom_prof = $_SESSION['prenom'] ?? '';

$message = '';
$error = '';
$sections_uniques = [];

// Onglet actif : 'general' ou 'mes_annonces'
$tab = $_GET['tab'] ?? 'general';

// Mode édition
$edit_mode = false;
$edit_id = $_GET['edit'] ?? null;
$edit_annonce = ['titre' => '', 'det' => '', 'cible' => 'tous', 'id_Sec' => '', 'image' => ''];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les sections avec leur spécialité et année
    $stmt_sections = $pdo->query("
        SELECT sec.id_Sec, sec.nom_sec, sp.NomSp, sp.annee 
        FROM section sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        ORDER BY sec.nom_sec ASC
    ");
    $sections_uniques = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);

    // 1. Traitement de la Publication / Modification (avec Image)
    if (isset($_POST['publier_annonce'])) {
        $titre = trim($_POST['titre'] ?? '');
        $det = trim($_POST['det'] ?? '');
        $cible = $_POST['cible'] ?? 'tous';
        $id_Sec = !empty($_POST['id_Sec']) ? $_POST['id_Sec'] : null;
        $date_pub = date('Y-m-d');
        $id_modif = $_POST['id_modif'] ?? null;
        
        $image_path = null;

        // Gestion de l'upload de l'image
        if (isset($_FILES['image_annonce']) && $_FILES['image_annonce']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_annonce'];
            $file_name = 'ann_' . time() . '_' . basename($file['name']);
            $upload_dir = 'uploads/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $destination = $upload_dir . $file_name;
            $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_exts)) {
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $image_path = $destination;
                } else {
                    $error = "Erreur lors du transfert de l'image.";
                }
            } else {
                $error = "Format d'image non autorisé (Extensions acceptées : JPG, PNG, WEBP, GIF).";
            }
        }

        if (empty($titre) || empty($det)) {
            $error = "Veuillez remplir le titre et les détails de l'annonce.";
        } elseif (empty($error)) {
            if (!empty($id_modif)) {
                // Récupérer l'ancienne image si une nouvelle est uploadée pour nettoyer le serveur
                if ($image_path) {
                    $stmt_img = $pdo->prepare("SELECT image FROM annonce WHERE id_Anm = ? AND id_user = ?");
                    $stmt_img->execute([$id_modif, $id_user_session]);
                    $old_img = $stmt_img->fetchColumn();
                    if ($old_img && file_exists($old_img)) {
                        unlink($old_img);
                    }
                }

                // Modification (mise à jour avec ou sans nouvelle image)
                if ($image_path) {
                    $stmt_up = $pdo->prepare("
                        UPDATE annonce 
                        SET titre = ?, det = ?, cible = ?, id_Sec = ?, image = ? 
                        WHERE id_Anm = ? AND id_user = ?
                    ");
                    $stmt_up->execute([$titre, $det, $cible, $id_Sec, $image_path, $id_modif, $id_user_session]);
                } else {
                    $stmt_up = $pdo->prepare("
                        UPDATE annonce 
                        SET titre = ?, det = ?, cible = ?, id_Sec = ? 
                        WHERE id_Anm = ? AND id_user = ?
                    ");
                    $stmt_up->execute([$titre, $det, $cible, $id_Sec, $id_modif, $id_user_session]);
                }
                $message = "L'annonce a été modifiée avec succès !";
            } else {
                // Insertion
                $stmt_ins = $pdo->prepare("
                    INSERT INTO annonce (id_user, titre, det, Date_Pub, cible, id_Sec, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$id_user_session, $titre, $det, $date_pub, $cible, $id_Sec, $image_path]);
                $message = "L'annonce a été publiée avec succès !";
            }
            $tab = 'mes_annonces';
        }
    }

    // 2. Traitement de la Suppression (et suppression du fichier image associé)
    if (isset($_POST['supprimer_annonce'])) {
        $id_Anm = $_POST['id_Anm'] ?? '';
        
        // Récupérer l'image pour la supprimer du dossier
        $stmt_img = $pdo->prepare("SELECT image FROM annonce WHERE id_Anm = ? AND id_user = ?");
        $stmt_img->execute([$id_Anm, $id_user_session]);
        $img_file = $stmt_img->fetchColumn();
        if ($img_file && file_exists($img_file)) {
            unlink($img_file);
        }

        $stmt_del = $pdo->prepare("DELETE FROM annonce WHERE id_Anm = ? AND id_user = ?");
        $stmt_del->execute([$id_Anm, $id_user_session]);
        $message = "L'annonce a été supprimée avec succès.";
        $tab = 'mes_annonces';
    }

    // 3. Charger les données si on est en mode édition
    if ($edit_id) {
        $stmt_edit = $pdo->prepare("SELECT * FROM annonce WHERE id_Anm = ? AND id_user = ?");
        $stmt_edit->execute([$edit_id, $id_user_session]);
        $res_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if ($res_edit) {
            $edit_annonce = $res_edit;
            $edit_mode = true;
            $tab = 'mes_annonces';
        }
    }

    // 4. Récupérer les annonces générales
    $stmt_general = $pdo->prepare("
        SELECT a.*, u.nom, u.prenom, sec.nom_sec, sp.NomSp, sp.annee
        FROM annonce a
        LEFT JOIN user u ON a.id_user = u.id_user
        LEFT JOIN section sec ON a.id_Sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        WHERE a.id_user != ? AND (a.cible = 'tous' OR a.cible = 'profs')
        ORDER BY a.Date_Pub DESC
    ");
    $stmt_general->execute([$id_user_session]);
    $annonces_generales = $stmt_general->fetchAll(PDO::FETCH_ASSOC);

    // 5. Récupérer "Mes annonces"
    $stmt_mes = $pdo->prepare("
        SELECT a.*, sec.nom_sec, sp.NomSp, sp.annee
        FROM annonce a
        LEFT JOIN section sec ON a.id_Sec = sec.id_Sec
        LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp
        WHERE a.id_user = ?
        ORDER BY a.Date_Pub DESC
    ");
    $stmt_mes->execute([$id_user_session]);
    $mes_annonces = $stmt_mes->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Annonces</title>
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

        .tabs-nav { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
        .tab-btn { background: none; border: none; padding: 10px 20px; font-size: 15px; font-weight: 600; cursor: pointer; color: var(--text); border-radius: 6px; transition: 0.2s; text-decoration: none; }
        .tab-btn.active { background-color: var(--header); color: var(--on-blue); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: span 2; }
        
        label { font-size: 13px; font-weight: 600; color: var(--text); }
        input[type="text"], select, textarea, input[type="file"] {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE;
        }
        textarea { resize: vertical; min-height: 100px; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--header); background: #fff; }

        .btn-save { background-color: var(--accent); color: var(--on-accent); border: none; padding: 12px 25px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { opacity: 0.9; }

        .annonce-box { background: #fff; border: 1px solid var(--border); border-left: 5px solid var(--header); border-radius: 8px; padding: 18px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); display: flex; gap: 20px; align-items: flex-start; }
        .annonce-content { flex: 1; }
        .annonce-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .annonce-title { font-size: 16px; font-weight: 700; color: var(--header); }
        .annonce-date { font-size: 12px; color: #64748b; }
        .annonce-details { font-size: 14px; color: #334155; line-height: 1.5; margin-bottom: 12px; }
        .annonce-footer { display: flex; gap: 15px; font-size: 12px; color: #64748b; align-items: center; }
        .badge-cible { background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: 600; text-transform: uppercase; font-size: 11px; }

        .annonce-img { width: 140px; height: 100px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }

        .btn-edit { background-color: var(--menu); color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; text-decoration: none; font-weight: 600; display: inline-block; }
        .btn-delete { background-color: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; }

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
                <a href="prof_emploi_du_temps.php" class="menu-item"><span>📅</span> Consulter Emploi du temps</a>
                <a href="prof_gestion_notes.php" class="menu-item"><span>📊</span> Gestion des Notes</a>
                <a href="prof_horaires_consultation.php" class="menu-item"><span>🕒</span> Horaire de Consultation</a>
                <a href="prof_ressources_pedagogiques.php" class="menu-item"><span>📁</span> Gestion des Ressources</a>
                <a href="prof_annonces.php" class="menu-item active"><span>📢</span> Annonces</a>
                <a href="prof_planning_examens.php" class="menu-item"><span>📋</span> Planning des Examens</a>
            </div>
        </div>

        <div class="user-info">
            <span>👨‍🏫 <?php echo htmlspecialchars($prenom_prof . ' ' . $nom_prof); ?></span>
            <a href="logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Enseignant - Gestion des Annonces</h2>
            
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

            <!-- Navigation par onglets -->
            <div class="tabs-nav">
                <a href="prof_annonces.php?tab=general" class="tab-btn <?php echo ($tab == 'general') ? 'active' : ''; ?>">📢 Annonces Générales (Scolarité & Collègues)</a>
                <a href="prof_annonces.php?tab=mes_annonces" class="tab-btn <?php echo ($tab == 'mes_annonces') ? 'active' : ''; ?>">✍️ Mes Annonces & Publication</a>
            </div>

            <!-- ONGLET 1 : ANNONCES GENERALES -->
            <?php if ($tab == 'general'): ?>
                <div class="card">
                    <h3 class="page-title">Annonces de la scolarité et de la communauté</h3>
                    <?php if (!empty($annonces_generales)): ?>
                        <?php foreach ($annonces_generales as $ann): ?>
                            <div class="annonce-box">
                                <?php if (!empty($ann['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($ann['image']); ?>" alt="Illustration" class="annonce-img">
                                <?php endif; ?>
                                <div class="annonce-content">
                                    <div class="annonce-header">
                                        <span class="annonce-title"><?php echo htmlspecialchars($ann['titre']); ?></span>
                                        <span class="annonce-date">📅 <?php echo date('d/m/Y', strtotime($ann['Date_Pub'])); ?></span>
                                    </div>
                                    <div class="annonce-details"><?php echo nl2br(htmlspecialchars($ann['det'])); ?></div>
                                    <div class="annonce-footer">
                                        <span>👤 Par : <strong><?php echo htmlspecialchars(($ann['nom'] ?? 'Scolarité/Admin') . ' ' . ($ann['prenom'] ?? '')); ?></strong></span>
                                        <span>Cible : <span class="badge-cible"><?php echo htmlspecialchars($ann['cible']); ?></span></span>
                                        <?php if (!empty($ann['nom_sec'])): ?>
                                            <span>Section : <strong><?php echo htmlspecialchars($ann['nom_sec'] . ' (' . $ann['NomSp'] . ' - ' . $ann['annee'] . ')'); ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #777; text-align: center; padding: 20px;">Aucune annonce générale pour le moment.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ONGLET 2 : MES ANNONCES & FORMULAIRE -->
            <?php if ($tab == 'mes_annonces'): ?>
                <!-- Formulaire de Publication / Modification (avec enctype="multipart/form-data") -->
                <div class="card">
                    <h3 class="page-title"><?php echo $edit_mode ? 'Modifier l\'annonce' : 'Publier une nouvelle annonce'; ?></h3>
                    <form action="prof_annonces.php?tab=mes_annonces" method="POST" enctype="multipart/form-data">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id_modif" value="<?php echo $edit_annonce['id_Anm']; ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="titre">Titre de l'annonce :</label>
                                <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($edit_annonce['titre']); ?>" placeholder="ex: Rattrapage du cours de Compilation" required>
                            </div>

                            <div class="form-group">
                                <label for="cible">Public ciblé :</label>
                                <select name="cible" id="cible" required>
                                    <option value="tous" <?php echo ($edit_annonce['cible'] == 'tous') ? 'selected' : ''; ?>>Tous (Scolarité, Étudiants, Profs)</option>
                                    <option value="etudiants" <?php echo ($edit_annonce['cible'] == 'etudiants') ? 'selected' : ''; ?>>Étudiants uniquement</option>
                                    <option value="profs" <?php echo ($edit_annonce['cible'] == 'profs') ? 'selected' : ''; ?>>Professeurs uniquement</option>
                                    <option value="scolarite" <?php echo ($edit_annonce['cible'] == 'scolarite') ? 'selected' : ''; ?>>Scolarité uniquement</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="id_Sec">Section concernée (Optionnel) :</label>
                                <select name="id_Sec" id="id_Sec">
                                    <option value="">-- Toute l'université / Aucune section spécifique --</option>
                                    <?php foreach ($sections_uniques as $sec): ?>
                                        <option value="<?php echo $sec['id_Sec']; ?>" <?php echo ($edit_annonce['id_Sec'] == $sec['id_Sec']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sec['nom_sec'] . ' (' . $sec['NomSp'] . ' - ' . $sec['annee'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label for="image_annonce">Illustration / Photo (Optionnel) :</label>
                                <input type="file" id="image_annonce" name="image_annonce" accept="image/*">
                                <?php if (!empty($edit_annonce['image'])): ?>
                                    <small style="color: #64748b; margin-top: 4px;">Image actuelle : <a href="<?php echo htmlspecialchars($edit_annonce['image']); ?>" target="_blank">Voir l'image</a></small>
                                <?php endif; ?>
                            </div>

                            <div class="form-group full-width">
                                <label for="det">Détails / Contenu de l'annonce :</label>
                                <textarea id="det" name="det" placeholder="Rédigez votre annonce ici..." required><?php echo htmlspecialchars($edit_annonce['det']); ?></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="publier_annonce" class="btn-save"><?php echo $edit_mode ? '💾 Enregistrer les modifications' : '📤 Publier l\'annonce'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="prof_annonces.php?tab=mes_annonces" style="padding: 12px 20px; background: #cbd5e1; color: #1e293b; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600;">Annuler</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Liste de mes annonces -->
                <div class="card">
                    <h3 class="page-title">Mes annonces publiées</h3>
                    <?php if (!empty($mes_annonces)): ?>
                        <?php foreach ($mes_annonces as $m_ann): ?>
                            <div class="annonce-box">
                                <?php if (!empty($m_ann['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($m_ann['image']); ?>" alt="Illustration" class="annonce-img">
                                <?php endif; ?>
                                <div class="annonce-content">
                                    <div class="annonce-header">
                                        <span class="annonce-title"><?php echo htmlspecialchars($m_ann['titre']); ?></span>
                                        <span class="annonce-date">📅 <?php echo date('d/m/Y', strtotime($m_ann['Date_Pub'])); ?></span>
                                    </div>
                                    <div class="annonce-details"><?php echo nl2br(htmlspecialchars($m_ann['det'])); ?></div>
                                    <div class="annonce-footer" style="justify-content: space-between; width: 100%;">
                                        <div style="display: flex; gap: 15px; align-items: center;">
                                            <span>Cible : <span class="badge-cible"><?php echo htmlspecialchars($m_ann['cible']); ?></span></span>
                                            <?php if (!empty($m_ann['nom_sec'])): ?>
                                                <span>Section : <strong><?php echo htmlspecialchars($m_ann['nom_sec'] . ' (' . $m_ann['NomSp'] . ' - ' . $m_ann['annee'] . ')'); ?></strong></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="prof_annonces.php?edit=<?php echo $m_ann['id_Anm']; ?>" class="btn-edit">✏️ Modifier</a>
                                            <form action="prof_annonces.php?tab=mes_annonces" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cette annonce ?');" style="margin: 0;">
                                                <input type="hidden" name="id_Anm" value="<?php echo $m_ann['id_Anm']; ?>">
                                                <button type="submit" name="supprimer_annonce" class="btn-delete">🗑️ Supprimer</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #777; text-align: center; padding: 20px;">Vous n'avez publié aucune annonce pour le moment.</p>
                    <?php endif; ?>
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