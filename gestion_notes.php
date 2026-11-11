<?php
session_start();

// Vérifier si l'utilisateur est connecté
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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- TRAITEMENT DU CALCUL DES MOYENNES POUR TOUS LES ETUDIANTS ---
    if (isset($_POST['calculer_moyennes'])) {
        
        // 1. Récupérer tous les étudiants
        $stmt_etus = $pdo->query("SELECT * FROM etudiant");
        $etudiants = $stmt_etus->fetchAll(PDO::FETCH_ASSOC);

        foreach ($etudiants as $etu) {
            $id_user = $etu['id_user'];
            $id_sp = $etu['id_sp'];

            // --- CALCUL SEMESTRE 1 (S1) ---
            // On récupère les notes de l'étudiant pour le S1 avec les coefficients des enseignements
            $stmt_notes_s1 = $pdo->prepare("
                SELECT n.valeur_note, en.coefficient 
                FROM note n 
                JOIN enseignement en ON n.id_enseignement = en.id_enseignement 
                WHERE n.id_user = ? AND n.semestre = 'S1'
            ");
            // Note : Adapte 'n.id_user' selon le nom de la colonne qui lie la table note à l'étudiant
            $stmt_notes_s1->execute([$id_user]);
            $notes_s1 = $stmt_notes_s1->fetchAll(PDO::FETCH_ASSOC);

            $somme_produits_s1 = 0;
            $somme_coefficients_s1 = 0;

            foreach ($notes_s1 as $note) {
                $somme_produits_s1 += ($note['valeur_note'] * $note['coefficient']);
                $somme_coefficients_s1 += $note['coefficient'];
            }

            $moyenne_s1 = ($somme_coefficients_s1 > 0) ? ($somme_produits_s1 / $somme_coefficients_s1) : null;


            // --- CALCUL SEMESTRE 2 (S2) ---
            $stmt_notes_s2 = $pdo->prepare("
                SELECT n.valeur_note, en.coefficient 
                FROM note n 
                JOIN enseignement en ON n.id_enseignement = en.id_enseignement 
                WHERE n.id_user = ? AND n.semestre = 'S2'
            ");
            $stmt_notes_s2->execute([$id_user]);
            $notes_s2 = $stmt_notes_s2->fetchAll(PDO::FETCH_ASSOC);

            $somme_produits_s2 = 0;
            $somme_coefficients_s2 = 0;

            foreach ($notes_s2 as $note) {
                $somme_produits_s2 += ($note['valeur_note'] * $note['coefficient']);
                $somme_coefficients_s2 += $note['coefficient'];
            }

            $moyenne_s2 = ($somme_coefficients_s2 > 0) ? ($somme_produits_s2 / $somme_coefficients_s2) : null;


            // --- CALCUL MOYENNE FINALE ET DECISION ---
            $moyenne_finale = null;
            $decision = 'En cours';

            // Si on a les deux semestres (ou selon ta logique)
            if ($moyenne_s1 !== null && $moyenne_s2 !== null) {
                $moyenne_finale = ($moyenne_s1 + $moyenne_s2) / 2;
                
                if ($moyenne_finale >= 10) {
                    $decision = 'Admis';
                } else {
                    $decision = 'Ajourné';
                }
            } elseif ($moyenne_s1 !== null) {
                $moyenne_finale = $moyenne_s1; // Si seul le S1 est bouclé
            }

            // --- ENREGISTREMENT DANS LA TABLE resultat_etudiant ---
            // Vérifier si un résultat existe déjà pour cet étudiant
            $stmt_check = $pdo->prepare("SELECT id_resultat FROM resultat_etudiant WHERE id_user = ?");
            $stmt_check->execute([$id_user]);

            if ($stmt_check->rowCount() > 0) {
                // Mise à jour
                $stmt_update = $pdo->prepare("
                    UPDATE resultat_etudiant 
                    SET moyenne_s1 = ?, moyenne_s2 = ?, moyenne_finale = ?, decision = ? 
                    WHERE id_user = ?
                ");
                $stmt_update->execute([$moyenne_s1, $moyenne_s2, $moyenne_finale, $decision, $id_user]);
            } else {
                // Insertion
                $stmt_insert = $pdo->prepare("
                    INSERT INTO resultat_etudiant (id_user, moyenne_s1, moyenne_s2, moyenne_finale, decision) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_insert->execute([$id_user, $moyenne_s1, $moyenne_s2, $moyenne_finale, $decision]);
            }
        }

        $message = "Les moyennes et les décisions ont été calculées et mises à jour avec succès !";
    }

    // Récupérer la liste des étudiants avec leurs résultats pour affichage
    $stmt_resultats = $pdo->query("
        SELECT e.id_user, u.nom, u.prenom, e.annee, r.moyenne_s1, r.moyenne_s2, r.moyenne_finale, r.decision
        FROM etudiant e
        JOIN user u ON e.id_user = u.id_user
        LEFT JOIN resultat_etudiant r ON e.id_user = r.id_user
        ORDER BY u.nom ASC
    ");
    $liste_resultats = $stmt_resultats->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Résultats & Moyennes</title>
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

        .sidebar {
            width: 280px; background-color: var(--header); display: flex; flex-direction: column; justifyContent: space-between; padding: 20px;
        }
        .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { color: var(--on-blue); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .content-body { padding: 30px; max-width: 1100px; margin: 0 auto; width: 100%; }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); margin-bottom: 25px; }
        .section-title { color: var(--header); font-size: 22px; margin-bottom: 15px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }

        .btn-calcul { background-color: var(--accent); color: var(--on-accent); border: none; padding: 12px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-calcul:hover { opacity: 0.9; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 12px 15px; text-align: left; font-size: 14px; border-bottom: 1px solid #eee; }
        th { background-color: #f1f5f9; color: var(--header); font-weight: 600; }

        .badge-admis { background-color: var(--success-bg); color: var(--success-text); padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-ajourne { background-color: var(--danger-bg); color: var(--danger-text); padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-cours { background-color: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }

        .alert-success { background-color: var(--success-bg); color: var(--success-text); padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: var(--danger-bg); color: var(--danger-text); padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        .menu-dropdown {
    position: relative;
}

.dropdown-toggle {
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Le sous-menu */
.submenu {
    flex-direction: column;
    gap: 4px;
    padding-left: 15px;
    margin-top: 4px;
    margin-bottom: 4px;
    border-left: 2px solid rgba(255, 255, 255, 0.2);
}

.submenu-item {
    color: rgba(255, 255, 255, 0.85);
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 400;
    transition: background 0.2s, color 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.submenu-item:hover, .submenu-item.active {
    background-color: var(--accent);
    color: var(--on-accent);
    font-weight: 600;
}
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
                    <!-- Le lien principal qui déclenche l'affichage au clic -->
                    <a href="javascript:void(0);" class="menu-item dropdown-toggle" onclick="toggleSubmenu()">
                        <span>📢 Notes, Absences & Annonces</span> 
                        <span id="arrow" style="font-size: 11px;">▾</span>
                    </a>
                    
                    <!-- Le sous-menu masqué par défaut grâce à l'attribut style="display: none;" -->
                    <div class="submenu" id="submenuNotes" style="display: none;">
                        <a href="gestion_notes.php" class="submenu-item">📊 Notes & Moyennes</a>
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
            <h2>Gestion des Résultats Académiques</h2>
        </div>

        <div class="content-body">

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="section-title">
                    <span>Calcul Automatique des Moyennes et Décisions</span>
                    <form action="gestion_resultats.php" method="POST">
                        <button type="submit" name="calculer_moyennes" class="btn-calcul">⚙️ Lancer le calcul global</button>
                    </form>
                </div>
                <p style="font-size: 13px; color: #51606A; line-height: 1.5;">
                    Ce bouton récupère les notes saisies par les professeurs, applique les coefficients par module, calcule la moyenne du S1, du S2 et la moyenne finale, puis attribue automatiquement le statut <strong>Admis</strong> ou <strong>Ajourné</strong> selon les règles pédagogiques.
                </p>
            </div>

            <div class="card">
                <h3 style="color: var(--header); margin-bottom: 15px; font-size: 18px;">Tableau récapitulatif des étudiants</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID Étudiant</th>
                            <th>Nom & Prénom</th>
                            <th>Année</th>
                            <th>Moy. S1</th>
                            <th>Moy. S2</th>
                            <th>Moyenne Finale</th>
                            <th>Décision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($liste_resultats)): ?>
                            <?php foreach ($liste_resultats as $res): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($res['id_user']); ?></td>
                                    <td><?php echo htmlspecialchars($res['nom'] . ' ' . $res['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($res['annee']); ?></td>
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
                                <td colspan="7" style="text-align: center; color: #777;">Aucun étudiant trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
    <script>
      function toggleSubmenu() {
    var submenu = document.getElementById('submenuNotes');
    var arrow = document.getElementById('arrow');
    
    if (submenu.style.display === 'none' || submenu.style.display === '') {
        submenu.style.display = 'flex'; // Affiche le sous-menu
        arrow.textContent = '▴';       // Change la flèche vers le haut
    } else {
        submenu.style.display = 'none'; // Masque le sous-menu
        arrow.textContent = '▾';       // Change la flèche vers le bas
    }
}
    <script>

</body>
</html>