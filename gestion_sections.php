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
$selected_section = null;
$modules_section = [];
$seances_grid = [];
$specialites = [];
$profs = [];
$salles = [];
$sections = [];
$toutes_seances = [];
$default_profs_names = [];
$filter_sp = '';
$filter_annee = '';
$notifications = [];
$nb_non_lus = 0;
$id_user_connecte = $_SESSION['id_user'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les notifications et le compteur non lus
    $stmt_notifs = $pdo->prepare("SELECT * FROM notification WHERE id_user = ? ORDER BY date_creation DESC LIMIT 15");
    $stmt_notifs->execute([$id_user_connecte]);
    $notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
    $stmt_count->execute([$id_user_connecte]);
    $nb_non_lus = $stmt_count->fetchColumn();

    // 1. Ajout d'une nouvelle section (avec vérification d'unicité)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_section'])) {
        $nom_sec = trim($_POST['nom_sec'] ?? '');
        $id_sp = trim($_POST['id_sp'] ?? '');
        $nombre_groupes = intval($_POST['nombre_groupes'] ?? 4);

        if (!empty($nom_sec) && !empty($id_sp)) {
            $stmt_check = $pdo->prepare("SELECT id_Sec FROM section WHERE nom_sec = ? AND id_sp = ?");
            $stmt_check->execute([$nom_sec, $id_sp]);

            if ($stmt_check->rowCount() > 0) {
                $error = "Erreur : La section '$nom_sec' existe déjà pour cette spécialité !";
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO section (nom_sec, id_sp, nombre_groupes) VALUES (?, ?, ?)");
                $stmt_ins->execute([$nom_sec, $id_sp, $nombre_groupes]);
                $message = "La section a été créée avec succès !";
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires.";
        }
    }

    // 1. bis Modification d'une section existante
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_section'])) {
        $id_Sec = intval($_POST['id_Sec'] ?? 0);
        $nom_sec = trim($_POST['nom_sec'] ?? '');
        $id_sp = trim($_POST['id_sp'] ?? '');
        $nombre_groupes = intval($_POST['nombre_groupes'] ?? 4);

        if ($id_Sec > 0 && !empty($nom_sec) && !empty($id_sp)) {
            $stmt_check = $pdo->prepare("SELECT id_Sec FROM section WHERE nom_sec = ? AND id_sp = ? AND id_Sec != ?");
            $stmt_check->execute([$nom_sec, $id_sp, $id_Sec]);

            if ($stmt_check->rowCount() > 0) {
                $error = "Erreur : Une autre section porte déjà le nom '$nom_sec' pour cette spécialité.";
            } else {
                $stmt_upd = $pdo->prepare("UPDATE section SET nom_sec = ?, id_sp = ?, nombre_groupes = ? WHERE id_Sec = ?");
                $stmt_upd->execute([$nom_sec, $id_sp, $nombre_groupes, $id_Sec]);
                $message = "La section a été modifiée avec succès !";
            }
        } else {
            $error = "Veuillez remplir tous les champs pour la modification.";
        }
    }

    // SUPPRESSION D'UNE SECTION
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_section'])) {
        $id_Sec_del = intval($_POST['id_Sec_to_delete'] ?? 0);
        if ($id_Sec_del > 0) {
            $pdo->prepare("DELETE FROM seance WHERE id_Sec = ?")->execute([$id_Sec_del]);
            $pdo->prepare("DELETE FROM enseignement WHERE id_Sec = ?")->execute([$id_Sec_del]);
            
            $stmt_del_sec = $pdo->prepare("DELETE FROM section WHERE id_Sec = ?");
            $stmt_del_sec->execute([$id_Sec_del]);
            
            $message = "La section a été supprimée avec succès.";
            if (isset($_GET['id_sec_selected']) && $_GET['id_sec_selected'] == $id_Sec_del) {
                $_GET['id_sec_selected'] = null;
            }
        }
    }

    // SUPPRESSION DE TOUT L'EMPLOI DU TEMPS DE LA SECTION (AVEC NOTIFICATIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_edt'])) {
        $id_Sec_edt = intval($_POST['id_Sec_edt'] ?? 0);
        if ($id_Sec_edt > 0) {
            $stmt_sec_name = $pdo->prepare("SELECT nom_sec FROM section WHERE id_Sec = ?");
            $stmt_sec_name->execute([$id_Sec_edt]);
            $sec_nom = $stmt_sec_name->fetchColumn();

            $stmt_all_s = $pdo->prepare("SELECT DISTINCT id_prof FROM seance WHERE id_Sec = ? AND id_prof IS NOT NULL");
            $stmt_all_s->execute([$id_Sec_edt]);
            $profs_concernes = $stmt_all_s->fetchAll(PDO::FETCH_COLUMN);

            $stmt_etu = $pdo->prepare("SELECT u.id_user FROM user u JOIN etudiant e ON u.id_user = e.id_user WHERE e.id_Sec = ?");
            $stmt_etu->execute([$id_Sec_edt]);
            $etudiants_users = $stmt_etu->fetchAll(PDO::FETCH_COLUMN);

            $msg_etu = "L'emploi du temps de votre section ($sec_nom) a été entièrement réinitialisé.";
            $stmt_notif = $pdo->prepare("INSERT INTO notification (id_user, type, message) VALUES (?, 'seance', ?)");
            foreach ($etudiants_users as $uid_etu) {
                $stmt_notif->execute([$uid_etu, $msg_etu]);
            }

            foreach ($profs_concernes as $id_prof_concerne) {
                $stmt_p_user = $pdo->prepare("SELECT id_user FROM prof WHERE id_prof = ?");
                $stmt_p_user->execute([$id_prof_concerne]);
                $prof_user_id = $stmt_p_user->fetchColumn();
                if ($prof_user_id) {
                    $msg_prof = "Toutes vos séances pour la section $sec_nom ont été supprimées suite à la réinitialisation de l'emploi du temps.";
                    $stmt_notif->execute([$prof_user_id, $msg_prof]);
                }
            }

            $stmt_del_edt = $pdo->prepare("DELETE FROM seance WHERE id_Sec = ?");
            $stmt_del_edt->execute([$id_Sec_edt]);
            $message = "L'emploi du temps de cette section a été entièrement supprimé avec succès.";
        }
    }

    // 2. Modification du professeur responsable d'un module
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_prof_module'])) {
        $id_mod_target = intval($_POST['id_mod_target'] ?? 0);
        $nom_prof_saisi = trim($_POST['nom_prof_saisi'] ?? '');
        $id_Sec_courante = isset($_GET['id_sec_selected']) ? intval($_GET['id_sec_selected']) : 0;

        if ($id_mod_target > 0 && $id_Sec_courante > 0) {
            if (empty($nom_prof_saisi)) {
                $stmt_del = $pdo->prepare("DELETE FROM enseignement WHERE id_mod = ? AND id_Sec = ?");
                $stmt_del->execute([$id_mod_target, $id_Sec_courante]);
                $message = "Le professeur responsable a été retiré pour cette section.";
            } else {
                $stmt_chk_prof = $pdo->prepare("SELECT p.id_prof FROM prof p JOIN user u ON p.id_user = u.id_user WHERE CONCAT(u.nom, ' ', u.prenom) = ? OR CONCAT(u.prenom, ' ', u.nom) = ? OR u.nom = ?");
                $stmt_chk_prof->execute([$nom_prof_saisi, $nom_prof_saisi, $nom_prof_saisi]);
                $prof_data = $stmt_chk_prof->fetch(PDO::FETCH_ASSOC);

                if ($prof_data) {
                    $id_prof_target = $prof_data['id_prof'];
                    $stmt_ens = $pdo->prepare("INSERT INTO enseignement (id_prof, id_mod, id_Sec) VALUES (?, ?, ?) 
                                               ON DUPLICATE KEY UPDATE id_prof = VALUES(id_prof)");
                    $stmt_ens->execute([$id_prof_target, $id_mod_target, $id_Sec_courante]);
                    $message = "Le professeur responsable de ce module pour cette section a été mis à jour avec succès !";
                } else {
                    $error = "Erreur : Le professeur '$nom_prof_saisi' n'existe pas dans la base de données.";
                }
            }
        } else {
            $error = "Erreur : Section ou module cible invalide.";
        }
    }

    // 3. Enregistrement d'une séance (AVEC NOTIFICATIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_seance'])) {
        $id_Sec = intval($_POST['id_Sec'] ?? 0);
        $jour = trim($_POST['jour'] ?? '');
        $heurD = trim($_POST['heurD'] ?? '');
        $heurF = trim($_POST['heurF'] ?? '');
        $id_mod = intval($_POST['id_mod'] ?? 0);
        $type_seance = trim($_POST['type_seance'] ?? 'cours');
        $id_salle = intval($_POST['id_salle'] ?? 0);
        $nom_prof_saisi = trim($_POST['nom_prof_saisi'] ?? '');
        $id_groupe = !empty($_POST['id_groupe']) ? intval($_POST['id_groupe']) : null;

        $id_prof = null;
        $prof_invalide = false;

        if (!empty($nom_prof_saisi)) {
            $stmt_chk_p = $pdo->prepare("SELECT p.id_prof FROM prof p JOIN user u ON p.id_user = u.id_user WHERE CONCAT(u.nom, ' ', u.prenom) = ? OR CONCAT(u.prenom, ' ', u.nom) = ? OR u.nom = ?");
            $stmt_chk_p->execute([$nom_prof_saisi, $nom_prof_saisi, $nom_prof_saisi]);
            $p_data = $stmt_chk_p->fetch(PDO::FETCH_ASSOC);
            
            if ($p_data) {
                $id_prof = $p_data['id_prof'];
            } else {
                $prof_invalide = true;
            }
        }

        $stmt_sec_info = $pdo->prepare("SELECT nombre_groupes FROM section WHERE id_Sec = ?");
        $stmt_sec_info->execute([$id_Sec]);
        $sec_info = $stmt_sec_info->fetch(PDO::FETCH_ASSOC);

        if ($prof_invalide) {
            $error = "Erreur : Le professeur '$nom_prof_saisi' n'existe pas dans la base de données.";
        } elseif ($type_seance !== 'cours' && (empty($id_groupe) || $id_groupe < 1 || $id_groupe > $sec_info['nombre_groupes'])) {
            $error = "Erreur : Pour un " . strtoupper($type_seance) . ", le groupe doit être compris entre 1 et " . $sec_info['nombre_groupes'] . ".";
        } elseif ($id_Sec > 0 && !empty($jour) && !empty($heurD) && !empty($heurF) && $id_mod > 0 && $id_salle > 0) {
            
            $stmt_conflict_room = $pdo->prepare("SELECT id_Seance FROM seance WHERE id_salle = ? AND jour = ? AND (heurD < ? AND heurF > ?)");
            $stmt_conflict_room->execute([$id_salle, $jour, $heurF, $heurD]);

            if ($type_seance === 'cours') {
                $stmt_conflict_sec = $pdo->prepare("SELECT id_Seance FROM seance WHERE id_Sec = ? AND jour = ? AND (heurD < ? AND heurF > ?)");
                $stmt_conflict_sec->execute([$id_Sec, $jour, $heurF, $heurD]);
            } else {
                $stmt_conflict_sec = $pdo->prepare("SELECT id_Seance FROM seance WHERE id_Sec = ? AND jour = ? AND (heurD < ? AND heurF > ?) AND (id_groupe IS NULL OR id_groupe = ?)");
                $stmt_conflict_sec->execute([$id_Sec, $jour, $heurF, $heurD, $id_groupe]);
            }

            $stmt_conflict_prof = null;
            if ($id_prof !== null) {
                $stmt_conflict_prof = $pdo->prepare("SELECT id_Seance FROM seance WHERE id_prof = ? AND jour = ? AND (heurD < ? AND heurF > ?)");
                $stmt_conflict_prof->execute([$id_prof, $jour, $heurF, $heurD]);
            }

            if ($stmt_conflict_room->rowCount() > 0) {
                $error = "Erreur : Cette salle est déjà occupée sur ce créneau horaire par un autre cours !";
            } elseif ($stmt_conflict_sec->rowCount() > 0) {
                $error = "Erreur : Conflit d'horaire ! Cette section (ou ce groupe) a déjà une séance programmée sur ce créneau.";
            } elseif ($id_prof !== null && $stmt_conflict_prof->rowCount() > 0) {
                $error = "Erreur : Conflit d'horaire ! Ce professeur a déjà une autre séance programmée ce $jour sur ce même créneau.";
            } else {
                $stmt_ins_s = $pdo->prepare("INSERT INTO seance (id_Sec, id_salle, id_prof, id_mod, type_seance, jour, heurD, heurF, id_groupe) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ins_s->execute([$id_Sec, $id_salle, $id_prof, $id_mod, $type_seance, $jour, $heurD, $heurF, ($type_seance === 'cours' ? null : $id_groupe)]);
                
                // --- NOTIFICATION : AJOUT DE SÉANCE ---
                $stmt_m_info = $pdo->prepare("SELECT Nom_mod FROM module WHERE id_mod = ?");
                $stmt_m_info->execute([$id_mod]);
                $mod_nom = $stmt_m_info->fetchColumn();

                $stmt_sec_name = $pdo->prepare("SELECT nom_sec FROM section WHERE id_Sec = ?");
                $stmt_sec_name->execute([$id_Sec]);
                $sec_nom = $stmt_sec_name->fetchColumn();

                $horaire_formate = substr($heurD, 0, 5) . ' - ' . substr($heurF, 0, 5);
                $groupe_texte = ($id_groupe ? " (Groupe $id_groupe)" : "");

                // 1. Notifier les étudiants de la section
                $stmt_etu = $pdo->prepare("SELECT u.id_user FROM user u JOIN etudiant e ON u.id_user = e.id_user WHERE e.id_Sec = ?");
                $stmt_etu->execute([$id_Sec]);
                $etudiants_users = $stmt_etu->fetchAll(PDO::FETCH_COLUMN);

                $msg_etu = "Nouvelle séance ajoutée : $mod_nom ($type_seance$groupe_texte) le $jour de $horaire_formate.";
                $stmt_notif = $pdo->prepare("INSERT INTO notification (id_user, type, message) VALUES (?, 'seance', ?)");
                foreach ($etudiants_users as $uid_etu) {
                    $stmt_notif->execute([$uid_etu, $msg_etu]);
                }

                // 2. Notifier le professeur s'il est assigné
                if (!empty($id_prof)) {
                    $stmt_p_user = $pdo->prepare("SELECT id_user FROM prof WHERE id_prof = ?");
                    $stmt_p_user->execute([$id_prof]);
                    $prof_user_id = $stmt_p_user->fetchColumn();

                    if ($prof_user_id) {
                        $msg_prof = "Nouvelle séance programmée : $mod_nom ($type_seance$groupe_texte) pour la section $sec_nom le $jour de $horaire_formate.";
                        $stmt_notif->execute([$prof_user_id, $msg_prof]);
                    }
                }
                // --- FIN NOTIFICATION ---

                $message = "Séance ajoutée avec succès à l'emploi du temps !";
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires du formulaire.";
        }
    }

    // 4. Suppression d'une séance unitaire (AVEC NOTIFICATIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_seance'])) {
        $id_Seance_del = intval($_POST['id_Seance_to_delete'] ?? 0);
        
        if ($id_Seance_del > 0) {
            $stmt_info_del = $pdo->prepare("
                SELECT s.*, m.Nom_mod, sec.nom_sec 
                FROM seance s 
                JOIN module m ON s.id_mod = m.id_mod 
                JOIN section sec ON s.id_Sec = sec.id_Sec 
                WHERE s.id_Seance = ?
            ");
            $stmt_info_del->execute([$id_Seance_del]);
            $s_info = $stmt_info_del->fetch(PDO::FETCH_ASSOC);

            if ($s_info) {
                $horaire_formate = substr($s_info['heurD'], 0, 5) . ' - ' . substr($s_info['heurF'], 0, 5);
                $groupe_texte = ($s_info['id_groupe'] ? " (Groupe {$s_info['id_groupe']})" : "");
                
                // 1. Notifier les étudiants de la section
                $stmt_etu = $pdo->prepare("SELECT u.id_user FROM user u JOIN etudiant e ON u.id_user = e.id_user WHERE e.id_Sec = ?");
                $stmt_etu->execute([$s_info['id_Sec']]);
                $etudiants_users = $stmt_etu->fetchAll(PDO::FETCH_COLUMN);

                $msg_etu = "Séance annulée : {$s_info['Nom_mod']} ({$s_info['type_seance']}{$groupe_texte}) prévue le {$s_info['jour']} de $horaire_formate.";
                $stmt_notif = $pdo->prepare("INSERT INTO notification (id_user, type, message) VALUES (?, 'seance', ?)");
                foreach ($etudiants_users as $uid_etu) {
                    $stmt_notif->execute([$uid_etu, $msg_etu]);
                }

                // 2. Notifier le professeur associé si présent
                if (!empty($s_info['id_prof'])) {
                    $stmt_p_user = $pdo->prepare("SELECT id_user FROM prof WHERE id_prof = ?");
                    $stmt_p_user->execute([$s_info['id_prof']]);
                    $prof_user_id = $stmt_p_user->fetchColumn();

                    if ($prof_user_id) {
                        $msg_prof = "Séance annulée : {$s_info['Nom_mod']} ({$s_info['type_seance']}{$groupe_texte}) pour la section {$s_info['nom_sec']} prévue le {$s_info['jour']} de $horaire_formate.";
                        $stmt_notif->execute([$prof_user_id, $msg_prof]);
                    }
                }
            }

            $pdo->prepare("DELETE FROM seance WHERE id_Seance = ?")->execute([$id_Seance_del]);
            $message = "Séance retirée de l'emploi du temps.";
        }
    }

    // Données globales
    $specialites = $pdo->query("SELECT * FROM specialite ORDER BY NomSp ASC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT p.id_prof, u.nom, u.prenom FROM prof p JOIN user u ON p.id_user = u.id_user ORDER BY u.nom ASC")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM salle ORDER BY typeSalle, nom_salle")->fetchAll(PDO::FETCH_ASSOC);

    $toutes_seances = $pdo->query("SELECT id_salle, jour, heurD, heurF FROM seance")->fetchAll(PDO::FETCH_ASSOC);

    $filter_sp = trim($_GET['filter_sp'] ?? '');
    $filter_annee = trim($_GET['filter_annee'] ?? '');

    $sql_sec = "SELECT sec.*, sp.NomSp, sp.annee FROM section sec LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp WHERE 1=1";
    $params_sec = [];

    if (!empty($filter_sp)) {
        $sql_sec .= " AND sec.id_sp = ?";
        $params_sec[] = $filter_sp;
    }
    if (!empty($filter_annee)) {
        $sql_sec .= " AND sp.annee = ?";
        $params_sec[] = $filter_annee;
    }

    $stmt_sec = $pdo->prepare($sql_sec);
    $stmt_sec->execute($params_sec);
    $sections = $stmt_sec->fetchAll(PDO::FETCH_ASSOC);

    $id_sec_selected = isset($_GET['id_sec_selected']) ? intval($_GET['id_sec_selected']) : null;

    if ($id_sec_selected) {
        $stmt_sel = $pdo->prepare("SELECT sec.*, sp.NomSp, sp.annee, sp.id_sp FROM section sec LEFT JOIN specialite sp ON sec.id_sp = sp.id_sp WHERE sec.id_Sec = ?");
        $stmt_sel->execute([$id_sec_selected]);
        $selected_section = $stmt_sel->fetch(PDO::FETCH_ASSOC);

        if ($selected_section) {
            $stmt_mods = $pdo->prepare("SELECT m.id_mod, m.Nom_mod, p.semestre, p.coefficient, u.nom as prof_nom, u.prenom as prof_prenom
                                       FROM programme p 
                                       JOIN module m ON p.id_mod = m.id_mod 
                                       LEFT JOIN enseignement e ON m.id_mod = e.id_mod AND e.id_Sec = ?
                                       LEFT JOIN prof pr ON e.id_prof = pr.id_prof
                                       LEFT JOIN user u ON pr.id_user = u.id_user
                                       WHERE p.id_sp = ?
                                       ORDER BY p.semestre ASC, m.Nom_mod ASC");
            $stmt_mods->execute([$selected_section['id_Sec'], $selected_section['id_sp']]);
            $modules_section = $stmt_mods->fetchAll(PDO::FETCH_ASSOC);

            foreach($modules_section as $ms) {
                if(!empty($ms['prof_nom'])) {
                    $default_profs_names[$ms['id_mod']] = trim($ms['prof_nom'] . ' ' . $ms['prof_prenom']);
                }
            }

            $stmt_seances = $pdo->prepare("SELECT s.*, m.Nom_mod, sal.nom_salle, u.nom as prof_nom, u.prenom as prof_prenom, sec_cible.nom_sec as section_nom 
                                           FROM seance s 
                                           JOIN module m ON s.id_mod = m.id_mod 
                                           JOIN salle sal ON s.id_salle = sal.id_salle 
                                           LEFT JOIN prof p ON s.id_prof = p.id_prof
                                           LEFT JOIN user u ON p.id_user = u.id_user
                                           JOIN section sec_cible ON s.id_Sec = sec_cible.id_Sec
                                           WHERE s.id_Sec = ?");
            $stmt_seances->execute([$id_sec_selected]);
            $raw_seances = $stmt_seances->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($raw_seances as $rs) {
                $creneau_key = substr($rs['heurD'], 0, 5) . ' - ' . substr($rs['heurF'], 0, 5);
                $seances_grid[$rs['jour']][$creneau_key][] = $rs;
            }
        }
    }

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}

$jours = ['Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi'];
$creneaux = [
    ['08:00', '09:30'],
    ['09:40', '11:10'],
    ['11:20', '12:50'],
    ['13:00', '14:30'],
    ['14:40', '16:10'],
    ['16:20', '17:50']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Gestion des Sections</title>
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

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 280px; background-color: var(--header); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; box-shadow: 4px 0 10px rgba(0,0,0,0.05); }
        .sidebar-top .logo-area { color: var(--on-blue); font-size: 18px; font-weight: bold; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu-item { color: var(--on-blue); padding: 10px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .menu-item:hover, .menu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background-color: var(--header); color: var(--on-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

        .content-body { padding: 30px; }
        .section-title { color: var(--header); font-size: 22px; margin-bottom: 20px; font-weight: 700; }

        .admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        @media (max-width: 1024px) { .admin-grid { grid-template-columns: 1fr; } }

        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(27,90,128,0.04); }
        .card h3 { color: var(--header); margin-bottom: 15px; font-size: 18px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background-color: #FAFCFE; }

        .btn-submit { background-color: var(--header); color: var(--on-blue); border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background-color: var(--menu); }
        .btn-edit { background-color: var(--menu); color: var(--on-blue); padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; border: none; }
        .btn-edit:hover { background-color: var(--header); }
        .btn-delete { background-color: var(--error); color: var(--on-error); border: none; padding: 6px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { background-color: #f4ebd0; color: var(--header); font-weight: 600; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        .edt-table { border: 1px solid var(--border); }
        .edt-table th, .edt-table td { border: 1px solid var(--border); text-align: center; vertical-align: top; padding: 8px; font-size: 12px; }
        .edt-cell { cursor: pointer; min-height: 70px; background-color: #fafbfc; transition: background 0.2s; }
        .edt-cell:hover { background-color: #f0f7ff; }
        .seance-card { border-left: 5px solid #1B5A80; background: #eef5f9; color: var(--text); border-radius: 6px; padding: 7px 8px; margin-bottom: 6px; text-align: left; font-size: 13px; line-height: 1.5; position: relative; padding-right: 34px; }
        .seance-card.TD { border-color: #2e7d32; background: #eef7ee; }
        .seance-card.TP { border-color: #8a5300; background: #fbf1e3; }
        .seance-card strong { display: block; font-size: 13px; }
        .type-pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 99px; color: #fff; background: #1B5A80; margin-bottom: 3px; }
        .type-pill.TD { background: #2e7d32; }
        .type-pill.TP { background: #8a5300; }
        .seance-card form { position: absolute; top: 6px; right: 6px; margin: 0; }
        .btn-del-x { background: transparent; color: var(--error); border: none; width: 24px; height: 24px; border-radius: 50%; font-size: 14px; font-weight: 700; line-height: 1; cursor: pointer; }
        .btn-del-x:hover { background: var(--error); color: var(--on-error); }

        .badge-status { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-assigned { background-color: #d4edda; color: #155724; }
        .badge-unassigned { background-color: #fff3cd; color: #856404; }

        #modalSeance, #modalModifierSection { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 10px; width: 450px; max-width: 90%; }
    
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
        .dropdown-header { font-weight: bold; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); font-size: 13px; color: #64748b; }
        .dropdown-item { display: block; width: 100%; padding: 8px 10px; text-align: left; background: none; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; color: var(--text); margin-bottom: 5px; }
        .dropdown-item:hover { background-color: var(--bg); }
        .dropdown-item.logout { background-color: var(--error); color: var(--on-error); text-align: center; }

        .menu-dropdown { position: relative; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .submenu { flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px; margin-bottom: 4px; border-left: 2px solid rgba(255, 255, 255, 0.2); }
        .submenu-item { color: rgba(255, 255, 255, 0.85); padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 400; display: flex; align-items: center; gap: 8px; }
        .submenu-item:hover, .submenu-item.active { background-color: var(--accent); color: var(--on-accent); font-weight: 600; }
    </style>
</head>
<body>

    <datalist id="liste_professeurs">
        <?php foreach ($profs as $p): ?>
            <option value="<?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>">
        <?php endforeach; ?>
    </datalist>

    <div class="sidebar">
        <div class="sidebar-top">
            <div class="logo-area"><span>🎓 Smart Campus</span></div>
            <div class="menu">
                <?php if (!empty($_SESSION['is_super_admin'])): ?>
                    <a href="admin_principal_dashboard.php" class="menu-item">👥 Agents de scolarité</a>
                <?php endif; ?>
                <a href="gestion_profs.php" class="menu-item">👨‍🏫 Professeurs</a>
                <a href="gestion_etudiants.php" class="menu-item">🎓 Étudiants</a>
                <a href="gestion_sections.php" class="menu-item active">🏢 Sections</a>
                <a href="gestion_specialites.php" class="menu-item">📚 Spécialités & Départements</a>
                <a href="gestion_examens.php" class="menu-item">📅 Plannings d'examens</a>
                <div class="menu-dropdown">
                    <a href="javascript:void(0);" class="menu-item dropdown-toggle" onclick="toggleSubmenu()">
                        <span>📢 Notes, Absences & Annonces</span> 
                        <span id="arrow" style="font-size: 11px;">▾</span>
                    </a>
                    <div class="submenu" id="submenuNotes" style="display: none;">
                        <a href="gestion_notes.php" class="submenu-item">📊 Notes & Moyennes</a>
                        <a href="gestion_absences.php" class="submenu-item">🚫 Absences</a>
                        <a href="gestion_annonces.php" class="submenu-item">📢 Annonces</a>
                    </div>
                </div> 
              </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <h2>Espace Scolarité - Gestion des Sections et Emplois du temps</h2>

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
        </div>

        <div class="content-body">
            <h3 class="section-title">Gestion des Sections et de la Planification</h3>

            <?php if (!empty($message)): ?><div class="alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="admin-grid">
                <div class="card">
                    <h3>Ajouter une section</h3>
                    <form action="gestion_sections.php" method="POST">
                        <div class="form-group">
                            <label for="nom_sec">Nom de la section</label>
                            <input type="text" id="nom_sec" name="nom_sec" required placeholder="Ex: Section A">
                        </div>
                        <div class="form-group">
                            <label for="id_sp">Spécialité</label>
                            <select id="id_sp" name="id_sp" required>
                                <option value="">-- Choisir une spécialité --</option>
                                <?php foreach ($specialites as $sp): ?>
                                    <option value="<?php echo $sp['id_sp']; ?>"><?php echo htmlspecialchars($sp['NomSp'] . ' (Année ' . $sp['annee'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nombre_groupes">Nombre de groupes</label>
                            <input type="number" id="nombre_groupes" name="nombre_groupes" min="1" max="10" value="4" required>
                        </div>
                        <button type="submit" name="ajouter_section" class="btn-submit">Créer la section</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Rechercher / Filtrer les sections</h3>
                    <form action="gestion_sections.php" method="GET">
                        <div class="form-group">
                            <label for="filter_sp">Filtrer par Spécialité</label>
                            <select id="filter_sp" name="filter_sp">
                                <option value="">-- Toutes les spécialités --</option>
                                <?php foreach ($specialites as $sp): ?>
                                    <option value="<?php echo $sp['id_sp']; ?>" <?php echo ($filter_sp == $sp['id_sp']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sp['NomSp']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_annee">Filtrer par Année</label>
                            <input type="number" id="filter_annee" name="filter_annee" min="1" max="5" value="<?php echo htmlspecialchars($filter_annee); ?>" placeholder="Ex: 2">
                        </div>
                        <button type="submit" class="btn-submit" style="background-color: var(--menu);">Filtrer</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h3>Sections enregistrées</h3>
                <?php if (empty($sections)): ?>
                    <p style="color: var(--text-muted);">Aucune section trouvée.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom de la Section</th>
                                <th>Spécialité</th>
                                <th>Année</th>
                                <th>Nb Groupes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sections as $sec): ?>
                                <tr style="<?php echo ($selected_section && $selected_section['id_Sec'] == $sec['id_Sec']) ? 'background-color: #fdf8e9;' : ''; ?>">
                                    <td><?php echo htmlspecialchars($sec['nom_sec']); ?></td>
                                    <td><?php echo htmlspecialchars($sec['NomSp']); ?></td>
                                    <td>Année <?php echo htmlspecialchars($sec['annee']); ?></td>
                                    <td><?php echo htmlspecialchars($sec['nombre_groupes']); ?> groupe(s)</td>
                                    <td style="display: flex; gap: 8px; align-items: center;">
                                        <a href="gestion_sections.php?id_sec_selected=<?php echo $sec['id_Sec']; ?>&filter_sp=<?php echo $filter_sp; ?>&filter_annee=<?php echo $filter_annee; ?>" class="btn-edit">
                                            ⚙️ Gérer l'EDT & Profs
                                        </a>
                                        <button type="button" class="btn-edit" style="background-color: var(--header);" onclick="ouvrirModalModifierSection(<?php echo $sec['id_Sec']; ?>, '<?php echo htmlspecialchars(addslashes($sec['nom_sec'])); ?>', '<?php echo $sec['id_sp']; ?>', <?php echo $sec['nombre_groupes']; ?>)">
                                            ✏️ Modifier
                                        </button>
                                        <form action="gestion_sections.php?filter_sp=<?php echo $filter_sp; ?>&filter_annee=<?php echo $filter_annee; ?>" method="POST" style="display:inline; margin:0;">
                                            <input type="hidden" name="id_Sec_to_delete" value="<?php echo $sec['id_Sec']; ?>">
                                            <button type="submit" name="supprimer_section" class="btn-delete" onclick="return confirm('Attention : La suppression de cette section supprimera également ses séances associées. Voulez-vous continuer ?');">
                                                🗑️ Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ($selected_section): ?>
                <div class="card" style="border: 2px solid var(--accent); background-color: #fffdf9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--header);">
                            ⚙️ Section : <span style="color: var(--menu);"><?php echo htmlspecialchars($selected_section['nom_sec']); ?></span> (<?php echo htmlspecialchars($selected_section['NomSp']); ?>)
                        </h3>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <form action="gestion_sections.php?id_sec_selected=<?php echo $selected_section['id_Sec']; ?>&filter_sp=<?php echo $filter_sp; ?>&filter_annee=<?php echo $filter_annee; ?>" method="POST" style="margin:0;" onsubmit="return confirm('Attention : Voulez-vous vraiment supprimer tout l\'emploi du temps de cette section ? Cette action est irréversible.');">
                                <input type="hidden" name="id_Sec_edt" value="<?php echo $selected_section['id_Sec']; ?>">
                                <button type="submit" name="supprimer_edt" class="btn-delete" style="padding: 7px 12px; font-size: 13px;">
                                    🗑️ Supprimer tout l'EDT
                                </button>
                            </form>
                            <a href="gestion_sections.php" style="color: var(--text-muted); text-decoration: none; font-size: 13px;">❌ Fermer</a>
                        </div>
                    </div>

                    <!-- BLOC PROFESSEURS ASSOCIÉS AUX MODULES -->
                    <div class="card" style="border: 1px solid var(--header); background: #fff; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="color: var(--header); font-size: 16px;">👨‍🏫 Attribution des Professeurs Responsables par Module (Pour cette section)</h4>
                            <span style="font-size: 12px; color: var(--text-muted);">Tapez le nom et prénom de l'enseignant</span>
                        </div>

                        <?php if (empty($modules_section)): ?>
                            <p style="color: var(--text-muted);">Aucun module trouvé dans le programme de cette spécialité.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Module</th>
                                        <th style="width: 10%;">Semestre</th>
                                        <th style="width: 10%;">Coeff</th>
                                        <th style="width: 15%;">Statut actuel</th>
                                        <th style="width: 40%;">Nom du professeur (Saisie validée)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules_section as $ms): 
                                        $nom_prof_actuel = (!empty($ms['prof_nom'])) ? trim($ms['prof_nom'] . ' ' . $ms['prof_prenom']) : '';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ms['Nom_mod']); ?></strong></td>
                                            <td><span class="type-pill">S<?php echo htmlspecialchars($ms['semestre']); ?></span></td>
                                            <td>Coeff <?php echo htmlspecialchars($ms['coefficient']); ?></td>
                                            <td>
                                                <?php if (!empty($nom_prof_actuel)): ?>
                                                    <span class="badge-status badge-assigned">✓ <?php echo htmlspecialchars($nom_prof_actuel); ?></span>
                                                <?php else: ?>
                                                    <span class="badge-status badge-unassigned">⚠ Non assigné</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form action="gestion_sections.php?id_sec_selected=<?php echo $selected_section['id_Sec']; ?>&filter_sp=<?php echo $filter_sp; ?>&filter_annee=<?php echo $filter_annee; ?>" method="POST" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                                                    <input type="hidden" name="id_mod_target" value="<?php echo $ms['id_mod']; ?>">
                                                    <input type="text" name="nom_prof_saisi" list="liste_professeurs" value="<?php echo htmlspecialchars($nom_prof_actuel); ?>" placeholder="Tapez le nom du prof..." style="padding: 7px; border-radius: 6px; border: 1px solid var(--border); flex: 1; background: #FAFCFE; font-size: 13px;">
                                                    <button type="submit" name="modifier_prof_module" class="btn-submit" style="width: auto; padding: 7px 12px; font-size: 12px; background-color: var(--menu); white-space: nowrap;">
                                                        💾 Enregistrer
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <h4 style="margin-bottom: 10px; color: var(--header);">📅 Emploi du temps (Cliquez sur une case pour ajouter une séance)</h4>

                    <div style="overflow-x: auto;">
                        <table class="edt-table">
                            <thead>
                                <tr>
                                    <th>Horaire</th>
                                    <?php foreach ($jours as $j): ?>
                                        <th><?php echo $j; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($creneaux as $c): 
                                    $horaire_str = $c[0] . ' - ' . $c[1];
                                ?>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa; min-width: 110px;"><?php echo $horaire_str; ?></td>
                                        <?php foreach ($jours as $j): ?>
                                            <td class="edt-cell" onclick="ouvrirModalSeance('<?php echo $j; ?>', '<?php echo $c[0]; ?>', '<?php echo $c[1]; ?>')">
                                                <?php 
                                                if (isset($seances_grid[$j][$horaire_str])) {
                                                    foreach ($seances_grid[$j][$horaire_str] as $s) {
                                                        echo '<div class="seance-card ' . htmlspecialchars($s['type_seance']) . '">';
                                                        echo '<span class="type-pill ' . htmlspecialchars($s['type_seance']) . '">' . htmlspecialchars(strtoupper($s['type_seance'])) . ($s['id_groupe'] ? ' · G' . (int)$s['id_groupe'] : '') . '</span>';
                                                        echo '<strong>' . htmlspecialchars($s['Nom_mod']) . '</strong>';
                                                        echo '🏛️ ' . htmlspecialchars($s['nom_salle']) . '<br>';
                                                        $affichage_prof = (!empty($s['prof_nom'])) ? htmlspecialchars($s['prof_nom'] . ' ' . $s['prof_prenom']) : '<em style="color: var(--text-muted);">Non assigné</em>';
                                                        echo '👨‍🏫 ' . $affichage_prof;
                                                        echo '<form method="POST" action="gestion_sections.php?id_sec_selected=' . $selected_section['id_Sec'] . '">
                                                        <input type="hidden" name="id_Seance_to_delete" value="' . $s['id_Seance'] . '">
                                                        <button type="submit" name="supprimer_seance" class="btn-del-x" title="Supprimer cette séance" aria-label="Supprimer cette séance" onclick="event.stopPropagation(); return confirm(\'Supprimer cette séance ?\');">✕</button>
                                                        </form>';
                                                        echo '</div>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Modale de planification de séance -->
    <?php if ($selected_section): ?>
    <div id="modalSeance">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; color: var(--header);">Planifier une séance</h3>
            <form action="gestion_sections.php?id_sec_selected=<?php echo $selected_section['id_Sec']; ?>" method="POST">
                <input type="hidden" name="id_Sec" value="<?php echo $selected_section['id_Sec']; ?>">
                
                <div class="form-group">
                    <label>Jour & Créneau</label>
                    <input type="text" id="modal_jour_horaire_display" readonly style="background-color: #eee;">
                    <input type="hidden" id="modal_jour" name="jour">
                    <input type="hidden" id="heurD" name="heurD">
                    <input type="hidden" id="heurF" name="heurF">
                </div>

                <div class="form-group">
                    <label for="modal_id_mod">Module</label>
                    <select id="modal_id_mod" name="id_mod" onchange="autoSelectProfName()" required>
                        <option value="">-- Choisir un module --</option>
                        <?php foreach ($modules_section as $ms): ?>
                            <option value="<?php echo $ms['id_mod']; ?>"><?php echo htmlspecialchars($ms['Nom_mod'] . ' (Semestre ' . $ms['semestre'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_type_seance">Type de séance</label>
                    <select id="modal_type_seance" name="type_seance" onchange="filtrerSallesEtGroupe()" required>
                        <option value="cours">Cours</option>
                        <option value="TD">TD</option>
                        <option value="TP">TP</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_id_salle">Salle libre disponible</label>
                    <select id="modal_id_salle" name="id_salle" required>
                        <option value="">-- Sélectionner une salle --</option>
                        <?php foreach ($salles as $sal): ?>
                            <option value="<?php echo $sal['id_salle']; ?>" data-id="<?php echo $sal['id_salle']; ?>" data-type="<?php echo $sal['typeSalle']; ?>">
                                <?php echo htmlspecialchars($sal['nom_salle'] . ' (' . $sal['typeSalle'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_nom_prof">Professeur responsable (Optionnel)</label>
                    <input type="text" id="modal_nom_prof" name="nom_prof_saisi" list="liste_professeurs" placeholder="Laisser vide ou taper le nom du prof...">
                </div>

                <div class="form-group" id="groupe_container" style="display:none;">
                    <label for="modal_id_groupe">Numéro du groupe (Max : <?php echo $selected_section['nombre_groupes']; ?>)</label>
                    <input type="number" id="modal_id_groupe" name="id_groupe" min="1" max="<?php echo $selected_section['nombre_groupes']; ?>" placeholder="Ex: 1">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="ajouter_seance" class="btn-submit">Valider et planifier</button>
                    <button type="button" onclick="fermerModalSeance()" class="btn-submit" style="background-color: #6c757d;">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modale de modification d'une section -->
    <div id="modalModifierSection">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; color: var(--header);">Modifier la section</h3>
            <form action="gestion_sections.php?filter_sp=<?php echo $filter_sp; ?>&filter_annee=<?php echo $filter_annee; ?>" method="POST">
                <input type="hidden" id="edit_id_Sec" name="id_Sec">
                
                <div class="form-group">
                    <label for="edit_nom_sec">Nom de la section</label>
                    <input type="text" id="edit_nom_sec" name="nom_sec" required>
                </div>

                <div class="form-group">
                    <label for="edit_id_sp">Spécialité</label>
                    <select id="edit_id_sp" name="id_sp" required>
                        <option value="">-- Choisir une spécialité --</option>
                        <?php foreach ($specialites as $sp): ?>
                            <option value="<?php echo $sp['id_sp']; ?>"><?php echo htmlspecialchars($sp['NomSp'] . ' (Année ' . $sp['annee'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_nombre_groupes">Nombre de groupes</label>
                    <input type="number" id="edit_nombre_groupes" name="nombre_groupes" min="1" max="10" required>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="modifier_section" class="btn-submit">Enregistrer les modifications</button>
                    <button type="button" onclick="fermerModalModifierSection()" class="btn-submit" style="background-color: #6c757d;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var defaultProfsNames = <?php echo json_encode($default_profs_names); ?>;
        var toutesSeances = <?php echo json_encode($toutes_seances); ?>;
        var currentJour = '';
        var currentHeurD = '';
        var currentHeurF = '';

        function ouvrirModalModifierSection(id, nom, idSp, nbGroupes) {
            document.getElementById('edit_id_Sec').value = id;
            document.getElementById('edit_nom_sec').value = nom;
            document.getElementById('edit_id_sp').value = idSp;
            document.getElementById('edit_nombre_groupes').value = nbGroupes;
            document.getElementById('modalModifierSection').style.display = 'flex';
        }

        function fermerModalModifierSection() {
            document.getElementById('modalModifierSection').style.display = 'none';
        }

        function ouvrirModalSeance(jour, heurD, heurF) {
            currentJour = jour;
            currentHeurD = heurD + ':00';
            currentHeurF = heurF + ':00';

            document.getElementById('modal_jour').value = jour;
            document.getElementById('heurD').value = currentHeurD;
            document.getElementById('heurF').value = currentHeurF;
            document.getElementById('modal_jour_horaire_display').value = jour + ' | ' + heurD + ' - ' + heurF;
            document.getElementById('modalSeance').style.display = 'flex';
            filtrerSallesEtGroupe();
        }

        function fermerModalSeance() {
            document.getElementById('modalSeance').style.display = 'none';
        }

        function autoSelectProfName() {
            var modId = document.getElementById('modal_id_mod').value;
            var profInput = document.getElementById('modal_nom_prof');
            if (defaultProfsNames[modId]) {
                profInput.value = defaultProfsNames[modId];
            } else {
                profInput.value = '';
            }
        }

        function filtrerSallesEtGroupe() {
            var typeSeance = document.getElementById('modal_type_seance').value;
            var salleSelect = document.getElementById('modal_id_salle');
            var options = salleSelect.options;

            var sallesOccupees = [];
            toutesSeances.forEach(function(s) {
                if (s.jour === currentJour) {
                    if (s.heurD < currentHeurF && s.heurF > currentHeurD) {
                        sallesOccupees.push(parseInt(s.id_salle));
                    }
                }
            });

            for (var i = 0; i < options.length; i++) {
                var opt = options[i];
                var optType = opt.getAttribute('data-type');
                var optId = parseInt(opt.getAttribute('data-id'));
                
                if (!optType) continue;

                var matchType = false;

                if (typeSeance === 'cours' && optType === 'cours') {
                    matchType = true;
                } else if (typeSeance === 'TD' && optType === 'TD') {
                    matchType = true;
                } else if (typeSeance === 'TP' && optType === 'TP' ) {
                    matchType = true;
                }

                if (matchType && !sallesOccupees.includes(optId)) {
                    opt.style.display = 'block';
                    opt.disabled = false;
                } else {
                    opt.style.display = 'none';
                    opt.disabled = true;
                }
            }
            salleSelect.value = '';

            var groupeContainer = document.getElementById('groupe_container');
            var groupeInput = document.getElementById('modal_id_groupe');
            if (typeSeance === 'TD' || typeSeance === 'TP') {
                groupeContainer.style.display = 'block';
                groupeInput.required = true;
            } else {
                groupeContainer.style.display = 'none';
                groupeInput.required = false;
                groupeInput.value = '';
            }
        }
        
        function toggleUserMenu(event) {
            event.stopPropagation();
            var userDropdown = document.getElementById('userDropdown');
            userDropdown.classList.toggle('active');
            var notifDropdown = document.getElementById('notifDropdown');
            if (notifDropdown) notifDropdown.classList.remove('active');
        }

        function toggleNotifMenu(event) {
            event.stopPropagation();
            var notifDropdown = document.getElementById('notifDropdown');
            notifDropdown.classList.toggle('active');
            var userDropdown = document.getElementById('userDropdown');
            if (userDropdown) userDropdown.classList.remove('active');
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
    </script>
</body>
</html>