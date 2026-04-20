-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 20 avr. 2026 à 12:12
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

--
-- Déchargement des données de la table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `entity_type`, `entity_id`, `field_name`, `old_value`, `new_value`, `user_id`, `created_at`) VALUES
(1, 'client', 8, 'updated_at', '2026-04-06 22:04:23', '2026-04-06 22:11:14', 1, '2026-04-06 22:11:14'),
(2, 'client', 8, 'updated_at', '2026-04-06 22:11:14', '2026-04-06 22:46:54', 1, '2026-04-06 22:46:54'),
(3, 'client', 7, 'generated_client_account', '', '411983200894', 1, '2026-04-07 00:23:28'),
(4, 'client', 7, 'updated_at', '2026-04-06 00:48:25', '2026-04-07 00:23:28', 1, '2026-04-07 00:23:28'),
(5, 'client', 8, 'phone', '+33612542633', '+33612542632', 1, '2026-04-07 03:20:00'),
(6, 'client', 8, 'updated_at', '2026-04-06 22:46:54', '2026-04-07 03:19:59', 1, '2026-04-07 03:20:00'),
(7, 'client', 7, 'updated_at', '2026-04-07 00:23:28', '2026-04-11 13:15:01', 1, '2026-04-11 13:15:02'),
(8, 'client', 11, 'initial_treasury_account_id', NULL, '12', 1, '2026-04-13 19:07:03'),
(9, 'client', 11, 'updated_at', '2026-04-13 18:53:36', '2026-04-13 19:07:03', 1, '2026-04-13 19:07:03');

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
(1, 'Compte client CLT0001', '411CLT0001', 'Compte client interne', 'France', NULL, NULL, 0.00, 11230.00, 1, '2026-03-29 00:19:28', '2026-04-13 23:29:36'),
(2, 'Compte client CLT0002', '411CLT0002', 'Compte client interne', 'France', NULL, NULL, 0.00, 7895.00, 1, '2026-03-29 00:19:28', '2026-04-13 23:29:36'),
(3, 'Compte client CLT0003', '411CLT0003', 'Compte client interne', 'Belgique', NULL, NULL, 0.00, 14110.00, 1, '2026-03-29 00:19:28', '2026-04-13 23:29:36'),
(4, 'Compte client CLT0004', '411CLT0004', 'Compte client interne', 'Cameroun', NULL, NULL, 0.00, 584790.00, 1, '2026-03-29 00:19:28', '2026-04-13 23:29:36'),
(5, 'Compte client CLT0005', '411CLT0005', 'Compte client interne', 'France', NULL, NULL, 0.00, 724982.00, 1, '2026-03-29 00:19:28', '2026-04-13 23:29:36'),
(6, 'Compte client CLT0006', '411CLT0006', 'Compte client interne', 'Espagne', NULL, NULL, 0.00, 282210.00, 1, '2026-03-29 00:19:28', '2026-04-13 23:29:36'),
(7, 'Compte client 870041597 - Nathael TIOMA', '411870041597', 'Compte client interne', 'Cameroun', NULL, NULL, 50000.00, 49590.00, 1, '2026-04-06 22:04:23', '2026-04-13 23:29:36'),
(8, 'Compte client 983200894 - Arcel TIOMA', '411983200894', 'Compte client interne', 'Cameroun', NULL, NULL, 90000.00, 89680.00, 1, '2026-04-07 00:23:28', '2026-04-13 23:29:36'),
(9, 'Compte client CLT058180 - Marc Tchatchouang', '411CLT058180', 'Compte client interne', 'Côte d\'Ivoire', NULL, NULL, 80000.00, 80000.00, 1, '2026-04-10 23:36:16', '2026-04-13 23:29:36'),
(10, 'Compte client 337819647 - Lenny TIOMA', '411337819647', 'Compte client interne', 'Burkina Faso', NULL, NULL, 60000.00, 0.00, 1, '2026-04-10 23:38:36', '2026-04-13 23:29:36'),
(11, 'Compte client 294030444 - Lysiah TIOMA', '411294030444', 'Compte client interne', 'Cameroun', NULL, NULL, 300.00, 49300.00, 1, '2026-04-13 18:53:36', '2026-04-13 23:29:36'),
(12, 'Compte client 656519872 - Yonah TIOMA', '411656519872', 'Compte client interne', 'Algérie', NULL, NULL, 500.00, 0.00, 1, '2026-04-13 21:42:38', '2026-04-13 23:29:36');

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
  `monthly_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `monthly_treasury_account_id` int(11) DEFAULT NULL,
  `monthly_day` tinyint(2) NOT NULL DEFAULT 26,
  `monthly_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `monthly_last_generated_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `archived_balance_amount` decimal(15,2) DEFAULT NULL,
  `archived_balance_512_account_id` int(11) DEFAULT NULL,
  `archived_balance_512_account_code` varchar(50) DEFAULT NULL,
  `archived_balance_transferred_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `first_name`, `last_name`, `full_name`, `email`, `phone`, `postal_address`, `passport_number`, `passport_issue_country`, `passport_issue_date`, `passport_expiry_date`, `country_origin`, `country_destination`, `country_commercial`, `client_type`, `client_status`, `status_id`, `category_id`, `currency`, `generated_client_account`, `initial_treasury_account_id`, `monthly_amount`, `monthly_treasury_account_id`, `monthly_day`, `monthly_enabled`, `monthly_last_generated_at`, `is_active`, `created_at`, `updated_at`, `archived_balance_amount`, `archived_balance_512_account_id`, `archived_balance_512_account_code`, `archived_balance_transferred_at`) VALUES
(1, 'CLT0001', 'Aminata', 'Diallo', 'Aminata Diallo', 'aminata.diallo@test.local', '+221700000001', NULL, NULL, NULL, NULL, NULL, 'Sénégal', 'France', 'Sénégal', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411CLT0001', 1, 120.00, 19, 13, 1, '2026-04-13 18:47:39', 1, '2026-03-29 00:19:28', '2026-04-13 18:47:39', NULL, NULL, NULL, NULL),
(2, 'CLT0002', 'Moussa', 'Traore', 'Moussa Traore', 'moussa.traore@test.local', '+223700000002', NULL, NULL, NULL, NULL, NULL, 'Mali', 'France', 'France', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411CLT0002', 2, 210.00, 6, 13, 1, '2026-04-13 18:47:39', 1, '2026-03-29 00:19:28', '2026-04-17 21:35:35', NULL, NULL, NULL, NULL),
(3, 'CLT0003', 'Sarah', 'Nguessan', 'Sarah Nguessan', 'sarah.nguessan@test.local', '+225700000003', NULL, NULL, NULL, NULL, NULL, 'Côte d’Ivoire', 'Belgique', 'Belgique', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411CLT0003', 10, 120.00, 18, 13, 1, '2026-04-13 18:47:39', 1, '2026-03-29 00:19:28', '2026-04-17 17:37:26', NULL, NULL, NULL, NULL),
(4, 'CLT0004', 'Kevin', 'Mba', 'Kevin Mba', 'kevin.mba@test.local', '+237700000004', NULL, NULL, NULL, NULL, NULL, 'Cameroun', 'Autres destinations', 'Cameroun', 'Particulier', 'Actif', NULL, NULL, 'XAF', '411CLT0004', 12, 110.00, 27, 13, 1, '2026-04-13 18:47:39', 1, '2026-03-29 00:19:28', '2026-04-17 17:37:08', NULL, NULL, NULL, NULL),
(5, 'CLT0005', 'Grace', 'Ekué', 'Grace Ekué', 'grace.ekue@test.local', '+228700000005', NULL, NULL, NULL, NULL, NULL, 'Togo', 'France', 'Togo', 'Entreprise', 'Actif', NULL, NULL, 'XOF', '411CLT0005', 48, 210.00, 51, 13, 1, '2026-04-13 18:47:40', 1, '2026-03-29 00:19:28', '2026-04-13 18:47:40', NULL, NULL, NULL, NULL),
(6, 'CLT0006', 'Nadia', 'Benali', 'Nadia Benali', 'nadia.benali@test.local', '+213700000006', NULL, NULL, NULL, NULL, NULL, 'Algérie', 'Espagne', 'Algérie', 'Partenaire', 'Actif', NULL, NULL, 'DZD', '411CLT0006', 50, 140.00, 55, 13, 1, '2026-04-13 18:47:40', 1, '2026-03-29 00:19:28', '2026-04-13 18:47:40', NULL, NULL, NULL, NULL),
(7, '983200894', 'Arcel', 'TIOMA', 'Arcel TIOMA', 'Arc.tioma@gmail.com', '+33673910669', '51 rue du westhoek 59760 Grande-Synthe, France', '1545df456124fs456', 'France', '2015-01-01', '2039-01-01', 'Cameroun', 'France', 'Cameroun', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411983200894', 21, 120.00, 52, 13, 1, '2026-04-13 18:47:39', 1, '2026-04-06 00:48:25', '2026-04-13 18:47:39', NULL, NULL, NULL, NULL),
(8, '870041597', 'Nathael', 'TIOMA', 'Nathael TIOMA', 'nathaeltioma@gmail.com', '+33612542632', '51 rue de làbas, 84521 Ville, France', '45475fgsrg421segf46', 'France', '2017-11-29', '2039-10-31', 'Algérie', 'France', 'Cameroun', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411870041597', 50, 210.00, 43, 13, 1, '2026-04-13 18:47:39', 1, '2026-04-06 22:04:23', '2026-04-13 18:47:39', NULL, NULL, NULL, NULL),
(9, 'CLT058180', 'Marc', 'Tchatchouang', 'Marc Tchatchouang', 'Arcel.tioma@gmail.com', '+33612542632', 'sqhgjyjzjyhrtgerger', 'thgrthyjytjryj', 'Côte d’Ivoire', '2026-04-10', '2031-08-21', 'Côte d’Ivoire', 'Allemagne', 'Côte d\'Ivoire', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411CLT058180', NULL, 160.00, 50, 13, 1, '2026-04-13 18:47:40', 1, '2026-04-10 23:36:16', '2026-04-17 20:05:49', NULL, NULL, NULL, NULL),
(10, '337819647', 'Lenny', 'TIOMA', 'Lenny TIOMA', 'Arcel.tioma@gmail.com', '+33612542632', 'fhekgqrjghjerohgkjlbgjksdffkhrjg', 'ghujksrt45425gbhjsd545', 'Burkina Faso', '2026-04-10', '2030-02-14', 'Burkina Faso', 'Espagne', 'Burkina Faso', 'Etudiant', 'Actif', NULL, NULL, 'EUR', '411337819647', NULL, 150.00, 42, 13, 1, '2026-04-13 18:47:39', 0, '2026-04-10 23:38:36', '2026-04-17 21:54:45', NULL, NULL, NULL, NULL),
(11, '294030444', 'Lysiah', 'TIOMA', 'Lysiah TIOMA', 'Arc.tioma@gmail.com', '+33612542633', '12 rue de l\'étage droite 59760 GSY', '1215645UHDGHHD', 'Cameroun', '2026-04-13', '2028-01-13', 'Cameroun', 'Italie', 'Cameroun', 'Etudiant', NULL, NULL, NULL, 'EUR', '411294030444', 12, 200.00, 12, 26, 1, NULL, 1, '2026-04-13 18:53:36', '2026-04-13 19:07:03', NULL, NULL, NULL, NULL),
(12, '656519872', 'Yonah', 'TIOMA', 'Yonah TIOMA', 'arc.tioma@gmail.com', '+33612542633', '51 rue de la courte échelle, 59760 GSY', '1455465653245453', 'Algérie', '2026-04-13', '2029-04-05', 'Algérie', 'Belgique', 'Algérie', 'Etudiant', NULL, NULL, NULL, 'EUR', '411656519872', NULL, 450.00, NULL, 26, 1, NULL, 1, '2026-04-13 21:42:38', '2026-04-13 21:42:38', NULL, NULL, NULL, NULL);

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
(6, 6, 6, '2026-03-29 00:19:28'),
(7, 8, 7, '2026-04-07 03:19:59'),
(8, 9, 9, '2026-04-10 23:36:16'),
(9, 10, 10, '2026-04-10 23:38:36'),
(10, 7, 8, '2026-04-11 13:15:02'),
(11, 11, 11, '2026-04-13 18:53:36'),
(12, 12, 12, '2026-04-13 21:42:38');

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
-- Structure de la table `monthly_payment_imports`
--

CREATE TABLE `monthly_payment_imports` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `monthly_payment_imports`
--

INSERT INTO `monthly_payment_imports` (`id`, `file_name`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'modele_virement_mensuel.csv', 'draft', 1, '2026-04-12 20:35:56', NULL),
(2, 'modele_virement_mensuel.csv', 'draft', 1, '2026-04-12 20:39:14', NULL),
(3, 'modele_virement_mensuel.csv', 'draft', 1, '2026-04-12 20:45:32', NULL),
(4, 'modele_virement_mensuel.csv', 'validated', 1, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(5, 'modele_virement_mensuel.csv', 'draft', 1, '2026-04-12 21:07:51', NULL),
(6, 'modele_virement_mensuel.csv', 'uploaded', 1, '2026-04-13 02:38:37', '2026-04-13 02:38:37'),
(7, 'modele_virement_mensuel.csv', 'validated', 1, '2026-04-13 18:47:00', '2026-04-13 18:47:21');

-- --------------------------------------------------------

--
-- Structure de la table `monthly_payment_import_rows`
--

CREATE TABLE `monthly_payment_import_rows` (
  `id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `row_number` int(11) NOT NULL,
  `client_code` varchar(50) DEFAULT NULL,
  `monthly_amount` decimal(15,2) DEFAULT NULL,
  `treasury_account_code` varchar(50) DEFAULT NULL,
  `monthly_day` int(11) DEFAULT 26,
  `label` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `resolved_client_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `monthly_payment_import_rows`
--

INSERT INTO `monthly_payment_import_rows` (`id`, `import_id`, `row_number`, `client_code`, `monthly_amount`, `treasury_account_code`, `monthly_day`, `label`, `status`, `error_message`, `resolved_client_id`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '337819647', 100.00, '5120101', 12, 'Mensualité avril', 'pending', NULL, 10, '2026-04-12 20:35:56', NULL),
(2, 1, 3, 'CLT058180', 150.00, '5120102', 12, 'Mensualité avril', 'pending', NULL, 9, '2026-04-12 20:35:56', NULL),
(3, 1, 4, '870041597', 200.00, '5120103', 12, 'Mensualité Avril', 'pending', NULL, 8, '2026-04-12 20:35:56', NULL),
(4, 1, 5, '983200894', 100.00, '5120301', 12, 'Mensualité avril', 'pending', NULL, 7, '2026-04-12 20:35:56', NULL),
(5, 1, 6, 'CLT0006', 150.00, '5120302', 12, 'Mensualité avril', 'pending', NULL, 6, '2026-04-12 20:35:56', NULL),
(6, 1, 7, 'CLT0005', 200.00, '5120404', 12, 'Mensualité avril', 'pending', NULL, 5, '2026-04-12 20:35:56', NULL),
(7, 1, 8, 'CLT0004', 100.00, '5120406', 12, 'Mensualité avril', 'pending', NULL, 4, '2026-04-12 20:35:56', NULL),
(8, 1, 9, 'CLT0003', 150.00, '5120405', 12, 'Mensualité avril', 'pending', NULL, 3, '2026-04-12 20:35:56', NULL),
(9, 1, 10, 'CLT0002', 200.00, '5120410', 12, 'Mensualité avril', 'pending', NULL, 2, '2026-04-12 20:35:56', NULL),
(10, 1, 11, 'CLT0001', 100.00, '5120414', 12, 'Mensualité avril', 'pending', NULL, 1, '2026-04-12 20:35:56', NULL),
(11, 2, 2, '337819647', 100.00, '5120101', 12, 'Mensualité avril', 'pending', NULL, 10, '2026-04-12 20:39:14', NULL),
(12, 2, 3, 'CLT058180', 150.00, '5120102', 12, 'Mensualité avril', 'pending', NULL, 9, '2026-04-12 20:39:14', NULL),
(13, 2, 4, '870041597', 200.00, '5120103', 12, 'Mensualité Avril', 'pending', NULL, 8, '2026-04-12 20:39:14', NULL),
(14, 2, 5, '983200894', 100.00, '5120301', 12, 'Mensualité avril', 'pending', NULL, 7, '2026-04-12 20:39:14', NULL),
(15, 2, 6, 'CLT0006', 150.00, '5120302', 12, 'Mensualité avril', 'pending', NULL, 6, '2026-04-12 20:39:14', NULL),
(16, 2, 7, 'CLT0005', 200.00, '5120404', 12, 'Mensualité avril', 'pending', NULL, 5, '2026-04-12 20:39:14', NULL),
(17, 2, 8, 'CLT0004', 100.00, '5120406', 12, 'Mensualité avril', 'pending', NULL, 4, '2026-04-12 20:39:14', NULL),
(18, 2, 9, 'CLT0003', 150.00, '5120405', 12, 'Mensualité avril', 'pending', NULL, 3, '2026-04-12 20:39:14', NULL),
(19, 2, 10, 'CLT0002', 200.00, '5120410', 12, 'Mensualité avril', 'pending', NULL, 2, '2026-04-12 20:39:14', NULL),
(20, 2, 11, 'CLT0001', 100.00, '5120414', 12, 'Mensualité avril', 'pending', NULL, 1, '2026-04-12 20:39:14', NULL),
(21, 3, 2, '337819647', 100.00, '5120101', 12, 'Mensualité avril', 'pending', NULL, 10, '2026-04-12 20:45:32', NULL),
(22, 3, 3, 'CLT058180', 150.00, '5120102', 12, 'Mensualité avril', 'pending', NULL, 9, '2026-04-12 20:45:32', NULL),
(23, 3, 4, '870041597', 200.00, '5120103', 12, 'Mensualité Avril', 'pending', NULL, 8, '2026-04-12 20:45:32', NULL),
(24, 3, 5, '983200894', 100.00, '5120301', 12, 'Mensualité avril', 'pending', NULL, 7, '2026-04-12 20:45:32', NULL),
(25, 3, 6, 'CLT0006', 150.00, '5120302', 12, 'Mensualité avril', 'pending', NULL, 6, '2026-04-12 20:45:32', NULL),
(26, 3, 7, 'CLT0005', 200.00, '5120404', 12, 'Mensualité avril', 'pending', NULL, 5, '2026-04-12 20:45:32', NULL),
(27, 3, 8, 'CLT0004', 100.00, '5120406', 12, 'Mensualité avril', 'pending', NULL, 4, '2026-04-12 20:45:32', NULL),
(28, 3, 9, 'CLT0003', 150.00, '5120405', 12, 'Mensualité avril', 'pending', NULL, 3, '2026-04-12 20:45:32', NULL),
(29, 3, 10, 'CLT0002', 200.00, '5120410', 12, 'Mensualité avril', 'pending', NULL, 2, '2026-04-12 20:45:32', NULL),
(30, 3, 11, 'CLT0001', 100.00, '5120414', 12, 'Mensualité avril', 'pending', NULL, 1, '2026-04-12 20:45:32', NULL),
(31, 4, 2, '337819647', 100.00, '5120101', 12, 'Mensualité avril', 'validated', NULL, 10, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(32, 4, 3, 'CLT058180', 150.00, '5120102', 12, 'Mensualité avril', 'validated', NULL, 9, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(33, 4, 4, '870041597', 200.00, '5120103', 12, 'Mensualité Avril', 'validated', NULL, 8, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(34, 4, 5, '983200894', 100.00, '5120301', 12, 'Mensualité avril', 'validated', NULL, 7, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(35, 4, 6, 'CLT0006', 150.00, '5120302', 12, 'Mensualité avril', 'validated', NULL, 6, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(36, 4, 7, 'CLT0005', 200.00, '5120404', 12, 'Mensualité avril', 'validated', NULL, 5, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(37, 4, 8, 'CLT0004', 100.00, '5120406', 12, 'Mensualité avril', 'validated', NULL, 4, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(38, 4, 9, 'CLT0003', 150.00, '5120405', 12, 'Mensualité avril', 'validated', NULL, 3, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(39, 4, 10, 'CLT0002', 200.00, '5120410', 12, 'Mensualité avril', 'validated', NULL, 2, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(40, 4, 11, 'CLT0001', 100.00, '5120414', 12, 'Mensualité avril', 'validated', NULL, 1, '2026-04-12 20:51:26', '2026-04-12 20:54:26'),
(41, 5, 2, '337819647', 100.00, '5120101', 12, 'Mensualité avril', 'pending', NULL, 10, '2026-04-12 21:07:51', NULL),
(42, 5, 3, 'CLT058180', 150.00, '5120102', 12, 'Mensualité avril', 'pending', NULL, 9, '2026-04-12 21:07:51', NULL),
(43, 5, 4, '870041597', 200.00, '5120103', 12, 'Mensualité Avril', 'pending', NULL, 8, '2026-04-12 21:07:51', NULL),
(44, 5, 5, '983200894', 100.00, '5120301', 12, 'Mensualité avril', 'pending', NULL, 7, '2026-04-12 21:07:51', NULL),
(45, 5, 6, 'CLT0006', 150.00, '5120302', 12, 'Mensualité avril', 'pending', NULL, 6, '2026-04-12 21:07:51', NULL),
(46, 5, 7, 'CLT0005', 200.00, '5120404', 12, 'Mensualité avril', 'pending', NULL, 5, '2026-04-12 21:07:51', NULL),
(47, 5, 8, 'CLT0004', 100.00, '5120406', 12, 'Mensualité avril', 'pending', NULL, 4, '2026-04-12 21:07:51', NULL),
(48, 5, 9, 'CLT0003', 150.00, '5120405', 12, 'Mensualité avril', 'pending', NULL, 3, '2026-04-12 21:07:51', NULL),
(49, 5, 10, 'CLT0002', 200.00, '5120410', 12, 'Mensualité avril', 'pending', NULL, 2, '2026-04-12 21:07:51', NULL),
(50, 5, 11, 'CLT0001', 100.00, '5120414', 12, 'Mensualité avril', 'pending', NULL, 1, '2026-04-12 21:07:51', NULL),
(51, 6, 2, '337819647', NULL, '5121102', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:37', '2026-04-13 02:38:37'),
(52, 6, 3, 'CLT058180', NULL, '5121701', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:37', '2026-04-13 02:38:37'),
(53, 6, 4, '870041597', NULL, '5121103', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(54, 6, 5, '983200894', NULL, '5121802', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(55, 6, 6, 'CLT0006', NULL, '5122101', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(56, 6, 7, 'CLT0005', NULL, '5121801', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(57, 6, 8, 'CLT0004', NULL, '5120416', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(58, 6, 9, 'CLT0003', NULL, '5120407', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(59, 6, 10, 'CLT0002', NULL, '5120106', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(60, 6, 11, 'CLT0001', NULL, '5120408', 12, 'Mensualité Mai', 'uploaded', NULL, NULL, '2026-04-13 02:38:38', '2026-04-13 02:38:38'),
(61, 7, 0, '337819647', 150.00, '5121102', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:00', '2026-04-13 18:47:20'),
(62, 7, 0, 'CLT058180', 160.00, '5121701', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:00', '2026-04-13 18:47:20'),
(63, 7, 0, '870041597', 210.00, '5121103', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:00', '2026-04-13 18:47:20'),
(64, 7, 0, '983200894', 120.00, '5121802', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:01', '2026-04-13 18:47:21'),
(65, 7, 0, 'CLT0006', 140.00, '5122101', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:01', '2026-04-13 18:47:21'),
(66, 7, 0, 'CLT0005', 210.00, '5121801', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:01', '2026-04-13 18:47:21'),
(67, 7, 0, 'CLT0004', 110.00, '5120416', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:01', '2026-04-13 18:47:21'),
(68, 7, 0, 'CLT0003', 120.00, '5120407', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:01', '2026-04-13 18:47:21'),
(69, 7, 0, 'CLT0002', 210.00, '5120106', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:01', '2026-04-13 18:47:21'),
(70, 7, 0, 'CLT0001', 120.00, '5120408', 13, 'Mensualité Mai', 'pending', NULL, NULL, '2026-04-13 18:47:01', '2026-04-13 18:47:21');

-- --------------------------------------------------------

--
-- Structure de la table `monthly_payment_runs`
--

CREATE TABLE `monthly_payment_runs` (
  `id` int(11) NOT NULL,
  `run_date` date NOT NULL,
  `scheduled_day` tinyint(4) NOT NULL DEFAULT 26,
  `total_clients` int(11) NOT NULL DEFAULT 0,
  `total_created` int(11) NOT NULL DEFAULT 0,
  `total_skipped` int(11) NOT NULL DEFAULT 0,
  `total_errors` int(11) NOT NULL DEFAULT 0,
  `executed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'executed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `monthly_payment_runs`
--

INSERT INTO `monthly_payment_runs` (`id`, `run_date`, `scheduled_day`, `total_clients`, `total_created`, `total_skipped`, `total_errors`, `executed_by`, `created_at`, `status`) VALUES
(1, '2026-04-12', 12, 0, 0, 0, 0, 1, '2026-04-12 20:53:25', 'executed'),
(2, '2026-04-12', 12, 10, 10, 0, 0, 1, '2026-04-12 21:08:13', 'executed'),
(3, '2026-04-12', 12, 10, 0, 10, 0, 1, '2026-04-12 21:08:25', 'executed'),
(4, '2026-04-13', 13, 10, 10, 0, 0, 1, '2026-04-13 18:47:39', 'executed');

-- --------------------------------------------------------

--
-- Structure de la table `monthly_payment_run_items`
--

CREATE TABLE `monthly_payment_run_items` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_code` varchar(50) DEFAULT NULL,
  `operation_id` int(11) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `treasury_account_id` int(11) DEFAULT NULL,
  `treasury_account_code` varchar(50) DEFAULT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `is_cancelled` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `monthly_payment_run_items`
--

INSERT INTO `monthly_payment_run_items` (`id`, `run_id`, `client_id`, `client_code`, `operation_id`, `status`, `amount`, `treasury_account_id`, `treasury_account_code`, `reference`, `label`, `message`, `created_at`, `updated_at`, `is_cancelled`) VALUES
(1, 2, 10, '337819647', 28, 'created', 100.00, 1, '5120101', 'MENS-337819647-20260412', 'Mensualité - 337819647 - Lenny TIOMA', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:13', '2026-04-12 21:08:13', 0),
(2, 2, 8, '870041597', 29, 'created', 200.00, 3, '5120103', 'MENS-870041597-20260412', 'Mensualité - 870041597 - Nathael TIOMA', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:13', '2026-04-12 21:08:13', 0),
(3, 2, 7, '983200894', 30, 'created', 100.00, 10, '5120301', 'MENS-983200894-20260412', 'Mensualité - 983200894 - Arcel TIOMA', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:13', '2026-04-12 21:08:13', 0),
(4, 2, 1, 'CLT0001', 31, 'created', 100.00, 25, '5120414', 'MENS-CLT0001-20260412', 'Mensualité - CLT0001 - Aminata Diallo', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:13', '2026-04-12 21:08:13', 0),
(5, 2, 2, 'CLT0002', 32, 'created', 200.00, 21, '5120410', 'MENS-CLT0002-20260412', 'Mensualité - CLT0002 - Moussa Traore', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:14', '2026-04-12 21:08:14', 0),
(6, 2, 3, 'CLT0003', 33, 'created', 150.00, 16, '5120405', 'MENS-CLT0003-20260412', 'Mensualité - CLT0003 - Sarah Nguessan', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:14', '2026-04-12 21:08:14', 0),
(7, 2, 4, 'CLT0004', 34, 'created', 100.00, 17, '5120406', 'MENS-CLT0004-20260412', 'Mensualité - CLT0004 - Kevin Mba', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:14', '2026-04-12 21:08:14', 0),
(8, 2, 5, 'CLT0005', 35, 'created', 200.00, 15, '5120404', 'MENS-CLT0005-20260412', 'Mensualité - CLT0005 - Grace Ekué', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:14', '2026-04-12 21:08:14', 0),
(9, 2, 6, 'CLT0006', 36, 'created', 150.00, 11, '5120302', 'MENS-CLT0006-20260412', 'Mensualité - CLT0006 - Nadia Benali', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:14', '2026-04-12 21:08:14', 0),
(10, 2, 9, 'CLT058180', 37, 'created', 150.00, 2, '5120102', 'MENS-CLT058180-20260412', 'Mensualité - CLT058180 - Marc Tchatchouang', 'Opération mensuelle créée avec succès.', '2026-04-12 21:08:14', '2026-04-12 21:08:14', 0),
(11, 3, 10, '337819647', NULL, 'skipped', 100.00, 1, '5120101', 'MENS-337819647-20260412', 'Mensualité - 337819647 - Lenny TIOMA', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(12, 3, 8, '870041597', NULL, 'skipped', 200.00, 3, '5120103', 'MENS-870041597-20260412', 'Mensualité - 870041597 - Nathael TIOMA', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(13, 3, 7, '983200894', NULL, 'skipped', 100.00, 10, '5120301', 'MENS-983200894-20260412', 'Mensualité - 983200894 - Arcel TIOMA', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(14, 3, 1, 'CLT0001', NULL, 'skipped', 100.00, 25, '5120414', 'MENS-CLT0001-20260412', 'Mensualité - CLT0001 - Aminata Diallo', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(15, 3, 2, 'CLT0002', NULL, 'skipped', 200.00, 21, '5120410', 'MENS-CLT0002-20260412', 'Mensualité - CLT0002 - Moussa Traore', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(16, 3, 3, 'CLT0003', NULL, 'skipped', 150.00, 16, '5120405', 'MENS-CLT0003-20260412', 'Mensualité - CLT0003 - Sarah Nguessan', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(17, 3, 4, 'CLT0004', NULL, 'skipped', 100.00, 17, '5120406', 'MENS-CLT0004-20260412', 'Mensualité - CLT0004 - Kevin Mba', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(18, 3, 5, 'CLT0005', NULL, 'skipped', 200.00, 15, '5120404', 'MENS-CLT0005-20260412', 'Mensualité - CLT0005 - Grace Ekué', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(19, 3, 6, 'CLT0006', NULL, 'skipped', 150.00, 11, '5120302', 'MENS-CLT0006-20260412', 'Mensualité - CLT0006 - Nadia Benali', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(20, 3, 9, 'CLT058180', NULL, 'skipped', 150.00, 2, '5120102', 'MENS-CLT058180-20260412', 'Mensualité - CLT058180 - Marc Tchatchouang', 'Opération déjà générée pour cette date.', '2026-04-12 21:08:25', '2026-04-12 21:08:25', 0),
(21, 4, 10, '337819647', 38, 'created', 150.00, 42, '5121102', 'MENS-337819647-20260413', 'Mensualité - 337819647 - Lenny TIOMA', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:39', '2026-04-13 18:47:39', 0),
(22, 4, 8, '870041597', 39, 'created', 210.00, 43, '5121103', 'MENS-870041597-20260413', 'Mensualité - 870041597 - Nathael TIOMA', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:39', '2026-04-13 18:47:39', 0),
(23, 4, 7, '983200894', 40, 'created', 120.00, 52, '5121802', 'MENS-983200894-20260413', 'Mensualité - 983200894 - Arcel TIOMA', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:39', '2026-04-13 18:47:39', 0),
(24, 4, 1, 'CLT0001', 41, 'created', 120.00, 19, '5120408', 'MENS-CLT0001-20260413', 'Mensualité - CLT0001 - Aminata Diallo', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:39', '2026-04-13 18:47:39', 0),
(25, 4, 2, 'CLT0002', 42, 'created', 210.00, 6, '5120106', 'MENS-CLT0002-20260413', 'Mensualité - CLT0002 - Moussa Traore', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:39', '2026-04-13 18:47:39', 0),
(26, 4, 3, 'CLT0003', 43, 'created', 120.00, 18, '5120407', 'MENS-CLT0003-20260413', 'Mensualité - CLT0003 - Sarah Nguessan', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:39', '2026-04-13 18:47:39', 0),
(27, 4, 4, 'CLT0004', 44, 'created', 110.00, 27, '5120416', 'MENS-CLT0004-20260413', 'Mensualité - CLT0004 - Kevin Mba', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:40', '2026-04-13 18:47:40', 0),
(28, 4, 5, 'CLT0005', 45, 'created', 210.00, 51, '5121801', 'MENS-CLT0005-20260413', 'Mensualité - CLT0005 - Grace Ekué', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:40', '2026-04-13 18:47:40', 0),
(29, 4, 6, 'CLT0006', 46, 'created', 140.00, 55, '5122101', 'MENS-CLT0006-20260413', 'Mensualité - CLT0006 - Nadia Benali', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:40', '2026-04-13 18:47:40', 0),
(30, 4, 9, 'CLT058180', 47, 'created', 160.00, 50, '5121701', 'MENS-CLT058180-20260413', 'Mensualité - CLT058180 - Marc Tchatchouang', 'Opération mensuelle créée avec succès.', '2026-04-13 18:47:40', '2026-04-13 18:47:40', 0);

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

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `message`, `level`, `link_url`, `entity_type`, `entity_id`, `is_read`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'client_create', 'Client créé : 983200894 - Arcel TIOMA', 'success', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=7', 'client', 7, 0, 1, '2026-04-06 00:48:25', NULL),
(2, 'client_create', 'Client créé : 870041597 - Nathael TIOMA', 'success', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=8', 'client', 8, 0, 1, '2026-04-06 22:04:23', NULL),
(3, 'client_update', 'Client mis à jour : 870041597 - Nathael TIOMA', 'info', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=8', 'client', 8, 0, 1, '2026-04-06 22:11:14', NULL),
(4, 'client_update', 'Client mis à jour : 870041597 - Nathael TIOMA', 'info', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=8', 'client', 8, 0, 1, '2026-04-06 22:46:54', NULL),
(5, 'client_update', 'Client mis à jour : 983200894 - Arcel TIOMA', 'info', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=7', 'client', 7, 0, 1, '2026-04-07 00:23:28', NULL),
(6, 'client_update', 'Client mis à jour : 870041597 - Nathael TIOMA', 'info', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=8', 'client', 8, 0, 1, '2026-04-07 03:20:00', NULL),
(7, 'operation_create', 'Nouvelle opération créée : VERSEMENT - VERSEMENT', 'success', 'http://localhost/StudelyLedge/modules/operations/operation_view.php?id=24', 'operation', 24, 0, 1, '2026-04-08 17:58:53', NULL),
(8, 'operation_create', 'Nouvelle opération créée : COMMISSION DE TRANSFERT - COMMISSION DE TRANSFERT', 'success', 'http://localhost/StudelyLedge/modules/operations/operation_view.php?id=25', 'operation', 25, 0, 1, '2026-04-08 17:59:27', NULL),
(9, 'operation_create', 'Nouvelle opération créée : test frais AVI crédit/débit auto', 'success', 'http://localhost/StudelyLedge/modules/operations/operation_view.php?id=26', 'operation', 26, 0, 1, '2026-04-10 22:21:46', NULL),
(10, 'operation_create', 'Nouvelle opération créée : FRAIS GESTION - GESTION', 'success', 'http://localhost/StudelyLedge/modules/operations/operation_view.php?id=27', 'operation', 27, 0, 1, '2026-04-10 22:23:13', NULL),
(11, 'client_update', 'Client mis à jour : 983200894 - Arcel TIOMA', 'info', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=7', 'client', 7, 0, 1, '2026-04-11 13:15:02', NULL),
(12, 'client_create', 'Client créé : 294030444 - Lysiah TIOMA', 'success', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=11', 'client', 11, 1, 1, '2026-04-13 18:53:36', '2026-04-15 23:26:16'),
(13, 'pending_debit_insufficient', 'Insuffisance de solde 411 pour le client 294030444 - Lysiah TIOMA | demandé : 1 000,00 | exécuté : 0,00 | restant dû : 1 000,00', 'warning', 'http://localhost/StudelyLedge/modules/pending_debits/pending_debit_view.php?id=1', 'pending_client_debit', 1, 0, 1, '2026-04-13 18:57:30', NULL),
(14, 'client_update', 'Client mis à jour : 294030444 - Lysiah TIOMA', 'info', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=11', 'client', 11, 0, 1, '2026-04-13 19:07:03', NULL),
(15, 'pending_debit_ready', 'Le compte client 411 de 294030444 - Lysiah TIOMA est de nouveau alimenté. Débit restant dû disponible : 1 000,00', 'info', 'http://localhost/StudelyLedge/modules/pending_debits/pending_debit_view.php?id=1', 'pending_client_debit', 1, 0, 1, '2026-04-13 19:07:59', NULL),
(16, 'operation_create', 'Nouvelle opération créée : TEST OPE VERSEMENT', 'success', 'http://localhost/StudelyLedge/modules/operations/operation_view.php?id=48', 'operation', 48, 0, 1, '2026-04-13 19:07:59', NULL),
(17, 'pending_debit_resolved', 'Le débit dû du client 294030444 a été totalement soldé.', 'success', 'http://localhost/StudelyLedge/modules/pending_debits/pending_debit_view.php?id=1', 'pending_client_debit', 1, 0, 1, '2026-04-13 21:25:17', NULL),
(18, 'client_create', 'Client créé : 656519872 - Yonah TIOMA', 'success', 'http://localhost/StudelyLedge/modules/clients/client_view.php?id=12', 'client', 12, 1, 1, '2026-04-13 21:42:38', '2026-04-15 23:26:34'),
(19, 'pending_debit_insufficient', 'Insuffisance de solde 411 pour le client 656519872 - Yonah TIOMA | demandé : 750,00 | exécuté : 500,00 | restant dû : 250,00', 'warning', 'http://localhost/StudelyLedge/modules/pending_debits/pending_debit_view.php?id=2', 'pending_client_debit', 2, 0, 1, '2026-04-13 23:29:36', NULL),
(20, 'pending_debit_partial_execution', 'Débit partiel exécuté sur 411 client. Reliquat placé en débit dû.', 'warning', 'http://localhost/StudelyLedge/modules/pending_debits/pending_debit_view.php?id=2', 'pending_client_debit', 2, 0, 1, '2026-04-13 23:29:36', NULL),
(21, 'operation_create', 'Nouvelle opération créée : TEST OPE DEBIT DU OPE', 'success', 'http://localhost/StudelyLedge/modules/operations/operation_view.php?id=50', 'operation', 50, 0, 1, '2026-04-13 23:29:37', NULL),
(22, 'pending_debit_insufficient', 'Insuffisance de solde 411 pour le client 656519872 - Yonah TIOMA | demandé : 450,00 | exécuté : 0,00 | restant dû : 450,00', 'warning', 'http://localhost/StudelyLedge/modules/pending_debits/pending_debit_view.php?id=3', 'pending_client_debit', 3, 0, 1, '2026-04-17 23:37:00', NULL),
(23, 'pending_debit_insufficient', 'Insuffisance de solde 411 pour le client 656519872 - Yonah TIOMA | demandé : 1 750,00 | exécuté : 0,00 | restant dû : 1 750,00', 'warning', 'http://localhost/StudelyLedge/modules/pending_debits/pending_debit_view.php?id=4', 'pending_client_debit', 4, 0, 1, '2026-04-17 23:42:20', NULL);

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
  `monthly_run_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `operations`
--

INSERT INTO `operations` (`id`, `client_id`, `service_id`, `operation_type_id`, `bank_account_id`, `linked_bank_account_id`, `operation_date`, `operation_type_code`, `operation_kind`, `label`, `amount`, `currency_code`, `reference`, `source_type`, `debit_account_code`, `credit_account_code`, `service_account_code`, `operation_hash`, `is_manual_accounting`, `notes`, `created_by`, `monthly_run_id`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, 1, NULL, '2026-03-01', 'VERSEMENT', 'seed', 'Versement initial client 1', 12000.00, NULL, 'TEST-OP-0001', 'seed', '5120101', '411CLT0001', NULL, NULL, 0, 'Alimentation initiale', NULL, NULL, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(2, 1, NULL, NULL, 1, NULL, '2026-03-03', 'FRAIS_DE_SERVICE', 'seed', 'Frais de services AVI', 250.00, NULL, 'TEST-OP-0002', 'seed', '411CLT0001', '706101', '706101', NULL, 0, 'Service SRV_AVI_SERVICE', NULL, NULL, '2026-03-29 00:19:28', '2026-03-29 00:19:28'),
(3, 1, NULL, NULL, 1, NULL, '2026-03-10', 'VIREMENT_MENSUEL', 'seed', 'Virement mensuel client 1', 900.00, NULL, 'TEST-OP-0003', 'seed', '411CLT0001', '5120101', NULL, NULL, 0, 'Décaissement mensuel', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(4, 2, NULL, NULL, 2, NULL, '2026-03-02', 'VERSEMENT', 'seed', 'Versement initial client 2', 8500.00, NULL, 'TEST-OP-0004', 'seed', '5120102', '411CLT0002', NULL, NULL, 0, 'Alimentation initiale', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(5, 2, NULL, NULL, 2, NULL, '2026-03-05', 'FRAIS_DE_SERVICE', 'seed', 'Frais de service ATS', 175.00, NULL, 'TEST-OP-0005', 'seed', '411CLT0002', '706104', '706104', NULL, 0, 'Service SRV_ATS_SERVICE', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(6, 2, NULL, NULL, 2, NULL, '2026-03-08', 'REGULARISATION_POSITIVE', 'seed', 'Régularisation positive client 2', 300.00, NULL, 'TEST-OP-0006', 'seed', '5120102', '411CLT0002', NULL, NULL, 0, 'Correction en faveur du client', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(7, 2, NULL, NULL, 2, NULL, '2026-03-16', 'REGULARISATION_NEGATIVE', 'seed', 'Régularisation négative client 2', 120.00, NULL, 'TEST-OP-0007', 'seed', '411CLT0002', '5120102', NULL, NULL, 0, 'Correction défavorable au client', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(8, 3, NULL, NULL, 3, NULL, '2026-03-04', 'VERSEMENT', 'seed', 'Versement initial client 3', 15000.00, NULL, 'TEST-OP-0008', 'seed', '5120301', '411CLT0003', NULL, NULL, 0, 'Alimentation initiale', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(9, 3, NULL, NULL, 3, NULL, '2026-03-06', 'VIREMENT_EXCEPTIONEL', 'seed', 'Commission de transfert', 420.00, NULL, 'TEST-OP-0009', 'seed', '411CLT0003', '5120301', '706103', NULL, 0, 'Virement exceptionnel avec commission', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(10, 4, NULL, NULL, 4, NULL, '2026-03-07', 'VERSEMENT', 'seed', 'Versement initial client 4', 600000.00, NULL, 'TEST-OP-0010', 'seed', '5120401', '411CLT0004', NULL, NULL, 0, 'Alimentation locale', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(11, 4, NULL, NULL, 4, NULL, '2026-03-09', 'FRAIS_BANCAIRES', 'seed', 'Frais de gestion', 15000.00, NULL, 'TEST-OP-0011', 'seed', '411CLT0004', '706102', '706102', NULL, 0, 'Service SRV_FRAIS_GESTION', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(12, 5, NULL, NULL, 5, NULL, '2026-03-11', 'VERSEMENT', 'seed', 'Versement initial client 5', 900000.00, NULL, 'TEST-OP-0012', 'seed', '5121401', '411CLT0005', NULL, NULL, 0, 'Alimentation initiale', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(13, 5, NULL, NULL, 5, NULL, '2026-03-13', 'VIREMENT_REGULIER', 'seed', 'Virement régulier client 5', 175000.00, NULL, 'TEST-OP-0013', 'seed', '411CLT0005', '5121401', NULL, NULL, 0, 'Décaissement régulier', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(14, 6, NULL, NULL, 6, NULL, '2026-03-12', 'VERSEMENT', 'seed', 'Versement initial client 6', 300000.00, NULL, 'TEST-OP-0014', 'seed', '5121701', '411CLT0006', NULL, NULL, 0, 'Alimentation initiale', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(15, 6, NULL, NULL, 6, NULL, '2026-03-18', 'FRAIS_DE_SERVICE', 'seed', 'Frais de services AVI', 6000.00, NULL, 'TEST-OP-0015', 'seed', '411CLT0006', '706101', '706101', NULL, 0, 'Facturation service', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(16, 6, NULL, NULL, 6, NULL, '2026-03-21', 'REGULARISATION_NEGATIVE', 'seed', 'Régularisation négative client 6', 12000.00, NULL, 'TEST-OP-0016', 'seed', '411CLT0006', '5121701', NULL, NULL, 0, 'Correction négative', NULL, NULL, '2026-03-29 00:19:29', '2026-03-29 00:19:29'),
(17, 1, NULL, NULL, 1, NULL, '2026-03-30', 'VERSEMENT', 'manual', 'VERSEMENT', 200.00, NULL, NULL, 'manual', '5120101', '411CLT0001', NULL, NULL, 0, NULL, 1, NULL, '2026-03-30 03:20:23', NULL),
(19, 5, NULL, NULL, 5, NULL, '2026-03-30', 'FRAIS_DE_SERVICE', 'manual', 'FRAIS DE SERVICE', 150.00, NULL, NULL, 'manual', '411CLT0005', '7061314', '7061314', NULL, 0, NULL, 1, NULL, '2026-03-30 03:25:40', NULL),
(20, 1, 18, 17, 1, 1, '2026-03-31', 'VERSEMENT', 'manual', 'VERSEMENT - VERSEMENT', 200.00, 'EUR', 'VERS31032026', 'manual', '5120101', '411CLT0001', NULL, 'bf0311b14fdc83d1d17e3d94ef11cc59d5f351dc15d043c6781b0568415b5534', 0, 'Versement client', 1, NULL, '2026-03-31 22:57:06', '2026-03-31 22:57:06'),
(21, 5, 18, 17, 5, 5, '2026-03-31', 'VERSEMENT', 'manual', 'VERSEMENT - VERSEMENT', 542.00, 'EUR', 'VERS0124520', 'manual', '5121401', '411CLT0005', NULL, 'e3197241aba8eccfc0daf230f97e8cf52eed8100860455a982e322099341f215', 0, NULL, 1, NULL, '2026-03-31 22:59:02', '2026-03-31 22:59:02'),
(22, 1, 22, 18, 1, 1, '2026-04-01', 'VIREMENT', 'manual', 'VIREMENT - INTERNE', 100.00, 'EUR', NULL, 'manual', '706311', '5120401', NULL, '0df8271cd21f3059fc5d53e0b4fba31e499096d1505a12619a6e2e0262453e11', 1, NULL, 1, NULL, '2026-04-01 21:44:06', '2026-04-01 21:44:06'),
(23, 1, 17, 19, 1, 1, '2026-04-01', 'REGULARISATION', 'manual', 'REGULARISATION - POSITIVE', 200.00, 'EUR', NULL, 'manual', '5120101', '411CLT0001', NULL, '697d9a8af0484b5cb9b1d5cd1d65324d8231907cf20b7ea2158deaa4e513b48a', 0, NULL, 1, NULL, '2026-04-01 21:45:23', '2026-04-01 21:45:23'),
(24, 6, 18, 17, 6, 6, '2026-04-08', 'VERSEMENT', 'manual', 'VERSEMENT - VERSEMENT', 500.00, 'EUR', NULL, 'manual', '5121701', '411CLT0006', NULL, 'b6e95752625e8b742b46985956d412021c954a57f0aa976680a7878481174a87', 0, NULL, 1, NULL, '2026-04-08 17:58:53', '2026-04-08 17:58:53'),
(25, 3, 11, 22, 3, 3, '2026-04-08', 'COMMISSION_DE_TRANSFERT', 'manual', 'COMMISSION DE TRANSFERT - COMMISSION DE TRANSFERT', 200.00, 'EUR', NULL, 'manual', '411CLT0003', '706303', '706303', '2bb96aae061c15190021ba35b71f6334bb2767bac8ccde828de87b6c87fa2060', 0, NULL, 1, NULL, '2026-04-08 17:59:27', '2026-04-08 17:59:27'),
(26, 2, 15, 20, 2, 2, '2026-04-10', 'FRAIS_SERVICE', 'manual', 'test frais AVI crédit/débit auto', 200.00, 'EUR', 'VERS0124520356', 'manual', '411CLT0002', '7061101', '7061101', 'b7f1608c7e6ade19ac4779a297a222af62d15bb204081b144a65d0de013764b1', 0, 'vzhkdhjksdhbfjlzednd', 1, NULL, '2026-04-10 22:21:46', '2026-04-10 22:21:46'),
(27, 7, 13, 21, NULL, NULL, '2026-04-10', 'FRAIS_GESTION', 'manual', 'FRAIS GESTION - GESTION', 100.00, 'EUR', NULL, 'manual', '411983200894', '706204', '706204', 'dc6d32d8708b47d9d8d9b6262c1e0691f4ae563ee2d5ae9a800bbc7952503d95', 0, NULL, 1, NULL, '2026-04-10 22:23:13', '2026-04-10 22:23:13'),
(28, 10, NULL, NULL, 10, 10, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - 337819647 - Lenny TIOMA', 100.00, 'EUR', 'MENS-337819647-20260412', 'monthly_import', '411337819647', '5120101', NULL, 'cac8fc053c966ed5260c63403285196b7e565efd58487b14befaa88625d15e3e', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:13', '2026-04-12 21:08:13'),
(29, 8, NULL, NULL, 7, 7, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - 870041597 - Nathael TIOMA', 200.00, 'EUR', 'MENS-870041597-20260412', 'monthly_import', '411870041597', '5120103', NULL, '1ae5b4077ad750a36da240fe7efe3338d384fef6238372906104a52b56f492fe', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:13', '2026-04-12 21:08:13'),
(30, 7, NULL, NULL, 8, 8, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - 983200894 - Arcel TIOMA', 100.00, 'EUR', 'MENS-983200894-20260412', 'monthly_import', '411983200894', '5120301', NULL, '37da45fdf64698a1b372950c7829716d995d0a6b84fc7f045f4d05e67036e121', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:13', '2026-04-12 21:08:13'),
(31, 1, NULL, NULL, 1, 1, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0001 - Aminata Diallo', 100.00, 'EUR', 'MENS-CLT0001-20260412', 'monthly_import', '411CLT0001', '5120414', NULL, 'b40b74f6623c98170a5adbead0e87042cd5989f2b09ada270f3ae6949862f5f8', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:13', '2026-04-12 21:08:13'),
(32, 2, NULL, NULL, 2, 2, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0002 - Moussa Traore', 200.00, 'EUR', 'MENS-CLT0002-20260412', 'monthly_import', '411CLT0002', '5120410', NULL, 'bf858c1e4a25ed95836ad887f335d839a023607bd8af168ad600b8667e70af79', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:13', '2026-04-12 21:08:14'),
(33, 3, NULL, NULL, 3, 3, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0003 - Sarah Nguessan', 150.00, 'EUR', 'MENS-CLT0003-20260412', 'monthly_import', '411CLT0003', '5120405', NULL, '59859c5491ad87b926ec00bb9a3680fcb62e76ca97fc466c378d060fa0950ccf', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:14', '2026-04-12 21:08:14'),
(34, 4, NULL, NULL, 4, 4, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0004 - Kevin Mba', 100.00, 'XAF', 'MENS-CLT0004-20260412', 'monthly_import', '411CLT0004', '5120406', NULL, 'f0d4e6e99b2b067bf32b3bdb1a61184e2a47227098b5a0fa50c86fe70da54e8c', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:14', '2026-04-12 21:08:14'),
(35, 5, NULL, NULL, 5, 5, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0005 - Grace Ekué', 200.00, 'XOF', 'MENS-CLT0005-20260412', 'monthly_import', '411CLT0005', '5120404', NULL, 'ffee1faa6aed9f6a3fa33fbc40518fd34ff29c5a4413847685ef8cca6a64e590', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:14', '2026-04-12 21:08:14'),
(36, 6, NULL, NULL, 6, 6, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0006 - Nadia Benali', 150.00, 'DZD', 'MENS-CLT0006-20260412', 'monthly_import', '411CLT0006', '5120302', NULL, '1a8e92f59d7e18c2c170a154694f8afdc426013b334934b80ee13b880a6cf666', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:14', '2026-04-12 21:08:14'),
(37, 9, NULL, NULL, 9, 9, '2026-04-12', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT058180 - Marc Tchatchouang', 150.00, 'EUR', 'MENS-CLT058180-20260412', 'monthly_import', '411CLT058180', '5120102', NULL, '4656867b4a06132bbc122255ba202071143801a09c174f357ae2fa85dff47840', 0, 'Mensualité générée automatiquement - jour planifié: 12', 1, 2, '2026-04-12 21:08:14', '2026-04-12 21:08:14'),
(38, 10, NULL, NULL, 10, 10, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - 337819647 - Lenny TIOMA', 150.00, 'EUR', 'MENS-337819647-20260413', 'monthly_import', '411337819647', '5121102', NULL, 'c9a1126483146feb2a4c6124b0a491fd5a93d4b8c1fd197bc3b95cca9159d784', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:39', '2026-04-13 18:47:39'),
(39, 8, NULL, NULL, 7, 7, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - 870041597 - Nathael TIOMA', 210.00, 'EUR', 'MENS-870041597-20260413', 'monthly_import', '411870041597', '5121103', NULL, 'c102c378922191c4ad9b0e257cb52094bcff83397fdc4a65b1f8c39e6ca88ade', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:39', '2026-04-13 18:47:39'),
(40, 7, NULL, NULL, 8, 8, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - 983200894 - Arcel TIOMA', 120.00, 'EUR', 'MENS-983200894-20260413', 'monthly_import', '411983200894', '5121802', NULL, '9a665391965fd9c05c256f849d40aade1b59f5cedd2cf6041806d28fecb347df', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:39', '2026-04-13 18:47:39'),
(41, 1, NULL, NULL, 1, 1, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0001 - Aminata Diallo', 120.00, 'EUR', 'MENS-CLT0001-20260413', 'monthly_import', '411CLT0001', '5120408', NULL, '4229ee8d6323fb0c656c97e7f93d795beca58840c1650bcbadcf3f1338cae59b', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:39', '2026-04-13 18:47:39'),
(42, 2, NULL, NULL, 2, 2, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0002 - Moussa Traore', 210.00, 'EUR', 'MENS-CLT0002-20260413', 'monthly_import', '411CLT0002', '5120106', NULL, '1e3d8ab8393b6fc411ed8556daab9977faab4323267dea41b344f417bad7a200', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:39', '2026-04-13 18:47:39'),
(43, 3, NULL, NULL, 3, 3, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0003 - Sarah Nguessan', 120.00, 'EUR', 'MENS-CLT0003-20260413', 'monthly_import', '411CLT0003', '5120407', NULL, '6cc22e72cafa4bd7ad2552cbb364b7c0bb7d688d6b8b521f4c03ee9e3a2beed5', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:39', '2026-04-13 18:47:39'),
(44, 4, NULL, NULL, 4, 4, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0004 - Kevin Mba', 110.00, 'XAF', 'MENS-CLT0004-20260413', 'monthly_import', '411CLT0004', '5120416', NULL, 'e8793cc511dce4d8c819b2d423a1e7570e21a571e94787dae30e6233032f0dcd', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:39', '2026-04-13 18:47:40'),
(45, 5, NULL, NULL, 5, 5, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0005 - Grace Ekué', 210.00, 'XOF', 'MENS-CLT0005-20260413', 'monthly_import', '411CLT0005', '5121801', NULL, 'ef74c486fece116d2a1467144be459bf106e8ec3c7fd8a84e2a89650b1de5842', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:40', '2026-04-13 18:47:40'),
(46, 6, NULL, NULL, 6, 6, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT0006 - Nadia Benali', 140.00, 'DZD', 'MENS-CLT0006-20260413', 'monthly_import', '411CLT0006', '5122101', NULL, '2179c71d72c46387459b7f4da1cf30408b5e272ca1303393352d180846c2214a', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:40', '2026-04-13 18:47:40'),
(47, 9, NULL, NULL, 9, 9, '2026-04-13', 'VIREMENT_MENSUEL', 'monthly_run', 'Mensualité - CLT058180 - Marc Tchatchouang', 160.00, 'EUR', 'MENS-CLT058180-20260413', 'monthly_import', '411CLT058180', '5121701', NULL, 'e56c4e8d85fb00ba36818497b233c35981a55c0f5694aaa04a0d0d5d0053c0df', 0, 'Mensualité générée automatiquement - jour planifié: 13', 1, 4, '2026-04-13 18:47:40', '2026-04-13 18:47:40'),
(48, 11, 18, 17, 11, 11, '2026-04-13', 'VERSEMENT', 'manual', 'TEST OPE VERSEMENT', 50000.00, 'EUR', 'LYS02124564', 'manual', '5120401', '411294030444', NULL, '5a4d0ddb5bb3e93901a83d9c2a90c8d31c60301d2590e5917695da5f6ecddd64', 0, 'TEST OPE VERSEMENT', 1, NULL, '2026-04-13 19:07:58', '2026-04-13 19:07:58'),
(49, 11, 11, 22, 11, 10, '2026-04-13', 'COMMISSION_DE_TRANSFERT', 'manual_pending_debit', 'Débit dû initié - TEST OP DEBIT DU', 1000.00, 'EUR', 'LYS02124-RELQ-20260413212516', 'pending_debit', '411294030444', '706304', '706304', '23ba51117fccad98de783c9f2a2fd3e7cf40096fb6fb3c215e13016517f8081c', 0, 'Exécution d’un reliquat de débit dû 411 #1', 1, NULL, '2026-04-13 21:25:16', '2026-04-13 21:25:16'),
(50, 12, 20, 18, 12, 12, '2026-04-13', 'VIREMENT', 'manual', 'TEST OPE DEBIT DU OPE', 500.00, 'EUR', 'YON12545451', 'manual', '411656519872', '5121701', NULL, '117d7be44a234f90109ef1987c500f86f6d74a6126e19dddca76e9150804a416', 0, 'TEST OPE DEBIT DU OPE', 1, NULL, '2026-04-13 23:29:36', '2026-04-13 23:29:36'),
(53, 9, NULL, NULL, NULL, NULL, '2026-04-17', 'ARCHIVE_CLIENT', NULL, '', 79690.00, 'EUR', NULL, NULL, '411CLT058180', '5120101', NULL, NULL, 0, NULL, 1, NULL, '2026-04-17 20:05:49', NULL),
(54, 2, NULL, NULL, NULL, NULL, '2026-04-17', 'ARCHIVE_CLIENT', NULL, 'Archivage client - transfert 411 vers 512', 7895.00, 'EUR', NULL, NULL, '5120101', '411CLT0002', NULL, NULL, 0, NULL, 1, NULL, '2026-04-17 20:59:11', '2026-04-17 20:59:11'),
(55, 2, NULL, NULL, NULL, NULL, '2026-04-17', 'RESTORE_CLIENT', NULL, 'Réactivation client - restitution 512 vers 411', 7895.00, 'EUR', NULL, NULL, '411CLT0002', '5120101', NULL, NULL, 0, NULL, 1, NULL, '2026-04-17 21:35:35', '2026-04-17 21:35:35'),
(56, 10, NULL, NULL, NULL, NULL, '2026-04-17', 'ARCHIVE_CLIENT', NULL, 'Archivage client - transfert 411 vers 512', 59750.00, 'EUR', NULL, NULL, '411337819647', '5120101', NULL, NULL, 0, NULL, 1, NULL, '2026-04-17 21:54:45', '2026-04-17 21:54:45');

-- --------------------------------------------------------

--
-- Structure de la table `pending_client_debits`
--

CREATE TABLE `pending_client_debits` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `client_code` varchar(50) DEFAULT NULL,
  `client_account_code` varchar(50) NOT NULL,
  `source_operation_id` int(11) DEFAULT NULL,
  `trigger_type` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `currency_code` varchar(10) DEFAULT 'EUR',
  `operation_date` date DEFAULT NULL,
  `initial_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `executed_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `priority_level` varchar(20) NOT NULL DEFAULT 'normal',
  `notes` text DEFAULT NULL,
  `last_notification_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `source_module` varchar(100) DEFAULT NULL,
  `source_entity_type` varchar(100) DEFAULT NULL,
  `source_entity_id` int(11) DEFAULT NULL,
  `operation_type_code` varchar(100) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `operation_type_id` int(11) DEFAULT NULL,
  `linked_bank_account_id` int(11) DEFAULT NULL,
  `debit_account_code` varchar(50) DEFAULT NULL,
  `credit_account_code` varchar(50) DEFAULT NULL,
  `service_account_code` varchar(50) DEFAULT NULL,
  `operation_label` varchar(255) DEFAULT NULL,
  `operation_reference` varchar(150) DEFAULT NULL,
  `last_notification_sent_at` datetime DEFAULT NULL,
  `settled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `pending_client_debits`
--

INSERT INTO `pending_client_debits` (`id`, `client_id`, `client_code`, `client_account_code`, `source_operation_id`, `trigger_type`, `label`, `currency_code`, `operation_date`, `initial_amount`, `executed_amount`, `remaining_amount`, `status`, `priority_level`, `notes`, `last_notification_at`, `resolved_at`, `created_by`, `created_at`, `updated_at`, `source_module`, `source_entity_type`, `source_entity_id`, `operation_type_code`, `service_id`, `operation_type_id`, `linked_bank_account_id`, `debit_account_code`, `credit_account_code`, `service_account_code`, `operation_label`, `operation_reference`, `last_notification_sent_at`, `settled_at`) VALUES
(1, 11, '294030444', '411294030444', NULL, 'COMMISSION_DE_TRANSFERT', 'TEST OP DEBIT DU', 'EUR', NULL, 1000.00, 1000.00, 0.00, 'settled', 'normal', 'Créé automatiquement suite à insuffisance de solde 411', '2026-04-13 19:07:59', NULL, 1, '2026-04-13 18:57:30', '2026-04-13 21:25:16', 'manual', 'operation', NULL, 'COMMISSION_DE_TRANSFERT', 11, 22, 10, '411294030444', '706304', '706304', 'TEST OP DEBIT DU', 'LYS02124', '2026-04-13 19:07:59', '2026-04-13 21:25:16'),
(2, 12, '656519872', '411656519872', 50, 'VIREMENT', 'TEST OPE DEBIT DU OPE', 'EUR', NULL, 750.00, 500.00, 250.00, 'partial', 'normal', 'Créé automatiquement suite à insuffisance de solde 411', '2026-04-13 23:29:36', NULL, 1, '2026-04-13 23:29:36', '2026-04-13 23:29:36', 'manual', 'operation', NULL, 'VIREMENT', 20, 18, 50, '411656519872', '5121701', NULL, 'TEST OPE DEBIT DU OPE', 'YON12545451', NULL, NULL),
(3, 12, '656519872', '411656519872', NULL, 'VIREMENT', 'TEST MULTIPLE DEBIT DÛ', 'EUR', NULL, 450.00, 0.00, 450.00, 'pending', 'normal', 'Créé automatiquement suite à insuffisance de solde 411', '2026-04-17 23:37:00', NULL, 1, '2026-04-17 23:37:00', '2026-04-17 23:37:00', 'manual', 'operation', NULL, 'VIREMENT', 20, 18, 12, '411656519872', '5120401', NULL, 'TEST MULTIPLE DEBIT DÛ', 'YON125454511121', NULL, NULL),
(4, 12, '656519872', '411656519872', NULL, 'REGULARISATION', 'TEST MULTIPLE DEBIT DU REGNEG', 'EUR', NULL, 1750.00, 0.00, 1750.00, 'pending', 'normal', 'Créé automatiquement suite à insuffisance de solde 411', '2026-04-17 23:42:20', NULL, 1, '2026-04-17 23:42:20', '2026-04-17 23:42:20', 'manual', 'operation', NULL, 'REGULARISATION', 16, 19, 18, '411656519872', '5120407', NULL, 'TEST MULTIPLE DEBIT DU REGNEG', 'YON12545451_REGNEG', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `pending_client_debit_logs`
--

CREATE TABLE `pending_client_debit_logs` (
  `id` int(11) NOT NULL,
  `pending_debit_id` int(11) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `pending_client_debit_logs`
--

INSERT INTO `pending_client_debit_logs` (`id`, `pending_debit_id`, `action_type`, `old_status`, `new_status`, `amount`, `message`, `created_by`, `created_at`) VALUES
(1, 1, 'create', NULL, 'pending', 1000.00, 'Création d’un débit dû 411', 1, '2026-04-13 18:57:30'),
(2, 1, 'ready', 'pending', 'ready', 1000.00, 'Le compte client est redevenu positif. Débit initiable.', 1, '2026-04-13 19:07:59'),
(3, 1, 'execute', 'ready', 'settled', 1000.00, 'Exécution manuelle du débit dû', 1, '2026-04-13 21:25:16'),
(4, 2, 'create', NULL, 'partial', 250.00, 'Création d’un débit dû 411', 1, '2026-04-13 23:29:36'),
(5, 2, 'attach_operation', NULL, NULL, NULL, 'Opération partielle liée au débit dû', 1, '2026-04-13 23:29:36'),
(6, 3, 'create', NULL, 'pending', 450.00, 'Création d’un débit dû 411', 1, '2026-04-17 23:37:00'),
(7, 4, 'create', NULL, 'pending', 1750.00, 'Création d’un débit dû 411', 1, '2026-04-17 23:42:20');

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
(728, 'admin_manage', 'Administration globale', '2026-03-30 21:39:13'),
(729, 'pending_debits_view', 'Voir les débits dus 411', '2026-04-12 22:01:29'),
(730, 'pending_debits_manage', 'Gérer les débits dus 411', '2026-04-12 22:01:29'),
(731, 'pending_debits_execute', 'Initier les débits dus 411', '2026-04-12 22:01:29'),
(732, 'pending_debits_edit', 'Modifier les débits dus 411', '2026-04-12 22:01:29'),
(733, 'pending_debits_cancel', 'Annuler les débits dus 411', '2026-04-12 22:01:29'),
(734, 'dashboard_view_page', 'Accès au dashboard principal', '2026-04-18 22:29:31'),
(735, 'analytics_view_page', 'Accès aux analyses et tableaux de bord avancés', '2026-04-18 22:29:31'),
(736, 'global_search_view_page', 'Accès à la recherche globale', '2026-04-18 22:29:31'),
(737, 'clients_view_page', 'Accès à la liste des clients', '2026-04-18 22:29:31'),
(738, 'client_view_page', 'Accès à la fiche client', '2026-04-18 22:29:31'),
(739, 'client_create_page', 'Accès à la création client', '2026-04-18 22:29:31'),
(740, 'client_edit_page', 'Accès à la modification client', '2026-04-18 22:29:31'),
(741, 'clients_archive_page', 'Accès à l’archivage / réactivation client', '2026-04-18 22:29:31'),
(742, 'clients_delete_page', 'Accès à la suppression client', '2026-04-18 22:29:31'),
(743, 'client_accounts_view_page', 'Accès à la liste des comptes clients 411', '2026-04-18 22:29:31'),
(744, 'client_timeline_view_page', 'Accès à la timeline client', '2026-04-18 22:29:31'),
(745, 'clients_import_page', 'Accès à l’import des clients', '2026-04-18 22:29:31'),
(746, 'clients_import', 'Importer des clients', '2026-04-18 22:29:31'),
(747, 'operations_view_page', 'Accès à la liste des opérations', '2026-04-18 22:29:31'),
(748, 'operation_view_page', 'Accès au détail d’une opération', '2026-04-18 22:29:31'),
(749, 'operation_create_page', 'Accès à la création d’une opération', '2026-04-18 22:29:31'),
(750, 'operation_edit_page', 'Accès à la modification d’une opération', '2026-04-18 22:29:31'),
(751, 'operation_delete_page', 'Accès à la suppression d’une opération', '2026-04-18 22:29:31'),
(752, 'manual_actions_create_page', 'Accès aux actions manuelles', '2026-04-18 22:29:31'),
(753, 'operations_monthly_run_page', 'Accès au lancement des opérations mensuelles clients', '2026-04-18 22:29:31'),
(754, 'operations_monthly_run', 'Lancer un traitement mensuel des opérations', '2026-04-18 22:29:31'),
(755, 'imports_upload_page', 'Accès à l’upload d’import', '2026-04-18 22:29:31'),
(756, 'imports_preview_page', 'Accès à la prévisualisation d’import', '2026-04-18 22:29:31'),
(757, 'imports_validate_page', 'Accès à la validation d’import', '2026-04-18 22:29:31'),
(758, 'imports_validate_batch_page', 'Accès à la validation d’un batch import', '2026-04-18 22:29:31'),
(759, 'imports_journal_page', 'Accès au journal des imports', '2026-04-18 22:29:31'),
(760, 'imports_mapping_page', 'Accès au mapping des imports', '2026-04-18 22:29:31'),
(761, 'imports_rejected_rows_page', 'Accès aux lignes rejetées', '2026-04-18 22:29:31'),
(762, 'imports_correct_rejected_row_page', 'Accès à la correction d’une ligne rejetée', '2026-04-18 22:29:31'),
(763, 'imports_validate_batch', 'Valider un batch import', '2026-04-18 22:29:31'),
(764, 'imports_mapping_manage', 'Gérer le mapping des imports', '2026-04-18 22:29:31'),
(765, 'imports_correct_rejected_rows', 'Corriger les lignes rejetées', '2026-04-18 22:29:31'),
(766, 'monthly_runs_list_page', 'Accès à la liste des runs mensuels', '2026-04-18 22:29:31'),
(767, 'monthly_run_view_page', 'Accès au détail d’un run mensuel', '2026-04-18 22:29:31'),
(768, 'monthly_run_execute_page', 'Accès à l’exécution d’un run mensuel', '2026-04-18 22:29:31'),
(769, 'monthly_run_cancel_page', 'Accès à l’annulation d’un run mensuel', '2026-04-18 22:29:31'),
(770, 'monthly_payments_import_page', 'Accès à l’import des mensualités', '2026-04-18 22:29:31'),
(771, 'monthly_payments_preview_page', 'Accès à la prévisualisation des mensualités', '2026-04-18 22:29:31'),
(772, 'monthly_payments_validate_page', 'Accès à la validation des mensualités', '2026-04-18 22:29:31'),
(773, 'monthly_runs_view', 'Consulter les runs mensuels', '2026-04-18 22:29:31'),
(774, 'monthly_run_execute', 'Exécuter un run mensuel', '2026-04-18 22:29:31'),
(775, 'monthly_run_cancel', 'Annuler un run mensuel', '2026-04-18 22:29:31'),
(776, 'monthly_payments_import', 'Importer des mensualités', '2026-04-18 22:29:31'),
(777, 'monthly_payments_validate', 'Valider des mensualités', '2026-04-18 22:29:31'),
(778, 'pending_debits_view_page', 'Accès à la liste des débits dus', '2026-04-18 22:29:31'),
(779, 'pending_debit_view_page', 'Accès au détail d’un débit dû', '2026-04-18 22:29:31'),
(780, 'pending_debit_edit_page', 'Accès à la modification d’un débit dû', '2026-04-18 22:29:31'),
(781, 'pending_debit_execute_page', 'Accès à l’exécution d’un débit dû', '2026-04-18 22:29:31'),
(782, 'pending_debit_cancel_page', 'Accès à l’annulation d’un débit dû', '2026-04-18 22:29:31'),
(783, 'treasury_view_page', 'Accès à la liste des comptes de trésorerie', '2026-04-18 22:29:31'),
(784, 'treasury_create_page', 'Accès à la création d’un compte 512', '2026-04-18 22:29:31'),
(785, 'treasury_edit_page', 'Accès à la modification d’un compte 512', '2026-04-18 22:29:31'),
(786, 'treasury_view_detail_page', 'Accès à la fiche d’un compte 512', '2026-04-18 22:29:31'),
(787, 'treasury_archive_page', 'Accès à l’archivage / réactivation d’un compte 512', '2026-04-18 22:29:31'),
(788, 'treasury_import_page', 'Accès à l’import des comptes 512', '2026-04-18 22:29:31'),
(789, 'bank_accounts_view_page', 'Accès à la vue des comptes bancaires', '2026-04-18 22:29:31'),
(790, 'treasury_service_accounts_page', 'Accès au lien trésorerie / comptes service', '2026-04-18 22:29:31'),
(791, 'treasury_archive', 'Archiver / réactiver un compte 512', '2026-04-18 22:29:31'),
(792, 'service_accounts_manage_page', 'Accès à la gestion des comptes de service 706', '2026-04-18 22:29:31'),
(793, 'service_accounts_create_page', 'Accès à la création d’un compte 706', '2026-04-18 22:29:31'),
(794, 'service_accounts_edit_page', 'Accès à la modification d’un compte 706', '2026-04-18 22:29:31'),
(795, 'service_accounts_view_page', 'Accès à la fiche d’un compte 706', '2026-04-18 22:29:31'),
(796, 'service_accounts_archive_page', 'Accès à l’archivage d’un compte 706', '2026-04-18 22:29:31'),
(797, 'service_accounts_import_page', 'Accès à l’import des comptes 706', '2026-04-18 22:29:31'),
(798, 'service_accounts_create', 'Créer un compte 706', '2026-04-18 22:29:31'),
(799, 'service_accounts_edit', 'Modifier un compte 706', '2026-04-18 22:29:31'),
(800, 'service_accounts_archive', 'Archiver un compte 706', '2026-04-18 22:29:31'),
(801, 'service_accounts_import', 'Importer des comptes 706', '2026-04-18 22:29:31'),
(802, 'statements_view_page', 'Accès au module des relevés', '2026-04-18 22:29:31'),
(803, 'account_statements_view_page', 'Accès aux relevés de comptes', '2026-04-18 22:29:31'),
(804, 'client_statement_view_page', 'Accès au relevé client', '2026-04-18 22:29:31'),
(805, 'client_profiles_view_page', 'Accès aux profils clients', '2026-04-18 22:29:31'),
(806, 'bulk_statement_export_page', 'Accès à l’export groupé de relevés', '2026-04-18 22:29:31'),
(807, 'generate_statement_pdf_page', 'Accès à la génération PDF de relevé', '2026-04-18 22:29:31'),
(808, 'generate_bulk_pdf_page', 'Accès à la génération PDF en masse', '2026-04-18 22:29:31'),
(809, 'client_profiles_export', 'Exporter les profils clients', '2026-04-18 22:29:31'),
(810, 'bulk_statement_export', 'Exporter des relevés en masse', '2026-04-18 22:29:31'),
(811, 'notifications_view_page', 'Accès aux notifications', '2026-04-18 22:29:31'),
(812, 'support_requests_view_page', 'Accès aux demandes support', '2026-04-18 22:29:31'),
(813, 'support_request_create_page', 'Accès à la création d’une demande support', '2026-04-18 22:29:31'),
(814, 'support_manage_page', 'Accès à la gestion des demandes support', '2026-04-18 22:29:31'),
(815, 'notifications_view', 'Consulter les notifications', '2026-04-18 22:29:31'),
(816, 'support_request_create', 'Créer une demande support', '2026-04-18 22:29:31'),
(817, 'admin_functional_dashboard_view_page', 'Accès au dashboard fonctionnel', '2026-04-18 22:29:31'),
(818, 'manage_services_page', 'Accès à la gestion des services', '2026-04-18 22:29:31'),
(819, 'create_service_page', 'Accès à la création d’un service', '2026-04-18 22:29:31'),
(820, 'edit_service_page', 'Accès à la modification d’un service', '2026-04-18 22:29:31'),
(821, 'delete_service_page', 'Accès à la suppression d’un service', '2026-04-18 22:29:31'),
(822, 'manage_operation_types_page', 'Accès à la gestion des types d’opérations', '2026-04-18 22:29:31'),
(823, 'create_operation_type_page', 'Accès à la création d’un type d’opération', '2026-04-18 22:29:31'),
(824, 'edit_operation_type_page', 'Accès à la modification d’un type d’opération', '2026-04-18 22:29:31'),
(825, 'delete_operation_type_page', 'Accès à la suppression d’un type d’opération', '2026-04-18 22:29:31'),
(826, 'manage_accounts_page', 'Accès à la gestion fonctionnelle des comptes', '2026-04-18 22:29:31'),
(827, 'manage_accounting_rules_page', 'Accès à la gestion des règles comptables', '2026-04-18 22:29:31'),
(828, 'accounting_rule_create_page', 'Accès à la création d’une règle comptable', '2026-04-18 22:29:31'),
(829, 'accounting_rule_edit_page', 'Accès à la modification d’une règle comptable', '2026-04-18 22:29:31'),
(830, 'accounting_rule_delete_page', 'Accès à la suppression d’une règle comptable', '2026-04-18 22:29:31'),
(831, 'accounting_rule_view_page', 'Accès à la fiche d’une règle comptable', '2026-04-18 22:29:31'),
(832, 'accounting_balance_audit_page', 'Accès à l’audit des équilibres comptables', '2026-04-18 22:29:31'),
(833, 'catalogs_manage_page', 'Accès à la gestion des catalogues', '2026-04-18 22:29:31'),
(834, 'services_create', 'Créer un service', '2026-04-18 22:29:31'),
(835, 'services_edit', 'Modifier un service', '2026-04-18 22:29:31'),
(836, 'services_delete', 'Supprimer un service', '2026-04-18 22:29:31'),
(837, 'operation_types_create', 'Créer un type d’opération', '2026-04-18 22:29:31'),
(838, 'operation_types_edit', 'Modifier un type d’opération', '2026-04-18 22:29:31'),
(839, 'operation_types_delete', 'Supprimer un type d’opération', '2026-04-18 22:29:31'),
(840, 'accounts_manage', 'Gérer les comptes fonctionnels', '2026-04-18 22:29:31'),
(841, 'accounting_rules_manage', 'Gérer les règles comptables', '2026-04-18 22:29:31'),
(842, 'accounting_rules_create', 'Créer une règle comptable', '2026-04-18 22:29:31'),
(843, 'accounting_rules_edit', 'Modifier une règle comptable', '2026-04-18 22:29:31'),
(844, 'accounting_rules_delete', 'Supprimer une règle comptable', '2026-04-18 22:29:31'),
(845, 'accounting_balance_audit_view', 'Consulter l’audit comptable', '2026-04-18 22:29:31'),
(846, 'catalogs_manage', 'Gérer les catalogues', '2026-04-18 22:29:31'),
(847, 'admin_dashboard_view_page', 'Accès au dashboard administration', '2026-04-18 22:29:31'),
(848, 'admin_users_manage_page', 'Accès à la gestion des utilisateurs', '2026-04-18 22:29:31'),
(849, 'user_create_page', 'Accès à la création d’un utilisateur', '2026-04-18 22:29:31'),
(850, 'user_edit_page', 'Accès à la modification d’un utilisateur', '2026-04-18 22:29:31'),
(851, 'user_delete_page', 'Accès à la suppression d’un utilisateur', '2026-04-18 22:29:31'),
(852, 'admin_roles_manage_page', 'Accès à la gestion des rôles', '2026-04-18 22:29:31'),
(853, 'roles_view_page', 'Accès à la liste des rôles', '2026-04-18 22:29:31'),
(854, 'access_matrix_manage_page', 'Accès à la matrice des accès', '2026-04-18 22:29:31'),
(855, 'user_logs_view_page', 'Accès aux logs utilisateurs', '2026-04-18 22:29:31'),
(856, 'audit_logs_view_page', 'Accès à l’audit détaillé', '2026-04-18 22:29:31'),
(857, 'intelligence_center_view_page', 'Accès au centre d’intelligence', '2026-04-18 22:29:31'),
(858, 'settings_manage_page', 'Accès aux paramètres', '2026-04-18 22:29:31'),
(859, 'statuses_manage_page', 'Accès à la gestion des statuts', '2026-04-18 22:29:31'),
(860, 'categories_manage_page', 'Accès à la gestion des catégories', '2026-04-18 22:29:31'),
(861, 'users_create', 'Créer un utilisateur', '2026-04-18 22:29:31'),
(862, 'users_edit', 'Modifier un utilisateur', '2026-04-18 22:29:31'),
(863, 'users_delete', 'Supprimer un utilisateur', '2026-04-18 22:29:31'),
(864, 'roles_view', 'Consulter les rôles', '2026-04-18 22:29:31'),
(865, 'access_matrix_manage', 'Gérer la matrice des accès', '2026-04-18 22:29:31'),
(866, 'audit_logs_view', 'Consulter l’audit détaillé', '2026-04-18 22:29:31'),
(867, 'intelligence_center_view', 'Consulter le centre d’intelligence', '2026-04-18 22:29:31'),
(868, 'categories_manage', 'Gérer les catégories', '2026-04-18 22:29:31');

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
(1, 698),
(1, 699),
(1, 700),
(1, 701),
(1, 702),
(1, 703),
(1, 704),
(1, 705),
(1, 706),
(1, 707),
(1, 708),
(1, 709),
(1, 710),
(1, 711),
(1, 712),
(1, 713),
(1, 714),
(1, 715),
(1, 716),
(1, 717),
(1, 718),
(1, 719),
(1, 720),
(1, 721),
(1, 722),
(1, 723),
(1, 724),
(1, 725),
(1, 726),
(1, 727),
(1, 728),
(1, 729),
(1, 730),
(1, 731),
(1, 732),
(1, 733),
(1, 734),
(1, 735),
(1, 736),
(1, 737),
(1, 738),
(1, 739),
(1, 740),
(1, 741),
(1, 742),
(1, 743),
(1, 744),
(1, 745),
(1, 746),
(1, 747),
(1, 748),
(1, 749),
(1, 750),
(1, 751),
(1, 752),
(1, 753),
(1, 754),
(1, 755),
(1, 756),
(1, 757),
(1, 758),
(1, 759),
(1, 760),
(1, 761),
(1, 762),
(1, 763),
(1, 764),
(1, 765),
(1, 766),
(1, 767),
(1, 768),
(1, 769),
(1, 770),
(1, 771),
(1, 772),
(1, 773),
(1, 774),
(1, 775),
(1, 776),
(1, 777),
(1, 778),
(1, 779),
(1, 780),
(1, 781),
(1, 782),
(1, 783),
(1, 784),
(1, 785),
(1, 786),
(1, 787),
(1, 788),
(1, 789),
(1, 790),
(1, 791),
(1, 792),
(1, 793),
(1, 794),
(1, 795),
(1, 796),
(1, 797),
(1, 798),
(1, 799),
(1, 800),
(1, 801),
(1, 802),
(1, 803),
(1, 804),
(1, 805),
(1, 806),
(1, 807),
(1, 808),
(1, 809),
(1, 810),
(1, 811),
(1, 812),
(1, 813),
(1, 814),
(1, 815),
(1, 816),
(1, 817),
(1, 818),
(1, 819),
(1, 820),
(1, 821),
(1, 822),
(1, 823),
(1, 824),
(1, 825),
(1, 826),
(1, 827),
(1, 828),
(1, 829),
(1, 830),
(1, 831),
(1, 832),
(1, 833),
(1, 834),
(1, 835),
(1, 836),
(1, 837),
(1, 838),
(1, 839),
(1, 840),
(1, 841),
(1, 842),
(1, 843),
(1, 844),
(1, 845),
(1, 846),
(1, 847),
(1, 848),
(1, 849),
(1, 850),
(1, 851),
(1, 852),
(1, 853),
(1, 854),
(1, 855),
(1, 856),
(1, 857),
(1, 858),
(1, 859),
(1, 860),
(1, 861),
(1, 862),
(1, 863),
(1, 864),
(1, 865),
(1, 866),
(1, 867),
(1, 868),
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
(3, 729),
(3, 730),
(3, 731),
(3, 732),
(3, 733),
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
(25, 729),
(25, 730),
(25, 731),
(25, 732),
(25, 733),
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
(26, 729),
(26, 731),
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
(1, NULL, 1, 0, '706101', 'FRAIS DE SERVICES AVI', 'FRAIS DE SERVICE', 'France', 'France', 3, 1, 1, 6250.00, '2026-03-28 23:53:57', '2026-04-13 23:29:37'),
(2, NULL, 1, 0, '706102', 'FRAIS DE GESTION', 'FRAIS BANCAIRES', 'International', 'International', 3, 1, 1, 15000.00, '2026-03-28 23:53:57', '2026-04-13 23:29:37'),
(3, NULL, 1, 0, '706103', 'COMMISSION DE TRANSFERT', 'VIREMENT EXCEPTIONEL', 'International', 'International', 3, 1, 1, 0.00, '2026-03-28 23:53:57', '2026-04-13 23:29:37'),
(4, NULL, 1, 0, '706104', 'FRAIS DE SERVICE ATS', 'FRAIS DE SERVICE', 'France', 'France', 3, 1, 1, 175.00, '2026-03-28 23:53:57', '2026-04-13 23:29:37'),
(5, NULL, 1, 0, '706105', 'CA PLACEMENT', 'VIREMENT INTERNE', 'International', 'International', 3, 1, 1, 0.00, '2026-03-28 23:53:57', '2026-04-13 23:29:37'),
(6, NULL, 1, 0, '706106', 'CA DIVERS', 'VIREMENT INTERNE', 'International', 'International', 3, 1, 1, 0.00, '2026-03-28 23:53:57', '2026-04-13 23:29:37'),
(31, NULL, 1, 10, '706', 'Prestations de services', NULL, NULL, NULL, 1, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(32, 31, 2, 20, '7061', 'FRAIS DE SERVICES AVI', 'FRAIS_DE_SERVICE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(33, 31, 2, 30, '7062', 'FRAIS DE GESTION', 'FRAIS_BANCAIRES', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(34, 31, 2, 40, '7063', 'COMMISSION DE TRANSFERT', 'VIREMENT_EXCEPTIONEL', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(35, 31, 2, 50, '7064', 'CA DIVERS', 'VIREMENT_INTERNE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(36, 31, 2, 60, '7065', 'FRAIS DE SERVICE ATS', 'FRAIS_DE_SERVICE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(37, 31, 2, 70, '7066', 'CA PLACEMENT', 'VIREMENT_INTERNE', NULL, NULL, 2, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(39, 32, 3, 110, '70611', 'FRAIS DE SERVICE AVI ALLEMAGNE', 'FRAIS_DE_SERVICE', 'Allemagne', NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(40, 32, 3, 120, '70612', 'FRAIS DE SERVICES AVI BELGIQUE', 'FRAIS_DE_SERVICE', 'Belgique', NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(41, 32, 3, 130, '70613', 'FRAIS DE SERVICES AVI FRANCE', 'FRAIS_DE_SERVICE', 'France', NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(42, 35, 3, 410, '70641', 'CA DEBOURS LOGEMENT', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(43, 35, 3, 420, '70642', 'CA DEBOURS ASSURANCE', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(44, 35, 3, 430, '70643', 'FRAIS DEBOURS MICROFINANCE', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(45, 35, 3, 440, '70644', 'CA COURTAGE PRÊT', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(46, 35, 3, 450, '70645', 'CA LOGEMENT', 'VIREMENT_INTERNE', NULL, NULL, 3, 0, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(54, 39, 4, 1101, '7061101', 'FRAIS DE SERVICE AVI ALLEMAGNE-France', 'FRAIS_DE_SERVICE', 'Allemagne', 'France', 4, 1, 1, 200.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(55, 39, 4, 1102, '7061102', 'FRAIS DE SERVICE AVI ALLEMAGNE-Allemagne', 'FRAIS_DE_SERVICE', 'Allemagne', 'Allemagne', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(56, 39, 4, 1103, '7061103', 'FRAIS DE SERVICE AVI ALLEMAGNE-Belgique', 'FRAIS_DE_SERVICE', 'Allemagne', 'Belgique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(57, 39, 4, 1104, '7061104', 'FRAIS DE SERVICE AVI ALLEMAGNE-Cameroun', 'FRAIS_DE_SERVICE', 'Allemagne', 'Cameroun', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(58, 39, 4, 1105, '7061105', 'FRAIS DE SERVICE AVI ALLEMAGNE-Sénégal', 'FRAIS_DE_SERVICE', 'Allemagne', 'Sénégal', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(59, 39, 4, 1106, '7061106', 'FRAIS DE SERVICE AVI ALLEMAGNE-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', 'Allemagne', 'Côte d\'Ivoire', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(60, 39, 4, 1107, '7061107', 'FRAIS DE SERVICE AVI ALLEMAGNE-Benin', 'FRAIS_DE_SERVICE', 'Allemagne', 'Benin', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(61, 39, 4, 1108, '7061108', 'FRAIS DE SERVICE AVI ALLEMAGNE-Burkina Faso', 'FRAIS_DE_SERVICE', 'Allemagne', 'Burkina Faso', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(62, 39, 4, 1109, '7061109', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Brazzaville', 'FRAIS_DE_SERVICE', 'Allemagne', 'Congo Brazzaville', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(63, 39, 4, 1110, '7061110', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Kinshasa', 'FRAIS_DE_SERVICE', 'Allemagne', 'Congo Kinshasa', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(64, 39, 4, 1111, '7061111', 'FRAIS DE SERVICE AVI ALLEMAGNE-Gabon', 'FRAIS_DE_SERVICE', 'Allemagne', 'Gabon', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(65, 39, 4, 1112, '7061112', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tchad', 'FRAIS_DE_SERVICE', 'Allemagne', 'Tchad', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(66, 39, 4, 1113, '7061113', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mali', 'FRAIS_DE_SERVICE', 'Allemagne', 'Mali', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(67, 39, 4, 1114, '7061114', 'FRAIS DE SERVICE AVI ALLEMAGNE-Togo', 'FRAIS_DE_SERVICE', 'Allemagne', 'Togo', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(68, 39, 4, 1115, '7061115', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mexique', 'FRAIS_DE_SERVICE', 'Allemagne', 'Mexique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(69, 39, 4, 1116, '7061116', 'FRAIS DE SERVICE AVI ALLEMAGNE-Inde', 'FRAIS_DE_SERVICE', 'Allemagne', 'Inde', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(70, 39, 4, 1117, '7061117', 'FRAIS DE SERVICE AVI ALLEMAGNE-Algérie', 'FRAIS_DE_SERVICE', 'Allemagne', 'Algérie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(71, 39, 4, 1118, '7061118', 'FRAIS DE SERVICE AVI ALLEMAGNE-Guinée', 'FRAIS_DE_SERVICE', 'Allemagne', 'Guinée', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(72, 39, 4, 1119, '7061119', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tunisie', 'FRAIS_DE_SERVICE', 'Allemagne', 'Tunisie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(73, 39, 4, 1120, '7061120', 'FRAIS DE SERVICE AVI ALLEMAGNE-Maroc', 'FRAIS_DE_SERVICE', 'Allemagne', 'Maroc', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(74, 39, 4, 1121, '7061121', 'FRAIS DE SERVICE AVI ALLEMAGNE-Niger', 'FRAIS_DE_SERVICE', 'Allemagne', 'Niger', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(75, 39, 4, 1122, '7061122', 'FRAIS DE SERVICE AVI ALLEMAGNE-Afrique de l\'est', 'FRAIS_DE_SERVICE', 'Allemagne', 'Afrique de l\'est', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(76, 39, 4, 1123, '7061123', 'FRAIS DE SERVICE AVI ALLEMAGNE-Autres pays', 'FRAIS_DE_SERVICE', 'Allemagne', 'Autres pays', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(85, 40, 4, 1201, '7061201', 'FRAIS DE SERVICES AVI BELGIQUE-France', 'FRAIS_DE_SERVICE', 'Belgique', 'France', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(86, 40, 4, 1202, '7061202', 'FRAIS DE SERVICES AVI BELGIQUE-Allemagne', 'FRAIS_DE_SERVICE', 'Belgique', 'Allemagne', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(87, 40, 4, 1203, '7061203', 'FRAIS DE SERVICES AVI BELGIQUE-Belgique', 'FRAIS_DE_SERVICE', 'Belgique', 'Belgique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(88, 40, 4, 1204, '7061204', 'FRAIS DE SERVICES AVI BELGIQUE-Cameroun', 'FRAIS_DE_SERVICE', 'Belgique', 'Cameroun', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(89, 40, 4, 1205, '7061205', 'FRAIS DE SERVICES AVI BELGIQUE-Sénégal', 'FRAIS_DE_SERVICE', 'Belgique', 'Sénégal', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(90, 40, 4, 1206, '7061206', 'FRAIS DE SERVICES AVI BELGIQUE-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', 'Belgique', 'Côte d\'Ivoire', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(91, 40, 4, 1207, '7061207', 'FRAIS DE SERVICES AVI BELGIQUE-Benin', 'FRAIS_DE_SERVICE', 'Belgique', 'Benin', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(92, 40, 4, 1208, '7061208', 'FRAIS DE SERVICES AVI BELGIQUE-Burkina Faso', 'FRAIS_DE_SERVICE', 'Belgique', 'Burkina Faso', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(93, 40, 4, 1209, '7061209', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Brazzaville', 'FRAIS_DE_SERVICE', 'Belgique', 'Congo Brazzaville', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(94, 40, 4, 1210, '7061210', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Kinshasa', 'FRAIS_DE_SERVICE', 'Belgique', 'Congo Kinshasa', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(95, 40, 4, 1211, '7061211', 'FRAIS DE SERVICES AVI BELGIQUE-Gabon', 'FRAIS_DE_SERVICE', 'Belgique', 'Gabon', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(96, 40, 4, 1212, '7061212', 'FRAIS DE SERVICES AVI BELGIQUE-Tchad', 'FRAIS_DE_SERVICE', 'Belgique', 'Tchad', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(97, 40, 4, 1213, '7061213', 'FRAIS DE SERVICES AVI BELGIQUE-Mali', 'FRAIS_DE_SERVICE', 'Belgique', 'Mali', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(98, 40, 4, 1214, '7061214', 'FRAIS DE SERVICES AVI BELGIQUE-Togo', 'FRAIS_DE_SERVICE', 'Belgique', 'Togo', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(99, 40, 4, 1215, '7061215', 'FRAIS DE SERVICES AVI BELGIQUE-Mexique', 'FRAIS_DE_SERVICE', 'Belgique', 'Mexique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(100, 40, 4, 1216, '7061216', 'FRAIS DE SERVICES AVI BELGIQUE-Inde', 'FRAIS_DE_SERVICE', 'Belgique', 'Inde', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(101, 40, 4, 1217, '7061217', 'FRAIS DE SERVICES AVI BELGIQUE-Algérie', 'FRAIS_DE_SERVICE', 'Belgique', 'Algérie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(102, 40, 4, 1218, '7061218', 'FRAIS DE SERVICES AVI BELGIQUE-Guinée', 'FRAIS_DE_SERVICE', 'Belgique', 'Guinée', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(103, 40, 4, 1219, '7061219', 'FRAIS DE SERVICES AVI BELGIQUE-Tunisie', 'FRAIS_DE_SERVICE', 'Belgique', 'Tunisie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(104, 40, 4, 1220, '7061220', 'FRAIS DE SERVICES AVI BELGIQUE-Maroc', 'FRAIS_DE_SERVICE', 'Belgique', 'Maroc', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(105, 40, 4, 1221, '7061221', 'FRAIS DE SERVICES AVI BELGIQUE-Niger', 'FRAIS_DE_SERVICE', 'Belgique', 'Niger', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(106, 40, 4, 1222, '7061222', 'FRAIS DE SERVICES AVI BELGIQUE-Afrique de l\'est', 'FRAIS_DE_SERVICE', 'Belgique', 'Afrique de l\'est', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(107, 40, 4, 1223, '7061223', 'FRAIS DE SERVICES AVI BELGIQUE-Autres pays', 'FRAIS_DE_SERVICE', 'Belgique', 'Autres pays', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(116, 41, 4, 1301, '7061301', 'FRAIS DE SERVICES AVI France-France', 'FRAIS_DE_SERVICE', 'France', 'France', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(117, 41, 4, 1302, '7061302', 'FRAIS DE SERVICES AVI France-Allemagne', 'FRAIS_DE_SERVICE', 'France', 'Allemagne', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(118, 41, 4, 1303, '7061303', 'FRAIS DE SERVICES AVI France-Belgique', 'FRAIS_DE_SERVICE', 'France', 'Belgique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(119, 41, 4, 1304, '7061304', 'FRAIS DE SERVICES AVI France-Cameroun', 'FRAIS_DE_SERVICE', 'France', 'Cameroun', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(120, 41, 4, 1305, '7061305', 'FRAIS DE SERVICES AVI France-Sénégal', 'FRAIS_DE_SERVICE', 'France', 'Sénégal', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(121, 41, 4, 1306, '7061306', 'FRAIS DE SERVICES AVI France-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', 'France', 'Côte d\'Ivoire', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(122, 41, 4, 1307, '7061307', 'FRAIS DE SERVICES AVI France-Benin', 'FRAIS_DE_SERVICE', 'France', 'Benin', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(123, 41, 4, 1308, '7061308', 'FRAIS DE SERVICES AVI France-Burkina Faso', 'FRAIS_DE_SERVICE', 'France', 'Burkina Faso', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(124, 41, 4, 1309, '7061309', 'FRAIS DE SERVICES AVI France-Congo Brazzaville', 'FRAIS_DE_SERVICE', 'France', 'Congo Brazzaville', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(125, 41, 4, 1310, '7061310', 'FRAIS DE SERVICES AVI France-Congo Kinshasa', 'FRAIS_DE_SERVICE', 'France', 'Congo Kinshasa', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(126, 41, 4, 1311, '7061311', 'FRAIS DE SERVICES AVI France-Gabon', 'FRAIS_DE_SERVICE', 'France', 'Gabon', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(127, 41, 4, 1312, '7061312', 'FRAIS DE SERVICES AVI France-Tchad', 'FRAIS_DE_SERVICE', 'France', 'Tchad', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(128, 41, 4, 1313, '7061313', 'FRAIS DE SERVICES AVI France-Mali', 'FRAIS_DE_SERVICE', 'France', 'Mali', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(129, 41, 4, 1314, '7061314', 'FRAIS DE SERVICES AVI France-Togo', 'FRAIS_DE_SERVICE', 'France', 'Togo', 4, 1, 1, 150.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(130, 41, 4, 1315, '7061315', 'FRAIS DE SERVICES AVI France-Mexique', 'FRAIS_DE_SERVICE', 'France', 'Mexique', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(131, 41, 4, 1316, '7061316', 'FRAIS DE SERVICES AVI France-Inde', 'FRAIS_DE_SERVICE', 'France', 'Inde', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(132, 41, 4, 1317, '7061317', 'FRAIS DE SERVICES AVI France-Algérie', 'FRAIS_DE_SERVICE', 'France', 'Algérie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(133, 41, 4, 1318, '7061318', 'FRAIS DE SERVICES AVI France-Guinée', 'FRAIS_DE_SERVICE', 'France', 'Guinée', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(134, 41, 4, 1319, '7061319', 'FRAIS DE SERVICES AVI France-Tunisie', 'FRAIS_DE_SERVICE', 'France', 'Tunisie', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(135, 41, 4, 1320, '7061320', 'FRAIS DE SERVICES AVI France-Maroc', 'FRAIS_DE_SERVICE', 'France', 'Maroc', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(136, 41, 4, 1321, '7061321', 'FRAIS DE SERVICES AVI France-Niger', 'FRAIS_DE_SERVICE', 'France', 'Niger', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(137, 41, 4, 1322, '7061322', 'FRAIS DE SERVICES AVI France-Afrique de l\'est', 'FRAIS_DE_SERVICE', 'France', 'Afrique de l\'est', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(138, 41, 4, 1323, '7061323', 'FRAIS DE SERVICES AVI France-Autres pays', 'FRAIS_DE_SERVICE', 'France', 'Autres pays', 4, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(147, 33, 3, 201, '706201', 'FRAIS DE GESTION-France', 'FRAIS_BANCAIRES', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(148, 33, 3, 202, '706202', 'FRAIS DE GESTION-Allemagne', 'FRAIS_BANCAIRES', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(149, 33, 3, 203, '706203', 'FRAIS DE GESTION-Belgique', 'FRAIS_BANCAIRES', NULL, 'Belgique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(150, 33, 3, 204, '706204', 'FRAIS DE GESTION-Cameroun', 'FRAIS_BANCAIRES', NULL, 'Cameroun', 3, 1, 1, 100.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(151, 33, 3, 205, '706205', 'FRAIS DE GESTION-Sénégal', 'FRAIS_BANCAIRES', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(152, 33, 3, 206, '706206', 'FRAIS DE GESTION-Côte d\'Ivoire', 'FRAIS_BANCAIRES', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(153, 33, 3, 207, '706207', 'FRAIS DE GESTION-Benin', 'FRAIS_BANCAIRES', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(154, 33, 3, 208, '706208', 'FRAIS DE GESTION-Burkina Faso', 'FRAIS_BANCAIRES', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(155, 33, 3, 209, '706209', 'FRAIS DE GESTION-Congo Brazzaville', 'FRAIS_BANCAIRES', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(156, 33, 3, 210, '706210', 'FRAIS DE GESTION-Congo Kinshasa', 'FRAIS_BANCAIRES', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(157, 33, 3, 211, '706211', 'FRAIS DE GESTION-Gabon', 'FRAIS_BANCAIRES', NULL, 'Gabon', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(158, 33, 3, 212, '706212', 'FRAIS DE GESTION-Tchad', 'FRAIS_BANCAIRES', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(159, 33, 3, 213, '706213', 'FRAIS DE GESTION-Mali', 'FRAIS_BANCAIRES', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(160, 33, 3, 214, '706214', 'FRAIS DE GESTION-Togo', 'FRAIS_BANCAIRES', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(161, 33, 3, 215, '706215', 'FRAIS DE GESTION-Mexique', 'FRAIS_BANCAIRES', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(162, 33, 3, 216, '706216', 'FRAIS DE GESTION-Inde', 'FRAIS_BANCAIRES', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(163, 33, 3, 217, '706217', 'FRAIS DE GESTION-Algérie', 'FRAIS_BANCAIRES', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(164, 33, 3, 218, '706218', 'FRAIS DE GESTION-Guinée', 'FRAIS_BANCAIRES', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(165, 33, 3, 219, '706219', 'FRAIS DE GESTION-Tunisie', 'FRAIS_BANCAIRES', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(166, 33, 3, 220, '706220', 'FRAIS DE GESTION-Maroc', 'FRAIS_BANCAIRES', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(167, 33, 3, 221, '706221', 'FRAIS DE GESTION-Niger', 'FRAIS_BANCAIRES', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(168, 33, 3, 222, '706222', 'FRAIS DE GESTION-Afrique de l\'est', 'FRAIS_BANCAIRES', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(169, 33, 3, 223, '706223', 'FRAIS DE GESTION-Autres pays', 'FRAIS_BANCAIRES', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(178, 34, 3, 301, '706301', 'COMMISSION DE TRANSFERT-France', 'VIREMENT_EXCEPTIONEL', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(179, 34, 3, 302, '706302', 'COMMISSION DE TRANSFERT-Allemagne', 'VIREMENT_EXCEPTIONEL', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(180, 34, 3, 303, '706303', 'COMMISSION DE TRANSFERT-Belgique', 'VIREMENT_EXCEPTIONEL', NULL, 'Belgique', 3, 1, 1, 200.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(181, 34, 3, 304, '706304', 'COMMISSION DE TRANSFERT-Cameroun', 'VIREMENT_EXCEPTIONEL', NULL, 'Cameroun', 3, 1, 1, 1000.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(182, 34, 3, 305, '706305', 'COMMISSION DE TRANSFERT-Sénégal', 'VIREMENT_EXCEPTIONEL', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(183, 34, 3, 306, '706306', 'COMMISSION DE TRANSFERT-Côte d\'Ivoire', 'VIREMENT_EXCEPTIONEL', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(184, 34, 3, 307, '706307', 'COMMISSION DE TRANSFERT-Benin', 'VIREMENT_EXCEPTIONEL', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(185, 34, 3, 308, '706308', 'COMMISSION DE TRANSFERT-Burkina Faso', 'VIREMENT_EXCEPTIONEL', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(186, 34, 3, 309, '706309', 'COMMISSION DE TRANSFERT-Congo Brazzaville', 'VIREMENT_EXCEPTIONEL', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(187, 34, 3, 310, '706310', 'COMMISSION DE TRANSFERT-Congo Kinshasa', 'VIREMENT_EXCEPTIONEL', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(188, 34, 3, 311, '706311', 'COMMISSION DE TRANSFERT-Gabon', 'VIREMENT_EXCEPTIONEL', NULL, 'Gabon', 3, 1, 1, -100.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(189, 34, 3, 312, '706312', 'COMMISSION DE TRANSFERT-Tchad', 'VIREMENT_EXCEPTIONEL', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(190, 34, 3, 313, '706313', 'COMMISSION DE TRANSFERT-Mali', 'VIREMENT_EXCEPTIONEL', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(191, 34, 3, 314, '706314', 'COMMISSION DE TRANSFERT-Togo', 'VIREMENT_EXCEPTIONEL', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(192, 34, 3, 315, '706315', 'COMMISSION DE TRANSFERT-Mexique', 'VIREMENT_EXCEPTIONEL', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(193, 34, 3, 316, '706316', 'COMMISSION DE TRANSFERT-Inde', 'VIREMENT_EXCEPTIONEL', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(194, 34, 3, 317, '706317', 'COMMISSION DE TRANSFERT-Algérie', 'VIREMENT_EXCEPTIONEL', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(195, 34, 3, 318, '706318', 'COMMISSION DE TRANSFERT-Guinée', 'VIREMENT_EXCEPTIONEL', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(196, 34, 3, 319, '706319', 'COMMISSION DE TRANSFERT-Tunisie', 'VIREMENT_EXCEPTIONEL', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(197, 34, 3, 320, '706320', 'COMMISSION DE TRANSFERT-Maroc', 'VIREMENT_EXCEPTIONEL', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(198, 34, 3, 321, '706321', 'COMMISSION DE TRANSFERT-Niger', 'VIREMENT_EXCEPTIONEL', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(199, 34, 3, 322, '706322', 'COMMISSION DE TRANSFERT-Afrique de l\'est', 'VIREMENT_EXCEPTIONEL', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(200, 34, 3, 323, '706323', 'COMMISSION DE TRANSFERT-Autres pays', 'VIREMENT_EXCEPTIONEL', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(209, 36, 3, 501, '706501', 'FRAIS DE SERVICE ATS-France', 'FRAIS_DE_SERVICE', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(210, 36, 3, 502, '706502', 'FRAIS DE SERVICE ATS-Allemagne', 'FRAIS_DE_SERVICE', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(211, 36, 3, 503, '706503', 'FRAIS DE SERVICE ATS-Belgique', 'FRAIS_DE_SERVICE', NULL, 'Belgique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(212, 36, 3, 504, '706504', 'FRAIS DE SERVICE ATS-Cameroun', 'FRAIS_DE_SERVICE', NULL, 'Cameroun', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(213, 36, 3, 505, '706505', 'FRAIS DE SERVICE ATS-Sénégal', 'FRAIS_DE_SERVICE', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(214, 36, 3, 506, '706506', 'FRAIS DE SERVICE ATS-Côte d\'Ivoire', 'FRAIS_DE_SERVICE', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(215, 36, 3, 507, '706507', 'FRAIS DE SERVICE ATS-Benin', 'FRAIS_DE_SERVICE', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(216, 36, 3, 508, '706508', 'FRAIS DE SERVICE ATS-Burkina Faso', 'FRAIS_DE_SERVICE', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(217, 36, 3, 509, '706509', 'FRAIS DE SERVICE ATS-Congo Brazzaville', 'FRAIS_DE_SERVICE', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(218, 36, 3, 510, '706510', 'FRAIS DE SERVICE ATS-Congo Kinshasa', 'FRAIS_DE_SERVICE', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(219, 36, 3, 511, '706511', 'FRAIS DE SERVICE ATS-Gabon', 'FRAIS_DE_SERVICE', NULL, 'Gabon', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(220, 36, 3, 512, '706512', 'FRAIS DE SERVICE ATS-Tchad', 'FRAIS_DE_SERVICE', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(221, 36, 3, 513, '706513', 'FRAIS DE SERVICE ATS-Mali', 'FRAIS_DE_SERVICE', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(222, 36, 3, 514, '706514', 'FRAIS DE SERVICE ATS-Togo', 'FRAIS_DE_SERVICE', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(223, 36, 3, 515, '706515', 'FRAIS DE SERVICE ATS-Mexique', 'FRAIS_DE_SERVICE', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(224, 36, 3, 516, '706516', 'FRAIS DE SERVICE ATS-Inde', 'FRAIS_DE_SERVICE', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(225, 36, 3, 517, '706517', 'FRAIS DE SERVICE ATS-Algérie', 'FRAIS_DE_SERVICE', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(226, 36, 3, 518, '706518', 'FRAIS DE SERVICE ATS-Guinée', 'FRAIS_DE_SERVICE', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(227, 36, 3, 519, '706519', 'FRAIS DE SERVICE ATS-Tunisie', 'FRAIS_DE_SERVICE', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(228, 36, 3, 520, '706520', 'FRAIS DE SERVICE ATS-Maroc', 'FRAIS_DE_SERVICE', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(229, 36, 3, 521, '706521', 'FRAIS DE SERVICE ATS-Niger', 'FRAIS_DE_SERVICE', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(230, 36, 3, 522, '706522', 'FRAIS DE SERVICE ATS-Afrique de l\'est', 'FRAIS_DE_SERVICE', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(231, 36, 3, 523, '706523', 'FRAIS DE SERVICE ATS-Autres pays', 'FRAIS_DE_SERVICE', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(240, 37, 3, 601, '706601', 'CA PLACEMENT-France', 'VIREMENT_INTERNE', NULL, 'France', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(241, 37, 3, 602, '706602', 'CA PLACEMENT-Allemagne', 'VIREMENT_INTERNE', NULL, 'Allemagne', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(242, 37, 3, 603, '706603', 'CA PLACEMENT-Belgique', 'VIREMENT_INTERNE', NULL, 'Belgique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(243, 37, 3, 604, '706604', 'CA PLACEMENT-Cameroun', 'VIREMENT_INTERNE', NULL, 'Cameroun', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(244, 37, 3, 605, '706605', 'CA PLACEMENT-Sénégal', 'VIREMENT_INTERNE', NULL, 'Sénégal', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(245, 37, 3, 606, '706606', 'CA PLACEMENT-Côte d\'Ivoire', 'VIREMENT_INTERNE', NULL, 'Côte d\'Ivoire', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(246, 37, 3, 607, '706607', 'CA PLACEMENT-Benin', 'VIREMENT_INTERNE', NULL, 'Benin', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(247, 37, 3, 608, '706608', 'CA PLACEMENT-Burkina Faso', 'VIREMENT_INTERNE', NULL, 'Burkina Faso', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(248, 37, 3, 609, '706609', 'CA PLACEMENT-Congo Brazzaville', 'VIREMENT_INTERNE', NULL, 'Congo Brazzaville', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(249, 37, 3, 610, '706610', 'CA PLACEMENT-Congo Kinshasa', 'VIREMENT_INTERNE', NULL, 'Congo Kinshasa', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(250, 37, 3, 611, '706611', 'CA PLACEMENT-Gabon', 'VIREMENT_INTERNE', NULL, 'Gabon', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(251, 37, 3, 612, '706612', 'CA PLACEMENT-Tchad', 'VIREMENT_INTERNE', NULL, 'Tchad', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(252, 37, 3, 613, '706613', 'CA PLACEMENT-Mali', 'VIREMENT_INTERNE', NULL, 'Mali', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(253, 37, 3, 614, '706614', 'CA PLACEMENT-Togo', 'VIREMENT_INTERNE', NULL, 'Togo', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(254, 37, 3, 615, '706615', 'CA PLACEMENT-Mexique', 'VIREMENT_INTERNE', NULL, 'Mexique', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(255, 37, 3, 616, '706616', 'CA PLACEMENT-Inde', 'VIREMENT_INTERNE', NULL, 'Inde', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(256, 37, 3, 617, '706617', 'CA PLACEMENT-Algérie', 'VIREMENT_INTERNE', NULL, 'Algérie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(257, 37, 3, 618, '706618', 'CA PLACEMENT-Guinée', 'VIREMENT_INTERNE', NULL, 'Guinée', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(258, 37, 3, 619, '706619', 'CA PLACEMENT-Tunisie', 'VIREMENT_INTERNE', NULL, 'Tunisie', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(259, 37, 3, 620, '706620', 'CA PLACEMENT-Maroc', 'VIREMENT_INTERNE', NULL, 'Maroc', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(260, 37, 3, 621, '706621', 'CA PLACEMENT-Niger', 'VIREMENT_INTERNE', NULL, 'Niger', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(261, 37, 3, 622, '706622', 'CA PLACEMENT-Afrique de l\'est', 'VIREMENT_INTERNE', NULL, 'Afrique de l\'est', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37'),
(262, 37, 3, 623, '706623', 'CA PLACEMENT-Autres pays', 'VIREMENT_INTERNE', NULL, 'Autres pays', 3, 1, 1, 0.00, '2026-03-29 00:32:50', '2026-04-13 23:29:37');

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
  `updated_at` datetime DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_secondary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `treasury_accounts`
--

INSERT INTO `treasury_accounts` (`id`, `account_code`, `account_label`, `bank_name`, `subsidiary_name`, `zone_code`, `country_label`, `country_type`, `payment_place`, `currency_code`, `opening_balance`, `current_balance`, `is_active`, `created_at`, `updated_at`, `is_primary`, `is_secondary`) VALUES
(1, '5120101', 'Fr_LCL_C - France', 'Fr_LCL_C', 'Studely', 'EU', 'France', 'Filiale', 'Local', 'EUR', 100000.00, 123150.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(2, '5120102', 'Fr_LCL_M - France', 'Fr_LCL_M', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 16470.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(3, '5120103', 'FR_CIC - France', 'FR_CIC', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 200.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(4, '5120104', 'FR_CCOOP - France', 'FR_CCOOP', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(5, '5120105', 'Fr_MANGO - France', 'Fr_MANGO', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(6, '5120106', 'FR_SG - France', 'FR_SG', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 210.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(7, '5120107', 'FR_SG_EXPL - France', 'FR_SG_EXPL', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(8, '5120108', 'FR_SPENDESK - France', 'FR_SPENDESK', 'Studely', 'EU', 'France', 'Filiale', 'Local', 'EUR', 100000.00, 50000.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(9, '5120109', 'FR_TRUST - France', 'FR_TRUST', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0.00, 50000.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(10, '5120301', 'BE_QUONTO - Belgique', 'BE_QUONTO', 'Studely', 'EU', 'Belgique', 'Filiale', 'Local', 'EUR', 100000.00, 85520.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(11, '5120302', 'BE_REVOLUT - Belgique', 'BE_REVOLUT', 'Studely', 'EU', 'Belgique', 'Filiale', 'France', 'EUR', 0.00, 150.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(12, '5120401', 'CM_BAC - Cameroun', 'CM_BAC', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 1000000.00, 350100.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(13, '5120402', 'CM_BAC_EXPL - Cameroun', 'CM_BAC_EXPL', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(14, '5120403', 'CM_BAC_REM - Cameroun', 'CM_BAC_REM', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(15, '5120404', 'CM_BGFI_DE - Cameroun', 'CM_BGFI_DE', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 200.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(16, '5120405', 'CM_BGFI_EXPL - Cameroun', 'CM_BGFI_EXPL', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 150.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(17, '5120406', 'CM_BGFI_FR - Cameroun', 'CM_BGFI_FR', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 100.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(18, '5120407', 'CM_CBC - Cameroun', 'CM_CBC', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 120.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(19, '5120408', 'CM_UBA - Cameroun', 'CM_UBA', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 120.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:36', 0, 0),
(20, '5120409', 'SF_CM_ACCESS_BANK - Cameroun', 'SF_CM_ACCESS_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(21, '5120410', 'SF_CM_AFD_BANK - Cameroun', 'SF_CM_AFD_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 200.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(22, '5120411', 'SF_CM_AFD_EXPL - Cameroun', 'SF_CM_AFD_EXPL', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(23, '5120412', 'SF_CM_BAC - Cameroun', 'SF_CM_BAC', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(24, '5120413', 'SF_CM_BAC_EXPL - Cameroun', 'SF_CM_BAC_EXPL', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(25, '5120414', 'SF_CM_BGFI - Cameroun', 'SF_CM_BGFI', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 100.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(26, '5120415', 'SF_CM_CCA_BANK - Cameroun', 'SF_CM_CCA_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(27, '5120416', 'SF_CM_UBA - Cameroun', 'SF_CM_UBA', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0.00, 110.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(28, '5120501', 'SF_SN_EcoBQ - Sénégal', 'SF_SN_EcoBQ', 'Studely Finance', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(29, '5120502', 'SN_ECOBQ - Sénégal', 'SN_ECOBQ', 'Studely', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(30, '5120601', 'CIV_ECOBQ - Côte d\'Ivoire', 'CIV_ECOBQ', 'Studely', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(31, '5120602', 'SF_CIV_AFG - Côte d\'Ivoire', 'SF_CIV_AFG', 'Studely Finance', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(32, '5120603', 'SF_CIV_EcoBQ - Côte d\'Ivoire', 'SF_CIV_EcoBQ', 'Studely Finance', 'AO', 'Côte d\'Ivoire', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(33, '5120701', 'BN_ECOBQ - Benin', 'BN_ECOBQ', 'Studely', 'AO', 'Benin', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(34, '5120702', 'SF_BN_EcoBQ - Benin', 'SF_BN_EcoBQ', 'Studely Finance', 'AO', 'Benin', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(35, '5120801', 'BFA_ECOBQ - Burkina Faso', 'BFA_ECOBQ', 'Studely', 'AO', 'Burkina Faso', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(36, '5120901', 'CD_BGFI - Congo Brazzaville', 'CD_BGFI', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(37, '5120902', 'CD_BGFI_EXPL - Congo Brazzaville', 'CD_BGFI_EXPL', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(38, '5120903', 'MUP_MF - Congo Brazzaville', 'MUP_MF', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(39, '5121001', 'RD_BGFI - Congo Kinshasa', 'RD_BGFI', 'Studely', 'AC', 'Congo Kinshasa', 'Filiale', 'Local', 'USD', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(40, '5121002', 'RD_BGFI_EURO - Congo Kinshasa', 'RD_BGFI_EURO', 'Studely', 'AC', 'Congo Kinshasa', 'Filiale', 'Local', 'EUR', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(41, '5121101', 'GB_BGFI - Gabon', 'GB_BGFI', 'Studely', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(42, '5121102', 'GB_BGFI_EXPL - Gabon', 'GB_BGFI_EXPL', 'Studely', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0.00, 150.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(43, '5121103', 'SF_GB_ECOBQ - Gabon', 'SF_GB_ECOBQ', 'Studely Finance', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0.00, 210.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(44, '5121201', 'SF_CHD_ECOBAQ - Tchad', 'SF_CHD_ECOBAQ', 'Studely Finance', 'AC', 'Tchad', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(45, '5121202', 'SF_TCHAD_UBA - Tchad', 'SF_TCHAD_UBA', 'Studely Finance', 'AC', 'Tchad', 'Filiale', 'Local', 'XAF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(46, '5121301', 'ML_ECOBQ - Mali', 'ML_ECOBQ', 'Studely', 'AO', 'Mali', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(47, '5121302', 'ML_SCOLARIS FI - Mali', 'ML_SCOLARIS FI', 'Studely', 'AO', 'Mali', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(48, '5121401', 'TGO_ECOBQ - Togo', 'TGO_ECOBQ', 'Studely', 'AO', 'Togo', 'Filiale', 'Local', 'XOF', 800000.00, 74458.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(49, '5121403', 'SF_TG_EcoBQ - Togo', 'SF_TG_EcoBQ', 'Studely Finance', 'AO', 'Togo', 'Filiale', 'Local', 'XOF', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(50, '5121701', 'ALG_BNP - Algérie', 'ALG_BNP', 'Studely', 'AN', 'Algérie', 'Filiale', 'Local', 'DZD', 1000000.00, 712160.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(51, '5121801', 'GUI_ECOBQ - Guinée', 'GUI_ECOBQ', 'Studely', 'AO', 'Guinée', 'Filiale', 'Local', 'GNF', 0.00, 210.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(52, '5121802', 'SF_GUI_EcoBQ - Guinée', 'SF_GUI_EcoBQ', 'Studely Finance', 'AO', 'Guinée', 'Filiale', 'Local', 'GNF', 0.00, 120.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(53, '5121901', 'TN_ATTI - Tunisie', 'TN_ATTI', 'Studely', 'AN', 'Tunisie', 'Filiale', 'Local', 'TND', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(54, '5122001', 'MA_ATTI - Maroc', 'MA_ATTI', 'Studely', 'AN', 'Maroc', 'Filiale', 'Local', 'MAD', 0.00, 0.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0),
(55, '5122101', 'NG_ECOBQ - Niger', 'NG_ECOBQ', 'Studely', 'AO', 'Niger', 'Filiale', 'Local', 'XOF', 0.00, 140.00, 1, '2026-03-28 23:53:57', '2026-04-13 23:29:37', 0, 0);

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
(26, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 1, 'Modification d’un compte de trésorerie', '2026-04-04 23:36:43'),
(27, 1, 'create_client', 'clients', 'client', 7, 'Création du client 983200894', '2026-04-06 00:48:25'),
(28, 1, 'create_client', 'clients', 'client', 8, 'Création du client 870041597', '2026-04-06 22:04:23'),
(29, 1, 'edit_client', 'clients', 'client', 8, 'Modification du client 870041597', '2026-04-06 22:11:14'),
(30, 1, 'edit_client', 'clients', 'client', 8, 'Modification du client 870041597', '2026-04-06 22:46:54'),
(31, 1, 'edit_client', 'clients', 'client', 7, 'Modification du client 983200894', '2026-04-07 00:23:28'),
(32, 1, 'edit_client', 'clients', 'client', 8, 'Modification du client 870041597', '2026-04-07 03:20:00'),
(33, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 48, 'Modification d’un compte de trésorerie', '2026-04-07 03:36:39'),
(34, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 48, 'Modification d’un compte de trésorerie', '2026-04-07 03:37:09'),
(35, 1, 'create_operation', 'operations', 'operation', 24, 'Création d’une opération', '2026-04-08 17:58:53'),
(36, 1, 'create_operation', 'operations', 'operation', 25, 'Création d’une opération', '2026-04-08 17:59:27'),
(37, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 48, 'Modification d’un compte de trésorerie', '2026-04-09 19:54:25'),
(38, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 48, 'Modification d’un compte de trésorerie', '2026-04-09 19:55:29'),
(39, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 12, 'Modification d’un compte de trésorerie', '2026-04-09 19:56:02'),
(40, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 50, 'Modification d’un compte de trésorerie', '2026-04-09 19:56:35'),
(41, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 8, 'Modification d’un compte de trésorerie', '2026-04-09 19:57:18'),
(42, 1, 'edit_treasury_account', 'treasury', 'treasury_account', 10, 'Modification d’un compte de trésorerie', '2026-04-09 19:57:39'),
(43, 1, 'create_operation', 'operations', 'operation', 26, 'Création d’une opération', '2026-04-10 22:21:46'),
(44, 1, 'create_operation', 'operations', 'operation', 27, 'Création d’une opération', '2026-04-10 22:23:13'),
(45, 1, 'create_client', 'clients', 'client', 9, 'Création du client CLT058180', '2026-04-10 23:36:16'),
(46, 1, 'create_client', 'clients', 'client', 10, 'Création du client 337819647', '2026-04-10 23:38:36'),
(47, 1, 'edit_client', 'clients', 'client', 7, 'Modification du client 983200894', '2026-04-11 13:15:02'),
(48, 1, 'execute_monthly_run', 'monthly_payments', 'monthly_payment_run', 1, 'Exécution d’une génération mensuelle traçable', '2026-04-12 20:53:25'),
(49, 1, 'validate_import', 'monthly_payments', 'monthly_payment_import', 4, 'Validation d’un import de mensualités', '2026-04-12 20:54:26'),
(50, 1, 'create_operation', 'monthly_payments', 'operation', 28, 'Génération d’une mensualité', '2026-04-12 21:08:13'),
(51, 1, 'create_operation', 'monthly_payments', 'operation', 29, 'Génération d’une mensualité', '2026-04-12 21:08:13'),
(52, 1, 'create_operation', 'monthly_payments', 'operation', 30, 'Génération d’une mensualité', '2026-04-12 21:08:13'),
(53, 1, 'create_operation', 'monthly_payments', 'operation', 31, 'Génération d’une mensualité', '2026-04-12 21:08:13'),
(54, 1, 'create_operation', 'monthly_payments', 'operation', 32, 'Génération d’une mensualité', '2026-04-12 21:08:14'),
(55, 1, 'create_operation', 'monthly_payments', 'operation', 33, 'Génération d’une mensualité', '2026-04-12 21:08:14'),
(56, 1, 'create_operation', 'monthly_payments', 'operation', 34, 'Génération d’une mensualité', '2026-04-12 21:08:14'),
(57, 1, 'create_operation', 'monthly_payments', 'operation', 35, 'Génération d’une mensualité', '2026-04-12 21:08:14'),
(58, 1, 'create_operation', 'monthly_payments', 'operation', 36, 'Génération d’une mensualité', '2026-04-12 21:08:14'),
(59, 1, 'create_operation', 'monthly_payments', 'operation', 37, 'Génération d’une mensualité', '2026-04-12 21:08:14'),
(60, 1, 'execute_monthly_run', 'monthly_payments', 'monthly_payment_run', 2, 'Exécution d’une génération mensuelle traçable', '2026-04-12 21:08:14'),
(61, 1, 'execute_monthly_run', 'monthly_payments', 'monthly_payment_run', 3, 'Exécution d’une génération mensuelle traçable', '2026-04-12 21:08:25'),
(62, 1, 'upload_monthly_payment_import', 'monthly_payments', 'monthly_payment_import', 6, 'Import CSV mensualités : modele_virement_mensuel.csv', '2026-04-13 02:38:38'),
(63, 1, 'import_monthly_payments', 'monthly_payments', 'monthly_payment_import', 7, 'Import du fichier de mensualités modele_virement_mensuel.csv', '2026-04-13 18:47:01'),
(64, 1, 'validate_monthly_payments_import', 'monthly_payments', 'monthly_payment_import', 7, 'Validation manuelle de l’import des mensualités', '2026-04-13 18:47:21'),
(65, 1, 'create_operation', 'monthly_payments', 'operation', 38, 'Génération d’une mensualité', '2026-04-13 18:47:39'),
(66, 1, 'create_operation', 'monthly_payments', 'operation', 39, 'Génération d’une mensualité', '2026-04-13 18:47:39'),
(67, 1, 'create_operation', 'monthly_payments', 'operation', 40, 'Génération d’une mensualité', '2026-04-13 18:47:39'),
(68, 1, 'create_operation', 'monthly_payments', 'operation', 41, 'Génération d’une mensualité', '2026-04-13 18:47:39'),
(69, 1, 'create_operation', 'monthly_payments', 'operation', 42, 'Génération d’une mensualité', '2026-04-13 18:47:39'),
(70, 1, 'create_operation', 'monthly_payments', 'operation', 43, 'Génération d’une mensualité', '2026-04-13 18:47:39'),
(71, 1, 'create_operation', 'monthly_payments', 'operation', 44, 'Génération d’une mensualité', '2026-04-13 18:47:40'),
(72, 1, 'create_operation', 'monthly_payments', 'operation', 45, 'Génération d’une mensualité', '2026-04-13 18:47:40'),
(73, 1, 'create_operation', 'monthly_payments', 'operation', 46, 'Génération d’une mensualité', '2026-04-13 18:47:40'),
(74, 1, 'create_operation', 'monthly_payments', 'operation', 47, 'Génération d’une mensualité', '2026-04-13 18:47:40'),
(75, 1, 'execute_monthly_run', 'monthly_payments', 'monthly_payment_run', 4, 'Exécution d’une génération mensuelle traçable', '2026-04-13 18:47:40'),
(76, 1, 'create_client', 'clients', 'client', 11, 'Création du client 294030444', '2026-04-13 18:53:36'),
(77, 1, 'create_pending_debit', 'pending_debits', 'client', 11, 'Création d’un débit dû sans exécution immédiate', '2026-04-13 18:57:30'),
(78, 1, 'edit_client', 'clients', 'client', 11, 'Modification du client 294030444', '2026-04-13 19:07:03'),
(79, 1, 'create_operation', 'operations', 'operation', 48, 'Création manuelle d’une opération', '2026-04-13 19:07:59'),
(80, 1, 'execute_pending_debit', 'pending_client_debits', 'pending_client_debit', 1, 'Exécution manuelle d’un débit dû #1', '2026-04-13 21:25:17'),
(81, 1, 'create_client', 'clients', 'client', 12, 'Création du client 656519872', '2026-04-13 21:42:38'),
(82, 1, 'create_operation', 'operations', 'operation', 50, 'Création manuelle d’une opération', '2026-04-13 23:29:37'),
(83, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-04-15 17:28:48'),
(84, 1, 'archive_client', 'clients', 'client', 4, 'Archivage du client CLT0004', '2026-04-17 17:37:08'),
(85, 1, 'archive_client', 'clients', 'client', 3, 'Archivage du client CLT0003', '2026-04-17 17:37:26'),
(86, 1, 'restore_client', 'clients', 'client', 3, 'Action client', '2026-04-17 19:06:41'),
(87, 1, 'restore_client', 'clients', 'client', 4, 'Action client', '2026-04-17 19:07:00'),
(88, 1, 'archive_client', 'clients', 'client', 2, 'Action client', '2026-04-17 19:07:24'),
(89, 1, 'restore_client', 'clients', 'client', 2, 'Action client', '2026-04-17 19:15:29'),
(90, 1, 'archive_client', 'clients', 'client', 9, 'Archivage client CLT058180', '2026-04-17 20:05:49'),
(91, 1, 'archive_client', 'clients', 'client', 2, 'Archivage client CLT0002', '2026-04-17 20:59:11'),
(92, 1, 'restore_client_with_balance', 'clients', 'client', 2, 'Réactivation client avec restitution du solde', '2026-04-17 21:35:35'),
(93, 1, 'archive_client', 'clients', 'client', 10, 'Archivage client 337819647', '2026-04-17 21:54:45'),
(94, 1, 'create_pending_debit', 'pending_debits', 'client', 12, 'Création d’un débit dû sans exécution immédiate', '2026-04-17 23:37:00'),
(95, 1, 'create_pending_debit', 'pending_debits', 'client', 12, 'Création d’un débit dû sans exécution immédiate', '2026-04-17 23:42:20'),
(96, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-04-18 14:09:19'),
(97, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-04-18 17:13:10'),
(98, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-04-19 14:48:49'),
(99, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-04-19 14:48:54'),
(100, 1, 'logout', 'auth', 'user', 1, 'Déconnexion utilisateur', '2026-04-19 14:59:20'),
(101, 1, 'login', 'auth', 'user', 1, 'Connexion utilisateur', '2026-04-19 14:59:24');

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
  ADD KEY `idx_clients_status_text` (`client_status`),
  ADD KEY `idx_clients_monthly_enabled` (`monthly_enabled`),
  ADD KEY `idx_clients_monthly_day` (`monthly_day`),
  ADD KEY `idx_clients_monthly_treasury` (`monthly_treasury_account_id`);

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
-- Index pour la table `monthly_payment_imports`
--
ALTER TABLE `monthly_payment_imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_monthly_payment_imports_user` (`created_by`);

--
-- Index pour la table `monthly_payment_import_rows`
--
ALTER TABLE `monthly_payment_import_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_monthly_payment_import_rows_import` (`import_id`),
  ADD KEY `fk_monthly_payment_import_rows_client` (`resolved_client_id`);

--
-- Index pour la table `monthly_payment_runs`
--
ALTER TABLE `monthly_payment_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_monthly_payment_runs_user` (`executed_by`);

--
-- Index pour la table `monthly_payment_run_items`
--
ALTER TABLE `monthly_payment_run_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_monthly_payment_run_items_run` (`run_id`),
  ADD KEY `idx_monthly_payment_run_items_client` (`client_id`),
  ADD KEY `idx_monthly_payment_run_items_operation` (`operation_id`),
  ADD KEY `idx_monthly_payment_run_items_status` (`status`),
  ADD KEY `fk_monthly_payment_run_items_treasury` (`treasury_account_id`);

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
  ADD KEY `idx_operations_hash` (`operation_hash`),
  ADD KEY `idx_operations_monthly_run_id` (`monthly_run_id`);

--
-- Index pour la table `pending_client_debits`
--
ALTER TABLE `pending_client_debits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pending_client_debits_client` (`client_id`),
  ADD KEY `idx_pending_client_debits_status` (`status`),
  ADD KEY `idx_pending_client_debits_remaining` (`remaining_amount`),
  ADD KEY `fk_pending_client_debits_operation` (`source_operation_id`),
  ADD KEY `fk_pending_client_debits_user` (`created_by`);

--
-- Index pour la table `pending_client_debit_logs`
--
ALTER TABLE `pending_client_debit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pending_client_debit_logs_pending` (`pending_debit_id`),
  ADD KEY `fk_pending_client_debit_logs_user` (`created_by`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_code` (`code`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
-- AUTO_INCREMENT pour la table `monthly_payment_imports`
--
ALTER TABLE `monthly_payment_imports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `monthly_payment_import_rows`
--
ALTER TABLE `monthly_payment_import_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT pour la table `monthly_payment_runs`
--
ALTER TABLE `monthly_payment_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `monthly_payment_run_items`
--
ALTER TABLE `monthly_payment_run_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT pour la table `pending_client_debits`
--
ALTER TABLE `pending_client_debits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `pending_client_debit_logs`
--
ALTER TABLE `pending_client_debit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=989;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

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
  ADD CONSTRAINT `fk_clients_monthly_treasury` FOREIGN KEY (`monthly_treasury_account_id`) REFERENCES `treasury_accounts` (`id`) ON DELETE SET NULL,
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
-- Contraintes pour la table `monthly_payment_imports`
--
ALTER TABLE `monthly_payment_imports`
  ADD CONSTRAINT `fk_monthly_payment_imports_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `monthly_payment_import_rows`
--
ALTER TABLE `monthly_payment_import_rows`
  ADD CONSTRAINT `fk_monthly_payment_import_rows_client` FOREIGN KEY (`resolved_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_monthly_payment_import_rows_import` FOREIGN KEY (`import_id`) REFERENCES `monthly_payment_imports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `monthly_payment_runs`
--
ALTER TABLE `monthly_payment_runs`
  ADD CONSTRAINT `fk_monthly_payment_runs_user` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `monthly_payment_run_items`
--
ALTER TABLE `monthly_payment_run_items`
  ADD CONSTRAINT `fk_monthly_payment_run_items_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_monthly_payment_run_items_operation` FOREIGN KEY (`operation_id`) REFERENCES `operations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_monthly_payment_run_items_run` FOREIGN KEY (`run_id`) REFERENCES `monthly_payment_runs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_monthly_payment_run_items_treasury` FOREIGN KEY (`treasury_account_id`) REFERENCES `treasury_accounts` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `operations`
--
ALTER TABLE `operations`
  ADD CONSTRAINT `fk_operations_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_operations_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_operations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_operations_monthly_run` FOREIGN KEY (`monthly_run_id`) REFERENCES `monthly_payment_runs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `pending_client_debits`
--
ALTER TABLE `pending_client_debits`
  ADD CONSTRAINT `fk_pending_client_debits_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pending_client_debits_operation` FOREIGN KEY (`source_operation_id`) REFERENCES `operations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pending_client_debits_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `pending_client_debit_logs`
--
ALTER TABLE `pending_client_debit_logs`
  ADD CONSTRAINT `fk_pending_client_debit_logs_pending` FOREIGN KEY (`pending_debit_id`) REFERENCES `pending_client_debits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pending_client_debit_logs_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
