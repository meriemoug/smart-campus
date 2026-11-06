<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'scolarite') {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db   = 'smart_campus';
$user = 'root';
$pass = '';

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Traitement de l'ajout d'un nouveau département (avec Nom_Dep)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_departement'])) {
        $nom_dep = trim($_POST['nom_dep'] ?? '');

        if (!empty($nom_dep)) {
            $stmt_dept = $pdo->prepare("INSERT INTO departement (Nom_Dep) VALUES (?)");
            $stmt_dept->execute([$nom_dep]);
            $message = "Le département a été créé avec succès !";
        } else {
            $error = "Veuillez entrer un nom de département valide.";
        }
    }

    // 2. Traitement de l'ajout d'une spécialité
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_specialite'])) {
        $nom_specialite = trim($_POST['nom_specialite'] ?? '');
        $annee = trim($_POST['annee'] ?? '');
        $id_dep = trim($_POST['id_dep'] ?? '');

        if (!empty($nom_specialite) && !empty($annee) && !empty($id_dep)) {
            // Adapte 'nom_spec' ou 'Nom_Spec' si besoin selon ta table specialite
            $stmt_spec = $pdo->prepare("INSERT INTO specialite (NomSp, annee, id_Dep) VALUES (?, ?, ?)");
            $stmt_spec->execute([$nom_specialite, $annee, $id_dep]);
            $message = "La spécialité a été ajoutée avec succès !";
        } else {
            $error = "Veuillez remplir tous les champs pour la spécialité.";
        }
    }

    
    // 3. Traitement de la suppression d'une spécialité
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_specialite'])) {
        // Remplace 'id_spec' par le nom exact de ta colonne clé primaire dans la table specialite si besoin
        $id_spec_to_delete = trim($_POST['id_spec_to_delete'] ?? '');

        if (!empty($id_spec_to_delete)) {
            $stmt_del = $pdo->prepare("DELETE FROM specialite WHERE id_sp = ?");
            $stmt_del->execute([$id_spec_to_delete]);

            // Vérifier si une ligne a réellement été supprimée
            if ($stmt_del->rowCount() > 0) {
                $message = "La spécialité a été supprimée avec succès.";
            } else {
                $error = "Erreur : Impossible de supprimer cette spécialité (ID introuvable ou liée à d'autres données).";
            }
        }
    }

    // 4. Récupérer la liste des départements existants (id_Dep et Nom_Dep)
    $stmt_dept_list = $pdo->query("SELECT id_Dep, Nom_Dep FROM departement");
    $departements = $stmt_dept_list->fetchAll(PDO::FETCH_ASSOC);

    // 5. Récupérer la liste des spécialités avec jointure sur id_Dep
    $stmt_spec_list = $pdo->query("SELECT s.*, d.Nom_Dep 
                                   FROM specialite s 
                                   JOIN departement d ON s.id_Dep = d.id_Dep");
    $specialites = $stmt_spec_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Spécialités & Départements</title>
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
            --text-muted: #51606A;
            --error: #A8162A;
            --on-error: #FFFFFF;
            --success: #2e7d32;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 280px;
            background-color: var(--header);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar-top .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--on-blue);
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu-item {
            background-color: transparent;
            color: var(--on-blue);
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-item:hover, .menu-item.active {
            background-color: var(--accent);
            color: var(--on-accent);
            font-weight: 600;
        }

        .btn-logout {
            background-color: var(--error);
            color: var(--on-error);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .btn-logout:hover {
            opacity: 0.9;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .top-navbar {
            background-color: var(--header);
            color: var(--on-blue);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background-color: var(--accent);
            color: var(--on-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .content-body {
            padding: 30px;
        }

        .section-title {
            color: var(--header);
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(27,90,128,0.04);
        }

        .card h3 {
            color: var(--header);
            margin-bottom: 20px;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background-color: #FAFCFE;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--menu);
            box-shadow: 0 0 0 3px rgba(34, 107, 148, 0.15);
        }

        .btn-submit {
            background-color: var(--header);
            color: var(--on-blue);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: var(--menu);
        }

        .btn-delete {
            background-color: var(--error);
            color: var(--on-error);
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-delete:hover {
            opacity: 0.85;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background-color: #f4ebd0;
            color: var(--header);
            font-weight: 600;
        }

        td {
            color: var(--text);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

    <!-- Sidebar Unifiée -->
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
                <a href="gestion_etudiants.php" class="menu-item">🎓 Étudiants</a>
                <a href="gestion_sections.php" class="menu-item">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item active">📚 Spécialités & Départements</a>
                <a href="gestion_examens.php" class="menu-item">📅 Plannings d'examens</a>
                <a href="gestion_annonces_notes.php" class="menu-item">📢 Annonces & Notes</a>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Se déconnecter</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Spécialités & Départements</h2>
            <div class="user-profile">
                <span>Utilisateur : <strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['prenom'], 0, 1)); ?></div>
            </div>
        </div>

        <div class="content-body">
            <h3 class="section-title">Gestion des Départements et des Spécialités</h3>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="admin-grid">
                <!-- 1. Formulaire d'ajout de Département -->
                <div class="card">
                    <h3>Créer un nouveau département</h3>
                    <form action="gestion_specialites.php" method="POST">
                        <div class="form-group">
                            <label for="nom_dep">Nom du département</label>
                            <input type="text" id="nom_dep" name="nom_dep" required placeholder="Ex: Département Informatique">
                        </div>
                        <button type="submit" name="ajouter_departement" class="btn-submit">Créer le département</button>
                    </form>
                </div>

                <!-- 2. Formulaire d'ajout de Spécialité -->
                <div class="card">
                    <h3>Ajouter une spécialité</h3>
                    <form action="gestion_specialites.php" method="POST">
                        <div class="form-group">
                            <label for="nom_specialite">Intitulé de la spécialité</label>
                            <input type="text" id="nom_specialite" name="nom_specialite" required placeholder="Ex: Master 2 - GL">
                        </div>
                        <div class="form-group">
                            <label for="annee">Année d'étude (1 à 5)</label>
                            <input type="number" id="annee" name="annee" min="1" max="5" required placeholder="Ex: 5">
                        </div>
                        <div class="form-group">
                            <label for="id_dep">Département de rattachement</label>
                            <select id="id_dep" name="id_dep" required>
                                <option value="">-- Choisir un département --</option>
                                <?php foreach ($departements as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['id_Dep']); ?>">
                                        <?php echo htmlspecialchars($dept['Nom_Dep']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="ajouter_specialite" class="btn-submit">Enregistrer la spécialité</button>
                    </form>
                </div>
            </div>

            <!-- Liste des spécialités existantes -->
            <div class="card">
                <h3>Spécialités enregistrées</h3>
                <?php if (empty($specialites)): ?>
                    <p style="color: var(--text-muted);">Aucune spécialité enregistrée pour le moment.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Spécialité</th>
                                    <th>Année</th>
                                    <th>Département rattaché</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($specialites as $spec): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($spec['id_sp'] ?? $spec['id'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($spec['NomSp'] ?? $spec['nom'] ?? ''); ?></td>
                                        <td>Année <?php echo htmlspecialchars($spec['annee'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($spec['Nom_Dep'] ?? 'Non rattaché'); ?></td>
                                        <td>
                                            <form action="gestion_specialites.php" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cette spécialité ?');" style="display:inline;">
                                                <input type="hidden" name="id_spec_to_delete" value="<?php echo htmlspecialchars($spec['id_sp'] ?? $spec['id'] ?? ''); ?>">
                                                <button type="submit" name="supprimer_specialite" class="btn-delete">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>