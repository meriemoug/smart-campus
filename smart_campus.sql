-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2026 at 06:42 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smart_campus`
--

-- --------------------------------------------------------

--
-- Table structure for table `annonce`
--

CREATE TABLE `annonce` (
  `id_Anm` int(11) NOT NULL,
  `id_sco` int(11) DEFAULT NULL,
  `titre` varchar(150) NOT NULL,
  `det` text NOT NULL,
  `Date_Pub` date NOT NULL,
  `cible` enum('tous','etudiants','profs') NOT NULL DEFAULT 'tous',
  `id_Sec` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departement`
--

CREATE TABLE `departement` (
  `id_Dep` int(11) NOT NULL,
  `Nom_Dep` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departement`
--

INSERT INTO `departement` (`id_Dep`, `Nom_Dep`) VALUES
(4, 'IA');

-- --------------------------------------------------------

--
-- Table structure for table `enseignement`
--

CREATE TABLE `enseignement` (
  `id_prof` int(11) NOT NULL,
  `id_mod` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enseignement`
--

INSERT INTO `enseignement` (`id_prof`, `id_mod`) VALUES
(2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `etudiant`
--

CREATE TABLE `etudiant` (
  `id_Etu` int(11) NOT NULL,
  `id_user` varchar(100) NOT NULL,
  `id_sp` int(11) DEFAULT NULL,
  `id_Sec` int(11) DEFAULT NULL,
  `groupe` int(11) NOT NULL DEFAULT 1,
  `annee` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `etudiant`
--

INSERT INTO `etudiant` (`id_Etu`, `id_user`, `id_sp`, `id_Sec`, `groupe`, `annee`) VALUES
(2, '232331674210', 6, 1, 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `exam`
--

CREATE TABLE `exam` (
  `id_exam` int(11) NOT NULL,
  `id_mod` int(11) NOT NULL,
  `id_sec` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `id_salle` int(11) DEFAULT NULL,
  `type_exam` enum('normal','rattrapage') NOT NULL DEFAULT 'normal',
  `dateEx` date NOT NULL,
  `heurD` time DEFAULT NULL,
  `heurF` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam`
--

INSERT INTO `exam` (`id_exam`, `id_mod`, `id_sec`, `id_prof`, `id_salle`, `type_exam`, `dateEx`, `heurD`, `heurF`) VALUES
(1, 1, 1, 2, 1, 'normal', '2026-12-29', '11:20:00', '12:50:00');

-- --------------------------------------------------------

--
-- Table structure for table `groupe`
--

CREATE TABLE `groupe` (
  `id_groupe` int(11) NOT NULL,
  `nom_groupe` varchar(20) NOT NULL,
  `id_Sec` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ligne_appel`
--

CREATE TABLE `ligne_appel` (
  `id_liste` int(11) NOT NULL,
  `id_Etu` int(11) NOT NULL,
  `presence` enum('present','absent') NOT NULL DEFAULT 'present',
  `justification` enum('non_justifiee','en_attente','justifiee') NOT NULL DEFAULT 'non_justifiee',
  `motif` varchar(255) DEFAULT NULL,
  `justificatif` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `liste_appel`
--

CREATE TABLE `liste_appel` (
  `id_liste` int(11) NOT NULL,
  `id_Seance` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `date_appel` date NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module`
--

CREATE TABLE `module` (
  `id_mod` int(11) NOT NULL,
  `Nom_mod` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module`
--

INSERT INTO `module` (`id_mod`, `Nom_mod`) VALUES
(1, 'compil1');

-- --------------------------------------------------------

--
-- Table structure for table `note`
--

CREATE TABLE `note` (
  `id_Etud` int(11) NOT NULL,
  `id_Mod` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `Exam` float DEFAULT NULL,
  `TD` float DEFAULT NULL,
  `TP` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `id_notif` int(11) NOT NULL,
  `id_user` varchar(100) NOT NULL,
  `type` enum('absence','exclusion','examen','note','annonce','seance','autre') NOT NULL DEFAULT 'autre',
  `message` varchar(255) NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prof`
--

CREATE TABLE `prof` (
  `id_prof` int(11) NOT NULL,
  `id_user` varchar(100) NOT NULL,
  `id_Dep` int(11) DEFAULT NULL,
  `grade` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prof`
--

INSERT INTO `prof` (`id_prof`, `id_user`, `id_Dep`, `grade`) VALUES
(2, 'prof1', NULL, 'professeur');

-- --------------------------------------------------------

--
-- Table structure for table `programme`
--

CREATE TABLE `programme` (
  `id_prog` int(11) NOT NULL,
  `id_sp` int(11) NOT NULL,
  `id_mod` int(11) NOT NULL,
  `semestre` tinyint(4) NOT NULL DEFAULT 1,
  `coefficient` decimal(3,1) NOT NULL DEFAULT 1.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programme`
--

INSERT INTO `programme` (`id_prog`, `id_sp`, `id_mod`, `semestre`, `coefficient`) VALUES
(1, 6, 1, 1, 4.0);

-- --------------------------------------------------------

--
-- Table structure for table `ressource`
--

CREATE TABLE `ressource` (
  `id_R` int(11) NOT NULL,
  `id_prof` int(11) DEFAULT NULL,
  `id_sect` int(11) DEFAULT NULL,
  `id_module` int(11) DEFAULT NULL,
  `titre` varchar(150) DEFAULT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `type_res` enum('cours','td','tp','examen_corrige','video','autre') NOT NULL DEFAULT 'cours',
  `date_ajout` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resultat_etudiant`
--

CREATE TABLE `resultat_etudiant` (
  `id_resultat` int(11) NOT NULL,
  `id_user` varchar(100) NOT NULL,
  `moyenne_s1` decimal(4,2) DEFAULT NULL,
  `moyenne_s2` decimal(4,2) DEFAULT NULL,
  `rattrapage_s1` decimal(4,2) DEFAULT NULL,
  `rattrapage_s2` decimal(4,2) DEFAULT NULL,
  `moyenne_finale` decimal(4,2) DEFAULT NULL,
  `decision` varchar(50) DEFAULT 'En cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salle`
--

CREATE TABLE `salle` (
  `id_salle` int(11) NOT NULL,
  `nom_salle` varchar(50) NOT NULL,
  `capacite` int(11) DEFAULT NULL,
  `typeSalle` varchar(15) NOT NULL DEFAULT 'salle_cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salle`
--

INSERT INTO `salle` (`id_salle`, `nom_salle`, `capacite`, `typeSalle`) VALUES
(1, 'Amphi A', 300, 'cours'),
(2, 'Amphi B', 250, 'cours'),
(3, 'Salle 101', 45, 'TD'),
(4, 'Salle 103', 45, 'TD'),
(5, 'TP1', 30, 'TP'),
(6, 'TP2', 30, 'TP');

-- --------------------------------------------------------

--
-- Table structure for table `scolarite`
--

CREATE TABLE `scolarite` (
  `id_sco` int(11) NOT NULL,
  `id_user` varchar(100) NOT NULL,
  `post_Admin` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scolarite`
--

INSERT INTO `scolarite` (`id_sco`, `id_user`, `post_Admin`) VALUES
(1, 'adminPrincipal1', 'principal'),
(3, 'agent2', 'agent');

-- --------------------------------------------------------

--
-- Table structure for table `seance`
--

CREATE TABLE `seance` (
  `id_Seance` int(11) NOT NULL,
  `id_salle` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `id_Sec` int(11) NOT NULL,
  `id_groupe` int(11) DEFAULT NULL,
  `id_mod` int(11) NOT NULL,
  `type_seance` enum('cours','TD','TP') NOT NULL DEFAULT 'cours',
  `jour` enum('Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
  `heurD` time NOT NULL,
  `heurF` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seance`
--

INSERT INTO `seance` (`id_Seance`, `id_salle`, `id_prof`, `id_Sec`, `id_groupe`, `id_mod`, `type_seance`, `jour`, `heurD`, `heurF`) VALUES
(1, 1, 2, 1, NULL, 1, 'cours', 'Samedi', '08:00:00', '09:30:00'),
(3, 3, 2, 1, 1, 1, 'TD', 'Dimanche', '08:00:00', '09:30:00'),
(4, 5, 2, 1, 2, 1, 'TP', 'Lundi', '08:00:00', '09:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `id_Sec` int(11) NOT NULL,
  `nom_sec` varchar(100) NOT NULL,
  `id_sp` int(11) DEFAULT NULL,
  `nombre_groupes` int(11) DEFAULT 4
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `section`
--

INSERT INTO `section` (`id_Sec`, `nom_sec`, `id_sp`, `nombre_groupes`) VALUES
(1, 'A', 6, 4),
(2, 'A', 7, 4);

-- --------------------------------------------------------

--
-- Table structure for table `specialite`
--

CREATE TABLE `specialite` (
  `id_sp` int(11) NOT NULL,
  `NomSp` varchar(100) NOT NULL,
  `id_Dep` int(11) DEFAULT NULL,
  `annee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `specialite`
--

INSERT INTO `specialite` (`id_sp`, `NomSp`, `id_Dep`, `annee`) VALUES
(6, 'Machine Learning', 4, 2),
(7, 'Deep Learning', 4, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id_user` varchar(100) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mdP` varchar(255) NOT NULL,
  `role` enum('etudiant','prof','scolarite') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id_user`, `nom`, `prenom`, `email`, `mdP`, `role`) VALUES
('232331674210', 'Babour', 'Yasmina', 'yasminababour0@gmail.com', '$2y$10$wQyruHfMNdsSIG0zTKzHeuIja.zHZ2ere2DFDD2nVo75XSv5YcJVO', 'etudiant'),
('adminPrincipal1', 'Admin', 'Principal', 'adminprincipal1@gmail.com', '$2y$10$MVxcSR9FDiWG5/YiWdaMCuHctrZTWweBuMxk8maKRMkOeERlSwUWS', 'scolarite'),
('agent2', 'mohamed', 'mohamed', 'agent2@gmail.com', '$2y$10$ecPEDl05u5.uhKfAKhF7D.Gpq0Ezbm3m/N/RXvnfVO65hz/9CtAZW', 'scolarite'),
('prof1', 'PROFESSEUR', 'prof', 'prof1@gmail.com', '$2y$10$lgJW25EabGJwtkwSfapsB.hoDjYqQLjqV6lzm48ApjvTjUwlGxvJC', 'prof');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `annonce`
--
ALTER TABLE `annonce`
  ADD PRIMARY KEY (`id_Anm`),
  ADD KEY `id_sco` (`id_sco`),
  ADD KEY `id_Sec` (`id_Sec`);

--
-- Indexes for table `departement`
--
ALTER TABLE `departement`
  ADD PRIMARY KEY (`id_Dep`);

--
-- Indexes for table `enseignement`
--
ALTER TABLE `enseignement`
  ADD PRIMARY KEY (`id_prof`,`id_mod`),
  ADD KEY `id_mod` (`id_mod`);

--
-- Indexes for table `etudiant`
--
ALTER TABLE `etudiant`
  ADD PRIMARY KEY (`id_Etu`),
  ADD UNIQUE KEY `uq_etudiant_user` (`id_user`),
  ADD KEY `id_sp` (`id_sp`),
  ADD KEY `id_Sec` (`id_Sec`),
  ADD KEY `id_groupe` (`groupe`);

--
-- Indexes for table `exam`
--
ALTER TABLE `exam`
  ADD PRIMARY KEY (`id_exam`),
  ADD KEY `id_mod` (`id_mod`),
  ADD KEY `id_prof` (`id_prof`),
  ADD KEY `id_sec` (`id_sec`),
  ADD KEY `id_salle` (`id_salle`);

--
-- Indexes for table `groupe`
--
ALTER TABLE `groupe`
  ADD PRIMARY KEY (`id_groupe`),
  ADD UNIQUE KEY `uq_groupe_section` (`id_Sec`,`nom_groupe`);

--
-- Indexes for table `ligne_appel`
--
ALTER TABLE `ligne_appel`
  ADD PRIMARY KEY (`id_liste`,`id_Etu`),
  ADD KEY `id_Etu` (`id_Etu`);

--
-- Indexes for table `liste_appel`
--
ALTER TABLE `liste_appel`
  ADD PRIMARY KEY (`id_liste`),
  ADD UNIQUE KEY `uq_liste` (`id_Seance`,`date_appel`),
  ADD KEY `id_prof` (`id_prof`);

--
-- Indexes for table `module`
--
ALTER TABLE `module`
  ADD PRIMARY KEY (`id_mod`);

--
-- Indexes for table `note`
--
ALTER TABLE `note`
  ADD PRIMARY KEY (`id_Etud`,`id_Mod`),
  ADD KEY `id_Mod` (`id_Mod`),
  ADD KEY `id_prof` (`id_prof`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`id_notif`),
  ADD KEY `idx_notif_user` (`id_user`,`lu`);

--
-- Indexes for table `prof`
--
ALTER TABLE `prof`
  ADD PRIMARY KEY (`id_prof`),
  ADD UNIQUE KEY `uq_prof_user` (`id_user`),
  ADD KEY `id_Dep` (`id_Dep`);

--
-- Indexes for table `programme`
--
ALTER TABLE `programme`
  ADD PRIMARY KEY (`id_prog`),
  ADD KEY `prog_ibfk_1` (`id_sp`),
  ADD KEY `module_ibfk_1` (`id_mod`);

--
-- Indexes for table `ressource`
--
ALTER TABLE `ressource`
  ADD PRIMARY KEY (`id_R`),
  ADD KEY `id_prof` (`id_prof`),
  ADD KEY `id_sect` (`id_sect`),
  ADD KEY `id_module` (`id_module`);

--
-- Indexes for table `resultat_etudiant`
--
ALTER TABLE `resultat_etudiant`
  ADD PRIMARY KEY (`id_resultat`),
  ADD KEY `fk_resultat_etudiant` (`id_user`);

--
-- Indexes for table `salle`
--
ALTER TABLE `salle`
  ADD PRIMARY KEY (`id_salle`),
  ADD UNIQUE KEY `uq_nom_salle` (`nom_salle`);

--
-- Indexes for table `scolarite`
--
ALTER TABLE `scolarite`
  ADD PRIMARY KEY (`id_sco`),
  ADD UNIQUE KEY `uq_scolarite_user` (`id_user`);

--
-- Indexes for table `seance`
--
ALTER TABLE `seance`
  ADD PRIMARY KEY (`id_Seance`),
  ADD KEY `id_salle` (`id_salle`),
  ADD KEY `id_prof` (`id_prof`),
  ADD KEY `id_section` (`id_Sec`),
  ADD KEY `id_groupe` (`id_groupe`),
  ADD KEY `id_mod` (`id_mod`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`id_Sec`),
  ADD KEY `id_sp` (`id_sp`);

--
-- Indexes for table `specialite`
--
ALTER TABLE `specialite`
  ADD PRIMARY KEY (`id_sp`),
  ADD KEY `id_Dep` (`id_Dep`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `annonce`
--
ALTER TABLE `annonce`
  MODIFY `id_Anm` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departement`
--
ALTER TABLE `departement`
  MODIFY `id_Dep` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `etudiant`
--
ALTER TABLE `etudiant`
  MODIFY `id_Etu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exam`
--
ALTER TABLE `exam`
  MODIFY `id_exam` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `groupe`
--
ALTER TABLE `groupe`
  MODIFY `id_groupe` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `liste_appel`
--
ALTER TABLE `liste_appel`
  MODIFY `id_liste` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module`
--
ALTER TABLE `module`
  MODIFY `id_mod` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `id_notif` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prof`
--
ALTER TABLE `prof`
  MODIFY `id_prof` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `programme`
--
ALTER TABLE `programme`
  MODIFY `id_prog` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ressource`
--
ALTER TABLE `ressource`
  MODIFY `id_R` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resultat_etudiant`
--
ALTER TABLE `resultat_etudiant`
  MODIFY `id_resultat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salle`
--
ALTER TABLE `salle`
  MODIFY `id_salle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `scolarite`
--
ALTER TABLE `scolarite`
  MODIFY `id_sco` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `seance`
--
ALTER TABLE `seance`
  MODIFY `id_Seance` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `id_Sec` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `specialite`
--
ALTER TABLE `specialite`
  MODIFY `id_sp` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `annonce`
--
ALTER TABLE `annonce`
  ADD CONSTRAINT `annonce_ibfk_1` FOREIGN KEY (`id_sco`) REFERENCES `scolarite` (`id_sco`) ON DELETE CASCADE,
  ADD CONSTRAINT `annonce_ibfk_2` FOREIGN KEY (`id_Sec`) REFERENCES `section` (`id_Sec`) ON DELETE CASCADE;

--
-- Constraints for table `enseignement`
--
ALTER TABLE `enseignement`
  ADD CONSTRAINT `enseignement_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`) ON DELETE CASCADE,
  ADD CONSTRAINT `enseignement_ibfk_2` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`) ON DELETE CASCADE;

--
-- Constraints for table `exam`
--
ALTER TABLE `exam`
  ADD CONSTRAINT `exam_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`),
  ADD CONSTRAINT `exam_ibfk_2` FOREIGN KEY (`id_sec`) REFERENCES `section` (`id_Sec`),
  ADD CONSTRAINT `exam_ibfk_3` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`),
  ADD CONSTRAINT `exam_ibfk_4` FOREIGN KEY (`id_salle`) REFERENCES `salle` (`id_salle`);

--
-- Constraints for table `programme`
--
ALTER TABLE `programme`
  ADD CONSTRAINT `module_ibfk_1` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`),
  ADD CONSTRAINT `prog_ibfk_1` FOREIGN KEY (`id_sp`) REFERENCES `specialite` (`id_sp`);

--
-- Constraints for table `resultat_etudiant`
--
ALTER TABLE `resultat_etudiant`
  ADD CONSTRAINT `fk_resultat_etudiant` FOREIGN KEY (`id_user`) REFERENCES `etudiant` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
