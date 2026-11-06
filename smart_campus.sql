
SET NAMES utf8mb4;
SET time_zone = "+00:00";
 
-- ---------------------------------------------------------------------
-- 1. user
-- ---------------------------------------------------------------------
CREATE TABLE `user` (
  `id_user` varchar(100) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mdP` varchar(255) NOT NULL,              -- stocker un hash (bcrypt/argon2), jamais le mot de passe en clair
  `role` enum('etudiant','prof','scolarite') NOT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 2. departement
-- ---------------------------------------------------------------------
CREATE TABLE `departement` (
  `id_Dep` int NOT NULL AUTO_INCREMENT,
  `Nom_Dep` varchar(100) NOT NULL,
  PRIMARY KEY (`id_Dep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 3. specialite
-- ---------------------------------------------------------------------
CREATE TABLE `specialite` (
  `id_sp` int NOT NULL AUTO_INCREMENT,
  `NomSp` varchar(100) NOT NULL,
  `id_Dep` int DEFAULT NULL,
  PRIMARY KEY (`id_sp`),
  KEY `id_Dep` (`id_Dep`),
  CONSTRAINT `specialite_ibfk_1` FOREIGN KEY (`id_Dep`) REFERENCES `departement` (`id_Dep`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 4. section
-- ---------------------------------------------------------------------
CREATE TABLE `section` (
  `id_Sec` int NOT NULL AUTO_INCREMENT,
  `nom_sec` varchar(100) NOT NULL,
  `id_sp` int DEFAULT NULL,
  PRIMARY KEY (`id_Sec`),
  KEY `id_sp` (`id_sp`),
  CONSTRAINT `section_ibfk_1` FOREIGN KEY (`id_sp`) REFERENCES `specialite` (`id_sp`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 5. groupe (NOUVEAU) : remplace les deux colonnes "groupe" incompatibles
-- ---------------------------------------------------------------------
CREATE TABLE `groupe` (
  `id_groupe` int NOT NULL AUTO_INCREMENT,
  `nom_groupe` varchar(20) NOT NULL,        -- ex : 'G1', 'G2'
  `id_Sec` int NOT NULL,
  PRIMARY KEY (`id_groupe`),
  UNIQUE KEY `uq_groupe_section` (`id_Sec`,`nom_groupe`),
  CONSTRAINT `groupe_ibfk_1` FOREIGN KEY (`id_Sec`) REFERENCES `section` (`id_Sec`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 6. prof
-- ---------------------------------------------------------------------
CREATE TABLE `prof` (
  `id_prof` int NOT NULL AUTO_INCREMENT,
  `id_user` varchar(100) NOT NULL,
  `id_Dep` int DEFAULT NULL,
  `grade` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_prof`),
  UNIQUE KEY `uq_prof_user` (`id_user`),
  KEY `id_Dep` (`id_Dep`),
  CONSTRAINT `prof_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `prof_ibfk_2` FOREIGN KEY (`id_Dep`) REFERENCES `departement` (`id_Dep`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 7. etudiant
-- ---------------------------------------------------------------------
CREATE TABLE `etudiant` (
  `id_Etu` int NOT NULL AUTO_INCREMENT,
  `id_user` varchar(100) NOT NULL,
  `id_sp` int DEFAULT NULL,
  `id_Sec` int DEFAULT NULL,
  `id_groupe` int DEFAULT NULL,             -- remplace l'ancien varchar "groupe"
  `annee` tinyint DEFAULT NULL,
  PRIMARY KEY (`id_Etu`),
  UNIQUE KEY `uq_etudiant_user` (`id_user`),
  KEY `id_sp` (`id_sp`),
  KEY `id_Sec` (`id_Sec`),
  KEY `id_groupe` (`id_groupe`),
  CONSTRAINT `chk_annee_etudiant` CHECK (`annee` BETWEEN 1 AND 5),
  CONSTRAINT `etudiant_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `etudiant_ibfk_2` FOREIGN KEY (`id_sp`) REFERENCES `specialite` (`id_sp`),
  CONSTRAINT `etudiant_ibfk_3` FOREIGN KEY (`id_Sec`) REFERENCES `section` (`id_Sec`),
  CONSTRAINT `etudiant_ibfk_4` FOREIGN KEY (`id_groupe`) REFERENCES `groupe` (`id_groupe`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 8. scolarite
-- ---------------------------------------------------------------------
CREATE TABLE `scolarite` (
  `id_sco` int NOT NULL AUTO_INCREMENT,
  `id_user` varchar(100) NOT NULL,
  `post_Admin` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_sco`),
  UNIQUE KEY `uq_scolarite_user` (`id_user`),
  CONSTRAINT `scolarite_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 9. salle
-- ---------------------------------------------------------------------
CREATE TABLE `salle` (
  `id_salle` int NOT NULL AUTO_INCREMENT,
  `nom_salle` varchar(50) NOT NULL,         -- NOUVEAU : ex 'Amphi A', 'Salle 12'
  `capacite` int DEFAULT NULL,              -- ancien "Capacité" (accent supprime)
  `typeSalle` enum('amphi','salle_cours','salle_TD','labo','salle_info') NOT NULL DEFAULT 'salle_cours',
  PRIMARY KEY (`id_salle`),
  UNIQUE KEY `uq_nom_salle` (`nom_salle`),
  CONSTRAINT `chk_capacite` CHECK (`capacite` IS NULL OR `capacite` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 10. module (+ semestre et coefficient)
-- ---------------------------------------------------------------------
CREATE TABLE `module` (
  `id_mod` int NOT NULL AUTO_INCREMENT,
  `Nom_mod` varchar(100) NOT NULL,
  `id_sp` int DEFAULT NULL,
  `semestre` tinyint NOT NULL DEFAULT 1,
  `coefficient` decimal(3,1) NOT NULL DEFAULT 1.0,
  PRIMARY KEY (`id_mod`),
  KEY `id_sp` (`id_sp`),
  CONSTRAINT `chk_semestre` CHECK (`semestre` BETWEEN 1 AND 2),
  CONSTRAINT `chk_coefficient` CHECK (`coefficient` > 0),
  CONSTRAINT `module_ibfk_1` FOREIGN KEY (`id_sp`) REFERENCES `specialite` (`id_sp`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 11. enseignement (NOUVEAU) : qui enseigne quel module
-- ---------------------------------------------------------------------
CREATE TABLE `enseignement` (
  `id_prof` int NOT NULL,
  `id_mod` int NOT NULL,
  PRIMARY KEY (`id_prof`,`id_mod`),
  KEY `id_mod` (`id_mod`),
  CONSTRAINT `enseignement_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`) ON DELETE CASCADE,
  CONSTRAINT `enseignement_ibfk_2` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 12. seance (+ type_seance, jour en ENUM, groupe en cle etrangere)
--     cours : id_groupe = NULL (toute la section)
--     TD/TP : id_groupe obligatoire
-- ---------------------------------------------------------------------
CREATE TABLE `seance` (
  `id_Seance` int NOT NULL AUTO_INCREMENT,
  `id_salle` int NOT NULL,
  `id_prof` int NOT NULL,
  `id_section` int NOT NULL,
  `id_groupe` int DEFAULT NULL,
  `id_mod` int NOT NULL,
  `type_seance` enum('cours','TD','TP') NOT NULL DEFAULT 'cours',
  `jour` enum('Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
  `heurD` time NOT NULL,
  `heurF` time NOT NULL,
  PRIMARY KEY (`id_Seance`),
  KEY `id_salle` (`id_salle`),
  KEY `id_prof` (`id_prof`),
  KEY `id_section` (`id_section`),
  KEY `id_groupe` (`id_groupe`),
  KEY `id_mod` (`id_mod`),
  CONSTRAINT `chk_seance_heures` CHECK (`heurF` > `heurD`),
  CONSTRAINT `seance_ibfk_1` FOREIGN KEY (`id_salle`) REFERENCES `salle` (`id_salle`),
  CONSTRAINT `seance_ibfk_2` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`),
  CONSTRAINT `seance_ibfk_3` FOREIGN KEY (`id_section`) REFERENCES `section` (`id_Sec`),
  CONSTRAINT `seance_ibfk_4` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`),
  CONSTRAINT `seance_ibfk_5` FOREIGN KEY (`id_groupe`) REFERENCES `groupe` (`id_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 13. note : PK (etudiant, module) -> une seule note par module
-- ---------------------------------------------------------------------
CREATE TABLE `note` (
  `id_Etud` int NOT NULL,
  `id_Mod` int NOT NULL,
  `id_prof` int NOT NULL,                   -- prof qui a saisi la note
  `Exam` float DEFAULT NULL,
  `TD` float DEFAULT NULL,
  `TP` float DEFAULT NULL,
  PRIMARY KEY (`id_Etud`,`id_Mod`),
  KEY `id_Mod` (`id_Mod`),
  KEY `id_prof` (`id_prof`),
  CONSTRAINT `chk_note_exam` CHECK (`Exam` IS NULL OR `Exam` BETWEEN 0 AND 20),
  CONSTRAINT `chk_note_td` CHECK (`TD` IS NULL OR `TD` BETWEEN 0 AND 20),
  CONSTRAINT `chk_note_tp` CHECK (`TP` IS NULL OR `TP` BETWEEN 0 AND 20),
  CONSTRAINT `note_ibfk_1` FOREIGN KEY (`id_Etud`) REFERENCES `etudiant` (`id_Etu`) ON DELETE CASCADE,
  CONSTRAINT `note_ibfk_2` FOREIGN KEY (`id_Mod`) REFERENCES `module` (`id_mod`) ON DELETE CASCADE,
  CONSTRAINT `note_ibfk_3` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 14. annonce (+ cible, + section optionnelle)
-- ---------------------------------------------------------------------
CREATE TABLE `annonce` (
  `id_Anm` int NOT NULL AUTO_INCREMENT,
  `id_sco` int DEFAULT NULL,
  `titre` varchar(150) NOT NULL,
  `det` text NOT NULL,
  `Date_Pub` date NOT NULL,
  `cible` enum('tous','etudiants','profs') NOT NULL DEFAULT 'tous',
  `id_Sec` int DEFAULT NULL,                -- NULL = toutes les sections
  PRIMARY KEY (`id_Anm`),
  KEY `id_sco` (`id_sco`),
  KEY `id_Sec` (`id_Sec`),
  CONSTRAINT `annonce_ibfk_1` FOREIGN KEY (`id_sco`) REFERENCES `scolarite` (`id_sco`) ON DELETE CASCADE,
  CONSTRAINT `annonce_ibfk_2` FOREIGN KEY (`id_Sec`) REFERENCES `section` (`id_Sec`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 15. ressource (+ type_res)
-- ---------------------------------------------------------------------
CREATE TABLE `ressource` (
  `id_R` int NOT NULL AUTO_INCREMENT,
  `id_prof` int DEFAULT NULL,
  `id_sect` int DEFAULT NULL,
  `id_module` int DEFAULT NULL,
  `titre` varchar(150) DEFAULT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `type_res` enum('cours','td','tp','examen_corrige','video','autre') NOT NULL DEFAULT 'cours',
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_R`),
  KEY `id_prof` (`id_prof`),
  KEY `id_sect` (`id_sect`),
  KEY `id_module` (`id_module`),
  CONSTRAINT `ressource_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`),
  CONSTRAINT `ressource_ibfk_2` FOREIGN KEY (`id_sect`) REFERENCES `section` (`id_Sec`),
  CONSTRAINT `ressource_ibfk_3` FOREIGN KEY (`id_module`) REFERENCES `module` (`id_mod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 16. exam (+ salle, + type_exam)
-- ---------------------------------------------------------------------
CREATE TABLE `exam` (
  `id_exam` int NOT NULL AUTO_INCREMENT,
  `id_mod` int NOT NULL,
  `id_sec` int NOT NULL,
  `id_prof` int NOT NULL,
  `id_salle` int DEFAULT NULL,
  `type_exam` enum('normal','rattrapage') NOT NULL DEFAULT 'normal',
  `dateEx` date NOT NULL,
  `heurD` time DEFAULT NULL,
  `heurF` time DEFAULT NULL,
  PRIMARY KEY (`id_exam`),
  KEY `id_mod` (`id_mod`),
  KEY `id_prof` (`id_prof`),
  KEY `id_sec` (`id_sec`),
  KEY `id_salle` (`id_salle`),
  CONSTRAINT `chk_exam_heures` CHECK (`heurD` IS NULL OR `heurF` IS NULL OR `heurF` > `heurD`),
  CONSTRAINT `exam_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`),
  CONSTRAINT `exam_ibfk_2` FOREIGN KEY (`id_sec`) REFERENCES `section` (`id_Sec`),
  CONSTRAINT `exam_ibfk_3` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`),
  CONSTRAINT `exam_ibfk_4` FOREIGN KEY (`id_salle`) REFERENCES `salle` (`id_salle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 17. liste_appel (NOUVEAU) : une liste = une seance a une date donnee
-- ---------------------------------------------------------------------
CREATE TABLE `liste_appel` (
  `id_liste` int NOT NULL AUTO_INCREMENT,
  `id_Seance` int NOT NULL,
  `id_prof` int NOT NULL,                   -- prof qui a fait l'appel
  `date_appel` date NOT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_liste`),
  UNIQUE KEY `uq_liste` (`id_Seance`,`date_appel`),   -- une seule liste par seance et par jour
  KEY `id_prof` (`id_prof`),
  CONSTRAINT `liste_appel_ibfk_1` FOREIGN KEY (`id_Seance`) REFERENCES `seance` (`id_Seance`) ON DELETE CASCADE,
  CONSTRAINT `liste_appel_ibfk_2` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 18. ligne_appel (NOUVEAU) : un etudiant par ligne (present / absent)
--     'justification' n'a de sens que si presence = 'absent'
-- ---------------------------------------------------------------------
CREATE TABLE `ligne_appel` (
  `id_liste` int NOT NULL,
  `id_Etu` int NOT NULL,
  `presence` enum('present','absent') NOT NULL DEFAULT 'present',
  `justification` enum('non_justifiee','en_attente','justifiee') NOT NULL DEFAULT 'non_justifiee',
  `motif` varchar(255) DEFAULT NULL,
  `justificatif` varchar(255) DEFAULT NULL, -- lien du fichier depose
  PRIMARY KEY (`id_liste`,`id_Etu`),
  KEY `id_Etu` (`id_Etu`),
  CONSTRAINT `ligne_appel_ibfk_1` FOREIGN KEY (`id_liste`) REFERENCES `liste_appel` (`id_liste`) ON DELETE CASCADE,
  CONSTRAINT `ligne_appel_ibfk_2` FOREIGN KEY (`id_Etu`) REFERENCES `etudiant` (`id_Etu`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- ---------------------------------------------------------------------
-- 19. notification (NOUVEAU) : personnelle, avec etat lu / non lu
-- ---------------------------------------------------------------------
CREATE TABLE `notification` (
  `id_notif` int NOT NULL AUTO_INCREMENT,
  `id_user` varchar(100) NOT NULL,
  `type` enum('absence','exclusion','examen','note','annonce','seance','autre') NOT NULL DEFAULT 'autre',
  `message` varchar(255) NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notif`),
  KEY `idx_notif_user` (`id_user`,`lu`),
  CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 