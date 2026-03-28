-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 28 mars 2026 à 01:16
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `studelyledge`
--

-- --------------------------------------------------------

--
-- Structure de la table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'France',
  `initial_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `account_number`, `bank_name`, `country`, `initial_balance`, `balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '411000000001', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 00:47:07', NULL),
(2, '411000000002', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 00:47:07', NULL),
(3, '411000000003', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 00:47:07', NULL),
(4, '411000000004', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(5, '411000000005', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(6, '411000000006', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(7, '411000000007', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(8, '411000000008', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(9, '411000000009', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(10, '411000000010', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(11, '411000000011', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(12, '411000000012', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(13, '411000000013', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(14, '411000000014', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(15, '411000000015', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(16, '411000000016', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(17, '411000000017', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(18, '411000000018', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(19, '411000000019', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL),
(20, '411000000020', 'Compte Client Interne', 'France', 15000.00, 15000.00, 1, '2026-03-26 01:59:08', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(9) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country_origin` varchar(100) DEFAULT NULL,
  `country_destination` varchar(100) DEFAULT NULL,
  `country_commercial` varchar(100) DEFAULT NULL,
  `client_type` varchar(100) DEFAULT NULL,
  `client_status` varchar(100) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'EUR',
  `generated_client_account` varchar(20) NOT NULL,
  `initial_treasury_account_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `first_name`, `last_name`, `full_name`, `email`, `phone`, `country_origin`, `country_destination`, `country_commercial`, `client_type`, `client_status`, `currency`, `generated_client_account`, `initial_treasury_account_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '000000001', 'Jean', 'Mekou', 'Jean Mekou', 'jean.mekou@studelyledger.com', '0600000001', 'Cameroun', 'France', 'Cameroun', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000001', 4, 1, '2026-03-26 00:47:06', NULL),
(2, '000000002', 'Awa', 'Ndiaye', 'Awa Ndiaye', 'awa.ndiaye@studelyledger.com', '0600000002', 'Sénégal', 'France', 'Sénégal', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000002', 5, 1, '2026-03-26 00:47:06', NULL),
(3, '000000003', 'Koffi', 'Kouassi', 'Koffi Kouassi', 'koffi.kouassi@studelyledger.com', '0600000003', 'Côte d\'Ivoire', 'France', 'Côte d\'Ivoire', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000003', 6, 1, '2026-03-26 00:47:06', NULL),
(4, '000000004', 'Client4', 'Studely4', 'Client4 Studely4', 'client4.studely4@studelyledger.com', '0600000004', 'Tunisie', 'Belgique', 'Tunisie', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000004', 7, 1, '2026-03-26 01:59:08', NULL),
(5, '000000005', 'Client5', 'Studely5', 'Client5 Studely5', 'client5.studely5@studelyledger.com', '0600000005', 'Sénégal', 'Allemagne', 'Sénégal', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000005', 5, 1, '2026-03-26 01:59:08', NULL),
(6, '000000006', 'Client6', 'Studely6', 'Client6 Studely6', 'client6.studely6@studelyledger.com', '0600000006', 'Cameroun', 'France', 'Cameroun', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000006', 4, 1, '2026-03-26 01:59:08', NULL),
(7, '000000007', 'Client7', 'Studely7', 'Client7 Studely7', 'client7.studely7@studelyledger.com', '0600000007', 'Côte d\'Ivoire', 'Belgique', 'Côte d\'Ivoire', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000007', 6, 1, '2026-03-26 01:59:08', NULL),
(8, '000000008', 'Client8', 'Studely8', 'Client8 Studely8', 'client8.studely8@studelyledger.com', '0600000008', 'Maroc', 'Allemagne', 'Maroc', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000008', 8, 1, '2026-03-26 01:59:08', NULL),
(9, '000000009', 'Client9', 'Studely9', 'Client9 Studely9', 'client9.studely9@studelyledger.com', '0600000009', 'Tunisie', 'France', 'Tunisie', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000009', 7, 1, '2026-03-26 01:59:08', NULL),
(10, '000000010', 'Client10', 'Studely10', 'Client10 Studely10', 'client10.studely10@studelyledger.com', '0600000010', 'Sénégal', 'Belgique', 'Sénégal', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000010', 5, 1, '2026-03-26 01:59:08', NULL),
(11, '000000011', 'Client11', 'Studely11', 'Client11 Studely11', 'client11.studely11@studelyledger.com', '0600000011', 'Cameroun', 'Allemagne', 'Cameroun', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000011', 4, 1, '2026-03-26 01:59:08', NULL),
(12, '000000012', 'Client12', 'Studely12', 'Client12 Studely12', 'client12.studely12@studelyledger.com', '0600000012', 'Côte d\'Ivoire', 'France', 'Côte d\'Ivoire', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000012', 6, 1, '2026-03-26 01:59:08', NULL),
(13, '000000013', 'Client13', 'Studely13', 'Client13 Studely13', 'client13.studely13@studelyledger.com', '0600000013', 'Maroc', 'Belgique', 'Maroc', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000013', 8, 1, '2026-03-26 01:59:08', NULL),
(14, '000000014', 'Client14', 'Studely14', 'Client14 Studely14', 'client14.studely14@studelyledger.com', '0600000014', 'Tunisie', 'Allemagne', 'Tunisie', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000014', 7, 1, '2026-03-26 01:59:08', NULL),
(15, '000000015', 'Client15', 'Studely15', 'Client15 Studely15', 'client15.studely15@studelyledger.com', '0600000015', 'Sénégal', 'France', 'Sénégal', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000015', 5, 1, '2026-03-26 01:59:08', NULL),
(16, '000000016', 'Client16', 'Studely16', 'Client16 Studely16', 'client16.studely16@studelyledger.com', '0600000016', 'Cameroun', 'Belgique', 'Cameroun', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000016', 4, 1, '2026-03-26 01:59:08', NULL),
(17, '000000017', 'Client17', 'Studely17', 'Client17 Studely17', 'client17.studely17@studelyledger.com', '0600000017', 'Côte d\'Ivoire', 'Allemagne', 'Côte d\'Ivoire', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000017', 6, 1, '2026-03-26 01:59:08', NULL),
(18, '000000018', 'Client18', 'Studely18', 'Client18 Studely18', 'client18.studely18@studelyledger.com', '0600000018', 'Maroc', 'France', 'Maroc', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000018', 8, 1, '2026-03-26 01:59:08', NULL),
(19, '000000019', 'Client19', 'Studely19', 'Client19 Studely19', 'client19.studely19@studelyledger.com', '0600000019', 'Tunisie', 'Belgique', 'Tunisie', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000019', 7, 1, '2026-03-26 01:59:08', NULL),
(20, '000000020', 'Client20', 'Studely20', 'Client20 Studely20', 'client20.studely20@studelyledger.com', '0600000020', 'Sénégal', 'Allemagne', 'Sénégal', 'Nouveau Client', 'Etudiant Actif', 'EUR', '411000000020', 5, 1, '2026-03-26 01:59:08', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `client_bank_accounts`
--

CREATE TABLE `client_bank_accounts` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `client_bank_accounts`
--

INSERT INTO `client_bank_accounts` (`id`, `client_id`, `bank_account_id`, `created_at`) VALUES
(1, 1, 1, '2026-03-26 00:47:07'),
(2, 2, 2, '2026-03-26 00:47:07'),
(3, 3, 3, '2026-03-26 00:47:07'),
(4, 4, 4, '2026-03-26 01:59:08'),
(5, 5, 5, '2026-03-26 01:59:08'),
(6, 6, 6, '2026-03-26 01:59:08'),
(7, 7, 7, '2026-03-26 01:59:08'),
(8, 8, 8, '2026-03-26 01:59:08'),
(9, 9, 9, '2026-03-26 01:59:08'),
(10, 10, 10, '2026-03-26 01:59:08'),
(11, 11, 11, '2026-03-26 01:59:08'),
(12, 12, 12, '2026-03-26 01:59:08'),
(13, 13, 13, '2026-03-26 01:59:08'),
(14, 14, 14, '2026-03-26 01:59:08'),
(15, 15, 15, '2026-03-26 01:59:08'),
(16, 16, 16, '2026-03-26 01:59:08'),
(17, 17, 17, '2026-03-26 01:59:08'),
(18, 18, 18, '2026-03-26 01:59:08'),
(19, 19, 19, '2026-03-26 01:59:08'),
(20, 20, 20, '2026-03-26 01:59:08');

-- --------------------------------------------------------

--
-- Structure de la table `imports`
--

CREATE TABLE `imports` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `import_rows`
--

CREATE TABLE `import_rows` (
  `id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `raw_data` longtext DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `operation_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `operation_type_code` varchar(50) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `debit_account_code` varchar(50) DEFAULT NULL,
  `credit_account_code` varchar(50) DEFAULT NULL,
  `service_account_code` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `operations`
--

INSERT INTO `operations` (`id`, `client_id`, `bank_account_id`, `operation_date`, `amount`, `operation_type_code`, `label`, `reference`, `notes`, `debit_account_code`, `credit_account_code`, `service_account_code`, `created_at`) VALUES
(1, 1, 1, '2026-03-25', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000001', 'Peuplement défensif', '5120401', '411000000001', NULL, '2026-03-26 01:59:08'),
(2, 2, 2, '2026-03-24', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000002', 'Peuplement défensif', '5120502', '411000000002', NULL, '2026-03-26 01:59:08'),
(3, 3, 3, '2026-03-23', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000003', 'Peuplement défensif', '5120601', '411000000003', NULL, '2026-03-26 01:59:08'),
(4, 4, 4, '2026-03-22', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000004', 'Peuplement défensif', '5121901', '411000000004', NULL, '2026-03-26 01:59:08'),
(5, 5, 5, '2026-03-21', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000005', 'Peuplement défensif', '5120502', '411000000005', NULL, '2026-03-26 01:59:08'),
(6, 6, 6, '2026-03-20', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000006', 'Peuplement défensif', '5120401', '411000000006', NULL, '2026-03-26 01:59:08'),
(7, 7, 7, '2026-03-19', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000007', 'Peuplement défensif', '5120601', '411000000007', NULL, '2026-03-26 01:59:08'),
(8, 8, 8, '2026-03-18', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000008', 'Peuplement défensif', '5122001', '411000000008', NULL, '2026-03-26 01:59:08'),
(9, 9, 9, '2026-03-17', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000009', 'Peuplement défensif', '5121901', '411000000009', NULL, '2026-03-26 01:59:08'),
(10, 10, 10, '2026-03-16', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000010', 'Peuplement défensif', '5120502', '411000000010', NULL, '2026-03-26 01:59:08'),
(11, 11, 11, '2026-03-15', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000011', 'Peuplement défensif', '5120401', '411000000011', NULL, '2026-03-26 01:59:08'),
(12, 12, 12, '2026-03-26', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000012', 'Peuplement défensif', '5120601', '411000000012', NULL, '2026-03-26 01:59:08'),
(13, 13, 13, '2026-03-25', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000013', 'Peuplement défensif', '5122001', '411000000013', NULL, '2026-03-26 01:59:08'),
(14, 14, 14, '2026-03-24', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000014', 'Peuplement défensif', '5121901', '411000000014', NULL, '2026-03-26 01:59:08'),
(15, 15, 15, '2026-03-23', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000015', 'Peuplement défensif', '5120502', '411000000015', NULL, '2026-03-26 01:59:08'),
(16, 16, 16, '2026-03-22', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000016', 'Peuplement défensif', '5120401', '411000000016', NULL, '2026-03-26 01:59:08'),
(17, 17, 17, '2026-03-21', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000017', 'Peuplement défensif', '5120601', '411000000017', NULL, '2026-03-26 01:59:08'),
(18, 18, 18, '2026-03-20', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000018', 'Peuplement défensif', '5122001', '411000000018', NULL, '2026-03-26 01:59:08'),
(19, 19, 19, '2026-03-19', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000019', 'Peuplement défensif', '5121901', '411000000019', NULL, '2026-03-26 01:59:08'),
(20, 20, 20, '2026-03-18', 1200.00, 'VERSEMENT', 'Versement initial client', 'REF-VERS-000000020', 'Peuplement défensif', '5120502', '411000000020', NULL, '2026-03-26 01:59:08'),
(32, 1, 1, '2026-03-25', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000001', 'Peuplement défensif', '411000000001', '7061304', '7061304', '2026-03-26 01:59:08'),
(33, 2, 2, '2026-03-24', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000002', 'Peuplement défensif', '411000000002', '7061304', '7061304', '2026-03-26 01:59:08'),
(34, 3, 3, '2026-03-23', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000003', 'Peuplement défensif', '411000000003', '7061304', '7061304', '2026-03-26 01:59:08'),
(35, 4, 4, '2026-03-22', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000004', 'Peuplement défensif', '411000000004', '7061304', '7061304', '2026-03-26 01:59:08'),
(36, 5, 5, '2026-03-21', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000005', 'Peuplement défensif', '411000000005', '7061304', '7061304', '2026-03-26 01:59:08'),
(37, 6, 6, '2026-03-20', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000006', 'Peuplement défensif', '411000000006', '7061304', '7061304', '2026-03-26 01:59:08'),
(38, 7, 7, '2026-03-19', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000007', 'Peuplement défensif', '411000000007', '7061304', '7061304', '2026-03-26 01:59:08'),
(39, 8, 8, '2026-03-18', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000008', 'Peuplement défensif', '411000000008', '7061304', '7061304', '2026-03-26 01:59:08'),
(40, 9, 9, '2026-03-17', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000009', 'Peuplement défensif', '411000000009', '7061304', '7061304', '2026-03-26 01:59:08'),
(41, 10, 10, '2026-03-26', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000010', 'Peuplement défensif', '411000000010', '7061304', '7061304', '2026-03-26 01:59:08'),
(42, 11, 11, '2026-03-25', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000011', 'Peuplement défensif', '411000000011', '7061304', '7061304', '2026-03-26 01:59:08'),
(43, 12, 12, '2026-03-24', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000012', 'Peuplement défensif', '411000000012', '7061304', '7061304', '2026-03-26 01:59:08'),
(44, 13, 13, '2026-03-23', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000013', 'Peuplement défensif', '411000000013', '7061304', '7061304', '2026-03-26 01:59:08'),
(45, 14, 14, '2026-03-22', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000014', 'Peuplement défensif', '411000000014', '7061304', '7061304', '2026-03-26 01:59:08'),
(46, 15, 15, '2026-03-21', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000015', 'Peuplement défensif', '411000000015', '7061304', '7061304', '2026-03-26 01:59:08'),
(47, 16, 16, '2026-03-20', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000016', 'Peuplement défensif', '411000000016', '7061304', '7061304', '2026-03-26 01:59:08'),
(48, 17, 17, '2026-03-19', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000017', 'Peuplement défensif', '411000000017', '7061304', '7061304', '2026-03-26 01:59:08'),
(49, 18, 18, '2026-03-18', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000018', 'Peuplement défensif', '411000000018', '7061304', '7061304', '2026-03-26 01:59:08'),
(50, 19, 19, '2026-03-17', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000019', 'Peuplement défensif', '411000000019', '7061304', '7061304', '2026-03-26 01:59:08'),
(51, 20, 20, '2026-03-26', 350.00, 'FRAIS_DE_SERVICE', 'Frais de service AVI', 'REF-FRAIS-000000020', 'Peuplement défensif', '411000000020', '7061304', '7061304', '2026-03-26 01:59:08'),
(63, 1, 1, '2026-03-25', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000001', 'Peuplement défensif', '411000000001', '5120401', NULL, '2026-03-26 01:59:08'),
(64, 2, 2, '2026-03-24', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000002', 'Peuplement défensif', '411000000002', '5120502', NULL, '2026-03-26 01:59:08'),
(65, 3, 3, '2026-03-23', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000003', 'Peuplement défensif', '411000000003', '5120601', NULL, '2026-03-26 01:59:08'),
(66, 4, 4, '2026-03-22', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000004', 'Peuplement défensif', '411000000004', '5121901', NULL, '2026-03-26 01:59:08'),
(67, 5, 5, '2026-03-21', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000005', 'Peuplement défensif', '411000000005', '5120502', NULL, '2026-03-26 01:59:08'),
(68, 6, 6, '2026-03-20', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000006', 'Peuplement défensif', '411000000006', '5120401', NULL, '2026-03-26 01:59:08'),
(69, 7, 7, '2026-03-19', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000007', 'Peuplement défensif', '411000000007', '5120601', NULL, '2026-03-26 01:59:08'),
(70, 8, 8, '2026-03-26', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000008', 'Peuplement défensif', '411000000008', '5122001', NULL, '2026-03-26 01:59:08'),
(71, 9, 9, '2026-03-25', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000009', 'Peuplement défensif', '411000000009', '5121901', NULL, '2026-03-26 01:59:08'),
(72, 10, 10, '2026-03-24', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000010', 'Peuplement défensif', '411000000010', '5120502', NULL, '2026-03-26 01:59:08'),
(73, 11, 11, '2026-03-23', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000011', 'Peuplement défensif', '411000000011', '5120401', NULL, '2026-03-26 01:59:08'),
(74, 12, 12, '2026-03-22', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000012', 'Peuplement défensif', '411000000012', '5120601', NULL, '2026-03-26 01:59:08'),
(75, 13, 13, '2026-03-21', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000013', 'Peuplement défensif', '411000000013', '5122001', NULL, '2026-03-26 01:59:08'),
(76, 14, 14, '2026-03-20', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000014', 'Peuplement défensif', '411000000014', '5121901', NULL, '2026-03-26 01:59:08'),
(77, 15, 15, '2026-03-19', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000015', 'Peuplement défensif', '411000000015', '5120502', NULL, '2026-03-26 01:59:08'),
(78, 16, 16, '2026-03-26', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000016', 'Peuplement défensif', '411000000016', '5120401', NULL, '2026-03-26 01:59:08'),
(79, 17, 17, '2026-03-25', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000017', 'Peuplement défensif', '411000000017', '5120601', NULL, '2026-03-26 01:59:08'),
(80, 18, 18, '2026-03-24', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000018', 'Peuplement défensif', '411000000018', '5122001', NULL, '2026-03-26 01:59:08'),
(81, 19, 19, '2026-03-23', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000019', 'Peuplement défensif', '411000000019', '5121901', NULL, '2026-03-26 01:59:08'),
(82, 20, 20, '2026-03-22', 500.00, 'VIREMENT_MENSUEL', 'Virement mensuel', 'REF-VIR-000000020', 'Peuplement défensif', '411000000020', '5120502', NULL, '2026-03-26 01:59:08');

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `code` varchar(120) NOT NULL,
  `label` varchar(180) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `code`, `label`, `created_at`) VALUES
(1, 'dashboard_view', 'Voir dashboard', '2026-03-26 23:43:44'),
(2, 'clients_view', 'Voir clients', '2026-03-26 23:43:44'),
(3, 'clients_create', 'Créer / modifier clients', '2026-03-26 23:43:44'),
(4, 'operations_view', 'Voir opérations', '2026-03-26 23:43:44'),
(5, 'operations_create', 'Créer / modifier opérations', '2026-03-26 23:43:44'),
(6, 'treasury_view', 'Gérer comptes internes', '2026-03-26 23:43:44'),
(7, 'imports_preview', 'Prévisualiser imports', '2026-03-26 23:43:44'),
(8, 'imports_validate', 'Valider imports', '2026-03-26 23:43:44'),
(9, 'imports_journal', 'Voir journal imports', '2026-03-26 23:43:44'),
(10, 'statements_export', 'Exporter relevés et fiches', '2026-03-26 23:43:44'),
(11, 'admin_dashboard_view', 'Voir dashboard admin technique', '2026-03-26 23:43:44'),
(12, 'admin_users_manage', 'Gérer utilisateurs', '2026-03-26 23:43:44'),
(13, 'admin_roles_manage', 'Gérer rôles', '2026-03-26 23:43:44'),
(14, 'admin_logs_view', 'Voir logs', '2026-03-26 23:43:44');

-- --------------------------------------------------------

--
-- Structure de la table `ref_operation_types`
--

CREATE TABLE `ref_operation_types` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `label` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_operation_types`
--

INSERT INTO `ref_operation_types` (`id`, `code`, `label`, `is_active`, `created_at`) VALUES
(1, 'VERSEMENT', 'Versement', 1, '2026-03-26 00:47:06'),
(2, 'FRAIS_DE_SERVICE', 'Frais de service', 1, '2026-03-26 00:47:06'),
(3, 'VIREMENT_MENSUEL', 'Virement mensuel', 1, '2026-03-26 00:47:06'),
(4, 'VIREMENT_EXCEPTIONEL', 'Virement exceptionnel', 1, '2026-03-26 00:47:06'),
(5, 'REGULARISATION_POSITIVE', 'Régularisation positive', 1, '2026-03-26 00:47:06'),
(6, 'REGULARISATION_NEGATIVE', 'Régularisation négative', 1, '2026-03-26 00:47:06'),
(7, 'FRAIS_BANCAIRES', 'Frais bancaires', 1, '2026-03-26 00:47:06'),
(8, 'VIREMENT_INTERNE', 'Virement interne', 1, '2026-03-26 00:47:06'),
(9, 'VIREMENT_REGULIER', 'Virement régulier', 1, '2026-03-26 00:47:06');

-- --------------------------------------------------------

--
-- Structure de la table `ref_services`
--

CREATE TABLE `ref_services` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `label` varchar(150) NOT NULL,
  `operation_type_id` int(11) DEFAULT NULL,
  `service_account_id` int(11) DEFAULT NULL,
  `treasury_account_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_services`
--

INSERT INTO `ref_services` (`id`, `code`, `label`, `operation_type_id`, `service_account_id`, `treasury_account_id`, `is_active`, `created_at`) VALUES
(1, 'AVI', 'FRAIS DE SERVICES AVI', 2, 1, NULL, 1, '2026-03-26 00:47:06'),
(2, 'GESTION', 'FRAIS DE GESTION', 2, 4, NULL, 1, '2026-03-26 00:47:06'),
(3, 'TRANSFERT', 'COMMISSION DE TRANSFERT', 2, 6, NULL, 1, '2026-03-26 00:47:06'),
(4, 'AVI_BENIN', 'FRAIS DE SERVICES AVI BENIN', 2, 7, NULL, 1, '2026-03-26 01:59:08');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `code`, `label`, `created_at`) VALUES
(1, 'admin_tech', 'Administrateur Technique', '2026-03-26 23:43:44'),
(2, 'admin_func', 'Administrateur Fonctionnel', '2026-03-26 23:43:44'),
(3, 'user_standard', 'Utilisateur Standard', '2026-03-26 23:43:44');

-- --------------------------------------------------------

--
-- Structure de la table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(3, 1),
(3, 2),
(3, 4),
(3, 10);

-- --------------------------------------------------------

--
-- Structure de la table `service_accounts`
--

CREATE TABLE `service_accounts` (
  `id` int(11) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_label` varchar(150) NOT NULL,
  `operation_type_label` varchar(255) DEFAULT NULL,
  `destination_country_label` varchar(100) DEFAULT NULL,
  `commercial_country_label` varchar(100) DEFAULT NULL,
  `level_depth` int(11) NOT NULL DEFAULT 3,
  `is_postable` tinyint(1) NOT NULL DEFAULT 1,
  `current_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_accounts`
--

INSERT INTO `service_accounts` (`id`, `account_code`, `account_label`, `operation_type_label`, `destination_country_label`, `commercial_country_label`, `level_depth`, `is_postable`, `current_balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '7061304', 'FRAIS DE SERVICE AVI FRANCE-Cameroun', 'FRAIS DE SERVICES AVI', 'France', 'Cameroun', 4, 1, 0.00, 1, '2026-03-26 00:47:06', NULL),
(2, '7061305', 'FRAIS DE SERVICE AVI FRANCE-Sénégal', 'FRAIS DE SERVICES AVI', 'France', 'Sénégal', 4, 1, 0.00, 1, '2026-03-26 00:47:06', NULL),
(3, '7061306', 'FRAIS DE SERVICE AVI FRANCE-Côte d\'Ivoire', 'FRAIS DE SERVICES AVI', 'France', 'Côte d\'Ivoire', 4, 1, 0.00, 1, '2026-03-26 00:47:06', NULL),
(4, '706204', 'FRAIS DE GESTION-Cameroun', 'FRAIS DE GESTION', NULL, 'Cameroun', 3, 1, 0.00, 1, '2026-03-26 00:47:06', NULL),
(5, '706205', 'FRAIS DE GESTION-Sénégal', 'FRAIS DE GESTION', NULL, 'Sénégal', 3, 1, 0.00, 1, '2026-03-26 00:47:06', NULL),
(6, '706304', 'COMMISSION DE TRANSFERT-Cameroun', 'COMMISSION DE TRANSFERT', NULL, 'Cameroun', 3, 1, 0.00, 1, '2026-03-26 00:47:06', NULL),
(7, '7061307', 'FRAIS DE SERVICE AVI FRANCE-Benin', 'FRAIS DE SERVICES AVI', 'France', 'Benin', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(8, '7061308', 'FRAIS DE SERVICE AVI FRANCE-Burkina Faso', 'FRAIS DE SERVICES AVI', 'France', 'Burkina Faso', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(9, '7061309', 'FRAIS DE SERVICE AVI FRANCE-Congo Brazzaville', 'FRAIS DE SERVICES AVI', 'France', 'Congo Brazzaville', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(10, '7061310', 'FRAIS DE SERVICE AVI FRANCE-Congo Kinshasa', 'FRAIS DE SERVICES AVI', 'France', 'Congo Kinshasa', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(11, '7061311', 'FRAIS DE SERVICE AVI FRANCE-Gabon', 'FRAIS DE SERVICES AVI', 'France', 'Gabon', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(12, '7061312', 'FRAIS DE SERVICE AVI FRANCE-Tchad', 'FRAIS DE SERVICES AVI', 'France', 'Tchad', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(13, '7061313', 'FRAIS DE SERVICE AVI FRANCE-Mali', 'FRAIS DE SERVICES AVI', 'France', 'Mali', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(14, '7061314', 'FRAIS DE SERVICE AVI FRANCE-Togo', 'FRAIS DE SERVICES AVI', 'France', 'Togo', 4, 1, 0.00, 1, '2026-03-26 01:59:08', NULL),
(15, '706206', 'FRAIS DE GESTION-Côte d\'Ivoire', 'FRAIS DE GESTION', NULL, 'Côte d\'Ivoire', 3, 1, 0.00, 1, '2026-03-26 01:59:08', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `support_requests`
--

CREATE TABLE `support_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `request_type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `treasury_accounts`
--

CREATE TABLE `treasury_accounts` (
  `id` int(11) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_label` varchar(150) NOT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `subsidiary_name` varchar(150) DEFAULT NULL,
  `zone_code` varchar(20) DEFAULT NULL,
  `country_label` varchar(100) DEFAULT NULL,
  `country_type` varchar(50) DEFAULT NULL,
  `payment_place` varchar(50) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `opening_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `treasury_accounts`
--

INSERT INTO `treasury_accounts` (`id`, `account_code`, `account_label`, `bank_name`, `subsidiary_name`, `zone_code`, `country_label`, `country_type`, `payment_place`, `currency_code`, `opening_balance`, `current_balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '5120101', 'Fr_LCL_C', 'Fr_LCL_C', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(2, '5120102', 'Fr_LCL_M', 'Fr_LCL_M', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(3, '5120301', 'BE_QUONTO', 'BE_QUONTO', 'Studely', 'EU', 'Belgique', 'Filiale', 'France', 'EUR', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(4, '5120401', 'CM_BAC', 'CM_BAC', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(5, '5120502', 'SN_ECOBQ', 'SN_ECOBQ', 'Studely', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(6, '5120601', 'CIV_ECOBQ', 'CIV_ECOBQ', 'Studely', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(7, '5121901', 'TN_ATTI', 'TN_ATTI', 'Studely', 'AN', 'Tunisie', 'Filiale', 'Local', 'EUR', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(8, '5122001', 'MA_ATTI', 'MA_ATTI', 'Studely', 'AN', 'Maroc', 'Filiale', 'Local', 'EUR', 1000000.00, 1000000.00, 1, '2026-03-26 00:47:06', NULL),
(9, '5120402', 'CM_BAC_EXPL', 'CM_BAC_EXPL', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(10, '5120403', 'CM_BAC_REM', 'CM_BAC_REM', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(11, '5120404', 'CM_BGFI_DE', 'CM_BGFI_DE', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(12, '5120501', 'SF_SN_EcoBQ', 'SF_SN_EcoBQ', 'Studely Finance', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(13, '5120602', 'SF_CIV_AFG', 'SF_CIV_AFG', 'Studely Finance', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(14, '5120701', 'BN_ECOBQ', 'BN_ECOBQ', 'Studely', 'AO', 'Benin', 'Filiale', 'Local', 'XOF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(15, '5120801', 'BFA_ECOBQ', 'BFA_ECOBQ', 'Studely', 'AO', 'Burkina Faso', 'Filiale', 'Local', 'XOF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(16, '5121001', 'RD_BGFI', 'RD_BGFI', 'Studely', 'AC', 'Congo Kinshasa', 'Filiale', 'Local', 'XAF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(17, '5121101', 'GB_BGFI', 'GB_BGFI', 'Studely', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL),
(18, '5121201', 'SF_CHD_ECOBAQ', 'SF_CHD_ECOBAQ', 'Studely Finance', 'AC', 'Tchad', 'Filiale', 'Local', 'XAF', 1000000.00, 1000000.00, 1, '2026-03-26 01:59:08', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `treasury_movements`
--

CREATE TABLE `treasury_movements` (
  `id` int(11) NOT NULL,
  `source_treasury_account_id` int(11) NOT NULL,
  `target_treasury_account_id` int(11) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `operation_date` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `role_id` int(11) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `role_id`, `last_login_at`, `created_at`) VALUES
(1, 'admin', '$2y$10$TLhKOHu6WQmUROqhcksMpuKGeS5VBykyuo3cMb1abDm6l8psZOV/6', 'admin', 1, NULL, '2026-03-26 00:47:07');

-- --------------------------------------------------------

--
-- Structure de la table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `module` varchar(100) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_code` (`client_code`),
  ADD KEY `fk_clients_initial_treasury` (`initial_treasury_account_id`);

--
-- Index pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_client_bank_account` (`client_id`,`bank_account_id`),
  ADD KEY `fk_client_bank_accounts_bank` (`bank_account_id`);

--
-- Index pour la table `imports`
--
ALTER TABLE `imports`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `import_rows`
--
ALTER TABLE `import_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_import_rows_import` (`import_id`),
  ADD KEY `idx_import_rows_status` (`status`);

--
-- Index pour la table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_operations_bank` (`bank_account_id`),
  ADD KEY `idx_operations_client_date` (`client_id`,`operation_date`),
  ADD KEY `idx_operations_reference` (`reference`),
  ADD KEY `idx_operations_type` (`operation_type_code`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `ref_operation_types`
--
ALTER TABLE `ref_operation_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `ref_services`
--
ALTER TABLE `ref_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_ref_services_operation_type` (`operation_type_id`),
  ADD KEY `fk_ref_services_service_account` (`service_account_id`),
  ADD KEY `fk_ref_services_treasury_account` (`treasury_account_id`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

--
-- Index pour la table `service_accounts`
--
ALTER TABLE `service_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`);

--
-- Index pour la table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_support_requests_user` (`user_id`);

--
-- Index pour la table `treasury_accounts`
--
ALTER TABLE `treasury_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`);

--
-- Index pour la table `treasury_movements`
--
ALTER TABLE `treasury_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_treasury_movements_source` (`source_treasury_account_id`),
  ADD KEY `fk_treasury_movements_target` (`target_treasury_account_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- Index pour la table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_logs_user` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT pour la table `imports`
--
ALTER TABLE `imports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `import_rows`
--
ALTER TABLE `import_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `ref_operation_types`
--
ALTER TABLE `ref_operation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `ref_services`
--
ALTER TABLE `ref_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `service_accounts`
--
ALTER TABLE `service_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `treasury_accounts`
--
ALTER TABLE `treasury_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `treasury_movements`
--
ALTER TABLE `treasury_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_initial_treasury` FOREIGN KEY (`initial_treasury_account_id`) REFERENCES `treasury_accounts` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  ADD CONSTRAINT `fk_client_bank_accounts_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_client_bank_accounts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `import_rows`
--
ALTER TABLE `import_rows`
  ADD CONSTRAINT `fk_import_rows_import` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `operations`
--
ALTER TABLE `operations`
  ADD CONSTRAINT `fk_operations_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_operations_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `ref_services`
--
ALTER TABLE `ref_services`
  ADD CONSTRAINT `fk_ref_services_operation_type` FOREIGN KEY (`operation_type_id`) REFERENCES `ref_operation_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ref_services_service_account` FOREIGN KEY (`service_account_id`) REFERENCES `service_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ref_services_treasury_account` FOREIGN KEY (`treasury_account_id`) REFERENCES `treasury_accounts` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `support_requests`
--
ALTER TABLE `support_requests`
  ADD CONSTRAINT `fk_support_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `treasury_movements`
--
ALTER TABLE `treasury_movements`
  ADD CONSTRAINT `fk_treasury_movements_source` FOREIGN KEY (`source_treasury_account_id`) REFERENCES `treasury_accounts` (`id`),
  ADD CONSTRAINT `fk_treasury_movements_target` FOREIGN KEY (`target_treasury_account_id`) REFERENCES `treasury_accounts` (`id`);

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `fk_user_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
