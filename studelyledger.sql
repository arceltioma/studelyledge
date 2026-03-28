-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 24 mars 2026 à 15:28
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
-- Base de données : `studelyledger`
--

-- --------------------------------------------------------

--
-- Structure de la table `account_categories`
--

CREATE TABLE `account_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `account_categories`
--

INSERT INTO `account_categories` (`id`, `name`, `created_at`) VALUES
(1, 'Etudiant', '2026-03-15 23:17:42'),
(2, 'Interne', '2026-03-15 23:17:42'),
(3, 'Partenaire', '2026-03-15 23:17:42'),
(4, 'Standard', '2026-03-16 20:16:21');

-- --------------------------------------------------------

--
-- Structure de la table `account_types`
--

CREATE TABLE `account_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `account_types`
--

INSERT INTO `account_types` (`id`, `name`, `created_at`) VALUES
(1, 'Nouveau Client', '2026-03-15 23:17:42'),
(2, 'Actualisation AVI', '2026-03-15 23:17:42'),
(3, 'Renouvellement AVI', '2026-03-15 23:17:42'),
(4, 'Actualisation via remboursement prêt', '2026-03-16 20:16:21');

-- --------------------------------------------------------

--
-- Structure de la table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `bank_name` varchar(150) NOT NULL,
  `iban` varchar(100) NOT NULL,
  `initial_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `country` varchar(100) DEFAULT NULL,
  `account_type_id` int(11) DEFAULT NULL,
  `account_category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `account_name`, `account_number`, `bank_name`, `iban`, `initial_balance`, `balance`, `country`, `account_type_id`, `account_category_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Compte 1', 'ACC-000001', 'Banque Démo 1', 'FR7611111111111111111111111', 120000.00, 120000.00, 'Allemagne', 1, 1, 1, '2026-03-16 01:02:44', '2026-03-14 17:50:21'),
(2, 'Compte 2', 'ACC-000002', 'Banque Démo 2', 'FR7622222222222222222222222', 85000.00, 85000.00, 'Allemagne', 1, 1, 1, '2026-03-16 01:02:44', '2026-03-14 17:50:21'),
(3, 'Compte CLT02013', 'ACC-CLT007', 'Banque Générique', 'FR76STUD0000000000000003', 2500.00, 2500.00, 'France', 1, 1, 1, '2026-03-16 19:37:08', '2026-03-16 19:37:08'),
(4, 'Compte CLT001', 'ACC-CLT001', 'Banque Générique', 'FR76STUD0000000000000004', 0.00, 1604.00, 'France', 1, 1, 1, '2026-03-16 19:43:56', '2026-03-20 17:55:52'),
(5, 'Compte CLT002', 'ACC-CLT002', 'Banque Générique', 'FR76STUD0000000000000005', 0.00, 1004.00, 'Allemagne', 1, 1, 1, '2026-03-16 19:43:56', '2026-03-20 18:58:22'),
(6, 'Compte CLT003', 'ACC-CLT003', 'Banque Générique', 'FR76STUD0000000000000006', 0.00, -35.00, 'Belgique', 1, 1, 1, '2026-03-16 19:43:56', '2026-03-16 19:43:56'),
(7, 'Compte CLT004', 'ACC-CLT004', 'Banque Générique', 'FR76STUD0000000000000007', 0.00, -9.00, 'France', 1, 1, 1, '2026-03-16 19:43:56', '2026-03-16 19:43:56'),
(8, 'Compte CLT005', 'ACC-CLT005', 'Banque Générique', 'FR76STUD0000000000000008', 0.00, 631.00, 'France', 1, 1, 1, '2026-03-16 19:43:56', '2026-03-16 19:43:56'),
(9, 'Compte CLT006', 'ACC-CLT006', 'Banque Générique', 'FR76STUD0000000000000009', 0.00, -25.00, 'Belgique', 1, 1, 1, '2026-03-16 19:43:56', '2026-03-16 19:43:56'),
(11, 'Compte client 000000008', '411000000008', 'Compte Client Interne', 'INT00000000000000000008', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(12, 'Compte client 000000009', '411000000009', 'Compte Client Interne', 'INT00000000000000000009', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(13, 'Compte client 000000010', '411000000010', 'Compte Client Interne', 'INT00000000000000000010', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(14, 'Compte client 000000011', '411000000011', 'Compte Client Interne', 'INT00000000000000000011', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(15, 'Compte client 000000012', '411000000012', 'Compte Client Interne', 'INT00000000000000000012', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(16, 'Compte client 000000013', '411000000013', 'Compte Client Interne', 'INT00000000000000000013', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(17, 'Compte client 000000014', '411000000014', 'Compte Client Interne', 'INT00000000000000000014', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(18, 'Compte client 000000015', '411000000015', 'Compte Client Interne', 'INT00000000000000000015', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(19, 'Compte client 000000016', '411000000016', 'Compte Client Interne', 'INT00000000000000000016', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(20, 'Compte client 000000017', '411000000017', 'Compte Client Interne', 'INT00000000000000000017', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(21, 'Compte client 000000018', '411000000018', 'Compte Client Interne', 'INT00000000000000000018', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(22, 'Compte client 000000019', '411000000019', 'Compte Client Interne', 'INT00000000000000000019', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(23, 'Compte client 000000020', '411000000020', 'Compte Client Interne', 'INT00000000000000000020', 15000.00, 15000.00, NULL, NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50');

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(2, 'ATS'),
(1, 'AVI'),
(4, 'ComptePaiement'),
(5, 'Divers'),
(6, 'Frais'),
(3, 'Transfert');

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `origin_country_id` int(10) UNSIGNED DEFAULT NULL,
  `destination_country_id` int(10) UNSIGNED DEFAULT NULL,
  `commercial_country_id` int(10) UNSIGNED DEFAULT NULL,
  `client_type_id` int(10) UNSIGNED DEFAULT NULL,
  `client_status_ref_id` int(10) UNSIGNED DEFAULT NULL,
  `main_service_type_id` int(10) UNSIGNED DEFAULT NULL,
  `main_service_other_label` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `passport_number` varchar(100) DEFAULT NULL,
  `main_currency` varchar(10) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `generated_client_account` varchar(50) DEFAULT NULL,
  `initial_deposit_amount` decimal(15,2) DEFAULT 0.00,
  `monthly_payment_amount` decimal(15,2) DEFAULT 0.00,
  `initial_treasury_account_id` int(10) UNSIGNED DEFAULT NULL,
  `internal_iban` varchar(100) DEFAULT NULL,
  `account_state_id` int(10) UNSIGNED DEFAULT NULL,
  `account_category_ref_id` int(10) UNSIGNED DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `status_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `first_name`, `last_name`, `full_name`, `email`, `phone`, `origin_country_id`, `destination_country_id`, `commercial_country_id`, `client_type_id`, `client_status_ref_id`, `main_service_type_id`, `main_service_other_label`, `birth_date`, `passport_number`, `main_currency`, `comment`, `generated_client_account`, `initial_deposit_amount`, `monthly_payment_amount`, `initial_treasury_account_id`, `internal_iban`, `account_state_id`, `account_category_ref_id`, `country`, `status_id`, `category_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '000000001', 'Amina', 'Belaid', 'Amina Belaid', 'amina.belaid@studelyledger.com', '0600000001', 1, 1, 4, 1, 1, 1, NULL, '2000-01-01', 'P00000001', 'EUR', NULL, '411CLT001', 0.00, 0.00, 2, 'INT00000000000000000001', 1, 1, 'France', 2, 1, 1, '2026-03-14 17:50:21', NULL),
(2, '000000002', 'Jonas', 'Keller', 'Jonas Keller', 'jonas.keller@studelyledger.com', '0600000002', 1, 1, 4, 1, 1, 1, NULL, '2000-01-01', 'P00000002', 'EUR', NULL, '411CLT002', 0.00, 0.00, 2, 'INT00000000000000000002', 1, 1, 'Allemagne', 1, 2, 1, '2026-03-14 17:50:21', NULL),
(3, '000000003', 'Lina', 'Mercier', 'Lina Mercier', 'lina.mercier@studelyledger.com', '0600000003', 1, 1, 4, 1, 1, 1, NULL, '2000-01-01', 'P00000003', 'EUR', NULL, '411CLT003', 0.00, 0.00, 2, 'INT00000000000000000003', 1, 1, 'Belgique', 2, 3, 1, '2026-03-14 17:50:21', NULL),
(4, '000000004', 'Sara', 'Ndiaye', 'Sara Ndiaye', 'sara.ndiaye@studelyledger.com', '0600000004', 1, 1, 4, 1, 1, 1, NULL, '2000-01-01', 'P00000004', 'EUR', NULL, '411CLT004', 0.00, 0.00, 2, 'INT00000000000000000004', 1, 1, 'France', 3, 6, 1, '2026-03-14 17:50:21', NULL),
(5, '000000005', 'Milan', 'Petrov', 'Milan Petrov', 'milan.petrov@studelyledger.com', '0600000005', 1, 1, 4, 1, 1, 1, NULL, '2000-01-01', 'P00000005', 'EUR', NULL, '411CLT005', 0.00, 0.00, 2, 'INT00000000000000000005', 1, 1, 'France', 2, 2, 1, '2026-03-14 17:50:21', NULL),
(6, '000000006', 'Nora', 'Silva', 'Nora Silva', 'nora.silva@studelyledger.com', '0600000006', 1, 1, 4, 1, 1, 1, NULL, '2000-01-01', 'P00000006', 'EUR', NULL, '411CLT006', 0.00, 0.00, 2, 'INT00000000000000000006', 1, 1, 'Belgique', 1, 4, 1, '2026-03-14 17:50:21', NULL),
(7, '000000007', 'Lenny', 'Tioma', 'Lenny Tioma', 'lenny.tioma@studelyledger.com', '0600000007', 1, 1, 4, 1, 1, 1, NULL, '2000-01-01', 'P00000007', 'EUR', NULL, '411CLT02013', 0.00, 0.00, 2, 'INT00000000000000000007', 1, 1, 'France', 2, 2, 1, '2026-03-16 19:37:08', NULL),
(8, '000000008', 'Prenom8', 'Nom8', 'Prenom8 Nom8', 'prenom8.nom8@studelyledger.com', '0600000008', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000008', 15000.00, 350.00, 2, 'INT00000000000000000008', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(9, '000000009', 'Prenom9', 'Nom9', 'Prenom9 Nom9', 'prenom9.nom9@studelyledger.com', '0600000009', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000009', 15000.00, 350.00, 2, 'INT00000000000000000009', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(10, '000000010', 'Prenom10', 'Nom10', 'Prenom10 Nom10', 'prenom10.nom10@studelyledger.com', '0600000010', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000010', 15000.00, 350.00, 2, 'INT00000000000000000010', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(11, '000000011', 'Prenom11', 'Nom11', 'Prenom11 Nom11', 'prenom11.nom11@studelyledger.com', '0600000011', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000011', 15000.00, 350.00, 2, 'INT00000000000000000011', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(12, '000000012', 'Prenom12', 'Nom12', 'Prenom12 Nom12', 'prenom12.nom12@studelyledger.com', '0600000012', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000012', 15000.00, 350.00, 2, 'INT00000000000000000012', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(13, '000000013', 'Prenom13', 'Nom13', 'Prenom13 Nom13', 'prenom13.nom13@studelyledger.com', '0600000013', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000013', 15000.00, 350.00, 2, 'INT00000000000000000013', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(14, '000000014', 'Prenom14', 'Nom14', 'Prenom14 Nom14', 'prenom14.nom14@studelyledger.com', '0600000014', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000014', 15000.00, 350.00, 2, 'INT00000000000000000014', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(15, '000000015', 'Prenom15', 'Nom15', 'Prenom15 Nom15', 'prenom15.nom15@studelyledger.com', '0600000015', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000015', 15000.00, 350.00, 2, 'INT00000000000000000015', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(16, '000000016', 'Prenom16', 'Nom16', 'Prenom16 Nom16', 'prenom16.nom16@studelyledger.com', '0600000016', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000016', 15000.00, 350.00, 2, 'INT00000000000000000016', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(17, '000000017', 'Prenom17', 'Nom17', 'Prenom17 Nom17', 'prenom17.nom17@studelyledger.com', '0600000017', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000017', 15000.00, 350.00, 2, 'INT00000000000000000017', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(18, '000000018', 'Prenom18', 'Nom18', 'Prenom18 Nom18', 'prenom18.nom18@studelyledger.com', '0600000018', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000018', 15000.00, 350.00, 2, 'INT00000000000000000018', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(19, '000000019', 'Prenom19', 'Nom19', 'Prenom19 Nom19', 'prenom19.nom19@studelyledger.com', '0600000019', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000019', 15000.00, 350.00, 2, 'INT00000000000000000019', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50'),
(20, '000000020', 'Prenom20', 'Nom20', 'Prenom20 Nom20', 'prenom20.nom20@studelyledger.com', '0600000020', 1, 1, 4, 1, 1, 1, NULL, NULL, NULL, 'EUR', NULL, '411000000020', 15000.00, 350.00, 2, 'INT00000000000000000020', 1, 1, '', NULL, NULL, 1, '2026-03-20 22:18:50', '2026-03-20 22:18:50');

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
(1, 7, 3, '2026-03-16 19:37:08'),
(2, 1, 4, '2026-03-16 19:45:27'),
(3, 2, 5, '2026-03-16 19:45:27'),
(4, 3, 6, '2026-03-16 19:45:27'),
(5, 4, 7, '2026-03-16 19:45:27'),
(6, 5, 8, '2026-03-16 19:45:27'),
(7, 6, 9, '2026-03-16 19:45:27'),
(9, 8, 11, '2026-03-20 22:18:50'),
(10, 9, 12, '2026-03-20 22:18:50'),
(11, 10, 13, '2026-03-20 22:18:50'),
(12, 11, 14, '2026-03-20 22:18:50'),
(13, 12, 15, '2026-03-20 22:18:50'),
(14, 13, 16, '2026-03-20 22:18:50'),
(15, 14, 17, '2026-03-20 22:18:50'),
(16, 15, 18, '2026-03-20 22:18:50'),
(17, 16, 19, '2026-03-20 22:18:50'),
(18, 17, 20, '2026-03-20 22:18:50'),
(19, 18, 21, '2026-03-20 22:18:50'),
(20, 19, 22, '2026-03-20 22:18:50'),
(21, 20, 23, '2026-03-20 22:18:50');

-- --------------------------------------------------------

--
-- Structure de la table `import_batches`
--

CREATE TABLE `import_batches` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `imported_by` int(11) DEFAULT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `valid_rows` int(11) NOT NULL DEFAULT 0,
  `rejected_rows` int(11) NOT NULL DEFAULT 0,
  `status` varchar(50) NOT NULL DEFAULT 'done',
  `imported_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `import_batches`
--

INSERT INTO `import_batches` (`id`, `file_name`, `imported_by`, `total_rows`, `valid_rows`, `rejected_rows`, `status`, `imported_at`) VALUES
(1, 'import_demo_mars.csv', 1, 6, 4, 2, 'validated', '2026-03-14 17:50:21'),
(2, 'import_demo_avril.csv', 2, 3, 3, 0, 'validated', '2026-03-14 17:50:21');

-- --------------------------------------------------------

--
-- Structure de la table `import_rows`
--

CREATE TABLE `import_rows` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `row_number` int(11) NOT NULL,
  `client_code` varchar(50) DEFAULT NULL,
  `operation_date` date DEFAULT NULL,
  `operation_type` varchar(20) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'valid',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `corrected_at` datetime DEFAULT NULL,
  `corrected_by` int(11) DEFAULT NULL,
  `raw_data` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `import_rows`
--

INSERT INTO `import_rows` (`id`, `batch_id`, `row_number`, `client_code`, `operation_date`, `operation_type`, `label`, `amount`, `category_name`, `reference`, `status`, `rejection_reason`, `corrected_at`, `corrected_by`, `raw_data`, `created_at`) VALUES
(1, 1, 1, 'CLT001', '2026-03-01', 'credit', 'Paiement AVI', 1200.00, 'AVI', 'REF001', 'valid', NULL, NULL, NULL, NULL, '2026-03-16 01:02:44'),
(2, 1, 2, 'CLTXXX', '2026-03-03', 'credit', 'Paiement inconnu', 900.00, 'ATS', 'REF005', 'rejected', 'Client introuvable', NULL, NULL, NULL, '2026-03-16 01:02:44'),
(3, 1, 3, 'CLT002', '2026-03-02', 'credit', 'Paiement ATS', 850.00, 'ATS', 'REF003', 'valid', NULL, NULL, NULL, NULL, '2026-03-16 01:02:44'),
(4, 1, 4, 'CLTABC', '2026-03-06', 'oops', 'Erreur type', 100.00, 'Divers', 'REF006', 'corrected', NULL, '2026-03-14 17:50:21', 1, NULL, '2026-03-16 01:02:44'),
(5, 1, 5, 'CLT005', '2026-03-06', 'credit', 'Paiement transfert', 640.00, 'Transfert', 'REF005', 'valid', NULL, NULL, NULL, NULL, '2026-03-16 01:02:44'),
(6, 1, 6, 'CLT006', '2026-03-07', 'debit', 'Régularisation', 25.00, 'Divers', 'REF006', 'valid', NULL, NULL, NULL, NULL, '2026-03-16 01:02:44'),
(7, 2, 1, 'CLT002', '2026-04-01', 'credit', 'Paiement ATS Avril', 850.00, 'ATS', 'APR001', 'valid', NULL, NULL, NULL, NULL, '2026-03-16 01:02:44'),
(8, 2, 2, 'CLT001', '2026-04-02', 'credit', 'Client erroné', 500.00, 'Divers', 'APR002', 'corrected', NULL, '2026-03-16 19:30:00', 1, NULL, '2026-03-16 01:02:44'),
(9, 2, 3, 'CLT003', '2026-04-03', 'debit', 'Frais Avril', 40.00, 'Frais', 'APR003', 'valid', NULL, NULL, NULL, NULL, '2026-03-16 01:02:44');

-- --------------------------------------------------------

--
-- Structure de la table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `operation_date` date NOT NULL,
  `operation_type` enum('credit','debit') NOT NULL,
  `operation_kind` varchar(150) DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `source_type` varchar(50) NOT NULL DEFAULT 'manual',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `operations`
--

INSERT INTO `operations` (`id`, `client_id`, `bank_account_id`, `operation_date`, `operation_type`, `operation_kind`, `label`, `amount`, `reference`, `source_type`, `created_by`, `created_at`) VALUES
(1, 1, 4, '2026-03-01', 'credit', NULL, 'Paiement AVI', 1200.00, 'REF001', 'import', 1, '2026-03-14 17:50:21'),
(2, 1, 4, '2026-03-05', 'debit', NULL, 'Frais service', 50.00, 'REF002', 'manual', 1, '2026-03-14 17:50:21'),
(3, 2, 5, '2026-03-02', 'credit', NULL, 'Paiement ATS', 850.00, 'REF003', 'import', 1, '2026-03-14 17:50:21'),
(4, 3, 6, '2026-03-04', 'debit', NULL, 'Frais dossier', 35.00, 'REF004', 'bulk_fee', 1, '2026-03-14 17:50:21'),
(5, 5, 8, '2026-03-06', 'credit', NULL, 'Paiement transfert', 640.00, 'REF005', 'import', 2, '2026-03-14 17:50:21'),
(6, 6, 9, '2026-03-07', 'debit', NULL, 'Régularisation', 25.00, 'REF006', 'manual_correction', 1, '2026-03-14 17:50:21'),
(11, 1, 4, '2026-03-16', 'debit', NULL, 'Frais de service', 9.00, NULL, 'bulk_fee', 1, '2026-03-16 17:35:49'),
(12, 4, 7, '2026-03-16', 'debit', NULL, 'Frais de service', 9.00, NULL, 'bulk_fee', 1, '2026-03-16 17:35:49'),
(13, 5, 8, '2026-03-16', 'debit', NULL, 'Frais de service', 9.00, NULL, 'bulk_fee', 1, '2026-03-16 17:35:49'),
(14, 1, 4, '2026-03-16', 'credit', 'manual', 'Annulation d’écriture', 213.00, 'REF0213', 'manual', 1, '2026-03-16 17:36:57'),
(17, 1, 4, '2026-04-02', 'credit', 'corrected_import', 'Client erroné', 500.00, 'APR002', 'import_correction', 1, '2026-03-16 19:30:00'),
(18, 1, 4, '2026-03-20', 'debit', 'FRAIS DE SERVICE', 'test frais AVI crédit/débit auto', 250.00, NULL, 'manual', 1, '2026-03-20 17:55:52'),
(19, 2, 5, '2026-03-20', 'credit', 'VERSEMENT', 'TEST', 154.00, 'REF02131', 'manual', 1, '2026-03-20 18:58:22');

-- --------------------------------------------------------

--
-- Structure de la table `operation_accounting_rules`
--

CREATE TABLE `operation_accounting_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `operation_type_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `destination_country_label` varchar(150) DEFAULT NULL,
  `commercial_country_label` varchar(150) DEFAULT NULL,
  `debit_account_type` enum('CLIENT','TREASURY','TREASURY_SOURCE','TREASURY_TARGET') NOT NULL,
  `credit_account_type` enum('CLIENT','TREASURY','TREASURY_SOURCE','TREASURY_TARGET') NOT NULL,
  `service_account_id` int(10) UNSIGNED DEFAULT NULL,
  `rule_code` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `operation_accounting_rules`
--

INSERT INTO `operation_accounting_rules` (`id`, `operation_type_id`, `service_id`, `destination_country_label`, `commercial_country_label`, `debit_account_type`, `credit_account_type`, `service_account_id`, `rule_code`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, NULL, 'TREASURY', 'CLIENT', NULL, 'VERSEMENT_CLIENT', 'Débit compte interne / Crédit compte client', 1, '2026-03-18 02:47:15', NULL),
(11, 8, NULL, NULL, NULL, 'TREASURY_SOURCE', 'TREASURY_TARGET', NULL, 'VIREMENT_INTERNE', 'Débit compte interne source / Crédit compte interne cible', 1, '2026-03-18 02:56:10', NULL),
(12, 5, NULL, NULL, NULL, 'TREASURY', 'CLIENT', NULL, 'REGULARISATION_POSITIVE', 'Débit interne / Crédit client', 1, '2026-03-18 02:57:06', NULL),
(13, 6, NULL, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'REGULARISATION_NEGATIVE', 'Débit client / Crédit interne', 1, '2026-03-18 02:57:34', NULL),
(14, 7, NULL, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'FRAIS_BANCAIRES', 'Frais bancaires client', 1, '2026-03-18 02:57:52', NULL),
(15, 1, NULL, NULL, NULL, 'TREASURY', 'CLIENT', NULL, 'RULE_VERSEMENT', 'Débit compte interne / Crédit compte client', 1, '2026-03-18 03:25:15', NULL),
(16, 8, NULL, NULL, NULL, 'TREASURY_SOURCE', 'TREASURY_TARGET', NULL, 'RULE_VIREMENT_INTERNE', 'Débit compte interne initiateur / Crédit compte interne destinataire', 1, '2026-03-18 03:27:37', NULL),
(17, 5, NULL, NULL, NULL, 'TREASURY', 'CLIENT', NULL, 'RULE_REGULARISATION_POSITIVE', 'Débit compte interne / Crédit compte client', 1, '2026-03-18 03:30:47', NULL),
(18, 6, NULL, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_REGULARISATION_NEGATIVE', 'Débit compte client / Crédit compte interne', 1, '2026-03-18 03:31:37', NULL),
(19, 7, NULL, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_FRAIS_BANCAIRES', 'Débit compte client / Crédit compte interne', 1, '2026-03-18 03:32:29', NULL),
(20, 3, NULL, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_VIREMENT_MENSUEL', 'Débit compte client / Crédit compte interne - VIREMENT MENSUEL', 1, '2026-03-18 03:43:30', NULL),
(21, 4, NULL, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_VIREMENT_EXCEPTIONEL', 'Débit compte client / Crédit compte interne - VIREMENT EXCEPTIONEL', 1, '2026-03-18 03:43:30', NULL),
(22, 9, NULL, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_VIREMENT_REGULIER', 'Débit compte client / Crédit compte interne - VIREMENT REGULIER', 1, '2026-03-18 03:43:30', NULL),
(23, 2, 1, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_FRAIS_AVI', 'Débit compte client / Crédit compte interne - FRAIS DE SERVICES AVI', 1, '2026-03-18 03:44:25', NULL),
(24, 2, 2, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_FRAIS_GESTION', 'Débit compte client / Crédit compte interne - FRAIS DE GESTION', 1, '2026-03-18 03:44:25', NULL),
(25, 2, 3, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_FRAIS_TRANSFERT', 'Débit compte client / Crédit compte interne - COMMISSION DE TRANSFERT', 1, '2026-03-18 03:44:25', NULL),
(26, 2, 4, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_FRAIS_ATS', 'Débit compte client / Crédit compte interne - FRAIS DE SERVICE ATS', 1, '2026-03-18 03:44:25', NULL),
(27, 2, 5, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_FRAIS_PLACEMENT', 'Débit compte client / Crédit compte interne - CA PLACEMENT', 1, '2026-03-18 03:44:25', NULL),
(28, 2, 6, NULL, NULL, 'CLIENT', 'TREASURY', NULL, 'RULE_FRAIS_DIVERS', 'Débit compte client / Crédit compte interne - CA DIVERS', 1, '2026-03-18 03:44:25', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `operation_type_services`
--

CREATE TABLE `operation_type_services` (
  `id` int(10) UNSIGNED NOT NULL,
  `operation_type_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `operation_type_services`
--

INSERT INTO `operation_type_services` (`id`, `operation_type_id`, `service_id`, `is_active`, `created_at`) VALUES
(1, 2, 4, 1, '2026-03-18 21:04:29'),
(2, 2, 1, 1, '2026-03-18 21:04:29'),
(3, 2, 6, 1, '2026-03-18 21:04:29'),
(4, 2, 2, 1, '2026-03-18 21:04:29'),
(5, 2, 5, 1, '2026-03-18 21:04:29'),
(6, 2, 3, 1, '2026-03-18 21:04:29'),
(8, 6, 4, 1, '2026-03-18 21:04:29'),
(9, 5, 4, 1, '2026-03-18 21:04:29'),
(10, 6, 1, 1, '2026-03-18 21:04:29'),
(11, 5, 1, 1, '2026-03-18 21:04:29'),
(12, 6, 6, 1, '2026-03-18 21:04:29'),
(13, 5, 6, 1, '2026-03-18 21:04:29'),
(14, 6, 2, 1, '2026-03-18 21:04:29'),
(15, 5, 2, 1, '2026-03-18 21:04:29'),
(16, 6, 5, 1, '2026-03-18 21:04:29'),
(17, 5, 5, 1, '2026-03-18 21:04:29'),
(18, 6, 3, 1, '2026-03-18 21:04:29'),
(19, 5, 3, 1, '2026-03-18 21:04:29');

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_code` varchar(100) NOT NULL,
  `permission_name` varchar(150) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_code`, `permission_name`, `module_name`, `created_at`) VALUES
(1, 'clients_view', 'Voir les clients', 'clients', '2026-03-14 17:50:21'),
(2, 'clients_create', 'Créer un client', 'clients', '2026-03-14 17:50:21'),
(3, 'clients_edit', 'Modifier un client', 'clients', '2026-03-14 17:50:21'),
(4, 'clients_archive', 'Archiver un client', 'clients', '2026-03-14 17:50:21'),
(5, 'operations_view', 'Voir les opérations', 'operations', '2026-03-14 17:50:21'),
(6, 'operations_create', 'Créer une opération', 'operations', '2026-03-14 17:50:21'),
(7, 'operations_edit', 'Modifier une opération', 'operations', '2026-03-14 17:50:21'),
(8, 'operations_delete', 'Supprimer une opération', 'operations', '2026-03-14 17:50:21'),
(9, 'imports_view', 'Voir les imports', 'imports', '2026-03-14 17:50:21'),
(10, 'imports_validate', 'Valider les imports', 'imports', '2026-03-14 17:50:21'),
(11, 'imports_correct', 'Corriger les rejets', 'imports', '2026-03-14 17:50:21'),
(12, 'admin_users_manage', 'Gérer les utilisateurs', 'admin', '2026-03-14 17:50:21'),
(13, 'admin_roles_manage', 'Gérer les rôles', 'admin', '2026-03-14 17:50:21'),
(14, 'admin_logs_view', 'Voir les logs', 'admin', '2026-03-14 17:50:21'),
(15, 'dashboard_view', 'Voir le dashboard', 'dashboard', '2026-03-15 20:39:02'),
(16, 'statements_view', 'Voir les relevés', 'statements', '2026-03-15 20:39:02'),
(17, 'statements_export_single', 'Exporter un relevé individuel', 'statements', '2026-03-15 20:39:02'),
(18, 'statements_export_bulk', 'Exporter des relevés en masse', 'statements', '2026-03-15 20:39:02'),
(19, 'treasury_view', 'Voir la trésorerie', 'treasury', '2026-03-15 20:39:02'),
(20, 'analytics_view', 'Voir les analyses', 'analytics', '2026-03-15 20:39:02'),
(21, 'support_request_access', 'Demander un accès', 'support', '2026-03-15 20:39:02'),
(22, 'support_report_bug', 'Signaler un bug', 'support', '2026-03-15 20:39:02'),
(23, 'support_ask_question', 'Poser une question', 'support', '2026-03-15 20:39:02'),
(24, 'support_admin_manage', 'Traiter les demandes support', 'support', '2026-03-15 20:39:02'),
(25, 'manual_actions_create', 'Créer une action manuelle', 'manual_actions', '2026-03-15 20:39:02'),
(26, 'bulk_fees_manage', 'Gérer les frais en masse', 'manual_actions', '2026-03-15 20:39:02'),
(27, 'clients_view_detail', 'Voir la fiche client', 'clients', '2026-03-15 21:21:15'),
(28, 'clients_delete_logic', 'Supprimer logiquement un client', 'clients', '2026-03-15 21:21:15'),
(29, 'imports_upload', 'Importer un fichier', 'imports', '2026-03-15 21:21:15'),
(30, 'imports_preview', 'Prévisualiser un import', 'imports', '2026-03-15 21:21:15'),
(31, 'imports_journal', 'Voir le journal des imports', 'imports', '2026-03-15 21:21:15'),
(32, 'admin_dashboard_view', 'Voir le dashboard admin', 'admin', '2026-03-15 21:21:15'),
(33, 'admin_user_create', 'Créer un utilisateur', 'admin', '2026-03-15 21:21:15'),
(34, 'admin_user_edit', 'Modifier un utilisateur', 'admin', '2026-03-15 21:21:15'),
(35, 'admin_user_disable', 'Désactiver ou réactiver un utilisateur', 'admin', '2026-03-15 21:21:15'),
(36, 'admin_access_matrix_manage', 'Gérer la matrice des accès', 'admin', '2026-03-15 21:21:15');

-- --------------------------------------------------------

--
-- Structure de la table `ref_account_states`
--

CREATE TABLE `ref_account_states` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_account_states`
--

INSERT INTO `ref_account_states` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Actif', 1, '2026-03-19 22:59:36'),
(2, 'Inactif', 1, '2026-03-19 22:59:36'),
(3, 'Dormant', 1, '2026-03-19 22:59:36'),
(4, 'Archivé', 1, '2026-03-19 22:59:36');

-- --------------------------------------------------------

--
-- Structure de la table `ref_client_statuses`
--

CREATE TABLE `ref_client_statuses` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_client_statuses`
--

INSERT INTO `ref_client_statuses` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Etudiant Actif', 1, '2026-03-19 22:59:36'),
(2, 'Etudiant en attente', 1, '2026-03-19 22:59:36'),
(3, 'Etudiant Dormant', 1, '2026-03-19 22:59:36'),
(4, 'Etudiant Remboursé', 1, '2026-03-19 22:59:36');

-- --------------------------------------------------------

--
-- Structure de la table `ref_client_types`
--

CREATE TABLE `ref_client_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_client_types`
--

INSERT INTO `ref_client_types` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Nouveau Client', 1, '2026-03-19 22:59:35'),
(2, 'Actualisation via remboursement prêt', 1, '2026-03-19 22:59:35'),
(3, 'Actualisation AVI', 1, '2026-03-19 22:59:35'),
(4, 'Renouvellement AVI', 1, '2026-03-19 22:59:35');

-- --------------------------------------------------------

--
-- Structure de la table `ref_commercial_countries`
--

CREATE TABLE `ref_commercial_countries` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_commercial_countries`
--

INSERT INTO `ref_commercial_countries` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'France', 1, '2026-03-19 22:59:35'),
(2, 'Allemagne', 1, '2026-03-19 22:59:35'),
(3, 'Belgique', 1, '2026-03-19 22:59:35'),
(4, 'Cameroun', 1, '2026-03-19 22:59:35'),
(5, 'Sénégal', 1, '2026-03-19 22:59:35'),
(6, 'Côte d\'Ivoire', 1, '2026-03-19 22:59:35'),
(7, 'Benin', 1, '2026-03-19 22:59:35'),
(8, 'Burkina Faso', 1, '2026-03-19 22:59:35'),
(9, 'Congo Brazzaville', 1, '2026-03-19 22:59:35'),
(10, 'Congo Kinshasa', 1, '2026-03-19 22:59:35'),
(11, 'Gabon', 1, '2026-03-19 22:59:35'),
(12, 'Tchad', 1, '2026-03-19 22:59:35'),
(13, 'Mali', 1, '2026-03-19 22:59:35'),
(14, 'Togo', 1, '2026-03-19 22:59:35'),
(15, 'Mexique', 1, '2026-03-19 22:59:35'),
(16, 'Inde', 1, '2026-03-19 22:59:35'),
(17, 'Algérie', 1, '2026-03-19 22:59:35'),
(18, 'Guinée', 1, '2026-03-19 22:59:35'),
(19, 'Tunisie', 1, '2026-03-19 22:59:35'),
(20, 'Maroc', 1, '2026-03-19 22:59:35'),
(21, 'Niger', 1, '2026-03-19 22:59:35'),
(22, 'Afrique de l\'est', 1, '2026-03-19 22:59:35'),
(23, 'Autres pays', 1, '2026-03-19 22:59:35');

-- --------------------------------------------------------

--
-- Structure de la table `ref_countries`
--

CREATE TABLE `ref_countries` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_countries`
--

INSERT INTO `ref_countries` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Cameroun', 1, '2026-03-19 22:59:35'),
(2, 'France', 1, '2026-03-19 22:59:35'),
(3, 'Sénégal', 1, '2026-03-19 22:59:35'),
(4, 'Côte d’Ivoire', 1, '2026-03-19 22:59:35'),
(5, 'Maroc', 1, '2026-03-19 22:59:35'),
(6, 'Tunisie', 1, '2026-03-19 22:59:35'),
(7, 'Algérie', 1, '2026-03-19 22:59:35'),
(8, 'Belgique', 1, '2026-03-19 22:59:35'),
(9, 'Allemagne', 1, '2026-03-19 22:59:35'),
(10, 'Inde', 1, '2026-03-19 22:59:35');

-- --------------------------------------------------------

--
-- Structure de la table `ref_destination_countries`
--

CREATE TABLE `ref_destination_countries` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_destination_countries`
--

INSERT INTO `ref_destination_countries` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'France', 1, '2026-03-19 22:59:35'),
(2, 'Belgique', 1, '2026-03-19 22:59:35'),
(3, 'Allemagne', 1, '2026-03-19 22:59:35'),
(4, 'Espagne', 1, '2026-03-19 22:59:35'),
(5, 'Italie', 1, '2026-03-19 22:59:35'),
(6, 'Autres destinations', 1, '2026-03-19 22:59:35');

-- --------------------------------------------------------

--
-- Structure de la table `ref_main_service_types`
--

CREATE TABLE `ref_main_service_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_main_service_types`
--

INSERT INTO `ref_main_service_types` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'FRAIS DE SERVICES AVI', 1, '2026-03-19 22:59:36'),
(2, 'COMMISSION DE TRANSFERT', 1, '2026-03-19 22:59:36'),
(3, 'FRAIS DE SERVICE ATS', 1, '2026-03-19 22:59:36'),
(4, 'CA PLACEMENT', 1, '2026-03-19 22:59:36'),
(5, 'CA DIVERS', 1, '2026-03-19 22:59:36'),
(6, 'CA DEBOURS LOGEMENT', 1, '2026-03-19 22:59:36'),
(7, 'CA DEBOURS ASSURANCE', 1, '2026-03-19 22:59:36'),
(8, 'FRAIS DEBOURS MICROFINANCE', 1, '2026-03-19 22:59:36'),
(9, 'CA COURTAGE PRÊT', 1, '2026-03-19 22:59:36'),
(10, 'CA LOGEMENT', 1, '2026-03-19 22:59:36'),
(11, 'AUTRE', 1, '2026-03-19 22:59:36');

-- --------------------------------------------------------

--
-- Structure de la table `ref_operation_types`
--

CREATE TABLE `ref_operation_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_operation_types`
--

INSERT INTO `ref_operation_types` (`id`, `code`, `label`, `is_active`, `created_at`) VALUES
(1, 'VERSEMENT', 'VERSEMENT', 1, '2026-03-18 02:02:18'),
(2, 'FRAIS_DE_SERVICE', 'FRAIS DE SERVICE', 1, '2026-03-18 02:02:18'),
(3, 'VIREMENT_MENSUEL', 'VIREMENT MENSUEL', 1, '2026-03-18 02:02:18'),
(4, 'VIREMENT_EXCEPTIONEL', 'VIREMENT EXCEPTIONEL', 1, '2026-03-18 02:02:18'),
(5, 'REGULARISATION_POSITIVE', 'REGULARISATION POSITIVE', 1, '2026-03-18 02:02:18'),
(6, 'REGULARISATION_NEGATIVE', 'REGULARISATION NEGATIVE', 1, '2026-03-18 02:02:18'),
(7, 'FRAIS_BANCAIRES', 'FRAIS BANCAIRES', 1, '2026-03-18 02:02:18'),
(8, 'VIREMENT_INTERNE', 'VIREMENT INTERNE', 1, '2026-03-18 02:02:18'),
(9, 'VIREMENT_REGULIER', 'VIREMENT REGULIER', 1, '2026-03-18 02:02:18');

-- --------------------------------------------------------

--
-- Structure de la table `ref_services`
--

CREATE TABLE `ref_services` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ref_services`
--

INSERT INTO `ref_services` (`id`, `code`, `label`, `is_active`, `created_at`) VALUES
(1, 'AVI', 'FRAIS DE SERVICES AVI', 1, '2026-03-18 02:02:18'),
(2, 'GESTION', 'FRAIS DE GESTION', 1, '2026-03-18 02:02:18'),
(3, 'TRANSFERT', 'COMMISSION DE TRANSFERT', 1, '2026-03-18 02:02:18'),
(4, 'ATS', 'FRAIS DE SERVICE ATS', 1, '2026-03-18 02:02:18'),
(5, 'PLACEMENT', 'CA PLACEMENT', 1, '2026-03-18 02:02:18'),
(6, 'DIVERS', 'CA DIVERS', 1, '2026-03-18 02:02:18');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `role_code`, `role_name`, `created_at`) VALUES
(1, 'super_admin', 'Super Administrateur', '2026-03-14 17:50:21'),
(2, 'admin', 'Administrateur', '2026-03-14 17:50:21'),
(3, 'manager', 'Manager', '2026-03-14 17:50:21'),
(4, 'viewer', 'Lecture seule', '2026-03-14 17:50:21'),
(5, 'operator', 'Operator', '2026-03-15 19:38:42');

-- --------------------------------------------------------

--
-- Structure de la table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(131, 1, 14, '2026-03-15 20:48:01'),
(132, 1, 13, '2026-03-15 20:48:01'),
(133, 1, 12, '2026-03-15 20:48:01'),
(134, 1, 20, '2026-03-15 20:48:01'),
(135, 1, 4, '2026-03-15 20:48:01'),
(136, 1, 2, '2026-03-15 20:48:01'),
(137, 1, 3, '2026-03-15 20:48:01'),
(138, 1, 1, '2026-03-15 20:48:01'),
(139, 1, 15, '2026-03-15 20:48:01'),
(140, 1, 11, '2026-03-15 20:48:01'),
(141, 1, 10, '2026-03-15 20:48:01'),
(142, 1, 9, '2026-03-15 20:48:01'),
(143, 1, 26, '2026-03-15 20:48:01'),
(144, 1, 25, '2026-03-15 20:48:01'),
(145, 1, 6, '2026-03-15 20:48:01'),
(146, 1, 8, '2026-03-15 20:48:01'),
(147, 1, 7, '2026-03-15 20:48:01'),
(148, 1, 5, '2026-03-15 20:48:01'),
(149, 1, 18, '2026-03-15 20:48:01'),
(150, 1, 17, '2026-03-15 20:48:01'),
(151, 1, 16, '2026-03-15 20:48:01'),
(152, 1, 24, '2026-03-15 20:48:01'),
(153, 1, 23, '2026-03-15 20:48:01'),
(154, 1, 22, '2026-03-15 20:48:01'),
(155, 1, 21, '2026-03-15 20:48:01'),
(156, 1, 19, '2026-03-15 20:48:01'),
(157, 2, 14, '2026-03-15 20:48:01'),
(158, 2, 12, '2026-03-15 20:48:01'),
(159, 2, 20, '2026-03-15 20:48:01'),
(160, 2, 4, '2026-03-15 20:48:01'),
(161, 2, 2, '2026-03-15 20:48:01'),
(162, 2, 3, '2026-03-15 20:48:01'),
(163, 2, 1, '2026-03-15 20:48:01'),
(164, 2, 15, '2026-03-15 20:48:01'),
(165, 2, 11, '2026-03-15 20:48:01'),
(166, 2, 10, '2026-03-15 20:48:01'),
(167, 2, 9, '2026-03-15 20:48:01'),
(168, 2, 26, '2026-03-15 20:48:01'),
(169, 2, 25, '2026-03-15 20:48:01'),
(170, 2, 6, '2026-03-15 20:48:01'),
(171, 2, 7, '2026-03-15 20:48:01'),
(172, 2, 5, '2026-03-15 20:48:01'),
(173, 2, 18, '2026-03-15 20:48:01'),
(174, 2, 17, '2026-03-15 20:48:01'),
(175, 2, 16, '2026-03-15 20:48:01'),
(176, 2, 24, '2026-03-15 20:48:01'),
(177, 2, 23, '2026-03-15 20:48:01'),
(178, 2, 22, '2026-03-15 20:48:01'),
(179, 2, 21, '2026-03-15 20:48:01'),
(180, 2, 19, '2026-03-15 20:48:01'),
(181, 3, 14, '2026-03-15 20:48:01'),
(182, 3, 20, '2026-03-15 20:48:01'),
(183, 3, 1, '2026-03-15 20:48:01'),
(184, 3, 15, '2026-03-15 20:48:01'),
(185, 3, 9, '2026-03-15 20:48:01'),
(186, 3, 25, '2026-03-15 20:48:01'),
(187, 3, 5, '2026-03-15 20:48:01'),
(188, 3, 17, '2026-03-15 20:48:01'),
(189, 3, 16, '2026-03-15 20:48:01'),
(190, 3, 23, '2026-03-15 20:48:01'),
(191, 3, 22, '2026-03-15 20:48:01'),
(192, 3, 21, '2026-03-15 20:48:01'),
(193, 3, 19, '2026-03-15 20:48:01'),
(194, 4, 1, '2026-03-15 20:48:01'),
(195, 4, 15, '2026-03-15 20:48:01'),
(196, 4, 9, '2026-03-15 20:48:01'),
(197, 4, 5, '2026-03-15 20:48:01'),
(198, 4, 16, '2026-03-15 20:48:01'),
(199, 4, 23, '2026-03-15 20:48:01'),
(200, 4, 22, '2026-03-15 20:48:01'),
(201, 4, 21, '2026-03-15 20:48:01'),
(202, 5, 1, '2026-03-15 20:48:01'),
(203, 5, 15, '2026-03-15 20:48:01'),
(204, 5, 11, '2026-03-15 20:48:01'),
(205, 5, 6, '2026-03-15 20:48:01'),
(206, 5, 7, '2026-03-15 20:48:01'),
(207, 5, 18, '2026-03-15 20:48:01'),
(208, 5, 17, '2026-03-15 20:48:01'),
(209, 5, 16, '2026-03-15 20:48:01'),
(210, 5, 23, '2026-03-15 20:48:01'),
(211, 5, 22, '2026-03-15 20:48:01'),
(212, 5, 21, '2026-03-15 20:48:01'),
(213, 1, 36, '2026-03-15 21:56:17'),
(214, 1, 32, '2026-03-15 21:56:17'),
(215, 1, 33, '2026-03-15 21:56:17'),
(216, 1, 35, '2026-03-15 21:56:17'),
(217, 1, 34, '2026-03-15 21:56:17'),
(218, 1, 28, '2026-03-15 21:56:17'),
(219, 1, 27, '2026-03-15 21:56:17'),
(220, 1, 31, '2026-03-15 21:56:17'),
(221, 1, 30, '2026-03-15 21:56:17'),
(222, 1, 29, '2026-03-15 21:56:17'),
(228, 2, 36, '2026-03-15 21:56:17'),
(229, 2, 32, '2026-03-15 21:56:17'),
(230, 2, 13, '2026-03-15 21:56:17'),
(231, 2, 33, '2026-03-15 21:56:17'),
(232, 2, 35, '2026-03-15 21:56:17'),
(233, 2, 34, '2026-03-15 21:56:17'),
(234, 2, 27, '2026-03-15 21:56:17'),
(235, 2, 31, '2026-03-15 21:56:17'),
(236, 2, 30, '2026-03-15 21:56:17'),
(237, 2, 29, '2026-03-15 21:56:17'),
(238, 2, 8, '2026-03-15 21:56:17'),
(243, 3, 27, '2026-03-15 21:56:17'),
(244, 3, 31, '2026-03-15 21:56:17'),
(246, 4, 27, '2026-03-15 21:56:17'),
(247, 5, 27, '2026-03-15 21:56:17'),
(248, 5, 31, '2026-03-15 21:56:17'),
(249, 5, 30, '2026-03-15 21:56:17'),
(250, 5, 29, '2026-03-15 21:56:17'),
(251, 5, 9, '2026-03-15 21:56:17'),
(252, 5, 25, '2026-03-15 21:56:17'),
(253, 5, 5, '2026-03-15 21:56:17');

-- --------------------------------------------------------

--
-- Structure de la table `service_accounts`
--

CREATE TABLE `service_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_label` varchar(255) NOT NULL,
  `operation_type_label` varchar(150) DEFAULT NULL,
  `destination_country_label` varchar(150) DEFAULT NULL,
  `commercial_country_label` varchar(150) DEFAULT NULL,
  `parent_account_code` varchar(20) DEFAULT NULL,
  `level_depth` int(11) NOT NULL DEFAULT 1,
  `is_postable` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_accounts`
--

INSERT INTO `service_accounts` (`id`, `account_code`, `account_label`, `operation_type_label`, `destination_country_label`, `commercial_country_label`, `parent_account_code`, `level_depth`, `is_postable`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '706', 'Prestations de services', NULL, NULL, NULL, NULL, 1, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(2, '7061', 'FRAIS DE SERVICES AVI', 'Frais de services AVI', NULL, NULL, '706', 2, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(3, '70611', 'FRAIS DE SERVICE AVI ALLEMAGNE', 'Frais de services AVI', 'Allemagne', NULL, '7061', 3, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(4, '70612', 'FRAIS DE SERVICES AVI BELGIQUE', 'Frais de services AVI', 'Belgique', NULL, '7061', 3, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(5, '70613', 'FRAIS DE SERVICES AVI France', 'Frais de services AVI', 'France', NULL, '7061', 3, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(6, '7062', 'FRAIS DE GESTION', 'Frais de Gestion', NULL, NULL, '706', 2, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(7, '7063', 'COMMISSION DE TRANSFERT', 'Commission de transfert', NULL, NULL, '706', 2, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(8, '7065', 'FRAIS DE SERVICE ATS', 'Frais de services ATS', NULL, NULL, '706', 2, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(9, '7066', 'CA PLACEMENT', 'CA Placement', NULL, NULL, '706', 2, 0, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(10, '7064', 'CA DIVERS', 'CA DIVERS', NULL, NULL, '706', 2, 1, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(11, '70641', 'CA DEBOURS LOGEMENT', 'CA DEBOURS LOGEMENT', NULL, NULL, '7064', 3, 1, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(12, '70642', 'CA DEBOURS ASSURANCE', 'CA DEBOURS ASSURANCE', NULL, NULL, '7064', 3, 1, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(13, '70643', 'FRAIS DEBOURS MICROFINANCE', 'FRAIS DEBOURS MICROFINANCE', NULL, NULL, '7064', 3, 1, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(14, '70644', 'CA COURTAGE PRÊT', 'CA COURTAGE PRÊT', NULL, NULL, '7064', 3, 1, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(15, '70645', 'CA LOGEMENT', 'CA LOGEMENT', NULL, NULL, '7064', 3, 1, 1, '2026-03-18 00:12:17', '2026-03-18 00:13:09'),
(16, '7061101', 'FRAIS DE SERVICE AVI ALLEMAGNE-France', 'Frais de services AVI', 'Allemagne', 'France', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(17, '7061102', 'FRAIS DE SERVICE AVI ALLEMAGNE-Allemagne', 'Frais de services AVI', 'Allemagne', 'Allemagne', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(18, '7061103', 'FRAIS DE SERVICE AVI ALLEMAGNE-Belgique', 'Frais de services AVI', 'Allemagne', 'Belgique', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(19, '7061104', 'FRAIS DE SERVICE AVI ALLEMAGNE-Cameroun', 'Frais de services AVI', 'Allemagne', 'Cameroun', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(20, '7061105', 'FRAIS DE SERVICE AVI ALLEMAGNE-Sénégal', 'Frais de services AVI', 'Allemagne', 'Sénégal', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(21, '7061106', 'FRAIS DE SERVICE AVI ALLEMAGNE-Côte d\'Ivoire', 'Frais de services AVI', 'Allemagne', 'Côte d\'Ivoire', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(22, '7061107', 'FRAIS DE SERVICE AVI ALLEMAGNE-Benin', 'Frais de services AVI', 'Allemagne', 'Benin', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(23, '7061108', 'FRAIS DE SERVICE AVI ALLEMAGNE-Burkina Faso', 'Frais de services AVI', 'Allemagne', 'Burkina Faso', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(24, '7061109', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Brazzaville', 'Frais de services AVI', 'Allemagne', 'Congo Brazzaville', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(25, '7061110', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Kinshasa', 'Frais de services AVI', 'Allemagne', 'Congo Kinshasa', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(26, '7061111', 'FRAIS DE SERVICE AVI ALLEMAGNE-Gabon', 'Frais de services AVI', 'Allemagne', 'Gabon', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(27, '7061112', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tchad', 'Frais de services AVI', 'Allemagne', 'Tchad', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(28, '7061113', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mali', 'Frais de services AVI', 'Allemagne', 'Mali', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(29, '7061114', 'FRAIS DE SERVICE AVI ALLEMAGNE-Togo', 'Frais de services AVI', 'Allemagne', 'Togo', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(30, '7061115', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mexique', 'Frais de services AVI', 'Allemagne', 'Mexique', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(31, '7061116', 'FRAIS DE SERVICE AVI ALLEMAGNE-Inde', 'Frais de services AVI', 'Allemagne', 'Inde', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(32, '7061117', 'FRAIS DE SERVICE AVI ALLEMAGNE-Algérie', 'Frais de services AVI', 'Allemagne', 'Algérie', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(33, '7061118', 'FRAIS DE SERVICE AVI ALLEMAGNE-Guinée', 'Frais de services AVI', 'Allemagne', 'Guinée', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(34, '7061119', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tunisie', 'Frais de services AVI', 'Allemagne', 'Tunisie', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(35, '7061120', 'FRAIS DE SERVICE AVI ALLEMAGNE-Maroc', 'Frais de services AVI', 'Allemagne', 'Maroc', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(36, '7061121', 'FRAIS DE SERVICE AVI ALLEMAGNE-Niger', 'Frais de services AVI', 'Allemagne', 'Niger', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(37, '7061122', 'FRAIS DE SERVICE AVI ALLEMAGNE-Afrique de l\'est', 'Frais de services AVI', 'Allemagne', 'Afrique de l\'est', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(38, '7061123', 'FRAIS DE SERVICE AVI ALLEMAGNE-Autres pays', 'Frais de services AVI', 'Allemagne', 'Autres pays', '70611', 4, 1, 1, '2026-03-18 01:06:17', '2026-03-18 01:15:49'),
(39, '7061201', 'FRAIS DE SERVICES AVI BELGIQUE-France', 'Frais de services AVI', 'Belgique', 'France', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(40, '7061202', 'FRAIS DE SERVICES AVI BELGIQUE-Allemagne', 'Frais de services AVI', 'Belgique', 'Allemagne', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(41, '7061203', 'FRAIS DE SERVICES AVI BELGIQUE-Belgique', 'Frais de services AVI', 'Belgique', 'Belgique', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(42, '7061204', 'FRAIS DE SERVICES AVI BELGIQUE-Cameroun', 'Frais de services AVI', 'Belgique', 'Cameroun', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(43, '7061205', 'FRAIS DE SERVICES AVI BELGIQUE-Sénégal', 'Frais de services AVI', 'Belgique', 'Sénégal', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(44, '7061206', 'FRAIS DE SERVICES AVI BELGIQUE-Côte d\'Ivoire', 'Frais de services AVI', 'Belgique', 'Côte d\'Ivoire', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(45, '7061207', 'FRAIS DE SERVICES AVI BELGIQUE-Benin', 'Frais de services AVI', 'Belgique', 'Benin', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(46, '7061208', 'FRAIS DE SERVICES AVI BELGIQUE-Burkina Faso', 'Frais de services AVI', 'Belgique', 'Burkina Faso', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(47, '7061209', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Brazzaville', 'Frais de services AVI', 'Belgique', 'Congo Brazzaville', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(48, '7061210', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Kinshasa', 'Frais de services AVI', 'Belgique', 'Congo Kinshasa', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(49, '7061211', 'FRAIS DE SERVICES AVI BELGIQUE-Gabon', 'Frais de services AVI', 'Belgique', 'Gabon', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(50, '7061212', 'FRAIS DE SERVICES AVI BELGIQUE-Tchad', 'Frais de services AVI', 'Belgique', 'Tchad', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(51, '7061213', 'FRAIS DE SERVICES AVI BELGIQUE-Mali', 'Frais de services AVI', 'Belgique', 'Mali', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(52, '7061214', 'FRAIS DE SERVICES AVI BELGIQUE-Togo', 'Frais de services AVI', 'Belgique', 'Togo', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(53, '7061215', 'FRAIS DE SERVICES AVI BELGIQUE-Mexique', 'Frais de services AVI', 'Belgique', 'Mexique', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(54, '7061216', 'FRAIS DE SERVICES AVI BELGIQUE-Inde', 'Frais de services AVI', 'Belgique', 'Inde', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(55, '7061217', 'FRAIS DE SERVICES AVI BELGIQUE-Algérie', 'Frais de services AVI', 'Belgique', 'Algérie', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(56, '7061218', 'FRAIS DE SERVICES AVI BELGIQUE-Guinée', 'Frais de services AVI', 'Belgique', 'Guinée', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(57, '7061219', 'FRAIS DE SERVICES AVI BELGIQUE-Tunisie', 'Frais de services AVI', 'Belgique', 'Tunisie', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(58, '7061220', 'FRAIS DE SERVICES AVI BELGIQUE-Maroc', 'Frais de services AVI', 'Belgique', 'Maroc', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(59, '7061221', 'FRAIS DE SERVICES AVI BELGIQUE-Niger', 'Frais de services AVI', 'Belgique', 'Niger', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(60, '7061222', 'FRAIS DE SERVICES AVI BELGIQUE-Afrique de l\'est', 'Frais de services AVI', 'Belgique', 'Afrique de l\'est', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(61, '7061223', 'FRAIS DE SERVICES AVI BELGIQUE-Autres pays', 'Frais de services AVI', 'Belgique', 'Autres pays', '70612', 4, 1, 1, '2026-03-18 01:07:11', '2026-03-18 01:15:49'),
(62, '7061301', 'FRAIS DE SERVICES AVI France-France', 'Frais de services AVI', 'France', 'France', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(63, '7061302', 'FRAIS DE SERVICES AVI France-Allemagne', 'Frais de services AVI', 'France', 'Allemagne', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(64, '7061303', 'FRAIS DE SERVICES AVI France-Belgique', 'Frais de services AVI', 'France', 'Belgique', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(65, '7061304', 'FRAIS DE SERVICES AVI France-Cameroun', 'Frais de services AVI', 'France', 'Cameroun', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(66, '7061305', 'FRAIS DE SERVICES AVI France-Sénégal', 'Frais de services AVI', 'France', 'Sénégal', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(67, '7061306', 'FRAIS DE SERVICES AVI France-Côte d\'Ivoire', 'Frais de services AVI', 'France', 'Côte d\'Ivoire', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(68, '7061307', 'FRAIS DE SERVICES AVI France-Benin', 'Frais de services AVI', 'France', 'Benin', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(69, '7061308', 'FRAIS DE SERVICES AVI France-Burkina Faso', 'Frais de services AVI', 'France', 'Burkina Faso', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(70, '7061309', 'FRAIS DE SERVICES AVI France-Congo Brazzaville', 'Frais de services AVI', 'France', 'Congo Brazzaville', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(71, '7061310', 'FRAIS DE SERVICES AVI France-Congo Kinshasa', 'Frais de services AVI', 'France', 'Congo Kinshasa', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(72, '7061311', 'FRAIS DE SERVICES AVI France-Gabon', 'Frais de services AVI', 'France', 'Gabon', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(73, '7061312', 'FRAIS DE SERVICES AVI France-Tchad', 'Frais de services AVI', 'France', 'Tchad', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(74, '7061313', 'FRAIS DE SERVICES AVI France-Mali', 'Frais de services AVI', 'France', 'Mali', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(75, '7061314', 'FRAIS DE SERVICES AVI France-Togo', 'Frais de services AVI', 'France', 'Togo', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(76, '7061315', 'FRAIS DE SERVICES AVI France-Mexique', 'Frais de services AVI', 'France', 'Mexique', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(77, '7061316', 'FRAIS DE SERVICES AVI France-Inde', 'Frais de services AVI', 'France', 'Inde', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(78, '7061317', 'FRAIS DE SERVICES AVI France-Algérie', 'Frais de services AVI', 'France', 'Algérie', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(79, '7061318', 'FRAIS DE SERVICES AVI France-Guinée', 'Frais de services AVI', 'France', 'Guinée', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(80, '7061319', 'FRAIS DE SERVICES AVI France-Tunisie', 'Frais de services AVI', 'France', 'Tunisie', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(81, '7061320', 'FRAIS DE SERVICES AVI France-Maroc', 'Frais de services AVI', 'France', 'Maroc', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(82, '7061321', 'FRAIS DE SERVICES AVI France-Niger', 'Frais de services AVI', 'France', 'Niger', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(83, '7061322', 'FRAIS DE SERVICES AVI France-Afrique de l\'est', 'Frais de services AVI', 'France', 'Afrique de l\'est', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(84, '7061323', 'FRAIS DE SERVICES AVI France-Autres pays', 'Frais de services AVI', 'France', 'Autres pays', '70613', 4, 1, 1, '2026-03-18 01:08:07', '2026-03-18 01:15:49'),
(85, '706301', 'COMMISSION DE TRANSFERT-France', 'Commission de transfert', NULL, 'France', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(86, '706302', 'COMMISSION DE TRANSFERT-Allemagne', 'Commission de transfert', NULL, 'Allemagne', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(87, '706303', 'COMMISSION DE TRANSFERT-Belgique', 'Commission de transfert', NULL, 'Belgique', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(88, '706304', 'COMMISSION DE TRANSFERT-Cameroun', 'Commission de transfert', NULL, 'Cameroun', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(89, '706305', 'COMMISSION DE TRANSFERT-Sénégal', 'Commission de transfert', NULL, 'Sénégal', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(90, '706306', 'COMMISSION DE TRANSFERT-Côte d\'Ivoire', 'Commission de transfert', NULL, 'Côte d\'Ivoire', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(91, '706307', 'COMMISSION DE TRANSFERT-Benin', 'Commission de transfert', NULL, 'Benin', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(92, '706308', 'COMMISSION DE TRANSFERT-Burkina Faso', 'Commission de transfert', NULL, 'Burkina Faso', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(93, '706309', 'COMMISSION DE TRANSFERT-Congo Brazzaville', 'Commission de transfert', NULL, 'Congo Brazzaville', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(94, '706310', 'COMMISSION DE TRANSFERT-Congo Kinshasa', 'Commission de transfert', NULL, 'Congo Kinshasa', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(95, '706311', 'COMMISSION DE TRANSFERT-Gabon', 'Commission de transfert', NULL, 'Gabon', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(96, '706312', 'COMMISSION DE TRANSFERT-Tchad', 'Commission de transfert', NULL, 'Tchad', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(97, '706313', 'COMMISSION DE TRANSFERT-Mali', 'Commission de transfert', NULL, 'Mali', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(98, '706314', 'COMMISSION DE TRANSFERT-Togo', 'Commission de transfert', NULL, 'Togo', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(99, '706315', 'COMMISSION DE TRANSFERT-Mexique', 'Commission de transfert', NULL, 'Mexique', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(100, '706316', 'COMMISSION DE TRANSFERT-Inde', 'Commission de transfert', NULL, 'Inde', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(101, '706317', 'COMMISSION DE TRANSFERT-Algérie', 'Commission de transfert', NULL, 'Algérie', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(102, '706318', 'COMMISSION DE TRANSFERT-Guinée', 'Commission de transfert', NULL, 'Guinée', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(103, '706319', 'COMMISSION DE TRANSFERT-Tunisie', 'Commission de transfert', NULL, 'Tunisie', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(104, '706320', 'COMMISSION DE TRANSFERT-Maroc', 'Commission de transfert', NULL, 'Maroc', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(105, '706321', 'COMMISSION DE TRANSFERT-Niger', 'Commission de transfert', NULL, 'Niger', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(106, '706322', 'COMMISSION DE TRANSFERT-Afrique de l\'est', 'Commission de transfert', NULL, 'Afrique de l\'est', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(107, '706323', 'COMMISSION DE TRANSFERT-Autres pays', 'Commission de transfert', NULL, 'Autres pays', '7063', 3, 1, 1, '2026-03-18 01:09:00', '2026-03-18 01:15:49'),
(108, '706501', 'FRAIS DE SERVICE ATS-France', 'Frais de services ATS', NULL, 'France', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(109, '706502', 'FRAIS DE SERVICE ATS-Allemagne', 'Frais de services ATS', NULL, 'Allemagne', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(110, '706503', 'FRAIS DE SERVICE ATS-Belgique', 'Frais de services ATS', NULL, 'Belgique', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(111, '706504', 'FRAIS DE SERVICE ATS-Cameroun', 'Frais de services ATS', NULL, 'Cameroun', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(112, '706505', 'FRAIS DE SERVICE ATS-Sénégal', 'Frais de services ATS', NULL, 'Sénégal', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(113, '706506', 'FRAIS DE SERVICE ATS-Côte d\'Ivoire', 'Frais de services ATS', NULL, 'Côte d\'Ivoire', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(114, '706507', 'FRAIS DE SERVICE ATS-Benin', 'Frais de services ATS', NULL, 'Benin', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(115, '706508', 'FRAIS DE SERVICE ATS-Burkina Faso', 'Frais de services ATS', NULL, 'Burkina Faso', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(116, '706509', 'FRAIS DE SERVICE ATS-Congo Brazzaville', 'Frais de services ATS', NULL, 'Congo Brazzaville', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(117, '706510', 'FRAIS DE SERVICE ATS-Congo Kinshasa', 'Frais de services ATS', NULL, 'Congo Kinshasa', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(118, '706511', 'FRAIS DE SERVICE ATS-Gabon', 'Frais de services ATS', NULL, 'Gabon', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(119, '706512', 'FRAIS DE SERVICE ATS-Tchad', 'Frais de services ATS', NULL, 'Tchad', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(120, '706513', 'FRAIS DE SERVICE ATS-Mali', 'Frais de services ATS', NULL, 'Mali', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(121, '706514', 'FRAIS DE SERVICE ATS-Togo', 'Frais de services ATS', NULL, 'Togo', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(122, '706515', 'FRAIS DE SERVICE ATS-Mexique', 'Frais de services ATS', NULL, 'Mexique', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(123, '706516', 'FRAIS DE SERVICE ATS-Inde', 'Frais de services ATS', NULL, 'Inde', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(124, '706517', 'FRAIS DE SERVICE ATS-Algérie', 'Frais de services ATS', NULL, 'Algérie', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(125, '706518', 'FRAIS DE SERVICE ATS-Guinée', 'Frais de services ATS', NULL, 'Guinée', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(126, '706519', 'FRAIS DE SERVICE ATS-Tunisie', 'Frais de services ATS', NULL, 'Tunisie', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(127, '706520', 'FRAIS DE SERVICE ATS-Maroc', 'Frais de services ATS', NULL, 'Maroc', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(128, '706521', 'FRAIS DE SERVICE ATS-Niger', 'Frais de services ATS', NULL, 'Niger', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(129, '706522', 'FRAIS DE SERVICE ATS-Afrique de l\'est', 'Frais de services ATS', NULL, 'Afrique de l\'est', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(130, '706523', 'FRAIS DE SERVICE ATS-Autres pays', 'Frais de services ATS', NULL, 'Autres pays', '7065', 3, 1, 1, '2026-03-18 01:09:55', '2026-03-18 01:15:49'),
(131, '706601', 'CA PLACEMENT-France', 'CA Placement', NULL, 'France', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(132, '706602', 'CA PLACEMENT-Allemagne', 'CA Placement', NULL, 'Allemagne', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(133, '706603', 'CA PLACEMENT-Belgique', 'CA Placement', NULL, 'Belgique', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(134, '706604', 'CA PLACEMENT-Cameroun', 'CA Placement', NULL, 'Cameroun', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(135, '706605', 'CA PLACEMENT-Sénégal', 'CA Placement', NULL, 'Sénégal', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(136, '706606', 'CA PLACEMENT-Côte d\'Ivoire', 'CA Placement', NULL, 'Côte d\'Ivoire', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(137, '706607', 'CA PLACEMENT-Benin', 'CA Placement', NULL, 'Benin', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(138, '706608', 'CA PLACEMENT-Burkina Faso', 'CA Placement', NULL, 'Burkina Faso', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(139, '706609', 'CA PLACEMENT-Congo Brazzaville', 'CA Placement', NULL, 'Congo Brazzaville', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(140, '706610', 'CA PLACEMENT-Congo Kinshasa', 'CA Placement', NULL, 'Congo Kinshasa', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(141, '706611', 'CA PLACEMENT-Gabon', 'CA Placement', NULL, 'Gabon', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(142, '706612', 'CA PLACEMENT-Tchad', 'CA Placement', NULL, 'Tchad', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(143, '706613', 'CA PLACEMENT-Mali', 'CA Placement', NULL, 'Mali', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(144, '706614', 'CA PLACEMENT-Togo', 'CA Placement', NULL, 'Togo', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(145, '706615', 'CA PLACEMENT-Mexique', 'CA Placement', NULL, 'Mexique', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(146, '706616', 'CA PLACEMENT-Inde', 'CA Placement', NULL, 'Inde', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(147, '706617', 'CA PLACEMENT-Algérie', 'CA Placement', NULL, 'Algérie', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(148, '706618', 'CA PLACEMENT-Guinée', 'CA Placement', NULL, 'Guinée', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(149, '706619', 'CA PLACEMENT-Tunisie', 'CA Placement', NULL, 'Tunisie', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(150, '706620', 'CA PLACEMENT-Maroc', 'CA Placement', NULL, 'Maroc', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(151, '706621', 'CA PLACEMENT-Niger', 'CA Placement', NULL, 'Niger', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(152, '706622', 'CA PLACEMENT-Afrique de l\'est', 'CA Placement', NULL, 'Afrique de l\'est', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(153, '706623', 'CA PLACEMENT-Autres pays', 'CA Placement', NULL, 'Autres pays', '7066', 3, 1, 1, '2026-03-18 01:10:50', '2026-03-18 01:15:49'),
(154, '706204', 'FRAIS DE GESTION-Cameroun', 'FRAIS DE GESTION', NULL, 'Cameroun', NULL, 3, 1, 1, '2026-03-20 22:18:50', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `service_account_rules`
--

CREATE TABLE `service_account_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `operation_type_label` varchar(150) NOT NULL,
  `destination_country_label` varchar(150) DEFAULT NULL,
  `commercial_country_label` varchar(150) DEFAULT NULL,
  `service_account_id` int(10) UNSIGNED NOT NULL,
  `priority_order` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_account_rules`
--

INSERT INTO `service_account_rules` (`id`, `operation_type_label`, `destination_country_label`, `commercial_country_label`, `service_account_id`, `priority_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Frais de services AVI', 'Allemagne', 'France', 16, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(2, 'Frais de services AVI', 'Allemagne', 'Allemagne', 17, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(3, 'Frais de services AVI', 'Allemagne', 'Belgique', 18, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(4, 'Frais de services AVI', 'Allemagne', 'Cameroun', 19, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(5, 'Frais de services AVI', 'Allemagne', 'Sénégal', 20, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(6, 'Frais de services AVI', 'Allemagne', 'Côte d\'Ivoire', 21, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(7, 'Frais de services AVI', 'Allemagne', 'Benin', 22, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(8, 'Frais de services AVI', 'Allemagne', 'Burkina Faso', 23, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(9, 'Frais de services AVI', 'Allemagne', 'Congo Brazzaville', 24, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(10, 'Frais de services AVI', 'Allemagne', 'Congo Kinshasa', 25, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(11, 'Frais de services AVI', 'Allemagne', 'Gabon', 26, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(12, 'Frais de services AVI', 'Allemagne', 'Tchad', 27, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(13, 'Frais de services AVI', 'Allemagne', 'Mali', 28, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(14, 'Frais de services AVI', 'Allemagne', 'Togo', 29, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(15, 'Frais de services AVI', 'Allemagne', 'Mexique', 30, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(16, 'Frais de services AVI', 'Allemagne', 'Inde', 31, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(17, 'Frais de services AVI', 'Allemagne', 'Algérie', 32, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(18, 'Frais de services AVI', 'Allemagne', 'Guinée', 33, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(19, 'Frais de services AVI', 'Allemagne', 'Tunisie', 34, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(20, 'Frais de services AVI', 'Allemagne', 'Maroc', 35, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(21, 'Frais de services AVI', 'Allemagne', 'Niger', 36, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(22, 'Frais de services AVI', 'Allemagne', 'Afrique de l\'est', 37, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(23, 'Frais de services AVI', 'Allemagne', 'Autres pays', 38, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(24, 'Frais de services AVI', 'Belgique', 'France', 39, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(25, 'Frais de services AVI', 'Belgique', 'Allemagne', 40, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(26, 'Frais de services AVI', 'Belgique', 'Belgique', 41, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(27, 'Frais de services AVI', 'Belgique', 'Cameroun', 42, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(28, 'Frais de services AVI', 'Belgique', 'Sénégal', 43, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(29, 'Frais de services AVI', 'Belgique', 'Côte d\'Ivoire', 44, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(30, 'Frais de services AVI', 'Belgique', 'Benin', 45, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(31, 'Frais de services AVI', 'Belgique', 'Burkina Faso', 46, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(32, 'Frais de services AVI', 'Belgique', 'Congo Brazzaville', 47, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(33, 'Frais de services AVI', 'Belgique', 'Congo Kinshasa', 48, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(34, 'Frais de services AVI', 'Belgique', 'Gabon', 49, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(35, 'Frais de services AVI', 'Belgique', 'Tchad', 50, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(36, 'Frais de services AVI', 'Belgique', 'Mali', 51, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(37, 'Frais de services AVI', 'Belgique', 'Togo', 52, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(38, 'Frais de services AVI', 'Belgique', 'Mexique', 53, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(39, 'Frais de services AVI', 'Belgique', 'Inde', 54, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(40, 'Frais de services AVI', 'Belgique', 'Algérie', 55, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(41, 'Frais de services AVI', 'Belgique', 'Guinée', 56, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(42, 'Frais de services AVI', 'Belgique', 'Tunisie', 57, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(43, 'Frais de services AVI', 'Belgique', 'Maroc', 58, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(44, 'Frais de services AVI', 'Belgique', 'Niger', 59, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(45, 'Frais de services AVI', 'Belgique', 'Afrique de l\'est', 60, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(46, 'Frais de services AVI', 'Belgique', 'Autres pays', 61, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(47, 'Frais de services AVI', 'France', 'France', 62, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(48, 'Frais de services AVI', 'France', 'Allemagne', 63, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(49, 'Frais de services AVI', 'France', 'Belgique', 64, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(50, 'Frais de services AVI', 'France', 'Cameroun', 65, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(51, 'Frais de services AVI', 'France', 'Sénégal', 66, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(52, 'Frais de services AVI', 'France', 'Côte d\'Ivoire', 67, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(53, 'Frais de services AVI', 'France', 'Benin', 68, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(54, 'Frais de services AVI', 'France', 'Burkina Faso', 69, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(55, 'Frais de services AVI', 'France', 'Congo Brazzaville', 70, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(56, 'Frais de services AVI', 'France', 'Congo Kinshasa', 71, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(57, 'Frais de services AVI', 'France', 'Gabon', 72, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(58, 'Frais de services AVI', 'France', 'Tchad', 73, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(59, 'Frais de services AVI', 'France', 'Mali', 74, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(60, 'Frais de services AVI', 'France', 'Togo', 75, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(61, 'Frais de services AVI', 'France', 'Mexique', 76, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(62, 'Frais de services AVI', 'France', 'Inde', 77, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(63, 'Frais de services AVI', 'France', 'Algérie', 78, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(64, 'Frais de services AVI', 'France', 'Guinée', 79, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(65, 'Frais de services AVI', 'France', 'Tunisie', 80, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(66, 'Frais de services AVI', 'France', 'Maroc', 81, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(67, 'Frais de services AVI', 'France', 'Niger', 82, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(68, 'Frais de services AVI', 'France', 'Afrique de l\'est', 83, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(69, 'Frais de services AVI', 'France', 'Autres pays', 84, 1, 1, '2026-03-18 01:11:58', '2026-03-18 01:16:37'),
(128, 'Commission de transfert', NULL, 'France', 85, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(129, 'Commission de transfert', NULL, 'Allemagne', 86, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(130, 'Commission de transfert', NULL, 'Belgique', 87, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(131, 'Commission de transfert', NULL, 'Cameroun', 88, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(132, 'Commission de transfert', NULL, 'Sénégal', 89, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(133, 'Commission de transfert', NULL, 'Côte d\'Ivoire', 90, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(134, 'Commission de transfert', NULL, 'Benin', 91, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(135, 'Commission de transfert', NULL, 'Burkina Faso', 92, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(136, 'Commission de transfert', NULL, 'Congo Brazzaville', 93, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(137, 'Commission de transfert', NULL, 'Congo Kinshasa', 94, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(138, 'Commission de transfert', NULL, 'Gabon', 95, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(139, 'Commission de transfert', NULL, 'Tchad', 96, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(140, 'Commission de transfert', NULL, 'Mali', 97, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(141, 'Commission de transfert', NULL, 'Togo', 98, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(142, 'Commission de transfert', NULL, 'Mexique', 99, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(143, 'Commission de transfert', NULL, 'Inde', 100, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(144, 'Commission de transfert', NULL, 'Algérie', 101, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(145, 'Commission de transfert', NULL, 'Guinée', 102, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(146, 'Commission de transfert', NULL, 'Tunisie', 103, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(147, 'Commission de transfert', NULL, 'Maroc', 104, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(148, 'Commission de transfert', NULL, 'Niger', 105, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(149, 'Commission de transfert', NULL, 'Afrique de l\'est', 106, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(150, 'Commission de transfert', NULL, 'Autres pays', 107, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(151, 'Frais de services ATS', NULL, 'France', 108, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(152, 'Frais de services ATS', NULL, 'Allemagne', 109, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(153, 'Frais de services ATS', NULL, 'Belgique', 110, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(154, 'Frais de services ATS', NULL, 'Cameroun', 111, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(155, 'Frais de services ATS', NULL, 'Sénégal', 112, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(156, 'Frais de services ATS', NULL, 'Côte d\'Ivoire', 113, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(157, 'Frais de services ATS', NULL, 'Benin', 114, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(158, 'Frais de services ATS', NULL, 'Burkina Faso', 115, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(159, 'Frais de services ATS', NULL, 'Congo Brazzaville', 116, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(160, 'Frais de services ATS', NULL, 'Congo Kinshasa', 117, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(161, 'Frais de services ATS', NULL, 'Gabon', 118, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(162, 'Frais de services ATS', NULL, 'Tchad', 119, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(163, 'Frais de services ATS', NULL, 'Mali', 120, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(164, 'Frais de services ATS', NULL, 'Togo', 121, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(165, 'Frais de services ATS', NULL, 'Mexique', 122, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(166, 'Frais de services ATS', NULL, 'Inde', 123, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(167, 'Frais de services ATS', NULL, 'Algérie', 124, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(168, 'Frais de services ATS', NULL, 'Guinée', 125, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(169, 'Frais de services ATS', NULL, 'Tunisie', 126, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(170, 'Frais de services ATS', NULL, 'Maroc', 127, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(171, 'Frais de services ATS', NULL, 'Niger', 128, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(172, 'Frais de services ATS', NULL, 'Afrique de l\'est', 129, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(173, 'Frais de services ATS', NULL, 'Autres pays', 130, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(174, 'CA Placement', NULL, 'France', 131, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(175, 'CA Placement', NULL, 'Allemagne', 132, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(176, 'CA Placement', NULL, 'Belgique', 133, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(177, 'CA Placement', NULL, 'Cameroun', 134, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(178, 'CA Placement', NULL, 'Sénégal', 135, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(179, 'CA Placement', NULL, 'Côte d\'Ivoire', 136, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(180, 'CA Placement', NULL, 'Benin', 137, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(181, 'CA Placement', NULL, 'Burkina Faso', 138, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(182, 'CA Placement', NULL, 'Congo Brazzaville', 139, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(183, 'CA Placement', NULL, 'Congo Kinshasa', 140, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(184, 'CA Placement', NULL, 'Gabon', 141, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(185, 'CA Placement', NULL, 'Tchad', 142, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(186, 'CA Placement', NULL, 'Mali', 143, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(187, 'CA Placement', NULL, 'Togo', 144, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(188, 'CA Placement', NULL, 'Mexique', 145, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(189, 'CA Placement', NULL, 'Inde', 146, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(190, 'CA Placement', NULL, 'Algérie', 147, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(191, 'CA Placement', NULL, 'Guinée', 148, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(192, 'CA Placement', NULL, 'Tunisie', 149, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(193, 'CA Placement', NULL, 'Maroc', 150, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(194, 'CA Placement', NULL, 'Niger', 151, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(195, 'CA Placement', NULL, 'Afrique de l\'est', 152, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(196, 'CA Placement', NULL, 'Autres pays', 153, 1, 1, '2026-03-18 01:13:44', '2026-03-18 01:16:37'),
(255, 'CA DIVERS', NULL, NULL, 10, 1, 1, '2026-03-18 01:14:42', '2026-03-18 01:16:37'),
(256, 'CA DEBOURS LOGEMENT', NULL, NULL, 11, 1, 1, '2026-03-18 01:14:42', '2026-03-18 01:16:37'),
(257, 'CA DEBOURS ASSURANCE', NULL, NULL, 12, 1, 1, '2026-03-18 01:14:42', '2026-03-18 01:16:37'),
(258, 'FRAIS DEBOURS MICROFINANCE', NULL, NULL, 13, 1, 1, '2026-03-18 01:14:42', '2026-03-18 01:16:37'),
(259, 'CA COURTAGE PRÊT', NULL, NULL, 14, 1, 1, '2026-03-18 01:14:42', '2026-03-18 01:16:37'),
(260, 'CA LOGEMENT', NULL, NULL, 15, 1, 1, '2026-03-18 01:14:42', '2026-03-18 01:16:37');

-- --------------------------------------------------------

--
-- Structure de la table `statuses`
--

CREATE TABLE `statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `statuses`
--

INSERT INTO `statuses` (`id`, `name`, `sort_order`) VALUES
(1, 'Étudiant en attente', 1),
(2, 'Étudiant actif', 2),
(3, 'Étudiant dormant', 3),
(4, 'Étudiant remboursé', 4);

-- --------------------------------------------------------

--
-- Structure de la table `support_requests`
--

CREATE TABLE `support_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `request_type` enum('access','bug','question') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `treasury_accounts`
--

CREATE TABLE `treasury_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_label` varchar(150) NOT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `subsidiary_name` varchar(150) DEFAULT NULL,
  `zone_code` varchar(10) DEFAULT NULL,
  `country_label` varchar(100) DEFAULT NULL,
  `country_type` varchar(50) DEFAULT NULL,
  `payment_place` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `currency_code` varchar(10) DEFAULT NULL,
  `opening_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `treasury_accounts`
--

INSERT INTO `treasury_accounts` (`id`, `account_code`, `account_label`, `bank_name`, `subsidiary_name`, `zone_code`, `country_label`, `country_type`, `payment_place`, `is_active`, `created_at`, `currency_code`, `opening_balance`, `current_balance`, `updated_at`) VALUES
(1, '5120101', 'FR_LCL_C', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-19 22:59:36', 'EUR', 1000000.00, 1000000.00, NULL),
(2, '5120401', 'CM_BAC', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-19 22:59:36', 'XAF', 1000000.00, 1000000.00, NULL),
(3, '5120502', 'SN_ECOBQ', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-19 22:59:36', 'XOF', 1000000.00, 1000000.00, NULL),
(4, '5120601', 'CIV_ECOBQ', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-19 22:59:36', 'XOF', 1000000.00, 1000000.00, NULL),
(5, '5122001', 'MA_ATTI', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-19 22:59:36', 'EUR', 1000000.00, 1000000.00, NULL),
(6, '5121701', 'ALG_BNP', 'ALG_BNP', 'Studely', 'AN', 'Algérie', 'Filiale', 'Local', 1, '2026-03-20 22:18:50', 'EUR', 1000000.00, 1000000.00, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `treasury_currency_mapping`
--

CREATE TABLE `treasury_currency_mapping` (
  `id` int(10) UNSIGNED NOT NULL,
  `match_type` enum('PREFIX','CONTAINS','EXACT') NOT NULL,
  `match_value` varchar(150) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `priority_order` int(11) NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `treasury_currency_mapping`
--

INSERT INTO `treasury_currency_mapping` (`id`, `match_type`, `match_value`, `currency_code`, `priority_order`, `is_active`, `created_at`) VALUES
(1, 'PREFIX', 'FR_', 'EUR', 10, 1, '2026-03-18 21:11:38'),
(2, 'PREFIX', 'BE_', 'EUR', 10, 1, '2026-03-18 21:11:38'),
(3, 'PREFIX', 'ALG_', 'EUR', 10, 1, '2026-03-18 21:11:38'),
(4, 'PREFIX', 'MA_', 'EUR', 10, 1, '2026-03-18 21:11:38'),
(5, 'PREFIX', 'TN_', 'EUR', 10, 1, '2026-03-18 21:11:38'),
(6, 'PREFIX', 'CM_', 'XAF', 20, 1, '2026-03-18 21:11:38'),
(7, 'PREFIX', 'SF_CM_', 'XAF', 20, 1, '2026-03-18 21:11:38'),
(8, 'PREFIX', 'CD_', 'XAF', 20, 1, '2026-03-18 21:11:38'),
(9, 'PREFIX', 'RD_', 'XAF', 20, 1, '2026-03-18 21:11:38'),
(10, 'PREFIX', 'GB_', 'XAF', 20, 1, '2026-03-18 21:11:38'),
(11, 'PREFIX', 'SF_CHD_', 'XAF', 20, 1, '2026-03-18 21:11:38'),
(12, 'PREFIX', 'SF_TCHAD_', 'XAF', 20, 1, '2026-03-18 21:11:38'),
(13, 'PREFIX', 'SN_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(14, 'PREFIX', 'SF_SN_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(15, 'PREFIX', 'BFA_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(16, 'PREFIX', 'CIV_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(17, 'PREFIX', 'SF_CIV_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(18, 'PREFIX', 'ML_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(19, 'PREFIX', 'TGO_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(20, 'PREFIX', 'SF_TG_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(21, 'PREFIX', 'BN_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(22, 'PREFIX', 'SF_BN_', 'XOF', 30, 1, '2026-03-18 21:11:38'),
(23, 'PREFIX', 'NG_', 'USD', 40, 1, '2026-03-18 21:11:38'),
(24, 'CONTAINS', 'QUONTO', 'EUR', 100, 1, '2026-03-18 21:11:38'),
(25, 'CONTAINS', 'REVOLUT', 'EUR', 100, 1, '2026-03-18 21:11:38'),
(26, 'CONTAINS', 'LCL', 'EUR', 100, 1, '2026-03-18 21:11:38'),
(27, 'CONTAINS', 'CIC', 'EUR', 100, 1, '2026-03-18 21:11:38'),
(28, 'CONTAINS', 'CCOOP', 'EUR', 100, 1, '2026-03-18 21:11:38'),
(29, 'CONTAINS', 'SPENDESK', 'EUR', 100, 1, '2026-03-18 21:11:38'),
(30, 'CONTAINS', 'MANGO', 'EUR', 100, 1, '2026-03-18 21:11:38'),
(31, 'CONTAINS', 'ATTI', 'EUR', 100, 1, '2026-03-18 21:11:38');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role_id`, `is_active`, `last_login_at`, `archived_at`, `created_at`) VALUES
(1, 'admin', '$2y$10$TLhKOHu6WQmUROqhcksMpuKGeS5VBykyuo3cMb1abDm6l8psZOV/6', 1, 1, '2026-03-18 05:04:46', NULL, '2026-03-14 17:50:21'),
(2, 'manager01', '$2y$10$TLhKOHu6WQmUROqhcksMpuKGeS5VBykyuo3cMb1abDm6l8psZOV/6', 3, 1, NULL, NULL, '2026-03-14 17:50:21'),
(3, 'viewer01', '$2y$10$TLhKOHu6WQmUROqhcksMpuKGeS5VBykyuo3cMb1abDm6l8psZOV/6', 4, 1, NULL, NULL, '2026-03-14 17:50:21'),
(4, 'operateur01', '$2y$10$wPinMY26YOOkKT0NpvBB/OSd.aretJebRxofpYf/CY9JyLt9zsJLW', 5, 1, '2026-03-16 00:14:09', NULL, '2026-03-15 20:49:33');

-- --------------------------------------------------------

--
-- Structure de la table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_logs`
--

INSERT INTO `user_logs` (`id`, `user_id`, `action_type`, `module_name`, `target_type`, `target_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'auth', 'user', 1, 'Connexion de l\'utilisateur admin', '127.0.0.1', 'Mozilla/5.0', '2026-03-14 17:50:21'),
(2, 1, 'create_user', 'admin', 'user', 2, 'Création de l\'utilisateur manager01', '127.0.0.1', 'Mozilla/5.0', '2026-03-14 17:50:21'),
(3, 1, 'create_user', 'admin', 'user', 3, 'Création de l\'utilisateur viewer01', '127.0.0.1', 'Mozilla/5.0', '2026-03-14 17:50:21'),
(4, 1, 'validate_import', 'imports', 'batch', 1, 'Validation du fichier import_demo_mars.csv', '127.0.0.1', 'Mozilla/5.0', '2026-03-14 17:50:21'),
(5, 1, 'correct_rejected_row', 'imports', 'import_row', 4, 'Correction manuelle d\'une ligne rejetée', '127.0.0.1', 'Mozilla/5.0', '2026-03-14 17:50:21'),
(6, 1, 'login', 'auth', 'user', 1, 'Connexion de l\'utilisateur admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 19:16:11'),
(7, 1, 'create_role', 'admin', 'role', 5, 'Création du rôle operator (Operator)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 19:38:42'),
(8, 1, 'update_access_matrix', 'admin', 'role_permissions', NULL, 'Sauvegarde de la matrice des accès.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 19:39:40'),
(9, 1, 'update_access_matrix', 'admin', 'role_permissions', NULL, 'Rôle Operator (operator) — Ajoutées : statements_export_single, statements_export_bulk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 20:48:01'),
(10, 1, 'create_user', 'admin', 'user', 4, 'Création de l\'utilisateur operateur01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 20:49:33'),
(11, 4, 'login', 'auth', 'user', 4, 'Connexion de l\'utilisateur operateur01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 20:50:19'),
(12, 4, 'login', 'auth', 'user', 4, 'Connexion de l\'utilisateur operateur01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 00:14:09'),
(13, 1, 'login', 'auth', 'user', 1, 'Connexion de l\'utilisateur admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 01:38:15'),
(14, 1, 'validate_import_batch', 'imports', 'import_batch', 2, 'Validation du batch #2 : 0 valide(s), 0 rejetée(s)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 01:50:36'),
(15, 1, 'validate_import_batch', 'imports', 'import_batch', 2, 'Validation du batch #2 : 0 valide(s), 0 rejetée(s)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 01:50:52'),
(16, 1, 'validate_import_batch', 'imports', 'import_batch', 1, 'Validation du batch #1 : 0 valide(s), 0 rejetée(s)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 01:51:31'),
(17, 1, 'validate_import_batch', 'imports', 'import_batch', 1, 'Validation du batch #1 : 0 valide(s), 0 rejetée(s)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 01:51:34'),
(18, 1, 'validate_import_batch', 'imports', 'import_batch', 1, 'Validation du batch #1 : 0 valide(s), 0 rejetée(s)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 01:51:47'),
(19, 1, 'create_operation', 'operations', 'bulk_targeted_operation', NULL, 'Création de 0 opération(s) via ciblage accounts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 04:17:35'),
(20, 1, 'login', 'auth', 'user', 1, 'Connexion de l\'utilisateur admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 17:18:26'),
(21, 1, 'manual_operation_create', 'manual_actions', 'operation', 14, 'Création d’une opération manuelle', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 17:36:57'),
(22, 1, 'correct_rejected_row', 'imports', 'import_row', 8, 'Correction manuelle de la ligne rejetée #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 19:30:00'),
(23, 1, 'create_client', 'clients', 'client', 7, 'Création du client CLT02013 avec compte bancaire auto #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 19:37:08'),
(24, 1, 'export_single_statement', 'statements', 'client', 1, 'Export PDF individuel du client #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 23:31:17'),
(25, 1, 'login', 'auth', 'user', 1, 'Connexion de l\'utilisateur admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 05:04:46'),
(26, 1, '', '', 'operation', 18, 'Création d’une opération', NULL, NULL, '2026-03-20 17:55:52'),
(27, 1, '', '', 'operation', 19, 'Création d’une opération', NULL, NULL, '2026-03-20 18:58:22');

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vw_client_accounting_context`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vw_client_accounting_context` (
`client_id` int(11)
,`client_code` varchar(50)
,`client_account_code` varchar(50)
,`initial_treasury_account_id` int(10) unsigned
,`treasury_account_code` varchar(20)
,`treasury_account_label` varchar(150)
,`treasury_currency_code` varchar(10)
,`destination_country_label` varchar(150)
,`commercial_country_label` varchar(150)
,`main_service_label` varchar(200)
,`main_service_type_id` int(10) unsigned
,`destination_country_id` int(10) unsigned
,`commercial_country_id` int(10) unsigned
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vw_operation_rule_resolution`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vw_operation_rule_resolution` (
`rule_id` int(10) unsigned
,`operation_type_code` varchar(100)
,`operation_type_label` varchar(150)
,`service_code` varchar(100)
,`service_label` varchar(150)
,`destination_country_label` varchar(150)
,`commercial_country_label` varchar(150)
,`debit_account_type` enum('CLIENT','TREASURY','TREASURY_SOURCE','TREASURY_TARGET')
,`credit_account_type` enum('CLIENT','TREASURY','TREASURY_SOURCE','TREASURY_TARGET')
,`service_account_code` varchar(20)
,`service_account_label` varchar(255)
,`rule_code` varchar(100)
,`description` text
,`is_active` tinyint(1)
);

-- --------------------------------------------------------

--
-- Structure de la vue `vw_client_accounting_context`
--
DROP TABLE IF EXISTS `vw_client_accounting_context`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_client_accounting_context`  AS SELECT `c`.`id` AS `client_id`, `c`.`client_code` AS `client_code`, `c`.`generated_client_account` AS `client_account_code`, `c`.`initial_treasury_account_id` AS `initial_treasury_account_id`, `ta`.`account_code` AS `treasury_account_code`, `ta`.`account_label` AS `treasury_account_label`, `ta`.`currency_code` AS `treasury_currency_code`, `dc`.`name` AS `destination_country_label`, `cc`.`name` AS `commercial_country_label`, `ms`.`name` AS `main_service_label`, `c`.`main_service_type_id` AS `main_service_type_id`, `c`.`destination_country_id` AS `destination_country_id`, `c`.`commercial_country_id` AS `commercial_country_id` FROM ((((`clients` `c` left join `treasury_accounts` `ta` on(`ta`.`id` = `c`.`initial_treasury_account_id`)) left join `ref_destination_countries` `dc` on(`dc`.`id` = `c`.`destination_country_id`)) left join `ref_commercial_countries` `cc` on(`cc`.`id` = `c`.`commercial_country_id`)) left join `ref_main_service_types` `ms` on(`ms`.`id` = `c`.`main_service_type_id`)) ;

-- --------------------------------------------------------

--
-- Structure de la vue `vw_operation_rule_resolution`
--
DROP TABLE IF EXISTS `vw_operation_rule_resolution`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_operation_rule_resolution`  AS SELECT `oar`.`id` AS `rule_id`, `rot`.`code` AS `operation_type_code`, `rot`.`label` AS `operation_type_label`, `rs`.`code` AS `service_code`, `rs`.`label` AS `service_label`, `oar`.`destination_country_label` AS `destination_country_label`, `oar`.`commercial_country_label` AS `commercial_country_label`, `oar`.`debit_account_type` AS `debit_account_type`, `oar`.`credit_account_type` AS `credit_account_type`, `sa`.`account_code` AS `service_account_code`, `sa`.`account_label` AS `service_account_label`, `oar`.`rule_code` AS `rule_code`, `oar`.`description` AS `description`, `oar`.`is_active` AS `is_active` FROM (((`operation_accounting_rules` `oar` join `ref_operation_types` `rot` on(`rot`.`id` = `oar`.`operation_type_id`)) left join `ref_services` `rs` on(`rs`.`id` = `oar`.`service_id`)) left join `service_accounts` `sa` on(`sa`.`id` = `oar`.`service_account_id`)) WHERE `oar`.`is_active` = 1 ;

--
-- Index pour les tables déchargées
--

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
-- Index pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bank_accounts_type` (`account_type_id`),
  ADD KEY `idx_bank_accounts_category` (`account_category_id`),
  ADD KEY `idx_bank_accounts_country` (`country`),
  ADD KEY `idx_bank_accounts_is_active` (`is_active`);

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
  ADD KEY `idx_clients_status_id` (`status_id`),
  ADD KEY `idx_clients_category_id` (`category_id`),
  ADD KEY `idx_clients_country` (`country`),
  ADD KEY `idx_clients_is_active` (`is_active`),
  ADD KEY `idx_clients_email` (`email`),
  ADD KEY `idx_clients_phone` (`phone`),
  ADD KEY `idx_clients_origin_country_id` (`origin_country_id`),
  ADD KEY `idx_clients_destination_country_id` (`destination_country_id`),
  ADD KEY `idx_clients_commercial_country_id` (`commercial_country_id`),
  ADD KEY `idx_clients_client_type_id` (`client_type_id`),
  ADD KEY `idx_clients_client_status_ref_id` (`client_status_ref_id`),
  ADD KEY `idx_clients_main_service_type_id` (`main_service_type_id`),
  ADD KEY `idx_clients_initial_treasury_account_id` (`initial_treasury_account_id`),
  ADD KEY `idx_clients_account_state_id` (`account_state_id`),
  ADD KEY `idx_clients_account_category_ref_id` (`account_category_ref_id`);

--
-- Index pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_client_bank_account` (`client_id`,`bank_account_id`),
  ADD KEY `fk_client_bank_accounts_account` (`bank_account_id`);

--
-- Index pour la table `import_batches`
--
ALTER TABLE `import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_import_batches_user` (`imported_by`),
  ADD KEY `idx_import_batches_status` (`status`);

--
-- Index pour la table `import_rows`
--
ALTER TABLE `import_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_import_rows_corrected_by` (`corrected_by`),
  ADD KEY `idx_import_rows_batch_id` (`batch_id`),
  ADD KEY `idx_import_rows_status` (`status`),
  ADD KEY `idx_import_rows_client_code` (`client_code`);

--
-- Index pour la table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_operations_client_id` (`client_id`),
  ADD KEY `idx_operations_bank_account` (`bank_account_id`),
  ADD KEY `idx_operations_date` (`operation_date`),
  ADD KEY `idx_operations_type` (`operation_type`),
  ADD KEY `idx_operations_kind` (`operation_kind`),
  ADD KEY `idx_operations_created_by` (`created_by`);

--
-- Index pour la table `operation_accounting_rules`
--
ALTER TABLE `operation_accounting_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rule_code` (`rule_code`),
  ADD KEY `idx_operation_type_id` (`operation_type_id`),
  ADD KEY `idx_service_id` (`service_id`),
  ADD KEY `idx_service_account_id` (`service_account_id`);

--
-- Index pour la table `operation_type_services`
--
ALTER TABLE `operation_type_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_operation_type_service` (`operation_type_id`,`service_id`),
  ADD KEY `idx_ots_operation_type_id` (`operation_type_id`),
  ADD KEY `idx_ots_service_id` (`service_id`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_code` (`permission_code`);

--
-- Index pour la table `ref_account_states`
--
ALTER TABLE `ref_account_states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_account_states_name` (`name`);

--
-- Index pour la table `ref_client_statuses`
--
ALTER TABLE `ref_client_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_client_statuses_name` (`name`);

--
-- Index pour la table `ref_client_types`
--
ALTER TABLE `ref_client_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_client_types_name` (`name`);

--
-- Index pour la table `ref_commercial_countries`
--
ALTER TABLE `ref_commercial_countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_commercial_countries_name` (`name`);

--
-- Index pour la table `ref_countries`
--
ALTER TABLE `ref_countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_countries_name` (`name`);

--
-- Index pour la table `ref_destination_countries`
--
ALTER TABLE `ref_destination_countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_destination_countries_name` (`name`);

--
-- Index pour la table `ref_main_service_types`
--
ALTER TABLE `ref_main_service_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_main_service_types_name` (`name`);

--
-- Index pour la table `ref_operation_types`
--
ALTER TABLE `ref_operation_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_operation_types_code` (`code`),
  ADD UNIQUE KEY `uq_ref_operation_types_label` (`label`);

--
-- Index pour la table `ref_services`
--
ALTER TABLE `ref_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref_services_code` (`code`),
  ADD UNIQUE KEY `uq_ref_services_label` (`label`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_code` (`role_code`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Index pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_permission` (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

--
-- Index pour la table `service_accounts`
--
ALTER TABLE `service_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_service_accounts_code` (`account_code`),
  ADD KEY `idx_service_accounts_parent_code` (`parent_account_code`),
  ADD KEY `idx_service_accounts_operation_type` (`operation_type_label`),
  ADD KEY `idx_service_accounts_destination` (`destination_country_label`),
  ADD KEY `idx_service_accounts_commercial` (`commercial_country_label`),
  ADD KEY `idx_service_accounts_is_postable` (`is_postable`);

--
-- Index pour la table `service_account_rules`
--
ALTER TABLE `service_account_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_service_account_rules_triplet` (`operation_type_label`,`destination_country_label`,`commercial_country_label`,`service_account_id`),
  ADD KEY `idx_service_account_rules_service_account_id` (`service_account_id`),
  ADD KEY `idx_service_account_rules_operation_type` (`operation_type_label`),
  ADD KEY `idx_service_account_rules_destination` (`destination_country_label`),
  ADD KEY `idx_service_account_rules_commercial` (`commercial_country_label`);

--
-- Index pour la table `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_requests_status` (`status`),
  ADD KEY `idx_support_requests_type` (`request_type`),
  ADD KEY `idx_support_requests_user_id` (`user_id`),
  ADD KEY `idx_support_requests_created_at` (`created_at`);

--
-- Index pour la table `treasury_accounts`
--
ALTER TABLE `treasury_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_treasury_accounts_code` (`account_code`),
  ADD UNIQUE KEY `uq_treasury_accounts_label` (`account_label`),
  ADD KEY `idx_treasury_accounts_currency_code` (`currency_code`);

--
-- Index pour la table `treasury_currency_mapping`
--
ALTER TABLE `treasury_currency_mapping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tcm_priority` (`priority_order`),
  ADD KEY `idx_tcm_match_type` (`match_type`),
  ADD KEY `idx_tcm_currency_code` (`currency_code`);

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
  ADD KEY `idx_user_logs_module_name` (`module_name`),
  ADD KEY `idx_user_logs_action_type` (`action_type`),
  ADD KEY `idx_user_logs_created_at` (`created_at`),
  ADD KEY `idx_user_logs_target_type_target_id` (`target_type`,`target_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `account_categories`
--
ALTER TABLE `account_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `account_types`
--
ALTER TABLE `account_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `import_batches`
--
ALTER TABLE `import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `import_rows`
--
ALTER TABLE `import_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `operation_accounting_rules`
--
ALTER TABLE `operation_accounting_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT pour la table `operation_type_services`
--
ALTER TABLE `operation_type_services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT pour la table `ref_account_states`
--
ALTER TABLE `ref_account_states`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `ref_client_statuses`
--
ALTER TABLE `ref_client_statuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `ref_client_types`
--
ALTER TABLE `ref_client_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `ref_commercial_countries`
--
ALTER TABLE `ref_commercial_countries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `ref_countries`
--
ALTER TABLE `ref_countries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `ref_destination_countries`
--
ALTER TABLE `ref_destination_countries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `ref_main_service_types`
--
ALTER TABLE `ref_main_service_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `ref_operation_types`
--
ALTER TABLE `ref_operation_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `ref_services`
--
ALTER TABLE `ref_services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=254;

--
-- AUTO_INCREMENT pour la table `service_accounts`
--
ALTER TABLE `service_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT pour la table `service_account_rules`
--
ALTER TABLE `service_account_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=261;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `treasury_currency_mapping`
--
ALTER TABLE `treasury_currency_mapping`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD CONSTRAINT `fk_bank_accounts_category` FOREIGN KEY (`account_category_id`) REFERENCES `account_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bank_accounts_type` FOREIGN KEY (`account_type_id`) REFERENCES `account_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_account_state` FOREIGN KEY (`account_state_id`) REFERENCES `ref_account_states` (`id`),
  ADD CONSTRAINT `fk_clients_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_clients_client_status_ref` FOREIGN KEY (`client_status_ref_id`) REFERENCES `ref_client_statuses` (`id`),
  ADD CONSTRAINT `fk_clients_client_type` FOREIGN KEY (`client_type_id`) REFERENCES `ref_client_types` (`id`),
  ADD CONSTRAINT `fk_clients_commercial_country` FOREIGN KEY (`commercial_country_id`) REFERENCES `ref_commercial_countries` (`id`),
  ADD CONSTRAINT `fk_clients_destination_country` FOREIGN KEY (`destination_country_id`) REFERENCES `ref_destination_countries` (`id`),
  ADD CONSTRAINT `fk_clients_initial_treasury_account` FOREIGN KEY (`initial_treasury_account_id`) REFERENCES `treasury_accounts` (`id`),
  ADD CONSTRAINT `fk_clients_main_service_type` FOREIGN KEY (`main_service_type_id`) REFERENCES `ref_main_service_types` (`id`),
  ADD CONSTRAINT `fk_clients_origin_country` FOREIGN KEY (`origin_country_id`) REFERENCES `ref_countries` (`id`),
  ADD CONSTRAINT `fk_clients_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  ADD CONSTRAINT `fk_client_bank_accounts_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_client_bank_accounts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `import_batches`
--
ALTER TABLE `import_batches`
  ADD CONSTRAINT `fk_import_batches_user` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `import_rows`
--
ALTER TABLE `import_rows`
  ADD CONSTRAINT `fk_import_rows_batch` FOREIGN KEY (`batch_id`) REFERENCES `import_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_import_rows_corrected_by` FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `operations`
--
ALTER TABLE `operations`
  ADD CONSTRAINT `fk_operations_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_operations_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_operations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_operations_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `operation_accounting_rules`
--
ALTER TABLE `operation_accounting_rules`
  ADD CONSTRAINT `fk_oar_operation_type` FOREIGN KEY (`operation_type_id`) REFERENCES `ref_operation_types` (`id`),
  ADD CONSTRAINT `fk_oar_service` FOREIGN KEY (`service_id`) REFERENCES `ref_services` (`id`),
  ADD CONSTRAINT `fk_oar_service_account` FOREIGN KEY (`service_account_id`) REFERENCES `service_accounts` (`id`),
  ADD CONSTRAINT `fk_operation_accounting_rules_operation_type_id` FOREIGN KEY (`operation_type_id`) REFERENCES `ref_operation_types` (`id`),
  ADD CONSTRAINT `fk_operation_accounting_rules_service_account_id` FOREIGN KEY (`service_account_id`) REFERENCES `service_accounts` (`id`),
  ADD CONSTRAINT `fk_operation_accounting_rules_service_id` FOREIGN KEY (`service_id`) REFERENCES `ref_services` (`id`);

--
-- Contraintes pour la table `operation_type_services`
--
ALTER TABLE `operation_type_services`
  ADD CONSTRAINT `fk_ots_operation_type` FOREIGN KEY (`operation_type_id`) REFERENCES `ref_operation_types` (`id`),
  ADD CONSTRAINT `fk_ots_service` FOREIGN KEY (`service_id`) REFERENCES `ref_services` (`id`);

--
-- Contraintes pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `support_requests`
--
ALTER TABLE `support_requests`
  ADD CONSTRAINT `fk_support_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `fk_user_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
