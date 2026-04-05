-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 05 avr. 2026 à 21:24
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
-- Structure de la table `accounting_rules`
--

CREATE TABLE `accounting_rules` (
  `id` int(11) NOT NULL,
  `operation_type_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `rule_code` varchar(120) NOT NULL,
  `debit_mode` varchar(50) NOT NULL,
  `credit_mode` varchar(50) NOT NULL,
  `debit_fixed_account_code` varchar(50) DEFAULT NULL,
  `credit_fixed_account_code` varchar(50) DEFAULT NULL,
  `requires_client` tinyint(1) NOT NULL DEFAULT 1,
  `requires_linked_bank` tinyint(1) NOT NULL DEFAULT 0,
  `requires_manual_accounts` tinyint(1) NOT NULL DEFAULT 0,
  `label_pattern` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `account_categories`
--

CREATE TABLE `account_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `account_categories`
--

INSERT INTO `account_categories` (`id`, `name`, `created_at`) VALUES
(1, 'Client', '2026-03-28 15:49:57'),
(2, 'Trésorerie', '2026-03-28 15:49:57'),
(3, 'Produit / Service', '2026-03-28 15:49:57');

-- --------------------------------------------------------

--
-- Structure de la table `account_types`
--

CREATE TABLE `account_types` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `account_types`
--

INSERT INTO `account_types` (`id`, `name`, `created_at`) VALUES
(1, 'Compte client', '2026-03-28 15:49:57'),
(2, 'Compte bancaire entreprise', '2026-03-28 15:49:57'),
(3, 'Compte interne', '2026-03-28 15:49:57');

-- --------------------------------------------------------

--
-- Structure de la table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(100) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `country` varchar(150) DEFAULT NULL,
  `account_type_id` int(11) DEFAULT NULL,
  `account_category_id` int(11) DEFAULT NULL,
  `initial_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `account_name`, `account_number`, `bank_name`, `country`, `account_type_id`, `account_category_id`, `initial_balance`, `balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Compte client CLT0001', '411CLT0001', 'Compte client interne', 'France', NULL, NULL, 0.00, 11450.00, 1, '2026-03-29 00:19:28', '2026-04-04 23:36:43'),
(2, 'Compte client CLT0002', '411CLT0002', 'Compte client interne', 'France', NULL, NULL, 0.00, 8505.00, 1, '2026-03-29 00:19:28', '2026-04-04 23:36:43'),
(3, 'Compte client CLT0003', '411CLT0003', 'Compte client interne', 'Belgique', NULL, NULL, 0.00, 14580.00, 1, '2026-03-29 00:19:28', '2026-04-04 23:36:43'),
(4, 'Compte client CLT0004', '411CLT0004', 'Compte client interne', 'Cameroun', NULL, NULL, 0.00, 585000.00, 1, '2026-03-29 00:19:28', '2026-04-04 23:36:43'),
(5, 'Compte client CLT0005', '411CLT0005', 'Compte client interne', 'France', NULL, NULL, 0.00, 725392.00, 1, '2026-03-29 00:19:28', '2026-04-04 23:36:43'),
(6, 'Compte client CLT0006', '411CLT0006', 'Compte client interne', 'Espagne', NULL, NULL, 0.00, 282000.00, 1, '2026-03-29 00:19:28', '2026-04-04 23:36:43');

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(1, 'AVI', '2026-03-28 15:49:57'),
(2, 'Transfert', '2026-03-28 15:49:57'),
(3, 'Compte de paiement', '2026-03-28 15:49:57'),
(4, 'ATS', '2026-03-28 15:49:57'),
(5, 'Placement', '2026-03-28 15:49:57'),
(6, 'Divers', '2026-03-28 15:49:57');

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(20) NOT NULL,
  `first_name` varchar(150) NOT NULL,
  `last_name` varchar(150) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `postal_address` varchar(255) DEFAULT NULL,
  `passport_number` varchar(100) DEFAULT NULL,
  `passport_issue_country` varchar(150) DEFAULT NULL,
  `passport_issue_date` date DEFAULT NULL,
  `passport_expiry_date` date DEFAULT NULL,
  `country_origin` varchar(150) DEFAULT NULL,
  `country_destination` varchar(150) DEFAULT NULL,
  `country_commercial` varchar(150) DEFAULT NULL,
  `client_type` varchar(150) DEFAULT NULL,
  `client_status` varchar(150) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'EUR',
  `generated_client_account` varchar(50) NOT NULL,
  `initial_treasury_account_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `first_name`, `last_name`, `full_name`, `email`, `phone`, `postal_address`, `passport_number`, `passport_issue_country`, `passport_issue_date`, `passport_expiry_date`, `country_origin`, `country_destination`, `country_commercial`, `client_type`, `client_status`, `status_id`, `category_id`, `currency`, `generated_client_account`, `initial_treasury_account_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CLT0001', 'Aminata', 'Diallo', 'Aminata Diallo', 'aminata.diallo@test.local', '+221700000001', NULL, NULL, NULL, NULL, NULL, 'Sénégal', 'France', 'Sénégal', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411CLT0001', 1, 1, '2026-03-29 00:19:28', '2026-03-31 22:41:57'),
(2, 'CLT0002', 'Moussa', 'Traore', 'Moussa Traore', 'moussa.traore@test.local', '+223700000002', NULL, NULL, NULL, NULL, NULL, 'Mali', 'France', 'France', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411CLT0002', 2, 1, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(3, 'CLT0003', 'Sarah', 'Nguessan', 'Sarah Nguessan', 'sarah.nguessan@test.local', '+225700000003', NULL, NULL, NULL, NULL, NULL, 'Côte d’Ivoire', 'Belgique', 'Belgique', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411CLT0003', 10, 1, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(4, 'CLT0004', 'Kevin', 'Mba', 'Kevin Mba', 'kevin.mba@test.local', '+237700000004', NULL, NULL, NULL, NULL, NULL, 'Cameroun', 'Autres destinations', 'Cameroun', 'Particulier', 'Actif', NULL, NULL, 'XAF', '411CLT0004', 12, 1, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(5, 'CLT0005', 'Grace', 'Ekué', 'Grace Ekué', 'grace.ekue@test.local', '+228700000005', NULL, NULL, NULL, NULL, NULL, 'Togo', 'France', 'Togo', 'Entreprise', 'Actif', NULL, NULL, 'XOF', '411CLT0005', 48, 1, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(6, 'CLT0006', 'Nadia', 'Benali', 'Nadia Benali', 'nadia.benali@test.local', '+213700000006', NULL, NULL, NULL, NULL, NULL, 'Algérie', 'Espagne', 'Algérie', 'Partenaire', 'Actif', NULL, NULL, 'DZD', '411CLT0006', 50, 1, '2026-03-29 00:19:28', '2026-03-29 00:19:28');

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
(1, 1, 1, '2026-03-29 00:19:28'),
(2, 2, 2, '2026-03-29 00:19:28'),
(3, 3, 3, '2026-03-29 00:19:28'),
(4, 4, 4, '2026-03-29 00:19:28'),
(5, 5, 5, '2026-03-29 00:19:28'),
(6, 6, 6, '2026-03-29 00:19:28');

-- --------------------------------------------------------

--
-- Structure de la table `currencies`
--

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `label` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `label`, `is_active`, `created_at`) VALUES
(1, 'EUR', 'Euro', 1, '2026-03-31 22:34:41'),
(2, 'USD', 'Dollar US', 1, '2026-03-31 22:34:41'),
(3, 'GBP', 'Livre Sterling', 1, '2026-03-31 22:34:41'),
(4, 'XAF', 'Franc CFA BEAC', 1, '2026-03-31 22:34:41'),
(5, 'XOF', 'Franc CFA BCEAO', 1, '2026-03-31 22:34:41'),
(6, 'CAD', 'Dollar Canadien', 1, '2026-03-31 22:34:41'),
(7, 'MAD', 'Dirham Marocain', 1, '2026-03-31 22:34:41'),
(8, 'TND', 'Dinar Tunisien', 1, '2026-03-31 22:34:41'),
(9, 'DZD', 'Dinar Algérien', 1, '2026-03-31 22:34:41'),
(10, 'INR', 'Roupie Indienne', 1, '2026-03-31 22:34:41');

-- --------------------------------------------------------

--
-- Structure de la table `imports`
--

CREATE TABLE `imports` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `status` varchar(100) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `import_rows`
--

CREATE TABLE `import_rows` (
  `id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `raw_data` longtext DEFAULT NULL,
  `status` varchar(100) NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `message` varchar(255) NOT NULL,
  `level` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `link_url` varchar(255) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `operation_type_id` int(11) DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `linked_bank_account_id` int(11) DEFAULT NULL,
  `operation_date` date NOT NULL,
  `operation_type_code` varchar(100) NOT NULL,
  `operation_kind` varchar(100) DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `source_type` varchar(100) DEFAULT NULL,
  `debit_account_code` varchar(50) DEFAULT NULL,
  `credit_account_code` varchar(50) DEFAULT NULL,
  `service_account_code` varchar(50) DEFAULT NULL,
  `operation_hash` varchar(64) DEFAULT NULL,
  `is_manual_accounting` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `operations`
--

INSERT INTO `operations` (`id`, `client_id`, `service_id`, `operation_type_id`, `bank_account_id`, `linked_bank_account_id`, `operation_date`, `operation_type_code`, `operation_kind`, `label`, `amount`, `currency_code`, `reference`, `source_type`, `debit_account_code`, `credit_account_code`, `service_account_code`, `operation_hash`, `is_manual_accounting`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, 1, NULL, '2026-03-01', 'VERSEMENT', 'seed', 'Versement initial client 1', 12000.00, NULL, 'TEST-OP-0001', 'seed', '5120101', '411CLT0001', NULL, NULL, 0, 'Alimentation initiale', NULL, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(2, 1, NULL, NULL, 1, NULL, '2026-03-03', 'FRAIS_DE_SERVICE', 'seed', 'Frais de services AVI', 250.00, NULL, 'TEST-OP-0002', 'seed', '411CLT0001', '706101', '706101', NULL, 0, 'Service SRV_AVI_SERVICE', NULL, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(3, 1, NULL, NULL, 1, NULL, '2026-03-10', 'VIREMENT_MENSUEL', 'seed', 'Virement mensuel client 1', 900.00, NULL, 'TEST-OP-0003', 'seed', '411CLT0001', '5120101', NULL, NULL, 0, 'Décaissement mensuel', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(4, 2, NULL, NULL, 2, NULL, '2026-03-02', 'VERSEMENT', 'seed', 'Versement initial client 2', 8500.00, NULL, 'TEST-OP-0004', 'seed', '5120102', '411CLT0002', NULL, NULL, 0, 'Alimentation initiale', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(5, 2, NULL, NULL, 2, NULL, '2026-03-05', 'FRAIS_DE_SERVICE', 'seed', 'Frais de service ATS', 175.00, NULL, 'TEST-OP-0005', 'seed', '411CLT0002', '706104', '706104', NULL, 0, 'Service SRV_ATS_SERVICE', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(6, 2, NULL, NULL, 2, NULL, '2026-03-08', 'REGULARISATION_POSITIVE', 'seed', 'Régularisation positive client 2', 300.00, NULL, 'TEST-OP-0006', 'seed', '5120102', '411CLT0002', NULL, NULL, 0, 'Correction en faveur du client', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(7, 2, NULL, NULL, 2, NULL, '2026-03-16', 'REGULARISATION_NEGATIVE', 'seed', 'Régularisation négative client 2', 120.00, NULL, 'TEST-OP-0007', 'seed', '411CLT0002', '5120102', NULL, NULL, 0, 'Correction défavorable au client', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(8, 3, NULL, NULL, 3, NULL, '2026-03-04', 'VERSEMENT', 'seed', 'Versement initial client 3', 15000.00, NULL, 'TEST-OP-0008', 'seed', '5120301', '411CLT0003', NULL, NULL, 0, 'Alimentation initiale', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(9, 3, NULL, NULL, 3, NULL, '2026-03-06', 'VIREMENT_EXCEPTIONEL', 'seed', 'Commission de transfert', 420.00, NULL, 'TEST-OP-0009', 'seed', '411CLT0003', '5120301', '706103', NULL, 0, 'Virement exceptionnel avec commission', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(10, 4, NULL, NULL, 4, NULL, '2026-03-07', 'VERSEMENT', 'seed', 'Versement initial client 4', 600000.00, NULL, 'TEST-OP-0010', 'seed', '5120401', '411CLT0004', NULL, NULL, 0, 'Alimentation locale', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(11, 4, NULL, NULL, 4, NULL, '2026-03-09', 'FRAIS_BANCAIRES', 'seed', 'Frais de gestion', 15000.00, NULL, 'TEST-OP-0011', 'seed', '411CLT0004', '706102', '706102', NULL, 0, 'Service SRV_FRAIS_GESTION', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(12, 5, NULL, NULL, 5, NULL, '2026-03-11', 'VERSEMENT', 'seed', 'Versement initial client 5', 900000.00, NULL, 'TEST-OP-0012', 'seed', '5121401', '411CLT0005', NULL, NULL, 0, 'Alimentation initiale', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(13, 5, NULL, NULL, 5, NULL, '2026-03-13', 'VIREMENT_REGULIER', 'seed', 'Virement régulier client 5', 175000.00, NULL, 'TEST-OP-0013', 'seed', '411CLT0005', '5121401', NULL, NULL, 0, 'Décaissement régulier', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(14, 6, NULL, NULL, 6, NULL, '2026-03-12', 'VERSEMENT', 'seed', 'Versement initial client 6', 300000.00, NULL, 'TEST-OP-0014', 'seed', '5121701', '411CLT0006', NULL, NULL, 0, 'Alimentation initiale', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(15, 6, NULL, NULL, 6, NULL, '2026-03-18', 'FRAIS_DE_SERVICE', 'seed', 'Frais de services AVI', 6000.00, NULL, 'TEST-OP-0015', 'seed', '411CLT0006', '706101', '706101', NULL, 0, 'Facturation service', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(16, 6, NULL, NULL, 6, NULL, '2026-03-21', 'REGULARISATION_NEGATIVE', 'seed', 'Régularisation négative client 6', 12000.00, NULL, 'TEST-OP-0016', 'seed', '411CLT0006', '5121701', NULL, NULL, 0, 'Correction négative', NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(17, 1, NULL, NULL, 1, NULL, '2026-03-30', 'VERSEMENT', 'manual', 'VERSEMENT', 200.00, NULL, NULL, 'manual', '5120101', '411CLT0001', NULL, NULL, 0, NULL, 1, '2026-03-30 03:20:23', NULL),
(19, 5, NULL, NULL, 5, NULL, '2026-03-30', 'FRAIS_DE_SERVICE', 'manual', 'FRAIS DE SERVICE', 150.00, NULL, NULL, 'manual', '411CLT0005', '7061314', '7061314', NULL, 0, NULL, 1, '2026-03-30 03:25:40', NULL),
(20, 1, 18, 17, 1, 1, '2026-03-31', 'VERSEMENT', 'manual', 'VERSEMENT - VERSEMENT', 200.00, 'EUR', 'VERS31032026', 'manual', '5120101', '411CLT0001', NULL, 'bf0311b14fdc83d1d17e3d94ef11cc59d5f351dc15d043c6781b0568415b5534', 0, 'Versement client', 1, '2026-03-31 22:57:06', '2026-03-31 22:57:06'),
(21, 5, 18, 17, 5, 5, '2026-03-31', 'VERSEMENT', 'manual', 'VERSEMENT - VERSEMENT', 542.00, 'EUR', 'VERS0124520', 'manual', '5121401', '411CLT0005', NULL, 'e3197241aba8eccfc0daf230f97e8cf52eed8100860455a982e322099341f215', 0, NULL, 1, '2026-03-31 22:59:02', '2026-03-31 22:59:02'),
(22, 1, 22, 18, 1, 1, '2026-04-01', 'VIREMENT', 'manual', 'VIREMENT - INTERNE', 100.00, 'EUR', NULL, 'manual', '706311', '5120401', NULL, '0df8271cd21f3059fc5d53e0b4fba31e499096d1505a12619a6e2e0262453e11', 1, NULL, 1, '2026-04-01 21:44:06', '2026-04-01 21:44:06'),
(23, 1, 17, 19, 1, 1, '2026-04-01', 'REGULARISATION', 'manual', 'REGULARISATION - POSITIVE', 200.00, 'EUR', NULL, 'manual', '5120101', '411CLT0001', NULL, '697d9a8af0484b5cb9b1d5cd1d65324d8231907cf20b7ea2158deaa4e513b48a', 0, NULL, 1, '2026-04-01 21:45:23', '2026-04-01 21:45:23');

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
(1, 'dashboard_view', 'Voir dashboard', '2026-03-28 15:49:57'),
(2, 'clients_view', 'Voir clients', '2026-03-28 15:49:57'),
(3, 'clients_create', 'Créer / modifier clients', '2026-03-28 15:49:57'),
(4, 'clients_archive', 'Archiver / réactiver clients', '2026-03-28 15:49:57'),
(5, 'operations_view', 'Voir opérations', '2026-03-28 15:49:57'),
(6, 'operations_create', 'Créer / modifier opérations', '2026-03-28 15:49:57'),
(7, 'treasury_view', 'Gérer comptes internes', '2026-03-28 15:49:57'),
(8, 'imports_preview', 'Prévisualiser imports', '2026-03-28 15:49:57'),
(9, 'imports_validate', 'Valider imports', '2026-03-28 15:49:57'),
(10, 'imports_journal', 'Voir journal imports', '2026-03-28 15:49:57'),
(11, 'statements_view', 'Voir relevés', '2026-03-28 15:49:57'),
(12, 'statements_export', 'Exporter relevés et fiches', '2026-03-28 15:49:57'),
(13, 'statements_export_single', 'Exporter relevé unitaire', '2026-03-28 15:49:57'),
(14, 'statements_export_bulk', 'Exporter relevés en masse', '2026-03-28 15:49:57'),
(15, 'analytics_view', 'Voir analytics', '2026-03-28 15:49:57'),
(16, 'manual_actions_create', 'Créer opérations manuelles', '2026-03-28 15:49:57'),
(17, 'support_admin_manage', 'Gérer demandes support', '2026-03-28 15:49:57'),
(18, 'admin_dashboard_view', 'Voir dashboard admin technique', '2026-03-28 15:49:57'),
(19, 'admin_users_manage', 'Gérer utilisateurs', '2026-03-28 15:49:57'),
(20, 'admin_roles_manage', 'Gérer rôles', '2026-03-28 15:49:57'),
(21, 'admin_logs_view', 'Voir logs', '2026-03-28 15:49:57'),
(698, 'clients_edit', 'Modifier les clients', '2026-03-30 21:39:13'),
(699, 'clients_delete', 'Supprimer les clients', '2026-03-30 21:39:13'),
(700, 'clients_manage', 'Gérer entièrement les clients', '2026-03-30 21:39:13'),
(701, 'operations_edit', 'Modifier les opérations', '2026-03-30 21:39:13'),
(702, 'operations_delete', 'Supprimer les opérations', '2026-03-30 21:39:13'),
(703, 'operations_validate', 'Valider les opérations', '2026-03-30 21:39:13'),
(704, 'operations_manage', 'Gérer entièrement les opérations', '2026-03-30 21:39:13'),
(705, 'imports_upload', 'Téléverser des imports', '2026-03-30 21:39:13'),
(706, 'imports_create', 'Créer un import', '2026-03-30 21:39:13'),
(707, 'imports_rejected_manage', 'Gérer les lignes rejetées', '2026-03-30 21:39:13'),
(708, 'imports_manage', 'Gérer entièrement les imports', '2026-03-30 21:39:13'),
(709, 'treasury_create', 'Créer des comptes internes', '2026-03-30 21:39:13'),
(710, 'treasury_edit', 'Modifier les comptes internes', '2026-03-30 21:39:13'),
(711, 'treasury_delete', 'Supprimer les comptes internes', '2026-03-30 21:39:13'),
(712, 'treasury_import', 'Importer la trésorerie', '2026-03-30 21:39:13'),
(713, 'treasury_manage', 'Gérer entièrement la trésorerie', '2026-03-30 21:39:13'),
(714, 'support_view', 'Voir le support', '2026-03-30 21:39:13'),
(715, 'support_requests_view', 'Voir les demandes support', '2026-03-30 21:39:13'),
(716, 'support_create', 'Créer une demande support', '2026-03-30 21:39:13'),
(717, 'support_manage', 'Gérer le support', '2026-03-30 21:39:13'),
(718, 'admin_functional_view', 'Voir l\'admin fonctionnelle', '2026-03-30 21:39:13'),
(719, 'services_manage', 'Gérer les services', '2026-03-30 21:39:13'),
(720, 'operation_types_manage', 'Gérer les types d\'opérations', '2026-03-30 21:39:13'),
(721, 'service_accounts_view', 'Voir / gérer les comptes 706', '2026-03-30 21:39:13'),
(722, 'statuses_manage', 'Gérer les statuts', '2026-03-30 21:39:13'),
(723, 'users_manage', 'Gérer les utilisateurs', '2026-03-30 21:39:13'),
(724, 'roles_manage', 'Gérer les rôles', '2026-03-30 21:39:13'),
(725, 'permissions_manage', 'Gérer les permissions', '2026-03-30 21:39:13'),
(726, 'user_logs_view', 'Voir les logs utilisateurs', '2026-03-30 21:39:13'),
(727, 'settings_manage', 'Gérer les paramètres', '2026-03-30 21:39:13'),
(728, 'admin_manage', 'Administration globale', '2026-03-30 21:39:13');

-- --------------------------------------------------------

--
-- Structure de la table `ref_operation_types`
--

CREATE TABLE `ref_operation_types` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `direction` enum('credit','debit','mixed') NOT NULL DEFAULT 'mixed',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_operation_types`
--

INSERT INTO `ref_operation_types` (`id`, `code`, `label`, `direction`, `is_active`, `created_at`, `updated_at`) VALUES
(17, 'VERSEMENT', 'VERSEMENT', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(18, 'VIREMENT', 'VIREMENT', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(19, 'REGULARISATION', 'REGULARISATION', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(20, 'FRAIS_SERVICE', 'FRAIS SERVICE', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(21, 'FRAIS_GESTION', 'FRAIS GESTION', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(22, 'COMMISSION_DE_TRANSFERT', 'COMMISSION DE TRANSFERT', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(23, 'CA_PLACEMENT', 'CA PLACEMENT', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(24, 'CA_DIVERS', 'CA DIVERS', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(25, 'CA_LOGEMENT', 'CA LOGEMENT', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(26, 'CA_COURTAGE_PRET', 'CA COURTAGE PRET', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(27, 'FRAIS_DEBOURDS_MICROFINANCE', 'FRAIS DEBOURDS MICROFINANCE', 'mixed', 1, '2026-03-31 18:27:56', NULL),
(32, 'CA_DEBOURDS_ASSURANCE', 'CA DEBOURDS ASSURANCE', 'mixed', 1, '2026-03-31 22:35:44', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `ref_services`
--

CREATE TABLE `ref_services` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `operation_type_id` int(11) DEFAULT NULL,
  `service_account_id` int(11) DEFAULT NULL,
  `treasury_account_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_services`
--

INSERT INTO `ref_services` (`id`, `code`, `label`, `operation_type_id`, `service_account_id`, `treasury_account_id`, `is_active`, `created_at`, `updated_at`) VALUES
(7, 'CA_COURTAGE_PRET', 'CA COURTAGE PRET', 26, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(8, 'CA_DIVERS', 'CA DIVERS', 24, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(9, 'CA_LOGEMENT', 'CA LOGEMENT', 25, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(10, 'CA_PLACEMENT', 'CA PLACEMENT', 23, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(11, 'COMMISSION_DE_TRANSFERT', 'COMMISSION DE TRANSFERT', 22, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(12, 'FRAIS_DEBOURDS_MICROFINANCE', 'FRAIS DEBOURDS MICROFINANCE', 27, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(13, 'GESTION', 'GESTION', 21, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(14, 'ATS', 'ATS', 20, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(15, 'AVI', 'AVI', 20, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(16, 'NEGATIVE', 'NEGATIVE', 19, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(17, 'POSITIVE', 'POSITIVE', 19, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(18, 'VERSEMENT', 'VERSEMENT', 17, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(19, 'REGULIER', 'REGULIER', 18, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(20, 'EXCEPTIONEL', 'EXCEPTIONEL', 18, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(21, 'MENSUEL', 'MENSUEL', 18, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(22, 'INTERNE', 'INTERNE', 18, NULL, NULL, 1, '2026-03-31 18:29:02', NULL),
(38, 'CA_DEBOURDS_ASSURANCE', 'CA DEBOURDS ASSURANCE', 32, NULL, NULL, 1, '2026-03-31 22:36:32', NULL);

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
(1, 'superadmin', 'Super Administrateur', '2026-03-28 15:49:57'),
(2, 'admin_tech', 'Administrateur Technique', '2026-03-28 15:49:57'),
(3, 'admin_functional', 'Administrateur Fonctionnel', '2026-03-28 15:49:57'),
(4, 'operator_finance', 'Opérateur Financier', '2026-03-28 15:49:57'),
(5, 'manager', 'Direction / Management', '2026-03-28 15:49:57'),
(6, 'viewer', 'Lecture seule', '2026-03-28 15:49:57'),
(25, 'super_admin', 'Super Admin', '2026-03-30 21:35:42'),
(26, 'manager_ops', 'Manager Opérations', '2026-03-30 21:35:42'),
(27, 'agent_import', 'Agent Import', '2026-03-30 21:35:42'),
(28, 'support_agent', 'Agent Support', '2026-03-30 21:35:42');

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
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(2, 1),
(2, 2),
(2, 5),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(2, 21),
(2, 705),
(2, 706),
(2, 707),
(2, 708),
(2, 714),
(2, 715),
(2, 716),
(2, 717),
(2, 723),
(2, 724),
(2, 725),
(2, 726),
(2, 727),
(2, 728),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 9),
(3, 10),
(3, 11),
(3, 12),
(3, 13),
(3, 14),
(3, 15),
(3, 16),
(3, 698),
(3, 700),
(3, 701),
(3, 703),
(3, 704),
(3, 705),
(3, 706),
(3, 707),
(3, 708),
(3, 709),
(3, 710),
(3, 712),
(3, 713),
(3, 714),
(3, 715),
(3, 716),
(3, 717),
(3, 718),
(3, 719),
(3, 720),
(3, 721),
(3, 722),
(4, 1),
(4, 2),
(4, 3),
(4, 5),
(4, 6),
(4, 7),
(4, 8),
(4, 9),
(4, 10),
(4, 11),
(4, 12),
(4, 13),
(4, 16),
(5, 1),
(5, 2),
(5, 5),
(5, 7),
(5, 10),
(5, 11),
(5, 12),
(5, 13),
(5, 14),
(5, 15),
(6, 1),
(6, 2),
(6, 5),
(6, 7),
(6, 11),
(6, 15),
(6, 714),
(6, 715),
(25, 1),
(25, 2),
(25, 3),
(25, 4),
(25, 5),
(25, 6),
(25, 7),
(25, 8),
(25, 9),
(25, 10),
(25, 11),
(25, 12),
(25, 13),
(25, 14),
(25, 15),
(25, 16),
(25, 17),
(25, 18),
(25, 19),
(25, 20),
(25, 21),
(25, 698),
(25, 699),
(25, 700),
(25, 701),
(25, 702),
(25, 703),
(25, 704),
(25, 705),
(25, 706),
(25, 707),
(25, 708),
(25, 709),
(25, 710),
(25, 711),
(25, 712),
(25, 713),
(25, 714),
(25, 715),
(25, 716),
(25, 717),
(25, 718),
(25, 719),
(25, 720),
(25, 721),
(25, 722),
(25, 723),
(25, 724),
(25, 725),
(25, 726),
(25, 727),
(25, 728),
(26, 1),
(26, 2),
(26, 3),
(26, 5),
(26, 6),
(26, 7),
(26, 8),
(26, 10),
(26, 11),
(26, 12),
(26, 698),
(26, 701),
(26, 703),
(26, 714),
(26, 715),
(26, 716),
(27, 1),
(27, 2),
(27, 5),
(27, 7),
(27, 8),
(27, 9),
(27, 10),
(27, 705),
(27, 706),
(27, 707),
(27, 712),
(27, 714),
(27, 715),
(27, 716),
(28, 1),
(28, 714),
(28, 715),
(28, 716),
(28, 717);

-- --------------------------------------------------------

--
-- Structure de la table `service_accounts`
--

CREATE TABLE `service_accounts` (
  `id` int(11) NOT NULL,
  `parent_account_id` int(11) DEFAULT NULL,
  `account_level` int(11) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `account_code` varchar(50) NOT NULL,
  `account_label` varchar(255) NOT NULL,
  `operation_type_label` varchar(255) DEFAULT NULL,
  `destination_country_label` varchar(150) DEFAULT NULL,
  `commercial_country_label` varchar(150) DEFAULT NULL,
  `level_depth` int(11) NOT NULL DEFAULT 3,
  `is_postable` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_accounts`
--

INSERT INTO `service_accounts` (`id`, `parent_account_id`, `account_level`, `sort_order`, `account_code`, `account_label`, `operation_type_label`, `destination_country_label`, `commercial_country_label`, `level_depth`, `is_postable`, `is_active`, `current_balance`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 0, '706101', 'FRAIS DE SERVICES AVI', 'FRAIS DE SERVICE', 'France', 'France', 3, 1, 1, 6250.00, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(2, NULL, 1, 0, '706102', 'FRAIS DE GESTION', 'FRAIS BANCAIRES', 'International', 'International', 3, 1, 1, 15000.00, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(3, NULL, 1, 0, '706103', 'COMMISSION DE TRANSFERT', 'VIREMENT EXCEPTIONEL', 'International', 'International', 3, 1, 1, 0.00, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(4, NULL, 1, 0, '706104', 'FRAIS DE SERVICE ATS', 'FRAIS DE SERVICE', 'France', 'France', 3, 1, 1, 175.00, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(5, NULL, 1, 0, '706105', 'CA PLACEMENT', 'VIREMENT INTERNE', 'International', 'International', 3, 1, 1, 0.00, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(6, NULL, 1, 0, '706106', 'CA DIVERS', 'VIREMENT INTERNE', 'International', 'International', 3, 1, 1, 0.00, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(31, NULL, 1, 10, '706', 'Prestations de services', NULL, NULL, NULL, 1, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(32, 31, 2, 20, '7061', 'FRAIS DE SERVICES AVI', 'FRAIS_DE_SERVICE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(33, 31, 2, 30, '7062', 'FRAIS DE GESTION', 'FRAIS_BANCAIRES', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(34, 31, 2, 40, '7063', 'COMMISSION DE TRANSFERT', 'VIREMENT_EXCEPTIONEL', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(35, 31, 2, 50, '7064', 'CA DIVERS', 'VIREMENT_INTERNE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(36, 31, 2, 60, '7065', 'FRAIS DE SERVICE ATS', 'FRAIS_DE_SERVICE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(37, 31, 2, 70, '7066', 'CA PLACEMENT', 'VIREMENT_INTERNE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(39, 32, 3, 110, '70611', 'FRAIS DE SERVICE AVI ALLEMAGNE', 'FRAIS_DE_SERVICE', 'Allemagne', NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(40, 32, 3, 120, '70612', 'FRAIS DE SERVICES AVI BELGIQUE', 'FRAIS_DE_SERVICE', 'Belgique', NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(41, 32, 3, 130, '70613', 'FRAIS DE SERVICES AVI FRANCE', 'FRAIS_DE_SERVICE', 'France', NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(42, 35, 3, 410, '70641', 'CA DEBOURS LOGEMENT', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(43, 35, 3, 420, '70642', 'CA DEBOURS ASSURANCE', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(44, 35, 3, 430, '70643', 'FRAIS DEBOURS MICROFINANCE', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(45, 35, 3, 440, '70644', 'CA COURTAGE PRÊT', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(46, 35, 3, 450, '70645', 'CA LOGEMENT', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(54, 39, 4, 1101, '7061101', 'FRAIS DE SERVICE AVI ALLEMAGNE-France', 'FRAIS_DE_SERVICE', 'Allemagne', 'France', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(55, 39, 4, 1102, '7061102', 'FRAIS DE SERVICE AVI ALLEMAGNE-Allemagne', 'FRAIS_DE_SERVICE', 'Allemagne', 'Allemagne', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(56, 39, 4, 1103, '7061103', 'FRAIS DE SERVICE AVI ALLEMAGNE-Belgique', 'FRAIS_DE_SERVICE', 'Allemagne', 'Belgique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(57, 39, 4, 1104, '7061104', 'FRAIS DE SERVICE AVI ALLEMAGNE-Cameroun', 'FRAIS_DE_SERVICE', 'Allemagne', 'Cameroun', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(58, 39, 4, 1105, '7061105', 'FRAIS DE SERVICE AVI ALLEMAGNE-Sénégal', 'FRAIS_DE_SERVICE', 'Allemagne', 'Sénégal', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(59, 39, 4, 1106, '7061106', 'FRAIS DE SERVICE AVI ALLEMAGNE-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', 'Allemagne', 'Côte d\'Ivoire', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(60, 39, 4, 1107, '7061107', 'FRAIS DE SERVICE AVI ALLEMAGNE-Benin', 'FRAIS_DE_SERVICE', 'Allemagne', 'Benin', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(61, 39, 4, 1108, '7061108', 'FRAIS DE SERVICE AVI ALLEMAGNE-Burkina Faso', 'FRAIS_DE_SERVICE', 'Allemagne', 'Burkina Faso', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(62, 39, 4, 1109, '7061109', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Brazzaville', 'FRAIS_DE_SERVICE', 'Allemagne', 'Congo Brazzaville', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(63, 39, 4, 1110, '7061110', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Kinshasa', 'FRAIS_DE_SERVICE', 'Allemagne', 'Congo Kinshasa', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(64, 39, 4, 1111, '7061111', 'FRAIS DE SERVICE AVI ALLEMAGNE-Gabon', 'FRAIS_DE_SERVICE', 'Allemagne', 'Gabon', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(65, 39, 4, 1112, '7061112', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tchad', 'FRAIS_DE_SERVICE', 'Allemagne', 'Tchad', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(66, 39, 4, 1113, '7061113', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mali', 'FRAIS_DE_SERVICE', 'Allemagne', 'Mali', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(67, 39, 4, 1114, '7061114', 'FRAIS DE SERVICE AVI ALLEMAGNE-Togo', 'FRAIS_DE_SERVICE', 'Allemagne', 'Togo', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(68, 39, 4, 1115, '7061115', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mexique', 'FRAIS_DE_SERVICE', 'Allemagne', 'Mexique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(69, 39, 4, 1116, '7061116', 'FRAIS DE SERVICE AVI ALLEMAGNE-Inde', 'FRAIS_DE_SERVICE', 'Allemagne', 'Inde', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(70, 39, 4, 1117, '7061117', 'FRAIS DE SERVICE AVI ALLEMAGNE-Algérie', 'FRAIS_DE_SERVICE', 'Allemagne', 'Algérie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(71, 39, 4, 1118, '7061118', 'FRAIS DE SERVICE AVI ALLEMAGNE-Guinée', 'FRAIS_DE_SERVICE', 'Allemagne', 'Guinée', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(72, 39, 4, 1119, '7061119', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tunisie', 'FRAIS_DE_SERVICE', 'Allemagne', 'Tunisie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(73, 39, 4, 1120, '7061120', 'FRAIS DE SERVICE AVI ALLEMAGNE-Maroc', 'FRAIS_DE_SERVICE', 'Allemagne', 'Maroc', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(74, 39, 4, 1121, '7061121', 'FRAIS DE SERVICE AVI ALLEMAGNE-Niger', 'FRAIS_DE_SERVICE', 'Allemagne', 'Niger', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(75, 39, 4, 1122, '7061122', 'FRAIS DE SERVICE AVI ALLEMAGNE-Afrique de l\'est', 'FRAIS_DE_SERVICE', 'Allemagne', 'Afrique de l\'est', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(76, 39, 4, 1123, '7061123', 'FRAIS DE SERVICE AVI ALLEMAGNE-Autres pays', 'FRAIS_DE_SERVICE', 'Allemagne', 'Autres pays', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(85, 40, 4, 1201, '7061201', 'FRAIS DE SERVICES AVI BELGIQUE-France', 'FRAIS_DE_SERVICE', 'Belgique', 'France', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(86, 40, 4, 1202, '7061202', 'FRAIS DE SERVICES AVI BELGIQUE-Allemagne', 'FRAIS_DE_SERVICE', 'Belgique', 'Allemagne', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(87, 40, 4, 1203, '7061203', 'FRAIS DE SERVICES AVI BELGIQUE-Belgique', 'FRAIS_DE_SERVICE', 'Belgique', 'Belgique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(88, 40, 4, 1204, '7061204', 'FRAIS DE SERVICES AVI BELGIQUE-Cameroun', 'FRAIS_DE_SERVICE', 'Belgique', 'Cameroun', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(89, 40, 4, 1205, '7061205', 'FRAIS DE SERVICES AVI BELGIQUE-Sénégal', 'FRAIS_DE_SERVICE', 'Belgique', 'Sénégal', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(90, 40, 4, 1206, '7061206', 'FRAIS DE SERVICES AVI BELGIQUE-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', 'Belgique', 'Côte d\'Ivoire', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(91, 40, 4, 1207, '7061207', 'FRAIS DE SERVICES AVI BELGIQUE-Benin', 'FRAIS_DE_SERVICE', 'Belgique', 'Benin', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(92, 40, 4, 1208, '7061208', 'FRAIS DE SERVICES AVI BELGIQUE-Burkina Faso', 'FRAIS_DE_SERVICE', 'Belgique', 'Burkina Faso', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(93, 40, 4, 1209, '7061209', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Brazzaville', 'FRAIS_DE_SERVICE', 'Belgique', 'Congo Brazzaville', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(94, 40, 4, 1210, '7061210', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Kinshasa', 'FRAIS_DE_SERVICE', 'Belgique', 'Congo Kinshasa', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(95, 40, 4, 1211, '7061211', 'FRAIS DE SERVICES AVI BELGIQUE-Gabon', 'FRAIS_DE_SERVICE', 'Belgique', 'Gabon', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(96, 40, 4, 1212, '7061212', 'FRAIS DE SERVICES AVI BELGIQUE-Tchad', 'FRAIS_DE_SERVICE', 'Belgique', 'Tchad', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(97, 40, 4, 1213, '7061213', 'FRAIS DE SERVICES AVI BELGIQUE-Mali', 'FRAIS_DE_SERVICE', 'Belgique', 'Mali', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(98, 40, 4, 1214, '7061214', 'FRAIS DE SERVICES AVI BELGIQUE-Togo', 'FRAIS_DE_SERVICE', 'Belgique', 'Togo', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(99, 40, 4, 1215, '7061215', 'FRAIS DE SERVICES AVI BELGIQUE-Mexique', 'FRAIS_DE_SERVICE', 'Belgique', 'Mexique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(100, 40, 4, 1216, '7061216', 'FRAIS DE SERVICES AVI BELGIQUE-Inde', 'FRAIS_DE_SERVICE', 'Belgique', 'Inde', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(101, 40, 4, 1217, '7061217', 'FRAIS DE SERVICES AVI BELGIQUE-Algérie', 'FRAIS_DE_SERVICE', 'Belgique', 'Algérie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(102, 40, 4, 1218, '7061218', 'FRAIS DE SERVICES AVI BELGIQUE-Guinée', 'FRAIS_DE_SERVICE', 'Belgique', 'Guinée', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(103, 40, 4, 1219, '7061219', 'FRAIS DE SERVICES AVI BELGIQUE-Tunisie', 'FRAIS_DE_SERVICE', 'Belgique', 'Tunisie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(104, 40, 4, 1220, '7061220', 'FRAIS DE SERVICES AVI BELGIQUE-Maroc', 'FRAIS_DE_SERVICE', 'Belgique', 'Maroc', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(105, 40, 4, 1221, '7061221', 'FRAIS DE SERVICES AVI BELGIQUE-Niger', 'FRAIS_DE_SERVICE', 'Belgique', 'Niger', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(106, 40, 4, 1222, '7061222', 'FRAIS DE SERVICES AVI BELGIQUE-Afrique de l\'est', 'FRAIS_DE_SERVICE', 'Belgique', 'Afrique de l\'est', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(107, 40, 4, 1223, '7061223', 'FRAIS DE SERVICES AVI BELGIQUE-Autres pays', 'FRAIS_DE_SERVICE', 'Belgique', 'Autres pays', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(116, 41, 4, 1301, '7061301', 'FRAIS DE SERVICES AVI France-France', 'FRAIS_DE_SERVICE', 'France', 'France', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(117, 41, 4, 1302, '7061302', 'FRAIS DE SERVICES AVI France-Allemagne', 'FRAIS_DE_SERVICE', 'France', 'Allemagne', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(118, 41, 4, 1303, '7061303', 'FRAIS DE SERVICES AVI France-Belgique', 'FRAIS_DE_SERVICE', 'France', 'Belgique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(119, 41, 4, 1304, '7061304', 'FRAIS DE SERVICES AVI France-Cameroun', 'FRAIS_DE_SERVICE', 'France', 'Cameroun', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(120, 41, 4, 1305, '7061305', 'FRAIS DE SERVICES AVI France-Sénégal', 'FRAIS_DE_SERVICE', 'France', 'Sénégal', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(121, 41, 4, 1306, '7061306', 'FRAIS DE SERVICES AVI France-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', 'France', 'Côte d\'Ivoire', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(122, 41, 4, 1307, '7061307', 'FRAIS DE SERVICES AVI France-Benin', 'FRAIS_DE_SERVICE', 'France', 'Benin', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(123, 41, 4, 1308, '7061308', 'FRAIS DE SERVICES AVI France-Burkina Faso', 'FRAIS_DE_SERVICE', 'France', 'Burkina Faso', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(124, 41, 4, 1309, '7061309', 'FRAIS DE SERVICES AVI France-Congo Brazzaville', 'FRAIS_DE_SERVICE', 'France', 'Congo Brazzaville', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(125, 41, 4, 1310, '7061310', 'FRAIS DE SERVICES AVI France-Congo Kinshasa', 'FRAIS_DE_SERVICE', 'France', 'Congo Kinshasa', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(126, 41, 4, 1311, '7061311', 'FRAIS DE SERVICES AVI France-Gabon', 'FRAIS_DE_SERVICE', 'France', 'Gabon', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(127, 41, 4, 1312, '7061312', 'FRAIS DE SERVICES AVI France-Tchad', 'FRAIS_DE_SERVICE', 'France', 'Tchad', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(128, 41, 4, 1313, '7061313', 'FRAIS DE SERVICES AVI France-Mali', 'FRAIS_DE_SERVICE', 'France', 'Mali', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(129, 41, 4, 1314, '7061314', 'FRAIS DE SERVICES AVI France-Togo', 'FRAIS_DE_SERVICE', 'France', 'Togo', 4, 1, 1, 150.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(130, 41, 4, 1315, '7061315', 'FRAIS DE SERVICES AVI France-Mexique', 'FRAIS_DE_SERVICE', 'France', 'Mexique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(131, 41, 4, 1316, '7061316', 'FRAIS DE SERVICES AVI France-Inde', 'FRAIS_DE_SERVICE', 'France', 'Inde', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(132, 41, 4, 1317, '7061317', 'FRAIS DE SERVICES AVI France-Algérie', 'FRAIS_DE_SERVICE', 'France', 'Algérie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(133, 41, 4, 1318, '7061318', 'FRAIS DE SERVICES AVI France-Guinée', 'FRAIS_DE_SERVICE', 'France', 'Guinée', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(134, 41, 4, 1319, '7061319', 'FRAIS DE SERVICES AVI France-Tunisie', 'FRAIS_DE_SERVICE', 'France', 'Tunisie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(135, 41, 4, 1320, '7061320', 'FRAIS DE SERVICES AVI France-Maroc', 'FRAIS_DE_SERVICE', 'France', 'Maroc', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(136, 41, 4, 1321, '7061321', 'FRAIS DE SERVICES AVI France-Niger', 'FRAIS_DE_SERVICE', 'France', 'Niger', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(137, 41, 4, 1322, '7061322', 'FRAIS DE SERVICES AVI France-Afrique de l\'est', 'FRAIS_DE_SERVICE', 'France', 'Afrique de l\'est', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(138, 41, 4, 1323, '7061323', 'FRAIS DE SERVICES AVI France-Autres pays', 'FRAIS_DE_SERVICE', 'France', 'Autres pays', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(147, 33, 3, 201, '706201', 'FRAIS DE GESTION-France', 'FRAIS_BANCAIRES', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(148, 33, 3, 202, '706202', 'FRAIS DE GESTION-Allemagne', 'FRAIS_BANCAIRES', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(149, 33, 3, 203, '706203', 'FRAIS DE GESTION-Belgique', 'FRAIS_BANCAIRES', NULL, 'Belgique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(150, 33, 3, 204, '706204', 'FRAIS DE GESTION-Cameroun', 'FRAIS_BANCAIRES', NULL, 'Cameroun', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(151, 33, 3, 205, '706205', 'FRAIS DE GESTION-Sénégal', 'FRAIS_BANCAIRES', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(152, 33, 3, 206, '706206', 'FRAIS DE GESTION-Côte d\'Ivoire', 'FRAIS_BANCAIRES', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(153, 33, 3, 207, '706207', 'FRAIS DE GESTION-Benin', 'FRAIS_BANCAIRES', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(154, 33, 3, 208, '706208', 'FRAIS DE GESTION-Burkina Faso', 'FRAIS_BANCAIRES', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(155, 33, 3, 209, '706209', 'FRAIS DE GESTION-Congo Brazzaville', 'FRAIS_BANCAIRES', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(156, 33, 3, 210, '706210', 'FRAIS DE GESTION-Congo Kinshasa', 'FRAIS_BANCAIRES', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(157, 33, 3, 211, '706211', 'FRAIS DE GESTION-Gabon', 'FRAIS_BANCAIRES', NULL, 'Gabon', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(158, 33, 3, 212, '706212', 'FRAIS DE GESTION-Tchad', 'FRAIS_BANCAIRES', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(159, 33, 3, 213, '706213', 'FRAIS DE GESTION-Mali', 'FRAIS_BANCAIRES', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(160, 33, 3, 214, '706214', 'FRAIS DE GESTION-Togo', 'FRAIS_BANCAIRES', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(161, 33, 3, 215, '706215', 'FRAIS DE GESTION-Mexique', 'FRAIS_BANCAIRES', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(162, 33, 3, 216, '706216', 'FRAIS DE GESTION-Inde', 'FRAIS_BANCAIRES', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(163, 33, 3, 217, '706217', 'FRAIS DE GESTION-Algérie', 'FRAIS_BANCAIRES', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(164, 33, 3, 218, '706218', 'FRAIS DE GESTION-Guinée', 'FRAIS_BANCAIRES', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(165, 33, 3, 219, '706219', 'FRAIS DE GESTION-Tunisie', 'FRAIS_BANCAIRES', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(166, 33, 3, 220, '706220', 'FRAIS DE GESTION-Maroc', 'FRAIS_BANCAIRES', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(167, 33, 3, 221, '706221', 'FRAIS DE GESTION-Niger', 'FRAIS_BANCAIRES', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(168, 33, 3, 222, '706222', 'FRAIS DE GESTION-Afrique de l\'est', 'FRAIS_BANCAIRES', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(169, 33, 3, 223, '706223', 'FRAIS DE GESTION-Autres pays', 'FRAIS_BANCAIRES', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(178, 34, 3, 301, '706301', 'COMMISSION DE TRANSFERT-France', 'VIREMENT_EXCEPTIONEL', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(179, 34, 3, 302, '706302', 'COMMISSION DE TRANSFERT-Allemagne', 'VIREMENT_EXCEPTIONEL', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(180, 34, 3, 303, '706303', 'COMMISSION DE TRANSFERT-Belgique', 'VIREMENT_EXCEPTIONEL', NULL, 'Belgique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(181, 34, 3, 304, '706304', 'COMMISSION DE TRANSFERT-Cameroun', 'VIREMENT_EXCEPTIONEL', NULL, 'Cameroun', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(182, 34, 3, 305, '706305', 'COMMISSION DE TRANSFERT-Sénégal', 'VIREMENT_EXCEPTIONEL', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(183, 34, 3, 306, '706306', 'COMMISSION DE TRANSFERT-Côte d\'Ivoire', 'VIREMENT_EXCEPTIONEL', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(184, 34, 3, 307, '706307', 'COMMISSION DE TRANSFERT-Benin', 'VIREMENT_EXCEPTIONEL', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(185, 34, 3, 308, '706308', 'COMMISSION DE TRANSFERT-Burkina Faso', 'VIREMENT_EXCEPTIONEL', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(186, 34, 3, 309, '706309', 'COMMISSION DE TRANSFERT-Congo Brazzaville', 'VIREMENT_EXCEPTIONEL', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(187, 34, 3, 310, '706310', 'COMMISSION DE TRANSFERT-Congo Kinshasa', 'VIREMENT_EXCEPTIONEL', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(188, 34, 3, 311, '706311', 'COMMISSION DE TRANSFERT-Gabon', 'VIREMENT_EXCEPTIONEL', NULL, 'Gabon', 3, 1, 1, -100.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(189, 34, 3, 312, '706312', 'COMMISSION DE TRANSFERT-Tchad', 'VIREMENT_EXCEPTIONEL', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(190, 34, 3, 313, '706313', 'COMMISSION DE TRANSFERT-Mali', 'VIREMENT_EXCEPTIONEL', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(191, 34, 3, 314, '706314', 'COMMISSION DE TRANSFERT-Togo', 'VIREMENT_EXCEPTIONEL', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(192, 34, 3, 315, '706315', 'COMMISSION DE TRANSFERT-Mexique', 'VIREMENT_EXCEPTIONEL', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(193, 34, 3, 316, '706316', 'COMMISSION DE TRANSFERT-Inde', 'VIREMENT_EXCEPTIONEL', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(194, 34, 3, 317, '706317', 'COMMISSION DE TRANSFERT-Algérie', 'VIREMENT_EXCEPTIONEL', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(195, 34, 3, 318, '706318', 'COMMISSION DE TRANSFERT-Guinée', 'VIREMENT_EXCEPTIONEL', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(196, 34, 3, 319, '706319', 'COMMISSION DE TRANSFERT-Tunisie', 'VIREMENT_EXCEPTIONEL', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(197, 34, 3, 320, '706320', 'COMMISSION DE TRANSFERT-Maroc', 'VIREMENT_EXCEPTIONEL', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(198, 34, 3, 321, '706321', 'COMMISSION DE TRANSFERT-Niger', 'VIREMENT_EXCEPTIONEL', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(199, 34, 3, 322, '706322', 'COMMISSION DE TRANSFERT-Afrique de l\'est', 'VIREMENT_EXCEPTIONEL', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(200, 34, 3, 323, '706323', 'COMMISSION DE TRANSFERT-Autres pays', 'VIREMENT_EXCEPTIONEL', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(209, 36, 3, 501, '706501', 'FRAIS DE SERVICE ATS-France', 'FRAIS_DE_SERVICE', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(210, 36, 3, 502, '706502', 'FRAIS DE SERVICE ATS-Allemagne', 'FRAIS_DE_SERVICE', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(211, 36, 3, 503, '706503', 'FRAIS DE SERVICE ATS-Belgique', 'FRAIS_DE_SERVICE', NULL, 'Belgique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(212, 36, 3, 504, '706504', 'FRAIS DE SERVICE ATS-Cameroun', 'FRAIS_DE_SERVICE', NULL, 'Cameroun', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(213, 36, 3, 505, '706505', 'FRAIS DE SERVICE ATS-Sénégal', 'FRAIS_DE_SERVICE', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(214, 36, 3, 506, '706506', 'FRAIS DE SERVICE ATS-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(215, 36, 3, 507, '706507', 'FRAIS DE SERVICE ATS-Benin', 'FRAIS_DE_SERVICE', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(216, 36, 3, 508, '706508', 'FRAIS DE SERVICE ATS-Burkina Faso', 'FRAIS_DE_SERVICE', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(217, 36, 3, 509, '706509', 'FRAIS DE SERVICE ATS-Congo Brazzaville', 'FRAIS_DE_SERVICE', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(218, 36, 3, 510, '706510', 'FRAIS DE SERVICE ATS-Congo Kinshasa', 'FRAIS_DE_SERVICE', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(219, 36, 3, 511, '706511', 'FRAIS DE SERVICE ATS-Gabon', 'FRAIS_DE_SERVICE', NULL, 'Gabon', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(220, 36, 3, 512, '706512', 'FRAIS DE SERVICE ATS-Tchad', 'FRAIS_DE_SERVICE', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(221, 36, 3, 513, '706513', 'FRAIS DE SERVICE ATS-Mali', 'FRAIS_DE_SERVICE', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(222, 36, 3, 514, '706514', 'FRAIS DE SERVICE ATS-Togo', 'FRAIS_DE_SERVICE', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(223, 36, 3, 515, '706515', 'FRAIS DE SERVICE ATS-Mexique', 'FRAIS_DE_SERVICE', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(224, 36, 3, 516, '706516', 'FRAIS DE SERVICE ATS-Inde', 'FRAIS_DE_SERVICE', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(225, 36, 3, 517, '706517', 'FRAIS DE SERVICE ATS-Algérie', 'FRAIS_DE_SERVICE', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(226, 36, 3, 518, '706518', 'FRAIS DE SERVICE ATS-Guinée', 'FRAIS_DE_SERVICE', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(227, 36, 3, 519, '706519', 'FRAIS DE SERVICE ATS-Tunisie', 'FRAIS_DE_SERVICE', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(228, 36, 3, 520, '706520', 'FRAIS DE SERVICE ATS-Maroc', 'FRAIS_DE_SERVICE', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(229, 36, 3, 521, '706521', 'FRAIS DE SERVICE ATS-Niger', 'FRAIS_DE_SERVICE', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(230, 36, 3, 522, '706522', 'FRAIS DE SERVICE ATS-Afrique de l\'est', 'FRAIS_DE_SERVICE', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(231, 36, 3, 523, '706523', 'FRAIS DE SERVICE ATS-Autres pays', 'FRAIS_DE_SERVICE', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(240, 37, 3, 601, '706601', 'CA PLACEMENT-France', 'VIREMENT_INTERNE', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(241, 37, 3, 602, '706602', 'CA PLACEMENT-Allemagne', 'VIREMENT_INTERNE', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(242, 37, 3, 603, '706603', 'CA PLACEMENT-Belgique', 'VIREMENT_INTERNE', NULL, 'Belgique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(243, 37, 3, 604, '706604', 'CA PLACEMENT-Cameroun', 'VIREMENT_INTERNE', NULL, 'Cameroun', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(244, 37, 3, 605, '706605', 'CA PLACEMENT-Sénégal', 'VIREMENT_INTERNE', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(245, 37, 3, 606, '706606', 'CA PLACEMENT-Côte d\'Ivoire', 'VIREMENT_INTERNE', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(246, 37, 3, 607, '706607', 'CA PLACEMENT-Benin', 'VIREMENT_INTERNE', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(247, 37, 3, 608, '706608', 'CA PLACEMENT-Burkina Faso', 'VIREMENT_INTERNE', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(248, 37, 3, 609, '706609', 'CA PLACEMENT-Congo Brazzaville', 'VIREMENT_INTERNE', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(249, 37, 3, 610, '706610', 'CA PLACEMENT-Congo Kinshasa', 'VIREMENT_INTERNE', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(250, 37, 3, 611, '706611', 'CA PLACEMENT-Gabon', 'VIREMENT_INTERNE', NULL, 'Gabon', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(251, 37, 3, 612, '706612', 'CA PLACEMENT-Tchad', 'VIREMENT_INTERNE', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(252, 37, 3, 613, '706613', 'CA PLACEMENT-Mali', 'VIREMENT_INTERNE', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(253, 37, 3, 614, '706614', 'CA PLACEMENT-Togo', 'VIREMENT_INTERNE', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(254, 37, 3, 615, '706615', 'CA PLACEMENT-Mexique', 'VIREMENT_INTERNE', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(255, 37, 3, 616, '706616', 'CA PLACEMENT-Inde', 'VIREMENT_INTERNE', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(256, 37, 3, 617, '706617', 'CA PLACEMENT-Algérie', 'VIREMENT_INTERNE', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(257, 37, 3, 618, '706618', 'CA PLACEMENT-Guinée', 'VIREMENT_INTERNE', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(258, 37, 3, 619, '706619', 'CA PLACEMENT-Tunisie', 'VIREMENT_INTERNE', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(259, 37, 3, 620, '706620', 'CA PLACEMENT-Maroc', 'VIREMENT_INTERNE', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(260, 37, 3, 621, '706621', 'CA PLACEMENT-Niger', 'VIREMENT_INTERNE', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(261, 37, 3, 622, '706622', 'CA PLACEMENT-Afrique de l\'est', 'VIREMENT_INTERNE', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43'),
(262, 37, 3, 623, '706623', 'CA PLACEMENT-Autres pays', 'VIREMENT_INTERNE', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-04 23:36:43');

-- --------------------------------------------------------

--
-- Structure de la table `statuses`
--

CREATE TABLE `statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `statuses`
--

INSERT INTO `statuses` (`id`, `name`, `sort_order`, `created_at`) VALUES
(1, 'Etudiant en attente AVI', 10, '2026-03-28 15:49:57'),
(2, 'Etudiant actif', 20, '2026-03-28 15:49:57'),
(3, 'Etudiant dormant', 30, '2026-03-28 15:49:57'),
(4, 'Etudiant remboursé', 40, '2026-03-28 15:49:57');

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
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `status` varchar(50) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `treasury_accounts`
--

CREATE TABLE `treasury_accounts` (
  `id` int(11) NOT NULL,
  `account_code` varchar(50) NOT NULL,
  `account_label` varchar(255) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `subsidiary_name` varchar(255) DEFAULT NULL,
  `zone_code` varchar(100) DEFAULT NULL,
  `country_label` varchar(150) DEFAULT NULL,
  `country_type` varchar(150) DEFAULT NULL,
  `payment_place` varchar(150) DEFAULT NULL,
  `currency_code` varchar(10) NOT NULL DEFAULT 'EUR',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `treasury_accounts`
--

INSERT INTO `treasury_accounts` (`id`, `account_code`, `account_label`, `bank_name`, `subsidiary_name`, `zone_code`, `country_label`, `country_type`, `payment_place`, `currency_code`, `opening_balance`, `current_balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '5120101', 'Fr_LCL_C - France', 'Fr_LCL_C', 'Studely', 'EU', 'France', 'Filiale', 'Local', 'EUR', 100000.00, 63300.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(2, '5120102', 'Fr_LCL_M - France', 'Fr_LCL_M', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 16320.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(3, '5120103', 'FR_CIC - France', 'FR_CIC', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(4, '5120104', 'FR_CCOOP - France', 'FR_CCOOP', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(5, '5120105', 'Fr_MANGO - France', 'Fr_MANGO', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(6, '5120106', 'FR_SG - France', 'FR_SG', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(7, '5120107', 'FR_SG_EXPL - France', 'FR_SG_EXPL', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(8, '5120108', 'FR_SPENDESK - France', 'FR_SPENDESK', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, -50000.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(9, '5120109', 'FR_TRUST - France', 'FR_TRUST', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 50000.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(10, '5120301', 'BE_QUONTO - Belgique', 'BE_QUONTO', 'Studely', 'EU', 'Belgique', 'Filiale', 'France', 'EUR', 0.00, -14580.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(11, '5120302', 'BE_REVOLUT - Belgique', 'BE_REVOLUT', 'Studely', 'EU', 'Belgique', 'Filiale', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(12, '5120401', 'CM_BAC - Cameroun', 'CM_BAC', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, -599900.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(13, '5120402', 'CM_BAC_EXPL - Cameroun', 'CM_BAC_EXPL', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(14, '5120403', 'CM_BAC_REM - Cameroun', 'CM_BAC_REM', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(15, '5120404', 'CM_BGFI_DE - Cameroun', 'CM_BGFI_DE', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(16, '5120405', 'CM_BGFI_EXPL - Cameroun', 'CM_BGFI_EXPL', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(17, '5120406', 'CM_BGFI_FR - Cameroun', 'CM_BGFI_FR', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(18, '5120407', 'CM_CBC - Cameroun', 'CM_CBC', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(19, '5120408', 'CM_UBA - Cameroun', 'CM_UBA', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(20, '5120409', 'SF_CM_ACCESS_BANK - Cameroun', 'SF_CM_ACCESS_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(21, '5120410', 'SF_CM_AFD_BANK - Cameroun', 'SF_CM_AFD_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(22, '5120411', 'SF_CM_AFD_EXPL - Cameroun', 'SF_CM_AFD_EXPL', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(23, '5120412', 'SF_CM_BAC - Cameroun', 'SF_CM_BAC', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(24, '5120413', 'SF_CM_BAC_EXPL - Cameroun', 'SF_CM_BAC_EXPL', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(25, '5120414', 'SF_CM_BGFI - Cameroun', 'SF_CM_BGFI', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(26, '5120415', 'SF_CM_CCA_BANK - Cameroun', 'SF_CM_CCA_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(27, '5120416', 'SF_CM_UBA - Cameroun', 'SF_CM_UBA', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(28, '5120501', 'SF_SN_EcoBQ - Sénégal', 'SF_SN_EcoBQ', 'Studely Finance', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(29, '5120502', 'SN_ECOBQ - Sénégal', 'SN_ECOBQ', 'Studely', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(30, '5120601', 'CIV_ECOBQ - Côte d\'Ivoire', 'CIV_ECOBQ', 'Studely', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(31, '5120602', 'SF_CIV_AFG - Côte d\'Ivoire', 'SF_CIV_AFG', 'Studely Finance', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(32, '5120603', 'SF_CIV_EcoBQ - Côte d\'Ivoire', 'SF_CIV_EcoBQ', 'Studely Finance', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(33, '5120701', 'BN_ECOBQ - Benin', 'BN_ECOBQ', 'Studely', 'AO', 'Benin', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(34, '5120702', 'SF_BN_EcoBQ - Benin', 'SF_BN_EcoBQ', 'Studely Finance', 'AO', 'Benin', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(35, '5120801', 'BFA_ECOBQ - Burkina Faso', 'BFA_ECOBQ', 'Studely', 'AO', 'Burkina Faso', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(36, '5120901', 'CD_BGFI - Congo Brazzaville', 'CD_BGFI', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(37, '5120902', 'CD_BGFI_EXPL - Congo Brazzaville', 'CD_BGFI_EXPL', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(38, '5120903', 'MUP_MF - Congo Brazzaville', 'MUP_MF', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(39, '5121001', 'RD_BGFI - Congo Kinshasa', 'RD_BGFI', 'Studely', 'AC', 'Congo Kinshasa', 'Filiale', 'Local', 'USD', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(40, '5121002', 'RD_BGFI_EURO - Congo Kinshasa', 'RD_BGFI_EURO', 'Studely', 'AC', 'Congo Kinshasa', 'Filiale', 'Local', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(41, '5121101', 'GB_BGFI - Gabon', 'GB_BGFI', 'Studely', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(42, '5121102', 'GB_BGFI_EXPL - Gabon', 'GB_BGFI_EXPL', 'Studely', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(43, '5121103', 'SF_GB_ECOBQ - Gabon', 'SF_GB_ECOBQ', 'Studely Finance', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(44, '5121201', 'SF_CHD_ECOBAQ - Tchad', 'SF_CHD_ECOBAQ', 'Studely Finance', 'AC', 'Tchad', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(45, '5121202', 'SF_TCHAD_UBA - Tchad', 'SF_TCHAD_UBA', 'Studely Finance', 'AC', 'Tchad', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(46, '5121301', 'ML_ECOBQ - Mali', 'ML_ECOBQ', 'Studely', 'AO', 'Mali', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(47, '5121302', 'ML_SCOLARIS FI - Mali', 'ML_SCOLARIS FI', 'Studely', 'AO', 'Mali', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(48, '5121401', 'TGO_ECOBQ - Togo', 'TGO_ECOBQ', 'Studely', 'AO', 'Togo', 'Filiale', 'Local', 'XOF', 0.00, -725542.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(49, '5121403', 'SF_TG_EcoBQ - Togo', 'SF_TG_EcoBQ', 'Studely Finance', 'AO', 'Togo', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(50, '5121701', 'ALG_BNP - Algérie', 'ALG_BNP', 'Studely', 'AN', 'Algérie', 'Filiale', 'Local', 'DZD', 0.00, -288000.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(51, '5121801', 'GUI_ECOBQ - Guinée', 'GUI_ECOBQ', 'Studely', 'AO', 'Guinée', 'Filiale', 'Local', 'GNF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(52, '5121802', 'SF_GUI_EcoBQ - Guinée', 'SF_GUI_EcoBQ', 'Studely Finance', 'AO', 'Guinée', 'Filiale', 'Local', 'GNF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(53, '5121901', 'TN_ATTI - Tunisie', 'TN_ATTI', 'Studely', 'AN', 'Tunisie', 'Filiale', 'Local', 'TND', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(54, '5122001', 'MA_ATTI - Maroc', 'MA_ATTI', 'Studely', 'AN', 'Maroc', 'Filiale', 'Local', 'MAD', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43'),
(55, '5122101', 'NG_ECOBQ - Niger', 'NG_ECOBQ', 'Studely', 'AO', 'Niger', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-04 23:36:43');

-- --------------------------------------------------------

--
-- Structure de la table `treasury_movements`
--

CREATE TABLE `treasury_movements` (
  `id` int(11) NOT NULL,
  `source_treasury_account_id` int(11) NOT NULL,
  `target_treasury_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `operation_date` date NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `treasury_movements`
--

INSERT INTO `treasury_movements` (`id`, `source_treasury_account_id`, `target_treasury_account_id`, `amount`, `operation_date`, `reference`, `label`, `created_at`, `updated_at`) VALUES
(1, 8, 9, 50000.00, '2026-03-14', 'TEST-TM-0001', 'Virement interne CA placement', '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(2, 1, 2, 25000.00, '2026-03-19', 'TEST-TM-0002', 'Virement interne trésorerie Europe', '2026-03-29 00:19:29', '2026-03-29 00:19:29');

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
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `role_id`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Arcel', '$2y$10$TLhKOHu6WQmUROqhcksMpuKGeS5VBykyuo3cMb1abDm6l8psZOV/6', 'admin', 1, 1, '2026-03-29 22:37:15', '2026-03-28 15:49:57', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `module` varchar(120) NOT NULL,
  `entity_type` varchar(120) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_logs`
--

INSERT INTO `user_logs` (`id`, `user_id`, `action`, `module`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(1, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-30 01:17:56'),
(2, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-03-30 01:30:34'),
(3, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-30 01:30:51'),
(4, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-03-30 01:31:21'),
(5, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-30 01:31:28'),
(6, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-03-30 01:42:55'),
(7, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-30 01:43:04'),
(8, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-03-30 01:51:48'),
(9, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-30 01:51:59'),
(10, 1, 'create_operation', 'operations', 'operation', 17, 'Création d’une opération alignée sur les règles métier', '2026-03-30 03:20:23'),
(11, 1, 'create_operation', 'operations', 'operation', 18, 'Création d’une opération alignée sur les règles métier', '2026-03-30 03:20:38'),
(12, 1, 'delete_operation', 'operations', 'operation', 18, 'Suppression d’une opération avec recalcul des soldes', '2026-03-30 03:23:18'),
(13, 1, 'create_operation', 'operations', 'operation', 19, 'Création d’une opération alignée sur les règles métier', '2026-03-30 03:25:41'),
(14, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-30 23:12:28'),
(15, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-31 16:16:00'),
(16, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-03-31 22:39:57'),
(17, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-03-31 22:40:10'),
(18, 1, 'edit_client', 'clients', 'client', 1, 'Modification du client CLT0001', '2026-03-31 22:41:57'),
(19, 1, 'create_operation', 'operations', 'operation', 20, 'Création d’une opération V2', '2026-03-31 22:57:06'),
(20, 1, 'create_operation', 'operations', 'operation', 21, 'Création d’une opération V2', '2026-03-31 22:59:02'),
(21, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-04-01 16:42:48'),
(22, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-04-01 16:42:55'),
(23, 1, 'create_operation', 'operations', 'operation', 22, 'Création d’une opération corrigée', '2026-04-01 21:44:06'),
(24, 1, 'create_operation', 'operations', 'operation', 23, 'Création d’une opération corrigée', '2026-04-01 21:45:23'),
(25, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-04-04 23:10:45'),
(26, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 1, 'Modification d’un compte de trésorerie', '2026-04-04 23:36:43');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `accounting_rules`
--
ALTER TABLE `accounting_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rule_pair` (`operation_type_id`,`service_id`);

--
-- Index pour la table `account_categories`
--
ALTER TABLE `account_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `account_types`
--
ALTER TABLE `account_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_trail_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_trail_created_at` (`created_at`);

--
-- Index pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`),
  ADD KEY `fk_bank_accounts_type` (`account_type_id`),
  ADD KEY `fk_bank_accounts_category` (`account_category_id`),
  ADD KEY `idx_bank_accounts_number` (`account_number`),
  ADD KEY `idx_bank_accounts_country` (`country`),
  ADD KEY `idx_bank_accounts_active` (`is_active`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_code` (`client_code`),
  ADD UNIQUE KEY `generated_client_account` (`generated_client_account`),
  ADD KEY `fk_clients_status` (`status_id`),
  ADD KEY `fk_clients_category` (`category_id`),
  ADD KEY `fk_clients_initial_treasury` (`initial_treasury_account_id`),
  ADD KEY `idx_clients_code` (`client_code`),
  ADD KEY `idx_clients_active` (`is_active`),
  ADD KEY `idx_clients_status_text` (`client_status`);

--
-- Index pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_client_bank_account` (`client_id`,`bank_account_id`),
  ADD KEY `fk_client_bank_accounts_bank` (`bank_account_id`);

--
-- Index pour la table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_currencies_code` (`code`);

--
-- Index pour la table `imports`
--
ALTER TABLE `imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_imports_status` (`status`),
  ADD KEY `idx_imports_created_at` (`created_at`);

--
-- Index pour la table `import_rows`
--
ALTER TABLE `import_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_import_rows_status` (`status`),
  ADD KEY `idx_import_rows_import` (`import_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_is_read` (`is_read`),
  ADD KEY `idx_notifications_created_at` (`created_at`);

--
-- Index pour la table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_operations_bank` (`bank_account_id`),
  ADD KEY `fk_operations_created_by` (`created_by`),
  ADD KEY `idx_operations_date` (`operation_date`),
  ADD KEY `idx_operations_client` (`client_id`),
  ADD KEY `idx_operations_type` (`operation_type_code`),
  ADD KEY `idx_operations_source_type` (`source_type`),
  ADD KEY `idx_operations_debit` (`debit_account_code`),
  ADD KEY `idx_operations_credit` (`credit_account_code`),
  ADD KEY `idx_operations_hash` (`operation_hash`);

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
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `uq_ref_operation_types_code` (`code`);

--
-- Index pour la table `ref_services`
--
ALTER TABLE `ref_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `uq_ref_services_type_code` (`operation_type_id`,`code`),
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
  ADD UNIQUE KEY `uq_role_permission` (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

--
-- Index pour la table `service_accounts`
--
ALTER TABLE `service_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`),
  ADD KEY `idx_service_accounts_code` (`account_code`),
  ADD KEY `idx_service_accounts_active` (`is_active`),
  ADD KEY `idx_service_accounts_parent_account_id` (`parent_account_id`),
  ADD KEY `idx_service_accounts_account_code` (`account_code`);

--
-- Index pour la table `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_support_requests_user` (`user_id`),
  ADD KEY `idx_support_requests_type` (`request_type`),
  ADD KEY `idx_support_requests_status` (`status`),
  ADD KEY `idx_support_requests_priority` (`priority`),
  ADD KEY `idx_support_requests_created_at` (`created_at`);

--
-- Index pour la table `treasury_accounts`
--
ALTER TABLE `treasury_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`),
  ADD KEY `idx_treasury_accounts_code` (`account_code`),
  ADD KEY `idx_treasury_accounts_active` (`is_active`);

--
-- Index pour la table `treasury_movements`
--
ALTER TABLE `treasury_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_treasury_movements_source` (`source_treasury_account_id`),
  ADD KEY `fk_treasury_movements_target` (`target_treasury_account_id`),
  ADD KEY `idx_treasury_movements_date` (`operation_date`);

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
  ADD KEY `idx_user_logs_user_id` (`user_id`),
  ADD KEY `idx_user_logs_module` (`module`),
  ADD KEY `idx_user_logs_created_at` (`created_at`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `accounting_rules`
--
ALTER TABLE `accounting_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `account_categories`
--
ALTER TABLE `account_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `account_types`
--
ALTER TABLE `account_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=729;

--
-- AUTO_INCREMENT pour la table `ref_operation_types`
--
ALTER TABLE `ref_operation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `ref_services`
--
ALTER TABLE `ref_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `service_accounts`
--
ALTER TABLE `service_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=263;

--
-- AUTO_INCREMENT pour la table `statuses`
--
ALTER TABLE `statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `treasury_accounts`
--
ALTER TABLE `treasury_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT pour la table `treasury_movements`
--
ALTER TABLE `treasury_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD CONSTRAINT `fk_bank_accounts_category` FOREIGN KEY (`account_category_id`) REFERENCES `account_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bank_accounts_type` FOREIGN KEY (`account_type_id`) REFERENCES `account_types` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_clients_initial_treasury` FOREIGN KEY (`initial_treasury_account_id`) REFERENCES `treasury_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_clients_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `fk_operations_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_operations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
