SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS studelyledge
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE studelyledge;

-- =========================================================
-- Nettoyage
-- =========================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS user_logs;
DROP TABLE IF EXISTS import_rows;
DROP TABLE IF EXISTS imports;
DROP TABLE IF EXISTS treasury_movements;
DROP TABLE IF EXISTS operations;
DROP TABLE IF EXISTS ref_services;
DROP TABLE IF EXISTS ref_operation_types;
DROP TABLE IF EXISTS client_bank_accounts;
DROP TABLE IF EXISTS bank_accounts;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS service_accounts;
DROP TABLE IF EXISTS treasury_accounts;
DROP TABLE IF EXISTS account_categories;
DROP TABLE IF EXISTS account_types;
DROP TABLE IF EXISTS statuses;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS support_requests;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Utilisateurs / sécurité
-- =========================================================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(120) NOT NULL UNIQUE,
    label VARCHAR(180) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    role_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(120) NOT NULL,
    module VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NULL,
    entity_id INT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_logs_user_id (user_id),
    INDEX idx_user_logs_module (module),
    INDEX idx_user_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Support
-- =========================================================
CREATE TABLE support_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NULL,
    request_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    status VARCHAR(50) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_support_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_support_requests_type (request_type),
    INDEX idx_support_requests_status (status),
    INDEX idx_support_requests_priority (priority),
    INDEX idx_support_requests_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Référentiels généraux
-- =========================================================
CREATE TABLE statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Trésorerie 512
-- =========================================================
CREATE TABLE treasury_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(50) NOT NULL UNIQUE,
    account_label VARCHAR(255) NOT NULL,
    bank_name VARCHAR(255) NULL,
    subsidiary_name VARCHAR(255) NULL,
    zone_code VARCHAR(100) NULL,
    country_label VARCHAR(150) NULL,
    country_type VARCHAR(150) NULL,
    payment_place VARCHAR(150) NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'EUR',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    INDEX idx_treasury_accounts_code (account_code),
    INDEX idx_treasury_accounts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE treasury_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_treasury_account_id INT NOT NULL,
    target_treasury_account_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    operation_date DATE NOT NULL,
    reference VARCHAR(150) NULL,
    label VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_treasury_movements_source
        FOREIGN KEY (source_treasury_account_id) REFERENCES treasury_accounts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_treasury_movements_target
        FOREIGN KEY (target_treasury_account_id) REFERENCES treasury_accounts(id) ON DELETE RESTRICT,
    INDEX idx_treasury_movements_date (operation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Produits / services 706
-- =========================================================
CREATE TABLE service_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(50) NOT NULL UNIQUE,
    account_label VARCHAR(255) NOT NULL,
    operation_type_label VARCHAR(255) NULL,
    destination_country_label VARCHAR(150) NULL,
    commercial_country_label VARCHAR(150) NULL,
    level_depth INT NOT NULL DEFAULT 3,
    is_postable TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    INDEX idx_service_accounts_code (account_code),
    INDEX idx_service_accounts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ref_operation_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    direction ENUM('credit','debit','mixed') NOT NULL DEFAULT 'mixed',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ref_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    operation_type_id INT NULL,
    service_account_id INT NULL,
    treasury_account_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_ref_services_operation_type
        FOREIGN KEY (operation_type_id) REFERENCES ref_operation_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_ref_services_service_account
        FOREIGN KEY (service_account_id) REFERENCES service_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_ref_services_treasury_account
        FOREIGN KEY (treasury_account_id) REFERENCES treasury_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Clients / comptes
-- =========================================================
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(150) NOT NULL,
    last_name VARCHAR(150) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(100) NULL,
    country_origin VARCHAR(150) NULL,
    country_destination VARCHAR(150) NULL,
    country_commercial VARCHAR(150) NULL,
    client_type VARCHAR(150) NULL,
    client_status VARCHAR(150) NULL,
    status_id INT NULL,
    category_id INT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
    generated_client_account VARCHAR(50) NOT NULL UNIQUE,
    initial_treasury_account_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_clients_status
        FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE SET NULL,
    CONSTRAINT fk_clients_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_clients_initial_treasury
        FOREIGN KEY (initial_treasury_account_id) REFERENCES treasury_accounts(id) ON DELETE SET NULL,
    INDEX idx_clients_code (client_code),
    INDEX idx_clients_active (is_active),
    INDEX idx_clients_status_text (client_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(255) NULL,
    account_number VARCHAR(100) NOT NULL UNIQUE,
    bank_name VARCHAR(255) NULL,
    country VARCHAR(150) NULL,
    account_type_id INT NULL,
    account_category_id INT NULL,
    initial_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_bank_accounts_type
        FOREIGN KEY (account_type_id) REFERENCES account_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_bank_accounts_category
        FOREIGN KEY (account_category_id) REFERENCES account_categories(id) ON DELETE SET NULL,
    INDEX idx_bank_accounts_number (account_number),
    INDEX idx_bank_accounts_country (country),
    INDEX idx_bank_accounts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    bank_account_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_bank_accounts_client
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_bank_accounts_bank
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_client_bank_account (client_id, bank_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Opérations
-- =========================================================
CREATE TABLE operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    bank_account_id INT NULL,
    operation_date DATE NOT NULL,
    operation_type_code VARCHAR(100) NOT NULL,
    operation_kind VARCHAR(100) NULL,
    label VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    reference VARCHAR(150) NULL,
    source_type VARCHAR(100) NULL,
    debit_account_code VARCHAR(50) NULL,
    credit_account_code VARCHAR(50) NULL,
    service_account_code VARCHAR(50) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_operations_client
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_operations_bank
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_operations_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_operations_date (operation_date),
    INDEX idx_operations_client (client_id),
    INDEX idx_operations_type (operation_type_code),
    INDEX idx_operations_source_type (source_type),
    INDEX idx_operations_debit (debit_account_code),
    INDEX idx_operations_credit (credit_account_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Imports
-- =========================================================
CREATE TABLE imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    status VARCHAR(100) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    INDEX idx_imports_status (status),
    INDEX idx_imports_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_rows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_id INT NOT NULL,
    raw_data LONGTEXT NULL,
    status VARCHAR(100) NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_import_rows_import
        FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE CASCADE,
    INDEX idx_import_rows_status (status),
    INDEX idx_import_rows_import (import_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Données de base
-- =========================================================
INSERT INTO roles (code, label) VALUES
('superadmin', 'Super Administrateur'),
('admin_tech', 'Administrateur Technique'),
('admin_functional', 'Administrateur Fonctionnel'),
('operator_finance', 'Opérateur Financier'),
('manager', 'Direction / Management'),
('viewer', 'Lecture seule');

INSERT INTO permissions (code, label) VALUES
('dashboard_view', 'Voir dashboard'),
('clients_view', 'Voir clients'),
('clients_create', 'Créer / modifier clients'),
('clients_archive', 'Archiver / réactiver clients'),
('operations_view', 'Voir opérations'),
('operations_create', 'Créer / modifier opérations'),
('treasury_view', 'Gérer comptes internes'),
('imports_preview', 'Prévisualiser imports'),
('imports_validate', 'Valider imports'),
('imports_journal', 'Voir journal imports'),
('statements_view', 'Voir relevés'),
('statements_export', 'Exporter relevés et fiches'),
('statements_export_single', 'Exporter relevé unitaire'),
('statements_export_bulk', 'Exporter relevés en masse'),
('analytics_view', 'Voir analytics'),
('manual_actions_create', 'Créer opérations manuelles'),
('support_admin_manage', 'Gérer demandes support'),
('admin_dashboard_view', 'Voir dashboard admin technique'),
('admin_users_manage', 'Gérer utilisateurs'),
('admin_roles_manage', 'Gérer rôles'),
('admin_logs_view', 'Voir logs');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'superadmin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'admin_tech'
  AND p.code IN (
    'dashboard_view','clients_view','operations_view','treasury_view','imports_preview','imports_validate',
    'imports_journal','statements_view','statements_export','statements_export_single','statements_export_bulk',
    'analytics_view','support_admin_manage','admin_dashboard_view','admin_users_manage','admin_roles_manage','admin_logs_view'
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'admin_functional'
  AND p.code IN (
    'dashboard_view','clients_view','clients_create','clients_archive',
    'operations_view','operations_create',
    'treasury_view','imports_preview','imports_validate','imports_journal',
    'statements_view','statements_export','statements_export_single','statements_export_bulk',
    'analytics_view','manual_actions_create'
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'operator_finance'
  AND p.code IN (
    'dashboard_view','clients_view','clients_create',
    'operations_view','operations_create',
    'imports_preview','imports_validate','imports_journal',
    'statements_view','statements_export','statements_export_single',
    'manual_actions_create','treasury_view'
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'manager'
  AND p.code IN (
    'dashboard_view','clients_view','operations_view','treasury_view',
    'imports_journal','statements_view','statements_export','statements_export_single','statements_export_bulk',
    'analytics_view'
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'viewer'
  AND p.code IN (
    'dashboard_view','clients_view','operations_view','statements_view'
  );

INSERT INTO statuses (name, sort_order) VALUES
('Etudiant en attente AVI', 10),
('Etudiant actif', 20),
('Etudiant dormant', 30),
('Etudiant remboursé', 40);

INSERT INTO categories (name) VALUES
('AVI'),
('Transfert'),
('Compte de paiement'),
('ATS'),
('Placement'),
('Divers');

INSERT INTO account_types (name) VALUES
('Compte client'),
('Compte bancaire entreprise'),
('Compte interne');

INSERT INTO account_categories (name) VALUES
('Client'),
('Trésorerie'),
('Produit / Service');

INSERT INTO ref_operation_types (code, label, direction) VALUES
('CREDIT_CLIENT', 'Crédit client', 'credit'),
('DEBIT_CLIENT', 'Débit client', 'debit'),
('FRAIS_SERVICE', 'Frais de service', 'debit'),
('REGULARISATION', 'Régularisation', 'mixed'),
('VIREMENT_INTERNE', 'Virement interne', 'mixed'),
('IMPORT_RELEVE', 'Import relevé', 'mixed'),
('MANUAL', 'Opération manuelle', 'mixed');

INSERT INTO users (username, password, role, role_id, is_active, created_at)
SELECT
    'admin',
    '$2y$10$1S2w3N4cW8s5M4zIYb5bC.iH4YcPcfX8jZ9WkR8f0f0Qf0c3JwY3K',
    'admin',
    r.id,
    1,
    NOW()
FROM roles r
WHERE r.code = 'superadmin'
LIMIT 1;

COMMIT;