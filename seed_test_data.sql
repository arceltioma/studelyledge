USE studelyledge;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- NETTOYAGE DONNEES DE TEST
-- =========================================================
DELETE FROM treasury_movements;
DELETE FROM operations;
DELETE FROM client_bank_accounts;
DELETE FROM bank_accounts;
DELETE FROM clients;
DELETE FROM ref_services;
DELETE FROM ref_operation_types;
DELETE FROM service_accounts;
DELETE FROM treasury_accounts;
DELETE FROM statuses;
DELETE FROM categories;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- STATUTS
-- =========================================================
INSERT INTO statuses (name, sort_order, created_at) VALUES
('Etudiant en attente AVI', 10, NOW()),
('Etudiant actif', 20, NOW()),
('Etudiant dormant', 30, NOW()),
('Etudiant remboursé', 40, NOW());

-- =========================================================
-- CATEGORIES
-- =========================================================
INSERT INTO categories (name, created_at) VALUES
('AVI', NOW()),
('Transfert', NOW()),
('Compte de paiement', NOW()),
('ATS', NOW()),
('Placement', NOW()),
('Divers', NOW());

-- =========================================================
-- COMPTES 512
-- =========================================================
INSERT INTO treasury_accounts (
    account_code,
    account_label,
    bank_name,
    subsidiary_name,
    zone_code,
    country_label,
    country_type,
    payment_place,
    currency_code,
    opening_balance,
    current_balance,
    is_active,
    created_at,
    updated_at
) VALUES
('512001', 'BNP France Principal', 'BNP Paribas', 'Studely France', 'EU-OUEST', 'France', 'Filiale', 'Paris', 'EUR', 200000.00, 200000.00, 1, NOW(), NOW()),
('512002', 'SG France Secondaire', 'Société Générale', 'Studely France', 'EU-OUEST', 'France', 'Filiale', 'Lyon', 'EUR', 120000.00, 120000.00, 1, NOW(), NOW()),
('512003', 'RBC Canada', 'RBC', 'Studely Canada', 'NA-EST', 'Canada', 'Filiale', 'Montréal', 'CAD', 90000.00, 90000.00, 1, NOW(), NOW()),
('512004', 'Ecobank Sénégal', 'Ecobank', 'Studely Sénégal', 'AF-OUEST', 'Sénégal', 'Partenaire', 'Dakar', 'XOF', 50000000.00, 50000000.00, 1, NOW(), NOW());

-- =========================================================
-- COMPTES 706
-- =========================================================
INSERT INTO service_accounts (
    account_code,
    account_label,
    operation_type_label,
    destination_country_label,
    commercial_country_label,
    level_depth,
    is_postable,
    is_active,
    current_balance,
    created_at,
    updated_at
) VALUES
('706100', 'Frais de dossier', 'FRAIS_SERVICE', 'France', 'France', 3, 1, 1, 0.00, NOW(), NOW()),
('706200', 'Frais visa', 'FRAIS_SERVICE', 'Canada', 'Canada', 3, 1, 1, 0.00, NOW(), NOW()),
('706300', 'Acompte traitement', 'CREDIT_CLIENT', 'France', 'France', 3, 1, 1, 0.00, NOW(), NOW()),
('706400', 'Régularisation service', 'REGULARISATION', 'International', 'International', 3, 1, 1, 0.00, NOW(), NOW()),
('706500', 'Frais administratifs premium', 'FRAIS_SERVICE', 'France', 'France', 3, 1, 1, 0.00, NOW(), NOW());

-- =========================================================
-- TYPES D'OPERATIONS
-- =========================================================
INSERT INTO ref_operation_types (
    code,
    label,
    direction,
    is_active,
    created_at,
    updated_at
) VALUES
('CREDIT_CLIENT', 'Crédit client', 'credit', 1, NOW(), NOW()),
('DEBIT_CLIENT', 'Débit client', 'debit', 1, NOW(), NOW()),
('FRAIS_SERVICE', 'Frais de service', 'debit', 1, NOW(), NOW()),
('REGULARISATION', 'Régularisation', 'mixed', 1, NOW(), NOW()),
('VIREMENT_INTERNE', 'Virement interne', 'mixed', 1, NOW(), NOW()),
('IMPORT_RELEVE', 'Import relevé', 'mixed', 1, NOW(), NOW()),
('MANUAL', 'Opération manuelle', 'mixed', 1, NOW(), NOW()),
('VIREMENT_MENSUEL', 'Virement mensuel', 'debit', 1, NOW(), NOW()),
('VIREMENT_EXCEPTIONEL', 'Virement exceptionnel', 'debit', 1, NOW(), NOW()),
('VERSEMENT', 'Versement', 'credit', 1, NOW(), NOW());

-- =========================================================
-- SERVICES
-- =========================================================
INSERT INTO ref_services (
    code,
    label,
    operation_type_id,
    service_account_id,
    treasury_account_id,
    is_active,
    created_at,
    updated_at
)
SELECT 'SRV_DOSSIER', 'Frais de dossier',
       rot.id, sa.id, ta.id, 1, NOW(), NOW()
FROM ref_operation_types rot, service_accounts sa, treasury_accounts ta
WHERE rot.code = 'FRAIS_SERVICE'
  AND sa.account_code = '706100'
  AND ta.account_code = '512001';

INSERT INTO ref_services (
    code,
    label,
    operation_type_id,
    service_account_id,
    treasury_account_id,
    is_active,
    created_at,
    updated_at
)
SELECT 'SRV_VISA', 'Frais visa',
       rot.id, sa.id, ta.id, 1, NOW(), NOW()
FROM ref_operation_types rot, service_accounts sa, treasury_accounts ta
WHERE rot.code = 'FRAIS_SERVICE'
  AND sa.account_code = '706200'
  AND ta.account_code = '512003';

INSERT INTO ref_services (
    code,
    label,
    operation_type_id,
    service_account_id,
    treasury_account_id,
    is_active,
    created_at,
    updated_at
)
SELECT 'SRV_ACOMPTE', 'Acompte traitement',
       rot.id, sa.id, ta.id, 1, NOW(), NOW()
FROM ref_operation_types rot, service_accounts sa, treasury_accounts ta
WHERE rot.code = 'CREDIT_CLIENT'
  AND sa.account_code = '706300'
  AND ta.account_code = '512002';

INSERT INTO ref_services (
    code,
    label,
    operation_type_id,
    service_account_id,
    treasury_account_id,
    is_active,
    created_at,
    updated_at
)
SELECT 'SRV_REGUL', 'Régularisation service',
       rot.id, sa.id, ta.id, 1, NOW(), NOW()
FROM ref_operation_types rot, service_accounts sa, treasury_accounts ta
WHERE rot.code = 'REGULARISATION'
  AND sa.account_code = '706400'
  AND ta.account_code = '512001';

INSERT INTO ref_services (
    code,
    label,
    operation_type_id,
    service_account_id,
    treasury_account_id,
    is_active,
    created_at,
    updated_at
)
SELECT 'SRV_PREMIUM', 'Frais premium',
       rot.id, sa.id, ta.id, 1, NOW(), NOW()
FROM ref_operation_types rot, service_accounts sa, treasury_accounts ta
WHERE rot.code = 'FRAIS_SERVICE'
  AND sa.account_code = '706500'
  AND ta.account_code = '512002';

-- =========================================================
-- CLIENTS
-- =========================================================
INSERT INTO clients (
    client_code,
    first_name,
    last_name,
    full_name,
    email,
    phone,
    country_origin,
    country_destination,
    country_commercial,
    client_type,
    client_status,
    status_id,
    category_id,
    currency,
    generated_client_account,
    initial_treasury_account_id,
    is_active,
    created_at,
    updated_at
)
SELECT
    '100000001', 'Aminata', 'Diallo', 'Aminata Diallo',
    'aminata.diallo@exemple.com', '+221700000001',
    'Sénégal', 'France', 'France',
    'AVI', 'Etudiant actif',
    s.id, c.id, 'EUR', '411000001', ta.id, 1, NOW(), NOW()
FROM statuses s, categories c, treasury_accounts ta
WHERE s.name = 'Etudiant actif'
  AND c.name = 'AVI'
  AND ta.account_code = '512001';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    country_origin, country_destination, country_commercial,
    client_type, client_status, status_id, category_id, currency,
    generated_client_account, initial_treasury_account_id, is_active, created_at, updated_at
)
SELECT
    '100000002', 'Moussa', 'Traore', 'Moussa Traore',
    'moussa.traore@exemple.com', '+223700000002',
    'Mali', 'France', 'France',
    'Transfert', 'Etudiant en attente AVI',
    s.id, c.id, 'EUR', '411000002', ta.id, 1, NOW(), NOW()
FROM statuses s, categories c, treasury_accounts ta
WHERE s.name = 'Etudiant en attente AVI'
  AND c.name = 'Transfert'
  AND ta.account_code = '512001';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    country_origin, country_destination, country_commercial,
    client_type, client_status, status_id, category_id, currency,
    generated_client_account, initial_treasury_account_id, is_active, created_at, updated_at
)
SELECT
    '100000003', 'Sarah', 'Nguessan', 'Sarah Nguessan',
    'sarah.nguessan@exemple.com', '+225700000003',
    'Côte d Ivoire', 'Canada', 'Canada',
    'Compte de paiement', 'Etudiant actif',
    s.id, c.id, 'CAD', '411000003', ta.id, 1, NOW(), NOW()
FROM statuses s, categories c, treasury_accounts ta
WHERE s.name = 'Etudiant actif'
  AND c.name = 'Compte de paiement'
  AND ta.account_code = '512003';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    country_origin, country_destination, country_commercial,
    client_type, client_status, status_id, category_id, currency,
    generated_client_account, initial_treasury_account_id, is_active, created_at, updated_at
)
SELECT
    '100000004', 'Kevin', 'Mba', 'Kevin Mba',
    'kevin.mba@exemple.com', '+241700000004',
    'Gabon', 'France', 'France',
    'AVI', 'Etudiant dormant',
    s.id, c.id, 'EUR', '411000004', ta.id, 1, NOW(), NOW()
FROM statuses s, categories c, treasury_accounts ta
WHERE s.name = 'Etudiant dormant'
  AND c.name = 'AVI'
  AND ta.account_code = '512002';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    country_origin, country_destination, country_commercial,
    client_type, client_status, status_id, category_id, currency,
    generated_client_account, initial_treasury_account_id, is_active, created_at, updated_at
)
SELECT
    '100000005', 'Grace', 'Ekué', 'Grace Ekué',
    'grace.ekue@exemple.com', '+228700000005',
    'Togo', 'France', 'France',
    'Placement', 'Etudiant remboursé',
    s.id, c.id, 'EUR', '411000005', ta.id, 1, NOW(), NOW()
FROM statuses s, categories c, treasury_accounts ta
WHERE s.name = 'Etudiant remboursé'
  AND c.name = 'Placement'
  AND ta.account_code = '512002';

-- =========================================================
-- COMPTES CLIENTS (IMPORTANT : account_number = generated_client_account
-- pour coller à la logique actuelle de recalcul)
-- =========================================================
INSERT INTO bank_accounts (
    account_name,
    account_number,
    bank_name,
    country,
    initial_balance,
    balance,
    is_active,
    created_at,
    updated_at
)
SELECT
    CONCAT('Compte client ', client_code),
    generated_client_account,
    'Compte interne client',
    country_destination,
    0.00,
    0.00,
    1,
    NOW(),
    NOW()
FROM clients;

-- =========================================================
-- LIAISON CLIENT ↔ COMPTE
-- =========================================================
INSERT INTO client_bank_accounts (client_id, bank_account_id, created_at)
SELECT c.id, ba.id, NOW()
FROM clients c
INNER JOIN bank_accounts ba
    ON ba.account_number = c.generated_client_account;

-- =========================================================
-- OPERATIONS
-- LOGIQUE RETENUE :
-- CREDIT_CLIENT / VERSEMENT : débit 512, crédit 411
-- FRAIS_SERVICE : débit 411, crédit 706
-- DEBIT_CLIENT / VIREMENT_MENSUEL : débit 411, crédit 512
-- REGULARISATION positive : débit 512, crédit 411
-- =========================================================

-- CLIENT 1
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-01', 'CREDIT_CLIENT', 'seed',
       'Versement initial', 15000.00, 'SEED-OP-0001', 'seed',
       '512001', c.generated_client_account, NULL,
       'Jeu de données initial', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000001';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-03', 'FRAIS_SERVICE', 'seed',
       'Frais de dossier', 250.00, 'SEED-OP-0002', 'seed',
       c.generated_client_account, '706100', '706100',
       'Frais service dossier', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000001';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-10', 'DEBIT_CLIENT', 'seed',
       'Virement mensuel', 1200.00, 'SEED-OP-0003', 'seed',
       c.generated_client_account, '512001', NULL,
       'Sortie mensuelle', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000001';

-- CLIENT 2
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-02', 'CREDIT_CLIENT', 'seed',
       'Versement dossier', 8000.00, 'SEED-OP-0004', 'seed',
       '512001', c.generated_client_account, NULL,
       'Versement de départ', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000002';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-06', 'REGULARISATION', 'seed',
       'Régularisation positive', 500.00, 'SEED-OP-0005', 'seed',
       '512001', c.generated_client_account, '706400',
       'Régularisation positive dossier', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000002';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-12', 'FRAIS_SERVICE', 'seed',
       'Frais premium', 180.00, 'SEED-OP-0006', 'seed',
       c.generated_client_account, '706500', '706500',
       'Frais premium de test', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000002';

-- CLIENT 3
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-04', 'CREDIT_CLIENT', 'seed',
       'Versement Canada', 20000.00, 'SEED-OP-0007', 'seed',
       '512003', c.generated_client_account, NULL,
       'Versement initial Canada', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000003';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-08', 'FRAIS_SERVICE', 'seed',
       'Frais visa', 600.00, 'SEED-OP-0008', 'seed',
       c.generated_client_account, '706200', '706200',
       'Frais visa Canada', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000003';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-15', 'DEBIT_CLIENT', 'seed',
       'Débit exceptionnel', 2300.00, 'SEED-OP-0009', 'seed',
       c.generated_client_account, '512003', NULL,
       'Retrait exceptionnel', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000003';

-- CLIENT 4
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-05', 'CREDIT_CLIENT', 'seed',
       'Versement initial', 10000.00, 'SEED-OP-0010', 'seed',
       '512002', c.generated_client_account, NULL,
       'Versement initial France', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000004';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-18', 'FRAIS_SERVICE', 'seed',
       'Frais dossier', 300.00, 'SEED-OP-0011', 'seed',
       c.generated_client_account, '706100', '706100',
       'Frais de dossier standard', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000004';

-- CLIENT 5
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-07', 'CREDIT_CLIENT', 'seed',
       'Versement placement', 5000.00, 'SEED-OP-0012', 'seed',
       '512002', c.generated_client_account, NULL,
       'Versement placement', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000005';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-20', 'DEBIT_CLIENT', 'seed',
       'Remboursement partiel', 3000.00, 'SEED-OP-0013', 'seed',
       c.generated_client_account, '512002', NULL,
       'Remboursement test', NOW(), NOW()
FROM clients c
INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = '100000005';

-- =========================================================
-- VIREMENTS INTERNES 512
-- =========================================================
INSERT INTO treasury_movements (
    source_treasury_account_id,
    target_treasury_account_id,
    amount,
    operation_date,
    reference,
    label,
    created_at,
    updated_at
)
SELECT
    ta1.id,
    ta2.id,
    15000.00,
    '2026-03-09',
    'SEED-TM-0001',
    'Virement interne France principal vers secondaire',
    NOW(),
    NOW()
FROM treasury_accounts ta1
INNER JOIN treasury_accounts ta2
    ON ta1.account_code = '512001'
   AND ta2.account_code = '512002';

INSERT INTO treasury_movements (
    source_treasury_account_id,
    target_treasury_account_id,
    amount,
    operation_date,
    reference,
    label,
    created_at,
    updated_at
)
SELECT
    ta1.id,
    ta2.id,
    7000.00,
    '2026-03-14',
    'SEED-TM-0002',
    'Virement interne France vers Canada',
    NOW(),
    NOW()
FROM treasury_accounts ta1
INNER JOIN treasury_accounts ta2
    ON ta1.account_code = '512001'
   AND ta2.account_code = '512003';

-- =========================================================
-- RECALCUL DES SOLDES
-- =========================================================

-- 1. Solde comptes clients (bank_accounts)
UPDATE bank_accounts ba
LEFT JOIN (
    SELECT
        account_ref,
        SUM(total_debit) AS total_debit,
        SUM(total_credit) AS total_credit
    FROM (
        SELECT debit_account_code AS account_ref, SUM(amount) AS total_debit, 0 AS total_credit
        FROM operations
        GROUP BY debit_account_code

        UNION ALL

        SELECT credit_account_code AS account_ref, 0 AS total_debit, SUM(amount) AS total_credit
        FROM operations
        GROUP BY credit_account_code
    ) x
    GROUP BY account_ref
) agg ON agg.account_ref = ba.account_number
SET
    ba.balance = COALESCE(ba.initial_balance, 0)
               + COALESCE(agg.total_debit, 0)
               - COALESCE(agg.total_credit, 0),
    ba.updated_at = NOW();

-- 2. Solde comptes 706
UPDATE service_accounts sa
LEFT JOIN (
    SELECT
        account_ref,
        SUM(total_credit) AS total_credit,
        SUM(total_debit) AS total_debit
    FROM (
        SELECT credit_account_code AS account_ref, SUM(amount) AS total_credit, 0 AS total_debit
        FROM operations
        GROUP BY credit_account_code

        UNION ALL

        SELECT debit_account_code AS account_ref, 0 AS total_credit, SUM(amount) AS total_debit
        FROM operations
        GROUP BY debit_account_code
    ) x
    GROUP BY account_ref
) agg ON agg.account_ref = sa.account_code
SET
    sa.current_balance = COALESCE(agg.total_credit, 0) - COALESCE(agg.total_debit, 0),
    sa.updated_at = NOW();

-- 3. Solde comptes 512
UPDATE treasury_accounts ta
LEFT JOIN (
    SELECT
        account_ref,
        SUM(total_debit) AS total_debit,
        SUM(total_credit) AS total_credit
    FROM (
        SELECT debit_account_code AS account_ref, SUM(amount) AS total_debit, 0 AS total_credit
        FROM operations
        GROUP BY debit_account_code

        UNION ALL

        SELECT credit_account_code AS account_ref, 0 AS total_debit, SUM(amount) AS total_credit
        FROM operations
        GROUP BY credit_account_code
    ) x
    GROUP BY account_ref
) agg_ops ON agg_ops.account_ref = ta.account_code
LEFT JOIN (
    SELECT
        treasury_id,
        SUM(total_in) AS total_in,
        SUM(total_out) AS total_out
    FROM (
        SELECT target_treasury_account_id AS treasury_id, SUM(amount) AS total_in, 0 AS total_out
        FROM treasury_movements
        GROUP BY target_treasury_account_id

        UNION ALL

        SELECT source_treasury_account_id AS treasury_id, 0 AS total_in, SUM(amount) AS total_out
        FROM treasury_movements
        GROUP BY source_treasury_account_id
    ) y
    GROUP BY treasury_id
) agg_mov ON agg_mov.treasury_id = ta.id
SET
    ta.current_balance = COALESCE(ta.opening_balance, 0)
                       + COALESCE(agg_ops.total_debit, 0)
                       - COALESCE(agg_ops.total_credit, 0)
                       + COALESCE(agg_mov.total_in, 0)
                       - COALESCE(agg_mov.total_out, 0),
    ta.updated_at = NOW();

-- =========================================================
-- CONTROLES RAPIDES
-- =========================================================
SELECT 'clients' AS table_name, COUNT(*) AS total FROM clients
UNION ALL
SELECT 'bank_accounts', COUNT(*) FROM bank_accounts
UNION ALL
SELECT 'service_accounts', COUNT(*) FROM service_accounts
UNION ALL
SELECT 'treasury_accounts', COUNT(*) FROM treasury_accounts
UNION ALL
SELECT 'operations', COUNT(*) FROM operations
UNION ALL
SELECT 'treasury_movements', COUNT(*) FROM treasury_movements;

SELECT client_code, full_name, generated_client_account
FROM clients
ORDER BY client_code;

SELECT account_code, account_label, current_balance
FROM treasury_accounts
ORDER BY account_code;

SELECT account_code, account_label, current_balance
FROM service_accounts
ORDER BY account_code;

SELECT account_number, account_name, balance
FROM bank_accounts
ORDER BY account_number;