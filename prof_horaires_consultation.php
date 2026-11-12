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

$date_consult = '';
$heure_consult = '';
$bureau = '';
$email_contact = $_SESSION['email'] ?? '';
$note_consult = '';
$consult_existante = false;

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

    // 2. Traitement de la suppression
    if (isset($_POST['supprimer_consultation'])) {
        $stmt_del = $pdo->prepare("DELETE FROM consultation WHERE id_prof = ?");
        $stmt_del->execute([$id_prof]);
        $message = "Votre consultation a été supprimée avec succès. Vous pouvez en publier une nouvelle.";
        // Réinitialiser les champs
        $date_consult = '';
        $heure_consult = '';
        $bureau = '';
        $note_consult = '';
    }

    // 3. Traitement de l'enregistrement / modification
    if (isset($_POST['enregistrer_consultation'])) {
        $date_consult = $_POST['date_consult'] ?? '';
        $heure_consult = $_POST['heure_consult'] ?? '';
        $bureau = $_POST['bureau'] ?? '';
        $email_contact = $_POST['email_contact'] ?? '';
        $note_consult = $_POST['note_consult'] ?? '';

        // Validation : s'assurer que la date n'est pas passée
        $date_actuelle = date('Y-m-d');
        if ($date_consult < $date_actuelle) {
            $error = "Erreur : La date de consultation doit être une date future (à partir d'aujourd'hui).";
        } else {
            // Vérifier si une consultation existe déjà
            $stmt_chk = $pdo->prepare("SELECT id_consult FROM consultation WHERE id_prof = ?");
            $stmt_chk->execute([$id_prof]);

            if ($stmt_chk->rowCount() > 0) {
                // Mise à jour
                $stmt_up = $pdo->prepare("
                    UPDATE consultation 
                    SET date_consult = ?, heure_consult = ?, bureau = ?, email_contact = ?, note = ? 
                    WHERE id_prof = ?
                ");
                $stmt_up->execute([$date_consult, $heure_consult, $bureau, $email_contact, $note_consult, $id_prof]);
                $message = "Vos horaires de consultation ont été mis à jour avec succès !";
            } else {
                // Insertion
                $stmt_ins = $pdo->prepare("
                    INSERT INTO consultation (id_prof, date_consult, heure_consult, bureau, email_contact, note) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$id_prof, $date_consult, $heure_consult, $bureau, $email_contact, $note_consult]);
                $message = "Vos horaires de consultation ont été publiés avec succès !";
            }
        }
    }

    // 4. Vérification et récupération des informations existantes
    $stmt_get = $pdo->prepare("SELECT * FROM consultation WHERE id_prof = ?");
    $stmt_get->execute([$id_prof]);
    $consult_data = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if ($consult_data) {
        $date_consult_db = $consult_data['date_consult'];
        $date_actuelle = date('Y-m-d');

        // Si la date est dépassée, on la supprime automatiquement de la base
        if ($date_consult_db < $date_actuelle) {
            $stmt_del_exp = $pdo->prepare("DELETE FROM consultation WHERE id_prof = ?");
            $stmt_del_exp->execute([$id_prof]);
            $error = "Votre précédente consultation a expiré (date dépassée). Veuillez entrer une nouvelle date future.";
            $date_consult = '';
            $heure_consult = '';
            $bureau = '';
            $note_consult = '';
            $consult_existante = false;
        } else {
            // La consultation est encore valide, on charge les données
            $date_consult = $consult_data['date_consult'];
            $heure_consult = $consult_data['heure_consult'];
            $bureau = $consult_data['bureau'];
            $email_contact = $consult_data['email_contact'];
            $note_consult = $consult_data['note'];
            $consult_existante = true;
        }
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
    <title>Smart Campus - Horaires de Consultation</title>
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
        .content-body { padding: 30px; max-width: 900px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .page-title { color: var(--header); font-size: 22px; font-weight: 700; margin-bottom: 20px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: span 2; }
        
        label { font-size: 13px; font-weight: 600; color: var(--text); }
        input[type="date"], input[type="text"], input[type="email"], textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: #FAFCFE;
        }
        textarea { resize: vertical; min-height: 100px; }
        input:focus, textarea:focus { outline: none; border-color: var(--header); background: #fff; }

        .actions-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .btn-save { background-color: var(--accent); color: var(--on-accent); border: none; padding: 12px 25px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { opacity: 0.9; }

        .btn-delete { background-color: #dc2626; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-delete:hover { background-color: #b91c1c; }

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
                <a href="prof_dashboard.php" class="menu-item"><span>📅</span> Consulter Emploi du temps</a>
                <a href="prof_gestion_notes.php" class="menu-item"><span>📊</span> Gestion des Notes</a>
                <a href="prof_horaires_consultation.php" class="menu-item active"><span>🕒</span> Horaire de Consultation</a>
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
            <h2>Espace Enseignant - Horaires de Consultation</h2>
            
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

            <div class="card">
                <h3 class="page-title"><?php echo $consult_existante ? 'Modifier / Gérer ma permanence' : 'Publier une nouvelle permanence'; ?></h3>
                <p style="font-size: 13px; color: #51606A; margin-bottom: 25px;">
                    Définissez ou modifiez votre date et heure de consultation. Si la date programmée est dépassée, la consultation est automatiquement retirée.
                </p>

                <form action="prof_horaires_consultation.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date_consult">Date de présence (Futur obligatoire) :</label>
                            <input type="date" id="date_consult" name="date_consult" value="<?php echo htmlspecialchars($date_consult); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="heure_consult">Plage horaire (ex: 10:00 - 12:00) :</label>
                            <input type="text" id="heure_consult" name="heure_consult" value="<?php echo htmlspecialchars($heure_consult); ?>" placeholder="ex: 10:00 - 12:00" required>
                        </div>

                        <div class="form-group">
                            <label for="bureau">Bureau / Localisation :</label>
                            <input type="text" id="bureau" name="bureau" value="<?php echo htmlspecialchars($bureau); ?>" placeholder="ex: Bureau B2-04, Département Info" required>
                        </div>

                        <div class="form-group">
                            <label for="email_contact">Adresse Email de contact :</label>
                            <input type="email" id="email_contact" name="email_contact" value="<?php echo htmlspecialchars($email_contact); ?>" placeholder="votre.email@univ.dz" required>
                        </div>

                        <div class="form-group full-width">
                            <label for="note_consult">Note / Instructions particulières :</label>
                            <textarea id="note_consult" name="note_consult" placeholder="Ex: Merci de m'envoyer un email 24h à l'avance pour fixer un rendez-vous précis..."><?php echo htmlspecialchars($note_consult); ?></textarea>
                        </div>
                    </div>

                    <div class="actions-bar">
                        <?php if ($consult_existante): ?>
                            <button type="submit" name="supprimer_consultation" class="btn-delete" onclick="return confirm('Voulez-vous vraiment supprimer cette consultation ?');">🗑️ Supprimer</button>
                        <?php else: ?>
                            <div></div> <!-- Espace vide pour aligner le bouton à droite -->
                        <?php endif; ?>

                        <button type="submit" name="enregistrer_consultation" class="btn-save">💾 Enregistrer / Mettre à jour</button>
                    </div>
                </form>
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