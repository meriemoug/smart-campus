-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2026 at 11:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
  `id_user` varchar(100) NOT NULL,
  `titre` varchar(150) NOT NULL,
  `det` text NOT NULL,
  `Date_Pub` date NOT NULL,
  `cible` enum('tous','etudiants','profs','scolarite') NOT NULL DEFAULT 'tous',
  `id_Sec` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `annonce`
--

INSERT INTO `annonce` (`id_Anm`, `id_user`, `titre`, `det`, `Date_Pub`, `cible`, `id_Sec`, `image`) VALUES
(10, 'adminPrincipal1', 'Test de Gestion d\'annonces', 'cette annonce est faite pour verifier que cette partie du systeme marche correctement', '2026-11-13', 'etudiants', NULL, NULL),
(11, 'adminPrincipal1', 'Test des annonce pour tous', 'de la meme maniere je redige cette annonce pour verifier que ca s\'envoie a tout le monde!', '2026-11-13', 'tous', NULL, NULL),
(12, 'prof_si31', 'Demande de salle', 'Je souhaite réserver un labo pour une séance supplémentaire samedi.', '2026-11-13', 'scolarite', NULL, NULL),
(13, 'prof_si31', 'Changement de salle td', 'Le TD de Bases de données passe en Salle TD 4 cette semaine.', '2026-11-13', 'etudiants', 3, NULL),
(14, 'prof_si31', 'Seance td reporter', 'Je vous informe dans cette annonce que la seance de cours programmer demain est annuler', '2026-11-13', 'etudiants', 3, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `consultation`
--

CREATE TABLE `consultation` (
  `id_consult` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `date_consult` date NOT NULL,
  `heure_consult` varchar(50) NOT NULL,
  `bureau` varchar(100) NOT NULL,
  `email_contact` varchar(150) NOT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultation`
--

INSERT INTO `consultation` (`id_consult`, `id_prof`, `date_consult`, `heure_consult`, `bureau`, `email_contact`, `note`) VALUES
(2, 5, '2026-12-01', '9:00-11:00', 'B205', 'meriem.kaci@usthb.dz', 'Merci de m\'envoyer un email a l\'avance s\'il vous plait');

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
(4, 'IA'),
(5, 'Informatique'),
(6, 'Mathématiques');

-- --------------------------------------------------------

--
-- Table structure for table `enseignement`
--

CREATE TABLE `enseignement` (
  `id_prof` int(11) NOT NULL,
  `id_mod` int(11) NOT NULL,
  `id_Sec` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enseignement`
--

INSERT INTO `enseignement` (`id_prof`, `id_mod`, `id_Sec`) VALUES
(5, 4, 3),
(5, 7, 3),
(5, 5, 4),
(5, 9, 4),
(6, 5, 3),
(6, 25, 3),
(6, 4, 4),
(6, 6, 4),
(7, 6, 3),
(7, 9, 3),
(7, 7, 4),
(7, 25, 4),
(8, 10, 5),
(8, 14, 5),
(8, 13, 6),
(8, 17, 6),
(9, 11, 5),
(9, 15, 5),
(9, 10, 6),
(9, 14, 6),
(10, 12, 5),
(10, 16, 5),
(10, 11, 6),
(10, 15, 6),
(11, 13, 5),
(11, 17, 5),
(11, 12, 6),
(11, 16, 6),
(12, 18, 7),
(12, 22, 7),
(12, 21, 8),
(13, 19, 7),
(13, 23, 7),
(13, 18, 8),
(13, 22, 8),
(14, 20, 7),
(14, 24, 7),
(14, 19, 8),
(14, 23, 8),
(15, 21, 7),
(15, 20, 8),
(15, 24, 8),
(16, 26, 9),
(16, 27, 9),
(16, 32, 10),
(17, 28, 9),
(17, 29, 9),
(17, 26, 10),
(17, 27, 10),
(18, 30, 9),
(18, 31, 9),
(18, 28, 10),
(18, 29, 10),
(19, 32, 9),
(19, 30, 10),
(19, 31, 10);

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
(2, '232331674210', 6, 1, 2, 2),
(3, '238151884472', 8, 3, 1, 3),
(4, '231707536455', 8, 3, 2, 3),
(5, '231689768469', 8, 3, 3, 3),
(6, '237680457178', 8, 3, 1, 3),
(7, '239405576169', 8, 3, 2, 3),
(8, '239787534778', 8, 3, 3, 3),
(9, '238876634400', 8, 3, 1, 3),
(10, '232671475136', 8, 3, 2, 3),
(11, '232731778486', 8, 3, 3, 3),
(12, '238809320819', 8, 3, 1, 3),
(13, '231610550087', 8, 4, 1, 3),
(14, '238222646861', 8, 4, 2, 3),
(15, '232080737028', 8, 4, 3, 3),
(16, '230845205997', 8, 4, 1, 3),
(17, '237568205258', 8, 4, 2, 3),
(18, '232524776472', 8, 4, 3, 3),
(19, '232294866283', 8, 4, 1, 3),
(20, '234751779922', 8, 4, 2, 3),
(21, '233695793186', 8, 4, 3, 3),
(22, '237718841330', 8, 4, 1, 3),
(23, '232837929841', 8, 4, 2, 3),
(24, '230107981933', 8, 4, 3, 3),
(25, '236363228088', 9, 5, 1, 4),
(26, '238319489394', 9, 5, 2, 4),
(27, '233976158464', 9, 5, 3, 4),
(28, '232185491452', 9, 5, 1, 4),
(29, '238214162105', 9, 5, 2, 4),
(30, '236270586021', 9, 5, 3, 4),
(31, '231064600130', 9, 5, 1, 4),
(32, '234230048764', 9, 5, 2, 4),
(33, '230853801291', 9, 5, 3, 4),
(34, '233557787943', 9, 5, 1, 4),
(35, '232859406925', 9, 5, 2, 4),
(36, '230370382653', 9, 5, 3, 4),
(37, '239128084585', 9, 5, 1, 4),
(38, '230604541465', 9, 5, 2, 4),
(39, '231895579906', 9, 5, 3, 4),
(40, '233097658280', 9, 6, 1, 4),
(41, '238299321571', 9, 6, 2, 4),
(42, '236882811265', 9, 6, 3, 4),
(43, '238421083949', 9, 6, 1, 4),
(44, '231649415771', 9, 6, 2, 4),
(45, '234093244845', 9, 6, 3, 4),
(46, '238220180515', 9, 6, 1, 4),
(47, '233579511174', 9, 6, 2, 4),
(48, '233735569632', 9, 6, 3, 4),
(49, '239264515474', 9, 6, 1, 4),
(50, '233256885576', 9, 6, 2, 4),
(51, '231253739241', 10, 7, 1, 3),
(52, '239677500314', 10, 7, 2, 3),
(53, '236085651788', 10, 7, 3, 3),
(54, '233073368828', 10, 7, 1, 3),
(55, '234864390618', 10, 7, 2, 3),
(56, '238982104602', 10, 7, 3, 3),
(57, '235407207547', 10, 7, 1, 3),
(58, '233784951838', 10, 7, 2, 3),
(59, '234791889575', 10, 7, 3, 3),
(60, '235218248861', 10, 7, 2, 3),
(61, '235506204842', 10, 7, 3, 3),
(62, '238375555974', 10, 7, 1, 3),
(63, '232445607874', 10, 8, 1, 3),
(64, '236979780252', 10, 8, 2, 3),
(65, '230854177588', 10, 8, 3, 3),
(66, '234502831663', 10, 8, 1, 3),
(67, '238222900442', 10, 8, 2, 3),
(68, '237804385817', 10, 8, 3, 3),
(69, '238527220226', 10, 8, 1, 3),
(70, '237285568983', 10, 8, 2, 3),
(71, '232337352866', 10, 8, 3, 3),
(72, '235347897133', 10, 8, 1, 3),
(73, '237212835769', 10, 8, 2, 3),
(74, '231440190187', 10, 8, 3, 3),
(75, '236252807001', 10, 8, 1, 3),
(76, '231592123636', 10, 8, 2, 3),
(77, '236128400198', 11, 9, 1, 4),
(78, '232438546905', 11, 9, 2, 4),
(79, '232143049074', 11, 9, 3, 4),
(80, '232845142038', 11, 9, 1, 4),
(81, '238281953918', 11, 9, 2, 4),
(82, '235607392731', 11, 9, 3, 4),
(83, '238288983708', 11, 9, 1, 4),
(84, '237805780370', 11, 9, 2, 4),
(85, '238671300668', 11, 9, 3, 4),
(86, '235247357363', 11, 9, 1, 4),
(87, '236116603304', 11, 9, 2, 4),
(88, '239678073608', 11, 9, 3, 4),
(89, '232607911357', 11, 10, 1, 4),
(90, '237252781206', 11, 10, 2, 4),
(91, '236477447967', 11, 10, 3, 4),
(92, '232098426407', 11, 10, 1, 4),
(93, '235219803346', 11, 10, 2, 4),
(94, '232710512983', 11, 10, 3, 4),
(95, '232791372987', 11, 10, 1, 4),
(96, '237695851904', 11, 10, 2, 4),
(97, '236349992480', 11, 10, 3, 4),
(98, '239223283856', 11, 10, 1, 4),
(99, '2381518844721', 8, 3, 1, 3);

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
(6, 4, 3, 5, 7, 'normal', '2027-01-03', '09:30:00', '11:10:00'),
(8, 6, 3, 7, 7, 'normal', '2027-01-05', '09:30:00', '11:10:00'),
(9, 7, 3, 5, 8, 'normal', '2027-01-09', '09:30:00', '11:10:00');

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

--
-- Dumping data for table `ligne_appel`
--

INSERT INTO `ligne_appel` (`id_liste`, `id_Etu`, `presence`, `justification`, `motif`, `justificatif`) VALUES
(1, 5, 'present', 'non_justifiee', NULL, NULL),
(1, 8, 'present', 'non_justifiee', NULL, NULL),
(1, 11, 'absent', 'justifiee', NULL, NULL),
(2, 5, 'present', 'non_justifiee', NULL, NULL),
(2, 8, 'present', 'non_justifiee', NULL, NULL),
(2, 11, 'absent', 'justifiee', NULL, NULL),
(3, 5, 'present', 'non_justifiee', NULL, NULL),
(3, 8, 'present', 'non_justifiee', NULL, NULL),
(3, 11, 'absent', 'non_justifiee', NULL, NULL),
(4, 5, 'present', 'non_justifiee', NULL, NULL),
(4, 8, 'present', 'non_justifiee', NULL, NULL),
(4, 11, 'absent', 'non_justifiee', NULL, NULL),
(5, 5, 'present', 'non_justifiee', NULL, NULL),
(5, 8, 'present', 'non_justifiee', NULL, NULL),
(5, 11, 'absent', 'non_justifiee', NULL, NULL);

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

--
-- Dumping data for table `liste_appel`
--

INSERT INTO `liste_appel` (`id_liste`, `id_Seance`, `id_prof`, `date_appel`, `date_creation`) VALUES
(1, 20, 5, '2026-11-16', '2026-11-13 15:38:05'),
(2, 20, 5, '2026-11-09', '2026-11-15 00:15:00'),
(3, 20, 5, '2026-11-23', '2026-11-15 00:15:54'),
(4, 20, 5, '2026-11-30', '2026-11-15 00:16:07'),
(5, 20, 5, '2026-12-07', '2026-11-15 00:16:22');

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
(1, 'compil1'),
(2, 'IHM'),
(3, 'management'),
(4, 'Bases de données avancées'),
(5, 'Programmation web'),
(6, 'Analyse et conception'),
(7, 'Anglais technique'),
(9, 'Reseaux informatiques'),
(10, 'Apprentissage automatique'),
(11, 'Traitement d\'images'),
(12, 'Vision par ordinateur'),
(13, 'Big Data'),
(14, 'Ethique de l\'IA'),
(15, 'Optimisation convexe'),
(16, 'Systemes multi-agents'),
(17, 'Entrepreneuriat'),
(18, 'Analyse numérique'),
(19, 'Algèbre linéaire avancée'),
(20, 'Probabilites'),
(21, 'Equations differentielles'),
(22, 'Logique mathematique'),
(23, 'Anglais scientifique'),
(24, 'Optimisation lineaire'),
(25, 'Systèmes d\'exploitation'),
(26, 'Statistiques inférentielles'),
(27, 'Séries temporelles'),
(28, 'Processus stochastiques'),
(29, 'Analyse des données'),
(30, 'Théorie de la mesure'),
(31, 'Anglais pour la recherche'),
(32, 'Modélisation statistique');

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
  `TP` float DEFAULT NULL,
  `exclu` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `note`
--

INSERT INTO `note` (`id_Etud`, `id_Mod`, `id_prof`, `Exam`, `TD`, `TP`, `exclu`) VALUES
(2, 1, 2, 11, 12, 15, 0),
(2, 2, 3, 13, 13, 14, 0),
(2, 3, 4, 16, 15, 15, 0),
(3, 4, 5, NULL, 14, 13.5, 0),
(3, 5, 6, NULL, NULL, NULL, 0),
(3, 6, 7, 15, 13, 16, 0),
(3, 7, 5, 13, 11, 12, 0),
(3, 9, 7, NULL, NULL, NULL, 0),
(3, 25, 6, NULL, NULL, NULL, 0),
(4, 4, 5, 11, 12.5, 8, 0),
(4, 5, 6, 9, 8, 12, 0),
(4, 6, 7, 8, 13, 15, 0),
(4, 7, 5, 13, 8, 15, 0),
(4, 9, 7, 14, 8, 9, 0),
(4, 25, 6, 14, 12, 6, 0),
(5, 4, 5, NULL, NULL, NULL, 0),
(5, 5, 6, NULL, NULL, NULL, 0),
(5, 6, 7, NULL, NULL, NULL, 0),
(5, 7, 5, 9, 14, 14.5, 0),
(5, 9, 7, NULL, NULL, NULL, 0),
(5, 25, 6, NULL, NULL, NULL, 0),
(6, 4, 5, NULL, NULL, NULL, 0),
(6, 5, 6, NULL, NULL, NULL, 0),
(6, 6, 7, NULL, NULL, NULL, 0),
(6, 7, 5, 13.49, 13, 13, 0),
(6, 9, 7, NULL, NULL, NULL, 0),
(6, 25, 6, NULL, NULL, NULL, 0),
(7, 4, 5, NULL, NULL, NULL, 0),
(7, 5, 6, NULL, NULL, NULL, 0),
(7, 6, 7, NULL, NULL, NULL, 0),
(7, 7, 5, 8.01, 5.5, 8, 0),
(7, 9, 7, NULL, NULL, NULL, 0),
(7, 25, 6, NULL, NULL, NULL, 0),
(8, 4, 5, NULL, NULL, NULL, 0),
(8, 5, 6, NULL, NULL, NULL, 0),
(8, 6, 7, NULL, NULL, NULL, 0),
(8, 7, 5, 12, 12, 12, 0),
(8, 9, 7, NULL, NULL, NULL, 0),
(8, 25, 6, NULL, NULL, NULL, 0),
(9, 4, 5, NULL, NULL, NULL, 0),
(9, 5, 6, NULL, NULL, NULL, 0),
(9, 6, 7, NULL, NULL, NULL, 0),
(9, 7, 5, NULL, NULL, NULL, 0),
(9, 9, 7, NULL, NULL, NULL, 0),
(9, 25, 6, NULL, NULL, NULL, 0),
(10, 4, 5, NULL, NULL, NULL, 0),
(10, 5, 6, NULL, NULL, NULL, 0),
(10, 6, 7, NULL, NULL, NULL, 0),
(10, 7, 5, NULL, NULL, NULL, 0),
(10, 9, 7, NULL, NULL, NULL, 0),
(10, 25, 6, NULL, NULL, NULL, 0),
(11, 4, 5, 0, 0, 0, 1),
(11, 5, 6, NULL, NULL, NULL, 0),
(11, 6, 7, NULL, NULL, NULL, 0),
(11, 7, 5, NULL, NULL, NULL, 0),
(11, 9, 7, NULL, NULL, NULL, 0),
(11, 25, 6, NULL, NULL, NULL, 0),
(12, 4, 5, NULL, NULL, NULL, 0),
(12, 5, 6, NULL, NULL, NULL, 0),
(12, 6, 7, NULL, NULL, NULL, 0),
(12, 7, 5, NULL, NULL, NULL, 0),
(12, 9, 7, NULL, NULL, NULL, 0),
(12, 25, 6, NULL, NULL, NULL, 0),
(99, 4, 5, NULL, NULL, NULL, 0),
(99, 5, 6, NULL, NULL, NULL, 0),
(99, 6, 7, NULL, NULL, NULL, 0),
(99, 7, 5, NULL, NULL, NULL, 0),
(99, 9, 7, NULL, NULL, NULL, 0),
(99, 25, 6, NULL, NULL, NULL, 0);

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

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`id_notif`, `id_user`, `type`, `message`, `lu`, `date_creation`) VALUES
(1, '232731778486', 'absence', 'Vous avez été marqué(e) absent(e) pour le module : Bases de données avancées (Date : 09/11/2026).', 0, '2026-11-15 00:15:00'),
(2, '232731778486', 'absence', 'Vous avez été marqué(e) absent(e) pour le module : Bases de données avancées (Date : 23/11/2026).', 0, '2026-11-15 00:15:54'),
(3, '232731778486', 'absence', 'Vous avez été marqué(e) absent(e) pour le module : Bases de données avancées (Date : 30/11/2026).', 0, '2026-11-15 00:16:07'),
(4, '232731778486', 'absence', 'Vous avez été marqué(e) absent(e) pour le module : Bases de données avancées (Date : 07/12/2026).', 1, '2026-11-15 00:16:22'),
(5, '232731778486', 'exclusion', 'Vous avez été exclu du module Bases de données avancées suite à vos absences non justifiées.', 1, '2026-11-15 00:17:08'),
(6, '238151884472', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 1, '2026-11-15 11:26:40'),
(7, '231707536455', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(8, '231689768469', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(9, '237680457178', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(10, '239405576169', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(11, '239787534778', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(12, '238876634400', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(13, '232671475136', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(14, '232731778486', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(15, '238809320819', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(16, '2381518844721', 'examen', 'Examen programmé : Bases de données avancées (normal) le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(17, 'prof_si31', 'examen', 'Surveillance / Examen programmé : Bases de données avancées (normal) pour la section SI3-A le 03/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:26:40'),
(18, '238151884472', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 1, '2026-11-15 11:27:10'),
(19, '231707536455', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(20, '231689768469', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(21, '237680457178', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(22, '239405576169', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(23, '239787534778', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(24, '238876634400', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(25, '232671475136', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(26, '232731778486', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(27, '238809320819', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(28, '2381518844721', 'examen', 'Examen programmé : Programmation web (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(29, 'prof_si32', 'examen', 'Surveillance / Examen programmé : Programmation web (normal) pour la section SI3-A le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:10'),
(30, '238151884472', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 1, '2026-11-15 11:27:52'),
(31, '231707536455', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(32, '231689768469', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(33, '237680457178', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(34, '239405576169', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(35, '239787534778', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(36, '238876634400', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(37, '232671475136', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(38, '232731778486', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(39, '238809320819', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(40, '2381518844721', 'examen', 'Examen annulé : Programmation web (normal) prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(41, 'prof_si32', 'examen', 'Examen annulé : Programmation web (normal) pour la section SI3-A prévu le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:27:53'),
(42, '238151884472', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 1, '2026-11-15 11:28:23'),
(43, '231707536455', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(44, '231689768469', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(45, '237680457178', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(46, '239405576169', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(47, '239787534778', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(48, '238876634400', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(49, '232671475136', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(50, '232731778486', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(51, '238809320819', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(52, '2381518844721', 'examen', 'Examen programmé : Analyse et conception (normal) le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(53, 'prof_si33', 'examen', 'Surveillance / Examen programmé : Analyse et conception (normal) pour la section SI3-A le 05/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:23'),
(54, '238151884472', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 1, '2026-11-15 11:28:57'),
(55, '231707536455', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(56, '231689768469', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(57, '237680457178', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(58, '239405576169', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(59, '239787534778', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(60, '238876634400', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(61, '232671475136', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(62, '232731778486', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(63, '238809320819', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(64, '2381518844721', 'examen', 'Examen programmé : Anglais technique (normal) le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(65, 'prof_si31', 'examen', 'Surveillance / Examen programmé : Anglais technique (normal) pour la section SI3-A le 09/01/2027 de 09:30 - 11:10.', 0, '2026-11-15 11:28:57'),
(66, '238151884472', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 1, '2026-11-15 11:30:48'),
(67, '231707536455', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(68, '231689768469', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(69, '237680457178', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(70, '239405576169', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(71, '239787534778', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(72, '238876634400', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(73, '232671475136', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(74, '232731778486', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(75, '238809320819', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(76, '2381518844721', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:48'),
(77, '238151884472', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 1, '2026-11-15 11:30:57'),
(78, '231707536455', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(79, '231689768469', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(80, '237680457178', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(81, '239405576169', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(82, '239787534778', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(83, '238876634400', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(84, '232671475136', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(85, '232731778486', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(86, '238809320819', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(87, '2381518844721', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Bases de données avancées.', 0, '2026-11-15 11:30:57'),
(88, '238151884472', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 1, '2026-11-15 11:33:42'),
(89, '231707536455', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(90, '231689768469', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(91, '237680457178', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(92, '239405576169', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(93, '239787534778', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(94, '238876634400', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(95, '232671475136', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(96, '232731778486', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(97, '238809320819', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(98, '2381518844721', 'note', 'De nouvelles notes ont été enregistrées ou mises à jour pour le module : Analyse et conception.', 0, '2026-11-15 11:33:42'),
(99, '238151884472', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : 9.75).', 0, '2026-11-15 11:43:08'),
(100, '231707536455', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : Admis (Moyenne finale : 10.86).', 0, '2026-11-15 11:43:08'),
(101, '231689768469', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : 11.10).', 0, '2026-11-15 11:43:08'),
(102, '237680457178', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : 13.29).', 0, '2026-11-15 11:43:08'),
(103, '239405576169', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : 7.51).', 0, '2026-11-15 11:43:08'),
(104, '239787534778', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : 12.00).', 0, '2026-11-15 11:43:08'),
(105, '238876634400', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : N/A).', 0, '2026-11-15 11:43:08'),
(106, '232671475136', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : N/A).', 0, '2026-11-15 11:43:08'),
(107, '232731778486', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : 0.00).', 0, '2026-11-15 11:43:08'),
(108, '238809320819', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : N/A).', 0, '2026-11-15 11:43:08'),
(109, '2381518844721', 'note', 'Vos moyennes et résultats pour l\'année académique 2026/2027 ont été validés. Décision : En cours (Moyenne finale : N/A).', 0, '2026-11-15 11:43:08'),
(110, '238151884472', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(111, '231707536455', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(112, '231689768469', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(113, '237680457178', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(114, '239405576169', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(115, '239787534778', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(116, '238876634400', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(117, '232671475136', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(118, '232731778486', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(119, '238809320819', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(120, '2381518844721', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(121, 'prof_si31', 'seance', 'Séance annulée : Bases de données avancées (TP (Groupe 2)) pour la section SI3-A prévue le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:19'),
(122, '238151884472', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(123, '231707536455', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(124, '231689768469', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(125, '237680457178', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(126, '239405576169', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(127, '239787534778', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(128, '238876634400', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(129, '232671475136', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(130, '232731778486', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(131, '238809320819', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(132, '2381518844721', 'seance', 'Nouvelle séance ajoutée : Bases de données avancées (TP (Groupe 1)) le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34'),
(133, 'prof_si31', 'seance', 'Nouvelle séance programmée : Bases de données avancées (TP (Groupe 1)) pour la section SI3-A le Dimanche de 09:40 - 11:10.', 0, '2026-11-22 09:08:34');

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
(2, 'prof1', 4, 'professeur'),
(3, 'prof2', 4, 'professeur'),
(4, 'prof3', 4, ''),
(5, 'prof_si31', 5, 'Professeur'),
(6, 'prof_si32', 5, 'MCA'),
(7, 'prof_si33', 5, 'MCB'),
(8, 'prof_ia41', 5, 'MAA'),
(9, 'prof_ia42', 5, 'Professeur'),
(10, 'prof_ia43', 5, 'MCA'),
(11, 'prof_ia44', 5, 'MCB'),
(12, 'prof_ma31', 6, 'MAA'),
(13, 'prof_ma32', 6, 'Professeur'),
(14, 'prof_ma33', 6, 'MCA'),
(15, 'prof_ma34', 6, 'MCB'),
(16, 'prof_sp41', 6, 'MAA'),
(17, 'prof_sp42', 6, 'Professeur'),
(18, 'prof_sp43', 6, 'MCA'),
(19, 'prof_sp44', 6, 'MCB');

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
(4, 8, 4, 1, 3.0),
(5, 8, 5, 2, 3.0),
(6, 8, 6, 1, 2.0),
(7, 8, 7, 1, 1.0),
(9, 8, 9, 2, 2.0),
(10, 9, 10, 1, 4.0),
(11, 9, 11, 1, 3.0),
(12, 9, 12, 2, 3.0),
(13, 9, 13, 2, 3.0),
(14, 9, 14, 1, 1.0),
(15, 9, 15, 1, 2.0),
(16, 9, 16, 2, 2.0),
(17, 9, 17, 2, 1.0),
(18, 10, 18, 1, 3.0),
(19, 10, 19, 2, 3.0),
(20, 10, 20, 1, 3.0),
(21, 10, 21, 2, 2.0),
(22, 10, 22, 1, 1.0),
(23, 10, 23, 1, 1.0),
(24, 10, 24, 2, 2.0),
(25, 8, 25, 2, 2.0),
(26, 11, 26, 1, 3.0),
(27, 11, 27, 2, 3.0),
(28, 11, 28, 1, 3.0),
(29, 11, 29, 2, 3.0),
(30, 11, 30, 1, 2.0),
(31, 11, 31, 1, 1.0),
(32, 11, 32, 2, 2.0);

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

--
-- Dumping data for table `ressource`
--

INSERT INTO `ressource` (`id_R`, `id_prof`, `id_sect`, `id_module`, `titre`, `lien`, `type_res`, `date_ajout`) VALUES
(1, 2, 1, 1, 'veuillez telecharger le cours de la semaine pasee svp', 'uploads/1794496580_Chapitre 01 -USTHB.pdf', 'cours', '2026-11-12 16:16:20'),
(2, 5, 3, 4, 'voici si-joint le cours de la semaine passee', 'https://example.com/cours-1.pdf', 'cours', '2026-11-13 15:31:26'),
(3, 5, 3, 4, 'veuillez preparer ce td pour la seance prochaine', 'https://example.com/td-7.pdf', 'td', '2026-11-13 15:32:21'),
(4, 5, 3, 4, 'le cours sou formas pdf', 'uploads/1794580392_1794496580_Chapitre 01 -USTHB.pdf', 'cours', '2026-11-13 15:33:12');

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
  `decision` varchar(50) DEFAULT 'En cours',
  `annee_academique` varchar(20) DEFAULT '2026/2027',
  `id_sp` int(11) DEFAULT NULL,
  `id_Sec` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resultat_etudiant`
--

INSERT INTO `resultat_etudiant` (`id_resultat`, `id_user`, `moyenne_s1`, `moyenne_s2`, `rattrapage_s1`, `rattrapage_s2`, `moyenne_finale`, `decision`, `annee_academique`, `id_sp`, `id_Sec`) VALUES
(1, '232331674210', 13.20, NULL, NULL, NULL, 13.20, 'En cours', '2026/2027', 6, 1),
(2, '232331674210', 9.50, 8.20, NULL, NULL, 8.85, 'Ajourné', '2025/2026', 6, 1),
(19, '238151884472', 9.75, NULL, NULL, NULL, 9.75, 'En cours', '2026/2027', 8, 3),
(20, '231707536455', 10.88, 10.83, NULL, NULL, 10.86, 'Admis', '2026/2027', 8, 3),
(21, '231689768469', 11.10, NULL, NULL, NULL, 11.10, 'En cours', '2026/2027', 8, 3),
(22, '237680457178', 13.29, NULL, NULL, NULL, 13.29, 'En cours', '2026/2027', 8, 3),
(23, '239405576169', 7.51, NULL, NULL, NULL, 7.51, 'En cours', '2026/2027', 8, 3),
(24, '239787534778', 12.00, NULL, NULL, NULL, 12.00, 'En cours', '2026/2027', 8, 3),
(25, '238876634400', NULL, NULL, NULL, NULL, NULL, 'En cours', '2026/2027', 8, 3),
(26, '232671475136', NULL, NULL, NULL, NULL, NULL, 'En cours', '2026/2027', 8, 3),
(27, '232731778486', 0.00, NULL, NULL, NULL, 0.00, 'En cours', '2026/2027', 8, 3),
(28, '238809320819', NULL, NULL, NULL, NULL, NULL, 'En cours', '2026/2027', 8, 3),
(29, '2381518844721', NULL, NULL, NULL, NULL, NULL, 'En cours', '2026/2027', 8, 3),
(30, '231707536455', 11.20, 11.40, NULL, NULL, 11.30, 'Admis(e)', '2025/2026', 8, 3),
(31, '231707536455', 10.50, 10.80, NULL, NULL, 10.65, 'Admis(e)', '2024/2025', 8, 3);

-- --------------------------------------------------------

--
-- Table structure for table `resultat_module`
--

CREATE TABLE `resultat_module` (
  `id_user` varchar(100) NOT NULL,
  `annee_academique` varchar(20) NOT NULL,
  `id_mod` int(11) NOT NULL,
  `nom_mod` varchar(150) NOT NULL,
  `semestre` tinyint(4) NOT NULL,
  `coefficient` decimal(3,1) NOT NULL,
  `td` float DEFAULT NULL,
  `tp` float DEFAULT NULL,
  `exam` float DEFAULT NULL,
  `moyenne` decimal(4,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resultat_module`
--

INSERT INTO `resultat_module` (`id_user`, `annee_academique`, `id_mod`, `nom_mod`, `semestre`, `coefficient`, `td`, `tp`, `exam`, `moyenne`) VALUES
('231689768469', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('231689768469', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('231689768469', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('231689768469', '2026/2027', 7, 'Anglais technique', 1, 1.0, 14, 14.5, 9, 11.10),
('231689768469', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('231689768469', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('231707536455', '2024/2025', 4, 'Bases de données avancées', 1, 3.0, 10, 9, 9.5, 9.65),
('231707536455', '2024/2025', 5, 'Programmation web', 2, 3.0, 11, 12, 10, 10.75),
('231707536455', '2024/2025', 6, 'Analyse et conception', 1, 2.0, 12.5, 13, 11, 11.95),
('231707536455', '2024/2025', 7, 'Anglais technique', 1, 1.0, 10, 11, 12, 11.10),
('231707536455', '2024/2025', 9, 'Reseaux informatiques', 2, 2.0, 9.5, 10.5, 10, 10.00),
('231707536455', '2024/2025', 25, 'Systèmes d\'exploitation', 2, 2.0, 11, 11.5, 12, 11.65),
('231707536455', '2025/2026', 4, 'Bases de données avancées', 1, 3.0, 11.5, 10, 12, 11.35),
('231707536455', '2025/2026', 5, 'Programmation web', 2, 3.0, 14, 13.5, 10, 12.25),
('231707536455', '2025/2026', 6, 'Analyse et conception', 1, 2.0, 9, 11, 10.5, 10.10),
('231707536455', '2025/2026', 7, 'Anglais technique', 1, 1.0, 12, 14, 15, 13.70),
('231707536455', '2025/2026', 9, 'Reseaux informatiques', 2, 2.0, 10, 12, 11, 11.00),
('231707536455', '2025/2026', 25, 'Systèmes d\'exploitation', 2, 2.0, 13, 10, 9.5, 10.75),
('231707536455', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, 12.5, 8, 11, 10.70),
('231707536455', '2026/2027', 5, 'Programmation web', 2, 3.0, 8, 12, 9, 9.40),
('231707536455', '2026/2027', 6, 'Analyse et conception', 1, 2.0, 13, 15, 8, 10.40),
('231707536455', '2026/2027', 7, 'Anglais technique', 1, 1.0, 8, 15, 13, 12.40),
('231707536455', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, 8, 9, 14, 11.80),
('231707536455', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, 12, 6, 14, 12.00),
('232331674210', '2025/2026', 1, 'compil1', 1, 4.0, 10, 10, 8, 8.80),
('232331674210', '2025/2026', 2, 'IHM', 1, 2.0, 12, 10, 10, 10.40),
('232331674210', '2025/2026', 3, 'management', 1, 2.0, 8, 10, 12, 10.80),
('232331674210', '2026/2027', 1, 'compil1', 1, 4.0, 12, 15, 11, 12.00),
('232331674210', '2026/2027', 2, 'IHM', 1, 2.0, 13, 14, 13, 13.20),
('232331674210', '2026/2027', 3, 'management', 1, 2.0, 15, 15, 16, 15.60),
('232671475136', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('232671475136', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('232671475136', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('232671475136', '2026/2027', 7, 'Anglais technique', 1, 1.0, NULL, NULL, NULL, NULL),
('232671475136', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('232671475136', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('232731778486', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, 0, 0, 0, 0.00),
('232731778486', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('232731778486', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('232731778486', '2026/2027', 7, 'Anglais technique', 1, 1.0, NULL, NULL, NULL, NULL),
('232731778486', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('232731778486', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('237680457178', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('237680457178', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('237680457178', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('237680457178', '2026/2027', 7, 'Anglais technique', 1, 1.0, 13, 13, 13.49, 13.29),
('237680457178', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('237680457178', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('238151884472', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, 14, 13.5, NULL, 5.50),
('238151884472', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('238151884472', '2026/2027', 6, 'Analyse et conception', 1, 2.0, 13, 16, 15, 14.80),
('238151884472', '2026/2027', 7, 'Anglais technique', 1, 1.0, 11, 12, 13, 12.40),
('238151884472', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('238151884472', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('2381518844721', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('2381518844721', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('2381518844721', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('2381518844721', '2026/2027', 7, 'Anglais technique', 1, 1.0, NULL, NULL, NULL, NULL),
('2381518844721', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('2381518844721', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('238809320819', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('238809320819', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('238809320819', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('238809320819', '2026/2027', 7, 'Anglais technique', 1, 1.0, NULL, NULL, NULL, NULL),
('238809320819', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('238809320819', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('238876634400', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('238876634400', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('238876634400', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('238876634400', '2026/2027', 7, 'Anglais technique', 1, 1.0, NULL, NULL, NULL, NULL),
('238876634400', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('238876634400', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('239405576169', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('239405576169', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('239405576169', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('239405576169', '2026/2027', 7, 'Anglais technique', 1, 1.0, 5.5, 8, 8.01, 7.51),
('239405576169', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('239405576169', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL),
('239787534778', '2026/2027', 4, 'Bases de données avancées', 1, 3.0, NULL, NULL, NULL, NULL),
('239787534778', '2026/2027', 5, 'Programmation web', 2, 3.0, NULL, NULL, NULL, NULL),
('239787534778', '2026/2027', 6, 'Analyse et conception', 1, 2.0, NULL, NULL, NULL, NULL),
('239787534778', '2026/2027', 7, 'Anglais technique', 1, 1.0, 12, 12, 12, 12.00),
('239787534778', '2026/2027', 9, 'Reseaux informatiques', 2, 2.0, NULL, NULL, NULL, NULL),
('239787534778', '2026/2027', 25, 'Systèmes d\'exploitation', 2, 2.0, NULL, NULL, NULL, NULL);

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
(6, 'TP2', 30, 'TP'),
(7, 'Amphi 1', 250, 'cours'),
(8, 'Amphi 2', 200, 'cours'),
(9, 'Amphi 3', 150, 'cours'),
(10, 'Salle TD 1', 40, 'TD'),
(11, 'Salle TD 2', 40, 'TD'),
(12, 'Salle TD 3', 40, 'TD'),
(13, 'Salle TD 4', 40, 'TD'),
(14, 'Salle TD 5', 40, 'TD'),
(15, 'Salle TD 6', 40, 'TD'),
(16, 'Salle TD 7', 40, 'TD'),
(17, 'Salle TD 8', 40, 'TD'),
(18, 'Salle TD 9', 40, 'TD'),
(19, 'Salle TD 10', 40, 'TD'),
(20, 'Labo TP 1', 24, 'TP'),
(21, 'Labo TP 2', 24, 'TP'),
(22, 'Labo TP 3', 24, 'TP'),
(23, 'Labo TP 4', 24, 'TP'),
(24, 'Labo TP 5', 24, 'TP'),
(25, 'Labo TP 6', 24, 'TP');

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
(12, 7, 7, 3, NULL, 6, 'cours', 'Samedi', '08:00:00', '09:30:00'),
(13, 7, 5, 3, NULL, 4, 'cours', 'Dimanche', '11:20:00', '12:50:00'),
(14, 3, 5, 3, 1, 7, 'TD', 'Dimanche', '08:00:00', '09:30:00'),
(15, 10, 5, 3, 2, 7, 'TD', 'Lundi', '08:00:00', '09:30:00'),
(16, 7, 5, 3, NULL, 7, 'cours', 'Samedi', '09:40:00', '11:10:00'),
(17, 20, 5, 3, 1, 4, 'TP', 'Samedi', '11:20:00', '12:50:00'),
(19, 10, 5, 3, 3, 7, 'TD', 'Lundi', '09:40:00', '11:10:00'),
(20, 21, 5, 3, 3, 4, 'TP', 'Lundi', '11:20:00', '12:50:00'),
(21, 21, 7, 3, 3, 6, 'TP', 'Samedi', '11:20:00', '12:50:00'),
(22, 20, 7, 3, 1, 6, 'TP', 'Lundi', '11:20:00', '12:50:00'),
(23, 20, 7, 3, 2, 6, 'TP', 'Lundi', '09:40:00', '11:10:00'),
(24, 1, 8, 5, NULL, 10, 'cours', 'Samedi', '08:00:00', '09:30:00'),
(25, 1, 8, 5, NULL, 10, 'cours', 'Samedi', '08:00:00', '09:30:00'),
(26, 1, 0, 5, 1, 10, 'TD', 'Samedi', '09:40:00', '11:10:00'),
(27, 1, 9, 5, NULL, 15, 'cours', 'Samedi', '11:20:00', '12:50:00'),
(28, 1, 0, 5, 2, 15, 'TD', 'Samedi', '13:00:00', '14:30:00'),
(29, 1, 9, 5, NULL, 11, 'cours', 'Dimanche', '08:00:00', '09:30:00'),
(30, 1, 0, 5, 1, 11, 'TP', 'Dimanche', '09:40:00', '11:10:00'),
(31, 1, 8, 5, NULL, 14, 'cours', 'Dimanche', '11:20:00', '12:50:00'),
(32, 1, 0, 5, 2, 10, 'TP', 'Lundi', '08:00:00', '09:30:00'),
(33, 1, 0, 5, 3, 10, 'TP', 'Lundi', '09:40:00', '11:10:00'),
(34, 1, 0, 5, 2, 11, 'TP', 'Lundi', '11:20:00', '12:50:00'),
(35, 1, 0, 5, 1, 15, 'TD', 'Mardi', '08:00:00', '09:30:00'),
(36, 1, 0, 5, 3, 15, 'TD', 'Mardi', '09:40:00', '11:10:00'),
(37, 1, 0, 5, 3, 11, 'TP', 'Mardi', '11:20:00', '12:50:00'),
(38, 7, 6, 3, NULL, 5, 'cours', 'Samedi', '14:40:00', '16:10:00'),
(39, 7, 6, 3, NULL, 25, 'cours', 'Dimanche', '14:40:00', '16:10:00'),
(40, 24, 5, 3, 1, 4, 'TP', 'Dimanche', '09:40:00', '11:10:00');

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
(3, 'SI3-A', 8, 3),
(4, 'SI3-B', 8, 3),
(5, 'IA4-A', 9, 3),
(6, 'IA4-B', 9, 3),
(7, 'MA3-A', 10, 3),
(8, 'MA3-B', 10, 3),
(9, 'SP4-A', 11, 3),
(10, 'SP4-B', 11, 3);

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
(8, 'Systèmes d\'Information', 5, 3),
(9, 'Intelligence Artificielle', 5, 4),
(10, 'Mathématiques Appliquées', 6, 3),
(11, 'Statistiques et Probabilités', 6, 4);

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
('230107981933', 'Toumi', 'Rania', 'rania.toumi33@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('230370382653', 'Mekki', 'Nour', 'nour.mekki53@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('230604541465', 'Rahmani', 'Massinissa', 'massinissa.rahmani65@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('230845205997', 'Dahmani', 'Meriem', 'meriem.dahmani97@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('230853801291', 'Lakhdar', 'Amine', 'amine.lakhdar91@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('230854177588', 'Boukhalfa', 'Riad', 'riad.boukhalfa88@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231064600130', 'Haddad', 'Fatima', 'fatima.haddad30@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231253739241', 'Benali', 'Ilyes', 'ilyes.benali41@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231440190187', 'Sebti', 'Massinissa', 'massinissa.sebti87@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231592123636', 'Zidane', 'Meriem', 'meriem.zidane36@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231610550087', 'Benali', 'Maroua', 'maroua.benali87@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231649415771', 'Ferrah', 'Salim', 'salim.ferrah71@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231689768469', 'Bouchama', 'Sofiane', 'sofiane.bouchama69@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231707536455', 'Bensalem', 'Meriem', 'meriem.bensalem55@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('231895579906', 'Saadi', 'Dounia', 'dounia.saadi06@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232080737028', 'Boudiaf', 'Lina', 'lina.boudiaf28@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232098426407', 'Hamidi', 'Meriem', 'meriem.hamidi07@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232143049074', 'Cherif', 'Celia', 'celia.cherif74@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232185491452', 'Chikh', 'Adel', 'adel.chikh52@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232294866283', 'Mekki', 'Sofia', 'sofia.mekki83@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('2323123456', 'ouguenoune', 'meriem', 'ougoumer@gmail.com', '$2y$10$MVxcSR9FDiWG5/YiWdaMCuHctrZTWweBuMxk8maKRMkOeERlSwUWS', 'etudiant'),
('232331674210', 'Babour', 'Yasmina', 'yasminababour0@gmail.com', '$2y$10$wQyruHfMNdsSIG0zTKzHeuIja.zHZ2ere2DFDD2nVo75XSv5YcJVO', 'etudiant'),
('232337352866', 'Ferrah', 'Ramzi', 'ramzi.ferrah66@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232438546905', 'Bendjama', 'Bouchra', 'bouchra.bendjama05@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232445607874', 'Azzouz', 'Yasmine', 'yasmine.azzouz74@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232524776472', 'Medjahed', 'Maroua', 'maroua.medjahed72@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232607911357', 'Bensalem', 'Nabil', 'nabil.bensalem57@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232671475136', 'Toumi', 'Fatima', 'fatima.toumi36@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232710512983', 'Kebir', 'Soumia', 'soumia.kebir83@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232731778486', 'Ziane', 'Feriel', 'feriel.ziane86@etu.usthb.dz', '$2y$10$iAd542feXY8fAf0jHDZSuO1ftwWMfzPZzL.i7cTMAm3Q2s8ogqdHS', 'etudiant'),
('232791372987', 'Lounis', 'Yasmine', 'yasmine.lounis87@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232837929841', 'Tebbal', 'Zakaria', 'zakaria.tebbal41@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232845142038', 'Chikh', 'Younes', 'younes.chikh38@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('232859406925', 'Mansouri', 'Meriem', 'meriem.mansouri25@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233073368828', 'Chaoui', 'Lyes', 'lyes.chaoui28@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233097658280', 'Aissani', 'Asma', 'asma.aissani80@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233256885576', 'Zidane', 'Fatima', 'fatima.zidane76@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233557787943', 'Lounis', 'Soumia', 'soumia.lounis43@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233579511174', 'Sebti', 'Sofiane', 'sofiane.sebti74@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233695793186', 'Sebti', 'Amine', 'amine.sebti86@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233735569632', 'Taleb', 'Kenza', 'kenza.taleb32@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233784951838', 'Khelifi', 'Kenza', 'kenza.khelifi38@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('233976158464', 'Chaoui', 'Kenza', 'kenza.chaoui64@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('234093244845', 'Haddad', 'Sofiane', 'sofiane.haddad45@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('234230048764', 'Kadri', 'Ikram', 'ikram.kadri64@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('234502831663', 'Boukhalfa', 'Rym', 'rym.boukhalfa63@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('234751779922', 'Nouri', 'Kenza', 'kenza.nouri22@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('234791889575', 'Laouar', 'Bilal', 'bilal.laouar75@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('234864390618', 'Djebbar', 'Lamia', 'lamia.djebbar18@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('235218248861', 'Lounis', 'Anis', 'anis.lounis61@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('235219803346', 'Idir', 'Celia', 'celia.idir46@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('235247357363', 'Tebbal', 'Meriem', 'meriem.tebbal63@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('235347897133', 'Hamdani', 'Bouchra', 'bouchra.hamdani33@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('235407207547', 'Kadri', 'Ahmed', 'ahmed.kadri47@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('235506204842', 'Meziane', 'Wissem', 'wissem.meziane42@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('235607392731', 'Idir', 'Yacine', 'yacine.idir31@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236085651788', 'Brahimi', 'Riad', 'riad.brahimi88@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236116603304', 'Yahiaoui', 'Hana', 'hana.yahiaoui04@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236128400198', 'Belkacem', 'Yasmine', 'yasmine.belkacem98@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236252807001', 'Yousfi', 'Bilal', 'bilal.yousfi01@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236270586021', 'Ghezali', 'Hana', 'hana.ghezali21@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236349992480', 'Saadi', 'Sabrina', 'sabrina.saadi80@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236363228088', 'Aissani', 'Sofia', 'sofia.aissani88@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236477447967', 'Hamidi', 'Farid', 'farid.hamidi67@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236882811265', 'Brahimi', 'Lina', 'lina.brahimi65@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('236979780252', 'Belkacem', 'Rachid', 'rachid.belkacem52@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237212835769', 'Rahmani', 'Ramzi', 'ramzi.rahmani69@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237252781206', 'Derradji', 'Massinissa', 'massinissa.derradji06@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237285568983', 'Ferhat', 'Salim', 'salim.ferhat83@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237568205258', 'Kadri', 'Imane', 'imane.kadri58@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237680457178', 'Derradji', 'Ilyes', 'ilyes.derradji78@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237695851904', 'Rezig', 'Imane', 'imane.rezig04@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237718841330', 'Sebti', 'Kenza', 'kenza.sebti30@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237804385817', 'Dahmani', 'Hichem', 'hichem.dahmani17@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('237805780370', 'Lakhdar', 'Meriem', 'meriem.lakhdar70@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238151884472', 'Aissani', 'Farid', 'farid.aissani72@etu.usthb.dz', '$2y$10$7NjlwrFgMSZGWyafghDIWu0E8RetH8HKp9iOz/CGdF7ZrxfqlSpA6', 'etudiant'),
('2381518844721', 'Ouguenoune', 'Meriem', 'meriemouguenoune@gmail.com', '$2y$10$tWlYuuBgVxQ9efXV50kKruW80QxEDCcZsGnC1kPIXc5GBVQp1A3c2', 'etudiant'),
('238214162105', 'Ferhat', 'Rachid', 'rachid.ferhat05@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238220180515', 'Kaci', 'Nesrine', 'nesrine.kaci15@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238222646861', 'Bendjama', 'Wissem', 'wissem.bendjama61@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238222900442', 'Chaoui', 'Rania', 'rania.chaoui42@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238281953918', 'Idir', 'Nabil', 'nabil.idir18@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238288983708', 'Kadri', 'Sara', 'sara.kadri08@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238299321571', 'Amrani', 'Dounia', 'dounia.amrani71@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238319489394', 'Benyahia', 'Bilal', 'bilal.benyahia94@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238375555974', 'Tebbal', 'Sofiane', 'sofiane.tebbal74@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238421083949', 'Djebbar', 'Dounia', 'dounia.djebbar49@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238527220226', 'Ferhat', 'Asma', 'asma.ferhat26@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238671300668', 'Larbi', 'Rym', 'rym.larbi68@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238809320819', 'Ziane', 'Lyes', 'lyes.ziane19@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238876634400', 'Medjahed', 'Anis', 'anis.medjahed00@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('238982104602', 'Guerfi', 'Rachid', 'rachid.guerfi02@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('239128084585', 'Merabet', 'Kenza', 'kenza.merabet85@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('239223283856', 'Toumi', 'Imane', 'imane.toumi56@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('239264515474', 'Yousfi', 'Racha', 'racha.yousfi74@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('239405576169', 'Kebir', 'Nadir', 'nadir.kebir69@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('239677500314', 'Boukhalfa', 'Amel', 'amel.boukhalfa14@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('239678073608', 'Ziane', 'Amine', 'amine.ziane08@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('239787534778', 'Kebir', 'Rania', 'rania.kebir78@etu.usthb.dz', '$2y$10$pgbX8Qof6dWHk2ME62h.F.ligJ0/L2x9C4L8XEbGWWX4saZWm.fsK', 'etudiant'),
('adminPrincipal1', 'Benali', 'Samira', 'adminprincipal1@gmail.com', '$2y$10$ZKydWnsbPawyRqXHn1cIUear2OWM1tQFPB4g2iLLhNcG8qIB3bXSe', 'scolarite'),
('agent2', 'mohamed', 'Ahmed', 'agent2@gmail.com', '$2y$10$mD.J4W6I2r5yloXDs4vsHeTtGUujisCpxfIhh9weK9giXHWOJAgqC', 'scolarite'),
('prof_ia41', 'Kadri', 'Khadija', 'khadija.kadri@usthb.dz', '$2y$10$V1xTJdjour1Ooppzi0c56edXTK5sPRKcjBlsEaNSVQK2uK7Wj1wzK', 'prof'),
('prof_ia42', 'Lakhdar', 'Ines', 'ines.lakhdar@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_ia43', 'Dahmani', 'Nabil', 'nabil.dahmani@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_ia44', 'Guerfi', 'Sofiane', 'sofiane.guerfi@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_ma31', 'Benyahia', 'Djamel', 'djamel.benyahia@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_ma32', 'Ferrah', 'Sofiane', 'sofiane.ferrah@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_ma33', 'Abbas', 'Adel', 'adel.abbas@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_ma34', 'Bouchama', 'Ilyes', 'ilyes.bouchama@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_si31', 'Kaci', 'Meriem', 'meriem.kaci@usthb.dz', '$2y$10$VVgQqaFaUFhZcEcjY2/8HuAIrEExPAhV5b7A3d9uKG3lK.bXwnkf.', 'prof'),
('prof_si32', 'Hadjadj', 'Yacine', 'yacine.hadjadj@usthb.dz', '$2y$10$PUF29q9SsPFx1mASthHnc./NwM6eNXlZRD6aZ5H1fhi.HSnRuFvRi', 'prof'),
('prof_si33', 'Idir', 'Hichem', 'hichem.idir@usthb.dz', '$2y$10$mR8D6FWqFYsMM9g1rQs6zOqShFfVClBIkdujPr086jUGUUSwS5TUS', 'prof'),
('prof_sp41', 'Abbas', 'Younes', 'younes.abbas@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_sp42', 'Toumi', 'Amine', 'amine.toumi@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_sp43', 'Benali', 'Sabrina', 'sabrina.benali@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof_sp44', 'Cherif', 'Samir', 'samir.cherif@usthb.dz', '$2y$10$YourPrecomputedHashHere', 'prof'),
('prof1', 'PROFESSEUR', 'prof', 'prof1@gmail.com', '$2y$10$lgJW25EabGJwtkwSfapsB.hoDjYqQLjqV6lzm48ApjvTjUwlGxvJC', 'prof'),
('prof2', 'profihm', 'prenomihm', 'profihm@gmail.com', '$2y$10$sCRNWSMm5K2ybJ06aOqqvelMwgDChvPjmMIBfz/XR0M7FZbe7KcxO', 'prof'),
('prof3', 'profmanag', 'prenommanag', 'management@gmail.com', '$2y$10$n8naVJp6GpSqBdAmMpaDg.YFdisWgXAAf743Z1rZBEYGcn82f0Vqu', 'prof');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `annonce`
--
ALTER TABLE `annonce`
  ADD PRIMARY KEY (`id_Anm`),
  ADD KEY `id_Sec` (`id_Sec`),
  ADD KEY `annonce_ibfk_1` (`id_user`);

--
-- Indexes for table `consultation`
--
ALTER TABLE `consultation`
  ADD PRIMARY KEY (`id_consult`),
  ADD KEY `fk_consult_prof` (`id_prof`);

--
-- Indexes for table `departement`
--
ALTER TABLE `departement`
  ADD PRIMARY KEY (`id_Dep`);

--
-- Indexes for table `enseignement`
--
ALTER TABLE `enseignement`
  ADD PRIMARY KEY (`id_Sec`,`id_mod`),
  ADD KEY `fk_enseignement_prof` (`id_prof`),
  ADD KEY `fk_enseignement_mod` (`id_mod`);

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
-- Indexes for table `resultat_module`
--
ALTER TABLE `resultat_module`
  ADD PRIMARY KEY (`id_user`,`annee_academique`,`id_mod`),
  ADD KEY `fk_resmod_module` (`id_mod`);

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
  MODIFY `id_Anm` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `consultation`
--
ALTER TABLE `consultation`
  MODIFY `id_consult` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `departement`
--
ALTER TABLE `departement`
  MODIFY `id_Dep` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `etudiant`
--
ALTER TABLE `etudiant`
  MODIFY `id_Etu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `exam`
--
ALTER TABLE `exam`
  MODIFY `id_exam` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `groupe`
--
ALTER TABLE `groupe`
  MODIFY `id_groupe` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `liste_appel`
--
ALTER TABLE `liste_appel`
  MODIFY `id_liste` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `module`
--
ALTER TABLE `module`
  MODIFY `id_mod` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `id_notif` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `prof`
--
ALTER TABLE `prof`
  MODIFY `id_prof` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `programme`
--
ALTER TABLE `programme`
  MODIFY `id_prog` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `ressource`
--
ALTER TABLE `ressource`
  MODIFY `id_R` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `resultat_etudiant`
--
ALTER TABLE `resultat_etudiant`
  MODIFY `id_resultat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `salle`
--
ALTER TABLE `salle`
  MODIFY `id_salle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `scolarite`
--
ALTER TABLE `scolarite`
  MODIFY `id_sco` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `seance`
--
ALTER TABLE `seance`
  MODIFY `id_Seance` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `id_Sec` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `specialite`
--
ALTER TABLE `specialite`
  MODIFY `id_sp` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `annonce`
--
ALTER TABLE `annonce`
  ADD CONSTRAINT `annonce_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `annonce_ibfk_2` FOREIGN KEY (`id_Sec`) REFERENCES `section` (`id_Sec`) ON DELETE CASCADE;

--
-- Constraints for table `consultation`
--
ALTER TABLE `consultation`
  ADD CONSTRAINT `fk_consult_prof` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`) ON DELETE CASCADE;

--
-- Constraints for table `enseignement`
--
ALTER TABLE `enseignement`
  ADD CONSTRAINT `fk_enseignement_mod` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enseignement_prof` FOREIGN KEY (`id_prof`) REFERENCES `prof` (`id_prof`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enseignement_sec` FOREIGN KEY (`id_Sec`) REFERENCES `section` (`id_Sec`) ON DELETE CASCADE ON UPDATE CASCADE;

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

--
-- Constraints for table `resultat_module`
--
ALTER TABLE `resultat_module`
  ADD CONSTRAINT `fk_resmod_etudiant` FOREIGN KEY (`id_user`) REFERENCES `etudiant` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resmod_module` FOREIGN KEY (`id_mod`) REFERENCES `module` (`id_mod`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
