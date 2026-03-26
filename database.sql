DROP DATABASE IF EXISTS studelyledge;
CREATE DATABASE studelyledge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studelyledge;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. UTILISATEURS
-- =========================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. LOGS UTILISATEURS
-- =========================================================
CREATE TABLE user_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NULL,
    module VARCHAR(100) NULL,
    entity_type VARCHAR(100) NULL,
    entity_id VARCHAR(100) NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3. TYPES D'OPÉRATIONS
-- =========================================================
CREATE TABLE ref_operation_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ref_operation_types (code, label) VALUES
('VERSEMENT', 'Versement'),
('FRAIS_DE_SERVICE', 'Frais de service'),
('VIREMENT_MENSUEL', 'Virement mensuel'),
('VIREMENT_EXCEPTIONEL', 'Virement exceptionnel'),
('REGULARISATION_POSITIVE', 'Régularisation positive'),
('REGULARISATION_NEGATIVE', 'Régularisation négative'),
('FRAIS_BANCAIRES', 'Frais bancaires'),
('VIREMENT_INTERNE', 'Virement interne'),
('VIREMENT_REGULIER', 'Virement régulier');

-- =========================================================
-- 4. COMPTES INTERNES 512
-- =========================================================
CREATE TABLE treasury_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    account_label VARCHAR(150) NOT NULL,
    bank_name VARCHAR(150) NULL,
    subsidiary_name VARCHAR(150) NULL,
    zone_code VARCHAR(20) NULL,
    country_label VARCHAR(100) NULL,
    country_type VARCHAR(50) NULL,
    payment_place VARCHAR(50) NULL,
    currency_code VARCHAR(10) NULL,
    opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. COMPTES DE SERVICES 706
-- =========================================================
CREATE TABLE service_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    account_label VARCHAR(150) NOT NULL,
    operation_type_label VARCHAR(255) NULL,
    destination_country_label VARCHAR(100) NULL,
    commercial_country_label VARCHAR(100) NULL,
    level_depth INT NOT NULL DEFAULT 3,
    is_postable TINYINT(1) NOT NULL DEFAULT 1,
    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. SERVICES
-- =========================================================
CREATE TABLE ref_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    operation_type_id INT NULL,
    service_account_id INT NULL,
    treasury_account_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ref_services_operation_type
        FOREIGN KEY (operation_type_id) REFERENCES ref_operation_types(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_ref_services_service_account
        FOREIGN KEY (service_account_id) REFERENCES service_accounts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_ref_services_treasury_account
        FOREIGN KEY (treasury_account_id) REFERENCES treasury_accounts(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. CLIENTS
-- =========================================================
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(9) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    country_origin VARCHAR(100) NULL,
    country_destination VARCHAR(100) NULL,
    country_commercial VARCHAR(100) NULL,
    client_type VARCHAR(100) NULL,
    client_status VARCHAR(100) NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
    generated_client_account VARCHAR(20) NOT NULL,
    initial_treasury_account_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_clients_initial_treasury
        FOREIGN KEY (initial_treasury_account_id) REFERENCES treasury_accounts(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8. COMPTES BANCAIRES CLIENTS
-- =========================================================
CREATE TABLE bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(50) NOT NULL,
    bank_name VARCHAR(100) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'France',
    initial_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 9. LIEN CLIENTS / COMPTES BANCAIRES
-- =========================================================
CREATE TABLE client_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    bank_account_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_bank_accounts_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_client_bank_accounts_bank
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_client_bank_account (client_id, bank_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 10. OPÉRATIONS CLIENTS
-- =========================================================
CREATE TABLE operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    bank_account_id INT NULL,
    operation_date DATE NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    operation_type_code VARCHAR(50) NOT NULL,
    label VARCHAR(255) NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    debit_account_code VARCHAR(50) NULL,
    credit_account_code VARCHAR(50) NULL,
    service_account_code VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_operations_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_operations_bank
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
        ON DELETE SET NULL,
    KEY idx_operations_client_date (client_id, operation_date),
    KEY idx_operations_reference (reference),
    KEY idx_operations_type (operation_type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 11. VIREMENTS INTERNES
-- =========================================================
CREATE TABLE treasury_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_treasury_account_id INT NOT NULL,
    target_treasury_account_id INT NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    operation_date DATE NOT NULL,
    reference VARCHAR(100) NULL,
    label VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_treasury_movements_source
        FOREIGN KEY (source_treasury_account_id) REFERENCES treasury_accounts(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_treasury_movements_target
        FOREIGN KEY (target_treasury_account_id) REFERENCES treasury_accounts(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 12. IMPORTS
-- =========================================================
CREATE TABLE imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_rows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_id INT NOT NULL,
    raw_data LONGTEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_rows_import
        FOREIGN KEY (import_id) REFERENCES imports(id)
        ON DELETE CASCADE,
    KEY idx_import_rows_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 13. DONNÉES DE BASE - COMPTES 512
-- =========================================================
INSERT INTO treasury_accounts (
    account_code, account_label, bank_name, subsidiary_name, zone_code,
    country_label, country_type, payment_place, currency_code,
    opening_balance, current_balance, is_active
) VALUES
('5120101','Fr_LCL_C','Fr_LCL_C','Studely','EU','France','Siege','France','EUR',1000000,1000000,1),
('5120102','Fr_LCL_M','Fr_LCL_M','Studely','EU','France','Siege','France','EUR',1000000,1000000,1),
('5120301','BE_QUONTO','BE_QUONTO','Studely','EU','Belgique','Filiale','France','EUR',1000000,1000000,1),
('5120401','CM_BAC','CM_BAC','Studely','AC','Cameroun','Filiale','Local','XAF',1000000,1000000,1),
('5120502','SN_ECOBQ','SN_ECOBQ','Studely','AO','Sénégal','Filiale','Local','XOF',1000000,1000000,1),
('5120601','CIV_ECOBQ','CIV_ECOBQ','Studely','AO','Côte d''Ivoire','Filiale','Local','XOF',1000000,1000000,1),
('5121901','TN_ATTI','TN_ATTI','Studely','AN','Tunisie','Filiale','Local','EUR',1000000,1000000,1),
('5122001','MA_ATTI','MA_ATTI','Studely','AN','Maroc','Filiale','Local','EUR',1000000,1000000,1);

-- =========================================================
-- 14. DONNÉES DE BASE - COMPTES 706
-- =========================================================
INSERT INTO service_accounts (
    account_code, account_label, operation_type_label, destination_country_label,
    commercial_country_label, level_depth, is_postable, current_balance, is_active
) VALUES
('7061304','FRAIS DE SERVICE AVI FRANCE-Cameroun','FRAIS DE SERVICES AVI','France','Cameroun',4,1,0,1),
('7061305','FRAIS DE SERVICE AVI FRANCE-Sénégal','FRAIS DE SERVICES AVI','France','Sénégal',4,1,0,1),
('7061306','FRAIS DE SERVICE AVI FRANCE-Côte d''Ivoire','FRAIS DE SERVICES AVI','France','Côte d''Ivoire',4,1,0,1),
('706204','FRAIS DE GESTION-Cameroun','FRAIS DE GESTION',NULL,'Cameroun',3,1,0,1),
('706205','FRAIS DE GESTION-Sénégal','FRAIS DE GESTION',NULL,'Sénégal',3,1,0,1),
('706304','COMMISSION DE TRANSFERT-Cameroun','COMMISSION DE TRANSFERT',NULL,'Cameroun',3,1,0,1);

-- =========================================================
-- 15. DONNÉES DE BASE - SERVICES
-- =========================================================
INSERT INTO ref_services (code, label, operation_type_id, service_account_id, treasury_account_id, is_active)
SELECT 'AVI', 'FRAIS DE SERVICES AVI', rot.id, sa.id, NULL, 1
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '7061304'
WHERE rot.code = 'FRAIS_DE_SERVICE'
LIMIT 1;

INSERT INTO ref_services (code, label, operation_type_id, service_account_id, treasury_account_id, is_active)
SELECT 'GESTION', 'FRAIS DE GESTION', rot.id, sa.id, NULL, 1
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706204'
WHERE rot.code = 'FRAIS_DE_SERVICE'
LIMIT 1;

INSERT INTO ref_services (code, label, operation_type_id, service_account_id, treasury_account_id, is_active)
SELECT 'TRANSFERT', 'COMMISSION DE TRANSFERT', rot.id, sa.id, NULL, 1
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706304'
WHERE rot.code = 'FRAIS_DE_SERVICE'
LIMIT 1;

-- =========================================================
-- 16. DONNÉES DE TEST - CLIENTS
-- =========================================================
INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    country_origin, country_destination, country_commercial,
    client_type, client_status, currency, generated_client_account,
    initial_treasury_account_id, is_active
) VALUES
('000000001','Jean','Mekou','Jean Mekou','jean.mekou@studelyledger.com','0600000001','Cameroun','France','Cameroun','Nouveau Client','Etudiant Actif','EUR','411000000001',4,1),
('000000002','Awa','Ndiaye','Awa Ndiaye','awa.ndiaye@studelyledger.com','0600000002','Sénégal','France','Sénégal','Nouveau Client','Etudiant Actif','EUR','411000000002',5,1),
('000000003','Koffi','Kouassi','Koffi Kouassi','koffi.kouassi@studelyledger.com','0600000003','Côte d''Ivoire','France','Côte d''Ivoire','Nouveau Client','Etudiant Actif','EUR','411000000003',6,1);

-- =========================================================
-- 17. DONNÉES DE TEST - COMPTES CLIENTS
-- =========================================================
INSERT INTO bank_accounts (account_number, bank_name, country, initial_balance, balance, is_active)
VALUES
('411000000001','Compte Client Interne','France',15000.00,15000.00,1),
('411000000002','Compte Client Interne','France',15000.00,15000.00,1),
('411000000003','Compte Client Interne','France',15000.00,15000.00,1);

INSERT INTO client_bank_accounts (client_id, bank_account_id)
VALUES
(1,1),
(2,2),
(3,3);

-- =========================================================
-- 18. UTILISATEUR ADMIN DE BASE
-- mot de passe conseillé à régénérer ensuite
-- =========================================================
INSERT INTO users (username, password, role)
VALUES ('admin', '$2y$10$wHh2m3XQO6gQqR1t6Qd8G.y9vYv2E5m8c8M8l4n3w4J2uF3i8oW3K', 'admin');

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- A. COMPTES 512 - INSERTION DÉFENSIVE
-- =========================================================
INSERT INTO treasury_accounts (
    account_code, account_label, bank_name, subsidiary_name, zone_code,
    country_label, country_type, payment_place, currency_code,
    opening_balance, current_balance, is_active, created_at
)
SELECT src.account_code, src.account_label, src.bank_name, src.subsidiary_name, src.zone_code,
       src.country_label, src.country_type, src.payment_place, src.currency_code,
       1000000.00, 1000000.00, 1, NOW()
FROM (
    SELECT '5120402' AS account_code, 'CM_BAC_EXPL' AS account_label, 'CM_BAC_EXPL' AS bank_name, 'Studely' AS subsidiary_name, 'AC' AS zone_code, 'Cameroun' AS country_label, 'Filiale' AS country_type, 'Local' AS payment_place, 'XAF' AS currency_code
    UNION ALL SELECT '5120403','CM_BAC_REM','CM_BAC_REM','Studely','AC','Cameroun','Filiale','Local','XAF'
    UNION ALL SELECT '5120404','CM_BGFI_DE','CM_BGFI_DE','Studely','AC','Cameroun','Filiale','Local','XAF'
    UNION ALL SELECT '5120501','SF_SN_EcoBQ','SF_SN_EcoBQ','Studely Finance','AO','Sénégal','Filiale','Local','XOF'
    UNION ALL SELECT '5120602','SF_CIV_AFG','SF_CIV_AFG','Studely Finance','AO','Côte d''Ivoire','Filiale','Local','XOF'
    UNION ALL SELECT '5120701','BN_ECOBQ','BN_ECOBQ','Studely','AO','Benin','Filiale','Local','XOF'
    UNION ALL SELECT '5120801','BFA_ECOBQ','BFA_ECOBQ','Studely','AO','Burkina Faso','Filiale','Local','XOF'
    UNION ALL SELECT '5121001','RD_BGFI','RD_BGFI','Studely','AC','Congo Kinshasa','Filiale','Local','XAF'
    UNION ALL SELECT '5121101','GB_BGFI','GB_BGFI','Studely','AC','Gabon','Filiale','Local','XAF'
    UNION ALL SELECT '5121201','SF_CHD_ECOBAQ','SF_CHD_ECOBAQ','Studely Finance','AC','Tchad','Filiale','Local','XAF'
) src
WHERE NOT EXISTS (
    SELECT 1 FROM treasury_accounts t WHERE t.account_code = src.account_code
);

-- =========================================================
-- B. COMPTES 706 - INSERTION DÉFENSIVE
-- =========================================================
INSERT INTO service_accounts (
    account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    level_depth, is_postable, current_balance, is_active, created_at
)
SELECT src.account_code, src.account_label, src.operation_type_label,
       src.destination_country_label, src.commercial_country_label,
       src.level_depth, 1, 0, 1, NOW()
FROM (
    SELECT '7061307' AS account_code, 'FRAIS DE SERVICE AVI FRANCE-Benin' AS account_label, 'FRAIS DE SERVICES AVI' AS operation_type_label, 'France' AS destination_country_label, 'Benin' AS commercial_country_label, 4 AS level_depth
    UNION ALL SELECT '7061308','FRAIS DE SERVICE AVI FRANCE-Burkina Faso','FRAIS DE SERVICES AVI','France','Burkina Faso',4
    UNION ALL SELECT '7061309','FRAIS DE SERVICE AVI FRANCE-Congo Brazzaville','FRAIS DE SERVICES AVI','France','Congo Brazzaville',4
    UNION ALL SELECT '7061310','FRAIS DE SERVICE AVI FRANCE-Congo Kinshasa','FRAIS DE SERVICES AVI','France','Congo Kinshasa',4
    UNION ALL SELECT '7061311','FRAIS DE SERVICE AVI FRANCE-Gabon','FRAIS DE SERVICES AVI','France','Gabon',4
    UNION ALL SELECT '7061312','FRAIS DE SERVICE AVI FRANCE-Tchad','FRAIS DE SERVICES AVI','France','Tchad',4
    UNION ALL SELECT '7061313','FRAIS DE SERVICE AVI FRANCE-Mali','FRAIS DE SERVICES AVI','France','Mali',4
    UNION ALL SELECT '7061314','FRAIS DE SERVICE AVI FRANCE-Togo','FRAIS DE SERVICES AVI','France','Togo',4
    UNION ALL SELECT '706205','FRAIS DE GESTION-Sénégal','FRAIS DE GESTION',NULL,'Sénégal',3
    UNION ALL SELECT '706206','FRAIS DE GESTION-Côte d''Ivoire','FRAIS DE GESTION',NULL,'Côte d''Ivoire',3
) src
WHERE NOT EXISTS (
    SELECT 1 FROM service_accounts s WHERE s.account_code = src.account_code
);

-- =========================================================
-- C. CLIENTS - INSERTION DÉFENSIVE
-- =========================================================
INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    country_origin, country_destination, country_commercial,
    client_type, client_status, currency,
    generated_client_account, initial_treasury_account_id,
    is_active, created_at
)
SELECT
    LPAD(seq.n, 9, '0'),
    CONCAT('Client', seq.n),
    CONCAT('Studely', seq.n),
    CONCAT('Client', seq.n, ' Studely', seq.n),
    LOWER(CONCAT('client', seq.n, '.studely', seq.n, '@studelyledger.com')),
    CONCAT('0600', LPAD(seq.n, 6, '0')),
    CASE
        WHEN MOD(seq.n, 5) = 0 THEN 'Sénégal'
        WHEN MOD(seq.n, 5) = 1 THEN 'Cameroun'
        WHEN MOD(seq.n, 5) = 2 THEN 'Côte d''Ivoire'
        WHEN MOD(seq.n, 5) = 3 THEN 'Maroc'
        ELSE 'Tunisie'
    END,
    CASE
        WHEN MOD(seq.n, 3) = 0 THEN 'France'
        WHEN MOD(seq.n, 3) = 1 THEN 'Belgique'
        ELSE 'Allemagne'
    END,
    CASE
        WHEN MOD(seq.n, 5) = 0 THEN 'Sénégal'
        WHEN MOD(seq.n, 5) = 1 THEN 'Cameroun'
        WHEN MOD(seq.n, 5) = 2 THEN 'Côte d''Ivoire'
        WHEN MOD(seq.n, 5) = 3 THEN 'Maroc'
        ELSE 'Tunisie'
    END,
    'Nouveau Client',
    'Etudiant Actif',
    'EUR',
    CONCAT('411', LPAD(seq.n, 9, '0')),
    CASE
        WHEN MOD(seq.n, 5) = 0 THEN 5
        WHEN MOD(seq.n, 5) = 1 THEN 4
        WHEN MOD(seq.n, 5) = 2 THEN 6
        WHEN MOD(seq.n, 5) = 3 THEN 8
        ELSE 7
    END,
    1,
    NOW()
FROM (
    SELECT 4 AS n UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
    UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13
    UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18
    UNION ALL SELECT 19 UNION ALL SELECT 20
) seq
WHERE NOT EXISTS (
    SELECT 1 FROM clients c WHERE c.client_code = LPAD(seq.n, 9, '0')
);

-- =========================================================
-- D. COMPTES CLIENTS LIÉS - INSERTION DÉFENSIVE
-- =========================================================
INSERT INTO bank_accounts (
    account_number, bank_name, country, initial_balance, balance, is_active, created_at
)
SELECT
    c.generated_client_account,
    'Compte Client Interne',
    'France',
    15000.00,
    15000.00,
    1,
    NOW()
FROM clients c
LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
WHERE cba.id IS NULL;

INSERT INTO client_bank_accounts (client_id, bank_account_id, created_at)
SELECT
    c.id,
    ba.id,
    NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
LEFT JOIN client_bank_accounts cba
    ON cba.client_id = c.id AND cba.bank_account_id = ba.id
WHERE cba.id IS NULL;

-- =========================================================
-- E. SERVICES FONCTIONNELS - RATTACHEMENTS SI ABSENTS
-- =========================================================
INSERT INTO ref_services (code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at)
SELECT 'AVI_BENIN', 'FRAIS DE SERVICES AVI BENIN', rot.id, sa.id, NULL, 1, NOW()
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '7061307'
WHERE rot.code = 'FRAIS_DE_SERVICE'
AND NOT EXISTS (
    SELECT 1 FROM ref_services rs WHERE rs.code = 'AVI_BENIN'
);

-- =========================================================
-- F. OPÉRATIONS CROISÉES - SANS DOUBLONS
-- =========================================================

-- Versements
INSERT INTO operations (
    client_id, bank_account_id, operation_date, amount, operation_type_code,
    label, reference, notes, debit_account_code, credit_account_code, created_at
)
SELECT
    c.id,
    ba.id,
    DATE_SUB(CURDATE(), INTERVAL MOD(c.id, 12) DAY),
    1200.00,
    'VERSEMENT',
    'Versement initial client',
    CONCAT('REF-VERS-', c.client_code),
    'Peuplement défensif',
    ta.account_code,
    c.generated_client_account,
    NOW()
FROM clients c
INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
INNER JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
WHERE NOT EXISTS (
    SELECT 1 FROM operations o WHERE o.reference = CONCAT('REF-VERS-', c.client_code)
);

-- Frais de service
INSERT INTO operations (
    client_id, bank_account_id, operation_date, amount, operation_type_code,
    label, reference, notes, debit_account_code, credit_account_code, service_account_code, created_at
)
SELECT
    c.id,
    ba.id,
    DATE_SUB(CURDATE(), INTERVAL MOD(c.id, 10) DAY),
    350.00,
    'FRAIS_DE_SERVICE',
    'Frais de service AVI',
    CONCAT('REF-FRAIS-', c.client_code),
    'Peuplement défensif',
    c.generated_client_account,
    sa.account_code,
    sa.account_code,
    NOW()
FROM clients c
INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
INNER JOIN service_accounts sa ON sa.account_code = '7061304'
WHERE NOT EXISTS (
    SELECT 1 FROM operations o WHERE o.reference = CONCAT('REF-FRAIS-', c.client_code)
);

-- Virements mensuels
INSERT INTO operations (
    client_id, bank_account_id, operation_date, amount, operation_type_code,
    label, reference, notes, debit_account_code, credit_account_code, created_at
)
SELECT
    c.id,
    ba.id,
    DATE_SUB(CURDATE(), INTERVAL MOD(c.id, 8) DAY),
    500.00,
    'VIREMENT_MENSUEL',
    'Virement mensuel',
    CONCAT('REF-VIR-', c.client_code),
    'Peuplement défensif',
    c.generated_client_account,
    ta.account_code,
    NOW()
FROM clients c
INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
INNER JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
WHERE NOT EXISTS (
    SELECT 1 FROM operations o WHERE o.reference = CONCAT('REF-VIR-', c.client_code)
);

-- =========================================================
-- G. SYNCHRO DÉFENSIVE DES SOLDES APRÈS PEUPLEMENT
-- =========================================================
UPDATE bank_accounts SET balance = COALESCE(initial_balance, 0);
UPDATE treasury_accounts SET current_balance = COALESCE(opening_balance, 0);
UPDATE service_accounts SET current_balance = 0;