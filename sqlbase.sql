USE studelyledge;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 0. NETTOYAGE CIBLE
-- =========================================================
DELETE FROM ref_services
WHERE code IN (
    'SRV_AVI_SERVICE',
    'SRV_FRAIS_GESTION',
    'SRV_COMMISSION_TRANSFERT',
    'SRV_ATS_SERVICE',
    'SRV_CA_PLACEMENT',
    'SRV_CA_DIVERS'
);

DELETE FROM ref_operation_types
WHERE code IN (
    'VERSEMENT',
    'FRAIS_DE_SERVICE',
    'VIREMENT_MENSUEL',
    'VIREMENT_EXCEPTIONEL',
    'REGULARISATION_POSITIVE',
    'REGULARISATION_NEGATIVE',
    'FRAIS_BANCAIRES',
    'VIREMENT_INTERNE',
    'VIREMENT_REGULIER'
);

DELETE FROM service_accounts
WHERE account_code IN (
    '706101',
    '706102',
    '706103',
    '706104',
    '706105',
    '706106'
);

DELETE FROM treasury_accounts
WHERE account_code IN (
    '5120101','5120102','5120103','5120104','5120105','5120106','5120107','5120108','5120109',
    '5120301','5120302',
    '5120401','5120402','5120403','5120404','5120405','5120406','5120407','5120408',
    '5120409','5120410','5120411','5120412','5120413','5120414','5120415','5120416',
    '5120501','5120502',
    '5120601','5120602','5120603',
    '5120701','5120702',
    '5120801',
    '5120901','5120902','5120903',
    '5121001','5121002',
    '5121101','5121102','5121103',
    '5121201','5121202',
    '5121301','5121302',
    '5121401','5121403',
    '5121701',
    '5121801','5121802',
    '5121901',
    '5122001',
    '5122101'
);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1. TYPES D'OPERATIONS
-- =========================================================
INSERT INTO ref_operation_types (code, label, direction, is_active, created_at, updated_at) VALUES
('VERSEMENT', 'VERSEMENT', 'credit', 1, NOW(), NOW()),
('FRAIS_DE_SERVICE', 'FRAIS DE SERVICE', 'debit', 1, NOW(), NOW()),
('VIREMENT_MENSUEL', 'VIREMENT MENSUEL', 'debit', 1, NOW(), NOW()),
('VIREMENT_EXCEPTIONEL', 'VIREMENT EXCEPTIONEL', 'debit', 1, NOW(), NOW()),
('REGULARISATION_POSITIVE', 'REGULARISATION POSITIVE', 'credit', 1, NOW(), NOW()),
('REGULARISATION_NEGATIVE', 'REGULARISATION NEGATIVE', 'debit', 1, NOW(), NOW()),
('FRAIS_BANCAIRES', 'FRAIS BANCAIRES', 'debit', 1, NOW(), NOW()),
('VIREMENT_INTERNE', 'VIREMENT INTERNE', 'mixed', 1, NOW(), NOW()),
('VIREMENT_REGULIER', 'VIREMENT REGULIER', 'debit', 1, NOW(), NOW());

-- =========================================================
-- 2. COMPTES 706
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
('706101', 'FRAIS DE SERVICES AVI', 'FRAIS DE SERVICE', 'France', 'France', 3, 1, 1, 0.00, NOW(), NOW()),
('706102', 'FRAIS DE GESTION', 'FRAIS BANCAIRES', 'International', 'International', 3, 1, 1, 0.00, NOW(), NOW()),
('706103', 'COMMISSION DE TRANSFERT', 'VIREMENT EXCEPTIONEL', 'International', 'International', 3, 1, 1, 0.00, NOW(), NOW()),
('706104', 'FRAIS DE SERVICE ATS', 'FRAIS DE SERVICE', 'France', 'France', 3, 1, 1, 0.00, NOW(), NOW()),
('706105', 'CA PLACEMENT', 'VIREMENT INTERNE', 'International', 'International', 3, 1, 1, 0.00, NOW(), NOW()),
('706106', 'CA DIVERS', 'VIREMENT INTERNE', 'International', 'International', 3, 1, 1, 0.00, NOW(), NOW());

-- =========================================================
-- 3. COMPTES 512
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
('5120101', 'Fr_LCL_C - France', 'Fr_LCL_C', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120102', 'Fr_LCL_M - France', 'Fr_LCL_M', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120103', 'FR_CIC - France', 'FR_CIC', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120104', 'FR_CCOOP - France', 'FR_CCOOP', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120105', 'Fr_MANGO - France', 'Fr_MANGO', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120106', 'FR_SG - France', 'FR_SG', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120107', 'FR_SG_EXPL - France', 'FR_SG_EXPL', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120108', 'FR_SPENDESK - France', 'FR_SPENDESK', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120109', 'FR_TRUST - France', 'FR_TRUST', 'Studely', 'EU', 'France', 'Siege', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),

('5120301', 'BE_QUONTO - Belgique', 'BE_QUONTO', 'Studely', 'EU', 'Belgique', 'Filiale', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),
('5120302', 'BE_REVOLUT - Belgique', 'BE_REVOLUT', 'Studely', 'EU', 'Belgique', 'Filiale', 'France', 'EUR', 0, 0, 1, NOW(), NOW()),

('5120401', 'CM_BAC - Cameroun', 'CM_BAC', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120402', 'CM_BAC_EXPL - Cameroun', 'CM_BAC_EXPL', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120403', 'CM_BAC_REM - Cameroun', 'CM_BAC_REM', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120404', 'CM_BGFI_DE - Cameroun', 'CM_BGFI_DE', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120405', 'CM_BGFI_EXPL - Cameroun', 'CM_BGFI_EXPL', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120406', 'CM_BGFI_FR - Cameroun', 'CM_BGFI_FR', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120407', 'CM_CBC - Cameroun', 'CM_CBC', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120408', 'CM_UBA - Cameroun', 'CM_UBA', 'Studely', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120409', 'SF_CM_ACCESS_BANK - Cameroun', 'SF_CM_ACCESS_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120410', 'SF_CM_AFD_BANK - Cameroun', 'SF_CM_AFD_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120411', 'SF_CM_AFD_EXPL - Cameroun', 'SF_CM_AFD_EXPL', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120412', 'SF_CM_BAC - Cameroun', 'SF_CM_BAC', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120413', 'SF_CM_BAC_EXPL - Cameroun', 'SF_CM_BAC_EXPL', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120414', 'SF_CM_BGFI - Cameroun', 'SF_CM_BGFI', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120415', 'SF_CM_CCA_BANK - Cameroun', 'SF_CM_CCA_BANK', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120416', 'SF_CM_UBA - Cameroun', 'SF_CM_UBA', 'Studely Finance', 'AC', 'Cameroun', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),

('5120501', 'SF_SN_EcoBQ - Sénégal', 'SF_SN_EcoBQ', 'Studely Finance', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),
('5120502', 'SN_ECOBQ - Sénégal', 'SN_ECOBQ', 'Studely', 'AO', 'Sénégal', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),

('5120601', 'CIV_ECOBQ - Côte d''Ivoire', 'CIV_ECOBQ', 'Studely', 'AO', 'Côte d''Ivoire', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),
('5120602', 'SF_CIV_AFG - Côte d''Ivoire', 'SF_CIV_AFG', 'Studely Finance', 'AO', 'Côte d''Ivoire', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),
('5120603', 'SF_CIV_EcoBQ - Côte d''Ivoire', 'SF_CIV_EcoBQ', 'Studely Finance', 'AO', 'Côte d''Ivoire', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),

('5120701', 'BN_ECOBQ - Benin', 'BN_ECOBQ', 'Studely', 'AO', 'Benin', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),
('5120702', 'SF_BN_EcoBQ - Benin', 'SF_BN_EcoBQ', 'Studely Finance', 'AO', 'Benin', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),

('5120801', 'BFA_ECOBQ - Burkina Faso', 'BFA_ECOBQ', 'Studely', 'AO', 'Burkina Faso', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),

('5120901', 'CD_BGFI - Congo Brazzaville', 'CD_BGFI', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120902', 'CD_BGFI_EXPL - Congo Brazzaville', 'CD_BGFI_EXPL', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5120903', 'MUP_MF - Congo Brazzaville', 'MUP_MF', 'Studely', 'AC', 'Congo Brazzaville', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),

('5121001', 'RD_BGFI - Congo Kinshasa', 'RD_BGFI', 'Studely', 'AC', 'Congo Kinshasa', 'Filiale', 'Local', 'USD', 0, 0, 1, NOW(), NOW()),
('5121002', 'RD_BGFI_EURO - Congo Kinshasa', 'RD_BGFI_EURO', 'Studely', 'AC', 'Congo Kinshasa', 'Filiale', 'Local', 'EUR', 0, 0, 1, NOW(), NOW()),

('5121101', 'GB_BGFI - Gabon', 'GB_BGFI', 'Studely', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5121102', 'GB_BGFI_EXPL - Gabon', 'GB_BGFI_EXPL', 'Studely', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5121103', 'SF_GB_ECOBQ - Gabon', 'SF_GB_ECOBQ', 'Studely Finance', 'AC', 'Gabon', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),

('5121201', 'SF_CHD_ECOBAQ - Tchad', 'SF_CHD_ECOBAQ', 'Studely Finance', 'AC', 'Tchad', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),
('5121202', 'SF_TCHAD_UBA - Tchad', 'SF_TCHAD_UBA', 'Studely Finance', 'AC', 'Tchad', 'Filiale', 'Local', 'XAF', 0, 0, 1, NOW(), NOW()),

('5121301', 'ML_ECOBQ - Mali', 'ML_ECOBQ', 'Studely', 'AO', 'Mali', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),
('5121302', 'ML_SCOLARIS FI - Mali', 'ML_SCOLARIS FI', 'Studely', 'AO', 'Mali', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),

('5121401', 'TGO_ECOBQ - Togo', 'TGO_ECOBQ', 'Studely', 'AO', 'Togo', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),
('5121403', 'SF_TG_EcoBQ - Togo', 'SF_TG_EcoBQ', 'Studely Finance', 'AO', 'Togo', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW()),

('5121701', 'ALG_BNP - Algérie', 'ALG_BNP', 'Studely', 'AN', 'Algérie', 'Filiale', 'Local', 'DZD', 0, 0, 1, NOW(), NOW()),

('5121801', 'GUI_ECOBQ - Guinée', 'GUI_ECOBQ', 'Studely', 'AO', 'Guinée', 'Filiale', 'Local', 'GNF', 0, 0, 1, NOW(), NOW()),
('5121802', 'SF_GUI_EcoBQ - Guinée', 'SF_GUI_EcoBQ', 'Studely Finance', 'AO', 'Guinée', 'Filiale', 'Local', 'GNF', 0, 0, 1, NOW(), NOW()),

('5121901', 'TN_ATTI - Tunisie', 'TN_ATTI', 'Studely', 'AN', 'Tunisie', 'Filiale', 'Local', 'TND', 0, 0, 1, NOW(), NOW()),
('5122001', 'MA_ATTI - Maroc', 'MA_ATTI', 'Studely', 'AN', 'Maroc', 'Filiale', 'Local', 'MAD', 0, 0, 1, NOW(), NOW()),
('5122101', 'NG_ECOBQ - Niger', 'NG_ECOBQ', 'Studely', 'AO', 'Niger', 'Filiale', 'Local', 'XOF', 0, 0, 1, NOW(), NOW());

-- =========================================================
-- 4. SERVICES METIER
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
SELECT
    'SRV_AVI_SERVICE',
    'FRAIS DE SERVICES AVI',
    rot.id,
    sa.id,
    ta.id,
    1,
    NOW(),
    NOW()
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706101'
JOIN treasury_accounts ta ON ta.account_code = '5120101'
WHERE rot.code = 'FRAIS_DE_SERVICE';

INSERT INTO ref_services (
    code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at, updated_at
)
SELECT
    'SRV_FRAIS_GESTION',
    'FRAIS DE GESTION',
    rot.id,
    sa.id,
    ta.id,
    1,
    NOW(),
    NOW()
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706102'
JOIN treasury_accounts ta ON ta.account_code = '5120106'
WHERE rot.code = 'FRAIS_BANCAIRES';

INSERT INTO ref_services (
    code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at, updated_at
)
SELECT
    'SRV_COMMISSION_TRANSFERT',
    'COMMISSION DE TRANSFERT',
    rot.id,
    sa.id,
    ta.id,
    1,
    NOW(),
    NOW()
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706103'
JOIN treasury_accounts ta ON ta.account_code = '5120301'
WHERE rot.code = 'VIREMENT_EXCEPTIONEL';

INSERT INTO ref_services (
    code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at, updated_at
)
SELECT
    'SRV_ATS_SERVICE',
    'FRAIS DE SERVICE ATS',
    rot.id,
    sa.id,
    ta.id,
    1,
    NOW(),
    NOW()
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706104'
JOIN treasury_accounts ta ON ta.account_code = '5120102'
WHERE rot.code = 'FRAIS_DE_SERVICE';

INSERT INTO ref_services (
    code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at, updated_at
)
SELECT
    'SRV_CA_PLACEMENT',
    'CA PLACEMENT',
    rot.id,
    sa.id,
    ta.id,
    1,
    NOW(),
    NOW()
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706105'
JOIN treasury_accounts ta ON ta.account_code = '5120108'
WHERE rot.code = 'VIREMENT_INTERNE';

INSERT INTO ref_services (
    code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at, updated_at
)
SELECT
    'SRV_CA_DIVERS',
    'CA DIVERS',
    rot.id,
    sa.id,
    ta.id,
    1,
    NOW(),
    NOW()
FROM ref_operation_types rot
JOIN service_accounts sa ON sa.account_code = '706106'
JOIN treasury_accounts ta ON ta.account_code = '5120109'
WHERE rot.code = 'VIREMENT_INTERNE';