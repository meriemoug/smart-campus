SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

-- 1. Table : user
CREATE TABLE `user` (
  `id_user` varchar(100) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mdP` varchar(255) NOT NULL,
  `role` enum('etudiant','prof','scolarite') NOT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table : departement
CREATE TABLE `departement` (
  `id_Dep` int(11) NOT NULL AUTO_INCREMENT,
  `Nom_Dep` varchar(100) NOT NULL,
  PRIMARY KEY (`id_Dep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table : specialite
CREATE TABLE `specialite` (
  `id_sp` int(11) NOT NULL AUTO_INCREMENT,
  `NomSp` varchar(100) NOT NULL,
  `id_Dep` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_sp`),
  KEY `id_Dep` (`id_Dep`),
  CONSTRAINT `specialite_ibfk_1` FOREIGN KEY (`id_Dep`) REFERENCES `departement` (`id_Dep`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table : section
CREATE TABLE `section` (
  `id_Sec` int(11) NOT NULL AUTO_INCREMENT,
  `nom_sec` varchar(100) NOT NULL,
  `id_sp` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_Sec`),
  KEY `id_sp` (`id_sp`),
  CONSTRAINT `section_ibfk_1` FOREIGN KEY (`id_sp`) REFERENCES `specialite` (`id_sp`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table : prof
CREATE TABLE `prof` (
  `id_prof` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` varchar(100) DEFAULT NULL,
  `id_Dep` int(11) DEFAULT NULL,
  `bureau` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_prof`),
  KEY `id_user` (`id_user`),
  KEY `id_Dep` (`id_Dep`),
  CONSTRAINT `prof_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `prof_ibfk_2` FOREIGN KEY (`id_Dep`) REFERENCES `departement` (`id_Dep`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table : etudiant
CREATE TABLE `etudiant` (
  `id_Etu` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` varchar(100) DEFAULT NULL,
  `id_sp` int(11) DEFAULT NULL,
  `groupe` varchar(20) DEFAULT NULL,
  `id_Sec` int(11) DEFAULT NULL,
  `annee` int(1) DEFAULT NULL,
  PRIMARY KEY (`id_Etu`),
  KEY `id_user` (`id_user`),
  KEY `id_sp` (`id_sp`),
  KEY `id_Sec` (`id_Sec`),
  CONSTRAINT `chk_annee_etudiant` CHECK (`annee` BETWEEN 1 AND 5),
  CONSTRAINT `etudiant_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `etudiant_ibfk_2` FOREIGN KEY (`id_sp`) REFERENCES `specialite` (`id_sp`),
  CONSTRAINT `etudiant_ibfk_3` FOREIGN KEY (`id_Sec`) REFERENCES `section` (`id_Sec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table : scolarite
CREATE TABLE `scolarite` (
  `id_sco` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` varchar(100) DEFAULT NULL,
  `post_Admin` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_sco`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `scolarite_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Table : salle
CREATE TABLE `salle` (
  `id_salle` int(11) NOT NULL AUTO_INCREMENT,
  `Capacité` int(11) DEFAULT NULL,
  `typeSalle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_salle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Table : module
CREATE TABLE `module` (
  `id_mod` int(11) NOT NULL AUTO_INCREMENT,
  `Nom_mod` varchar(100) NOT NULL,
  `id_sp` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_mod`),
  KEY `id_sp` (`id_sp`),
  CONSTRAINT `module_ibfk_1` FOREIGN KEY (`id_sp`) REFERENCES `specialite` (`id_sp`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Table : seance
CREATE TABLE `seance` (
  `id_Seance` int(11) NOT NULL AUTO_INCREMENT,
  `id_salle` int(11) DEFAULT NULL,
  `id_prof` int(11) DEFAULT NULL,
  `id_section` int(11) DEFAULT NULL,
  `groupe` int(11) DEFAULT NULL,
  `jour` varchar(20) DEFAULT NULL,
  `heurD` time DEFAULT NULL,
  `heurF` time DEFAULT NULL,
  `id_mod` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_Seance`),
  KEY `id_salle` (`id_salle`),
  KEY `id_prof` (`id_prof`),
  KEY `id_section` (`id_section`),
  KEY `id_mod` (`id_mod`),
  CONSTRAINT `seance_ibfk_1` FOREIGN KEY (`id_salle`) REFERENCES `salle` (`id_salle`),
  CONSTRAINT `seance_ibfk_2` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`),
  CONSTRAINT `seance_ibfk_3` FOREIGN KEY (`id_section`) REFERENCES `section` (`id_Sec`),
  CONSTRAINT `seance_ibfk_4` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Table : note
CREATE TABLE `note` (
  `id_Etud` int(11) NOT NULL,
  `id_Mod` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `Exam` float DEFAULT NULL,
  `TD` float DEFAULT NULL,
  `TP` float DEFAULT NULL,
  PRIMARY KEY (`id_Etud`,`id_Mod`,`id_prof`),
  KEY `id_Mod` (`id_Mod`),
  KEY `id_prof` (`id_prof`),
  CONSTRAINT `note_ibfk_1` FOREIGN KEY (`id_Etud`) REFERENCES `etudiant` (`id_Etu`) ON DELETE CASCADE,
  CONSTRAINT `note_ibfk_2` FOREIGN KEY (`id_Mod`) REFERENCES `module` (`id_mod`) ON DELETE CASCADE,
  CONSTRAINT `note_ibfk_3` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Table : annonce
CREATE TABLE `annonce` (
  `id_Anm` int(11) NOT NULL AUTO_INCREMENT,
  `id_sco` int(11) DEFAULT NULL,
  `titre` varchar(150) NOT NULL,
  `det` text NOT NULL,
  `Date_Pub` date NOT NULL,
  PRIMARY KEY (`id_Anm`),
  KEY `id_sco` (`id_sco`),
  CONSTRAINT `annonce_ibfk_1` FOREIGN KEY (`id_sco`) REFERENCES `scolarite` (`id_sco`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Table : ressource
CREATE TABLE `ressource` (
  `id_R` int(11) NOT NULL AUTO_INCREMENT,
  `id_prof` int(11) DEFAULT NULL,
  `id_sect` int(11) DEFAULT NULL,
  `id_module` int(11) DEFAULT NULL,
  `titre` varchar(150) DEFAULT NULL,
  `lien` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_R`),
  KEY `id_prof` (`id_prof`),
  KEY `id_sect` (`id_sect`),
  KEY `id_module` (`id_module`),
  CONSTRAINT `ressource_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`),
  CONSTRAINT `ressource_ibfk_2` FOREIGN KEY (`id_sect`) REFERENCES `section` (`id_Sec`),
  CONSTRAINT `ressource_ibfk_3` FOREIGN KEY (`id_module`) REFERENCES `module` (`id_mod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Table : exam
CREATE TABLE `exam` (
  `id_exam` int(11) NOT NULL AUTO_INCREMENT,
  `id_mod` int(11) NOT NULL,
  `id_sec` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `dateEx` date NOT NULL,
  `heurD` time DEFAULT NULL,
  `heurF` time DEFAULT NULL,
  PRIMARY KEY (`id_exam`),
  KEY `id_mod` (`id_mod`),
  KEY `id_prof` (`id_prof`),
  KEY `id_sec` (`id_sec`),
  CONSTRAINT `exam_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`),
  CONSTRAINT `exam_ibfk_2` FOREIGN KEY (`id_sec`) REFERENCES `section` (`id_Sec`),
  CONSTRAINT `exam_ibfk_3` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;