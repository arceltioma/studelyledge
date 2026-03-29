USE studelyledge;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 0. NETTOYAGE DU JEU DE TEST
-- =========================================================
DELETE FROM treasury_movements
WHERE reference LIKE 'TEST-TM-%';

DELETE FROM operations
WHERE reference LIKE 'TEST-OP-%';

DELETE FROM client_bank_accounts
WHERE client_id IN (
    SELECT id FROM clients WHERE client_code IN (
        'CLT0001','CLT0002','CLT0003','CLT0004','CLT0005','CLT0006'
    )
);

DELETE FROM bank_accounts
WHERE account_number IN (
    '411CLT0001',
    '411CLT0002',
    '411CLT0003',
    '411CLT0004',
    '411CLT0005',
    '411CLT0006'
);

DELETE FROM clients
WHERE client_code IN (
    'CLT0001','CLT0002','CLT0003','CLT0004','CLT0005','CLT0006'
);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1. CLIENTS
-- =========================================================
INSERT INTO clients (
    client_code,
    first_name,
    last_name,
    full_name,
    email,
    phone,
    client_type,
    client_status,
    currency,
    country_origin,
    country_destination,
    country_commercial,
    generated_client_account,
    initial_treasury_account_id,
    is_active,
    created_at,
    updated_at
)
SELECT
    'CLT0001',
    'Aminata',
    'Diallo',
    'Aminata Diallo',
    'aminata.diallo@test.local',
    '+221700000001',
    'Etudiant',
    'Actif',
    'EUR',
    'Sénégal',
    'France',
    'France',
    '411CLT0001',
    ta.id,
    1,
    NOW(),
    NOW()
FROM treasury_accounts ta
WHERE ta.account_code = '5120101';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    client_type, client_status, currency,
    country_origin, country_destination, country_commercial,
    generated_client_account, initial_treasury_account_id,
    is_active, created_at, updated_at
)
SELECT
    'CLT0002',
    'Moussa',
    'Traore',
    'Moussa Traore',
    'moussa.traore@test.local',
    '+223700000002',
    'Etudiant',
    'Actif',
    'EUR',
    'Mali',
    'France',
    'France',
    '411CLT0002',
    ta.id,
    1,
    NOW(),
    NOW()
FROM treasury_accounts ta
WHERE ta.account_code = '5120102';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    client_type, client_status, currency,
    country_origin, country_destination, country_commercial,
    generated_client_account, initial_treasury_account_id,
    is_active, created_at, updated_at
)
SELECT
    'CLT0003',
    'Sarah',
    'Nguessan',
    'Sarah Nguessan',
    'sarah.nguessan@test.local',
    '+225700000003',
    'Etudiant',
    'Actif',
    'EUR',
    'Côte d’Ivoire',
    'Belgique',
    'Belgique',
    '411CLT0003',
    ta.id,
    1,
    NOW(),
    NOW()
FROM treasury_accounts ta
WHERE ta.account_code = '5120301';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    client_type, client_status, currency,
    country_origin, country_destination, country_commercial,
    generated_client_account, initial_treasury_account_id,
    is_active, created_at, updated_at
)
SELECT
    'CLT0004',
    'Kevin',
    'Mba',
    'Kevin Mba',
    'kevin.mba@test.local',
    '+237700000004',
    'Particulier',
    'Actif',
    'XAF',
    'Cameroun',
    'Autres destinations',
    'Cameroun',
    '411CLT0004',
    ta.id,
    1,
    NOW(),
    NOW()
FROM treasury_accounts ta
WHERE ta.account_code = '5120401';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    client_type, client_status, currency,
    country_origin, country_destination, country_commercial,
    generated_client_account, initial_treasury_account_id,
    is_active, created_at, updated_at
)
SELECT
    'CLT0005',
    'Grace',
    'Ekué',
    'Grace Ekué',
    'grace.ekue@test.local',
    '+228700000005',
    'Entreprise',
    'Actif',
    'XOF',
    'Togo',
    'France',
    'Togo',
    '411CLT0005',
    ta.id,
    1,
    NOW(),
    NOW()
FROM treasury_accounts ta
WHERE ta.account_code = '5121401';

INSERT INTO clients (
    client_code, first_name, last_name, full_name, email, phone,
    client_type, client_status, currency,
    country_origin, country_destination, country_commercial,
    generated_client_account, initial_treasury_account_id,
    is_active, created_at, updated_at
)
SELECT
    'CLT0006',
    'Nadia',
    'Benali',
    'Nadia Benali',
    'nadia.benali@test.local',
    '+213700000006',
    'Partenaire',
    'Actif',
    'DZD',
    'Algérie',
    'Espagne',
    'Algérie',
    '411CLT0006',
    ta.id,
    1,
    NOW(),
    NOW()
FROM treasury_accounts ta
WHERE ta.account_code = '5121701';

-- =========================================================
-- 2. COMPTES CLIENTS (411 techniques via bank_accounts)
-- IMPORTANT : account_number = generated_client_account
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
VALUES
('Compte client CLT0001', '411CLT0001', 'Compte client interne', 'France', 0, 0, 1, NOW(), NOW()),
('Compte client CLT0002', '411CLT0002', 'Compte client interne', 'France', 0, 0, 1, NOW(), NOW()),
('Compte client CLT0003', '411CLT0003', 'Compte client interne', 'Belgique', 0, 0, 1, NOW(), NOW()),
('Compte client CLT0004', '411CLT0004', 'Compte client interne', 'Cameroun', 0, 0, 1, NOW(), NOW()),
('Compte client CLT0005', '411CLT0005', 'Compte client interne', 'France', 0, 0, 1, NOW(), NOW()),
('Compte client CLT0006', '411CLT0006', 'Compte client interne', 'Espagne', 0, 0, 1, NOW(), NOW());

-- =========================================================
-- 3. LIEN CLIENT ↔ COMPTE
-- =========================================================
INSERT INTO client_bank_accounts (client_id, bank_account_id, created_at)
SELECT c.id, ba.id, NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code IN ('CLT0001','CLT0002','CLT0003','CLT0004','CLT0005','CLT0006');

-- =========================================================
-- 4. OPERATIONS DE TEST
-- Règles retenues :
-- VERSEMENT / REGULARISATION POSITIVE  : débit 512 / crédit 411
-- VIREMENT MENSUEL / EXCEPTIONEL / REG NEGATIVE / REGULIER : débit 411 / crédit 512
-- FRAIS DE SERVICE / FRAIS BANCAIRES : débit 411 / crédit 706
-- VIREMENT INTERNE : dans treasury_movements
-- =========================================================

-- ---------------------------------------------------------
-- CLIENT 1 : parcours France / AVI
-- ---------------------------------------------------------
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-01', 'VERSEMENT', 'seed',
       'Versement initial client 1', 12000.00, 'TEST-OP-0001', 'seed',
       '5120101', c.generated_client_account, NULL,
       'Alimentation initiale', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0001';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-03', 'FRAIS_DE_SERVICE', 'seed',
       'Frais de services AVI', 250.00, 'TEST-OP-0002', 'seed',
       c.generated_client_account, '706101', '706101',
       'Service SRV_AVI_SERVICE', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0001';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-10', 'VIREMENT_MENSUEL', 'seed',
       'Virement mensuel client 1', 900.00, 'TEST-OP-0003', 'seed',
       c.generated_client_account, '5120101', NULL,
       'Décaissement mensuel', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0001';

-- ---------------------------------------------------------
-- CLIENT 2 : ATS + régularisations
-- ---------------------------------------------------------
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-02', 'VERSEMENT', 'seed',
       'Versement initial client 2', 8500.00, 'TEST-OP-0004', 'seed',
       '5120102', c.generated_client_account, NULL,
       'Alimentation initiale', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0002';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-05', 'FRAIS_DE_SERVICE', 'seed',
       'Frais de service ATS', 175.00, 'TEST-OP-0005', 'seed',
       c.generated_client_account, '706104', '706104',
       'Service SRV_ATS_SERVICE', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0002';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-08', 'REGULARISATION_POSITIVE', 'seed',
       'Régularisation positive client 2', 300.00, 'TEST-OP-0006', 'seed',
       '5120102', c.generated_client_account, NULL,
       'Correction en faveur du client', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0002';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-16', 'REGULARISATION_NEGATIVE', 'seed',
       'Régularisation négative client 2', 120.00, 'TEST-OP-0007', 'seed',
       c.generated_client_account, '5120102', NULL,
       'Correction défavorable au client', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0002';

-- ---------------------------------------------------------
-- CLIENT 3 : commission transfert
-- ---------------------------------------------------------
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-04', 'VERSEMENT', 'seed',
       'Versement initial client 3', 15000.00, 'TEST-OP-0008', 'seed',
       '5120301', c.generated_client_account, NULL,
       'Alimentation initiale', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0003';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-06', 'VIREMENT_EXCEPTIONEL', 'seed',
       'Commission de transfert', 420.00, 'TEST-OP-0009', 'seed',
       c.generated_client_account, '5120301', '706103',
       'Virement exceptionnel avec commission', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0003';

-- ---------------------------------------------------------
-- CLIENT 4 : frais bancaires
-- ---------------------------------------------------------
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-07', 'VERSEMENT', 'seed',
       'Versement initial client 4', 600000.00, 'TEST-OP-0010', 'seed',
       '5120401', c.generated_client_account, NULL,
       'Alimentation locale', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0004';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-09', 'FRAIS_BANCAIRES', 'seed',
       'Frais de gestion', 15000.00, 'TEST-OP-0011', 'seed',
       c.generated_client_account, '706102', '706102',
       'Service SRV_FRAIS_GESTION', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0004';

-- ---------------------------------------------------------
-- CLIENT 5 : virement régulier
-- ---------------------------------------------------------
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-11', 'VERSEMENT', 'seed',
       'Versement initial client 5', 900000.00, 'TEST-OP-0012', 'seed',
       '5121401', c.generated_client_account, NULL,
       'Alimentation initiale', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0005';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-13', 'VIREMENT_REGULIER', 'seed',
       'Virement régulier client 5', 175000.00, 'TEST-OP-0013', 'seed',
       c.generated_client_account, '5121401', NULL,
       'Décaissement régulier', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0005';

-- ---------------------------------------------------------
-- CLIENT 6 : combinaison service + négatif
-- ---------------------------------------------------------
INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-12', 'VERSEMENT', 'seed',
       'Versement initial client 6', 300000.00, 'TEST-OP-0014', 'seed',
       '5121701', c.generated_client_account, NULL,
       'Alimentation initiale', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0006';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-18', 'FRAIS_DE_SERVICE', 'seed',
       'Frais de services AVI', 6000.00, 'TEST-OP-0015', 'seed',
       c.generated_client_account, '706101', '706101',
       'Facturation service', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0006';

INSERT INTO operations (
    client_id, bank_account_id, operation_date, operation_type_code, operation_kind,
    label, amount, reference, source_type,
    debit_account_code, credit_account_code, service_account_code,
    notes, created_at, updated_at
)
SELECT c.id, ba.id, '2026-03-21', 'REGULARISATION_NEGATIVE', 'seed',
       'Régularisation négative client 6', 12000.00, 'TEST-OP-0016', 'seed',
       c.generated_client_account, '5121701', NULL,
       'Correction négative', NOW(), NOW()
FROM clients c
JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
WHERE c.client_code = 'CLT0006';

-- =========================================================
-- 5. VIREMENTS INTERNES ENTRE 512
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
    50000.00,
    '2026-03-14',
    'TEST-TM-0001',
    'Virement interne CA placement',
    NOW(),
    NOW()
FROM treasury_accounts ta1
JOIN treasury_accounts ta2
    ON ta1.account_code = '5120108'
   AND ta2.account_code = '5120109';

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
    25000.00,
    '2026-03-19',
    'TEST-TM-0002',
    'Virement interne trésorerie Europe',
    NOW(),
    NOW()
FROM treasury_accounts ta1
JOIN treasury_accounts ta2
    ON ta1.account_code = '5120101'
   AND ta2.account_code = '5120102';

-- =========================================================
-- 6. RECALCUL DES SOLDES
-- =========================================================

-- 6.1 comptes clients techniques
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
    ba.updated_at = NOW()
WHERE ba.account_number IN (
    '411CLT0001','411CLT0002','411CLT0003','411CLT0004','411CLT0005','411CLT0006'
);

-- 6.2 comptes 706
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
    sa.updated_at = NOW()
WHERE sa.account_code IN ('706101','706102','706103','706104','706105','706106');

-- 6.3 comptes 512
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
    ta.updated_at = NOW()
WHERE ta.account_code IN (
    '5120101','5120102','5120108','5120109','5120301','5120401','5121401','5121701'
);

-- =========================================================
-- 7. CONTROLES RAPIDES
-- =========================================================
SELECT client_code, full_name, generated_client_account, country_destination, country_commercial
FROM clients
WHERE client_code IN ('CLT0001','CLT0002','CLT0003','CLT0004','CLT0005','CLT0006')
ORDER BY client_code;

SELECT account_number, account_name, balance
FROM bank_accounts
WHERE account_number IN ('411CLT0001','411CLT0002','411CLT0003','411CLT0004','411CLT0005','411CLT0006')
ORDER BY account_number;

SELECT account_code, account_label, current_balance
FROM service_accounts
WHERE account_code IN ('706101','706102','706103','706104','706105','706106')
ORDER BY account_code;

SELECT account_code, account_label, current_balance
FROM treasury_accounts
WHERE account_code IN ('5120101','5120102','5120108','5120109','5120301','5120401','5121401','5121701')
ORDER BY account_code;

SELECT operation_type_code, COUNT(*) AS total_ops, SUM(amount) AS total_amount
FROM operations
WHERE reference LIKE 'TEST-OP-%'
GROUP BY operation_type_code
ORDER BY operation_type_code;