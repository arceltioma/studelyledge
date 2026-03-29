USE studelyledge;

-- =========================================================
-- 0. AJOUT DES COLONNES HIERARCHIQUES SI NECESSAIRE
-- =========================================================
ALTER TABLE service_accounts
    ADD COLUMN IF NOT EXISTS parent_account_id INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS account_level INT NOT NULL DEFAULT 1 AFTER parent_account_id,
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER account_level;

ALTER TABLE service_accounts
    ADD INDEX IF NOT EXISTS idx_service_accounts_parent_account_id (parent_account_id),
    ADD INDEX IF NOT EXISTS idx_service_accounts_account_code (account_code);

-- =========================================================
-- 1. NETTOYAGE CIBLE
-- =========================================================
DELETE FROM service_accounts
WHERE account_code IN (
    '706',
    '7061','70611','70612','70613',
    '7062',
    '7063',
    '7064','70641','70642','70643','70644','70645',
    '7065',
    '7066',

    '7061101','7061102','7061103','7061104','7061105','7061106','7061107','7061108','7061109','7061110','7061111','7061112','7061113','7061114','7061115','7061116','7061117','7061118','7061119','7061120','7061121','7061122','7061123',
    '7061201','7061202','7061203','7061204','7061205','7061206','7061207','7061208','7061209','7061210','7061211','7061212','7061213','7061214','7061215','7061216','7061217','7061218','7061219','7061220','7061221','7061222','7061223',
    '7061301','7061302','7061303','7061304','7061305','7061306','7061307','7061308','7061309','7061310','7061311','7061312','7061313','7061314','7061315','7061316','7061317','7061318','7061319','7061320','7061321','7061322','7061323',

    '706201','706202','706203','706204','706205','706206','706207','706208','706209','706210','706211','706212','706213','706214','706215','706216','706217','706218','706219','706220','706221','706222','706223',

    '706301','706302','706303','706304','706305','706306','706307','706308','706309','706310','706311','706312','706313','706314','706315','706316','706317','706318','706319','706320','706321','706322','706323',

    '706501','706502','706503','706504','706505','706506','706507','706508','706509','706510','706511','706512','706513','706514','706515','706516','706517','706518','706519','706520','706521','706522','706523',

    '706601','706602','706603','706604','706605','706606','706607','706608','706609','706610','706611','706612','706613','706614','706615','706616','706617','706618','706619','706620','706621','706622','706623'
);

-- =========================================================
-- 2. RACINE
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id,
    account_code,
    account_label,
    operation_type_label,
    destination_country_label,
    commercial_country_label,
    account_level,
    level_depth,
    sort_order,
    is_postable,
    is_active,
    current_balance,
    created_at,
    updated_at
) VALUES (
    NULL,
    '706',
    'Prestations de services',
    NULL,
    NULL,
    NULL,
    1,
    1,
    10,
    0,
    1,
    0.00,
    NOW(),
    NOW()
);

-- =========================================================
-- 3. NIVEAU 2
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id,
    account_code,
    account_label,
    operation_type_label,
    destination_country_label,
    commercial_country_label,
    account_level,
    level_depth,
    sort_order,
    is_postable,
    is_active,
    current_balance,
    created_at,
    updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    x.operation_type_label,
    x.destination_country_label,
    NULL,
    2,
    2,
    x.sort_order,
    0,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '7061' AS account_code, 'FRAIS DE SERVICES AVI' AS account_label, 'FRAIS_DE_SERVICE' AS operation_type_label, NULL AS destination_country_label, 20 AS sort_order
    UNION ALL SELECT '7062', 'FRAIS DE GESTION', 'FRAIS_BANCAIRES', NULL, 30
    UNION ALL SELECT '7063', 'COMMISSION DE TRANSFERT', 'VIREMENT_EXCEPTIONEL', NULL, 40
    UNION ALL SELECT '7064', 'CA DIVERS', 'VIREMENT_INTERNE', NULL, 50
    UNION ALL SELECT '7065', 'FRAIS DE SERVICE ATS', 'FRAIS_DE_SERVICE', NULL, 60
    UNION ALL SELECT '7066', 'CA PLACEMENT', 'VIREMENT_INTERNE', NULL, 70
) x
WHERE p.account_code = '706';

-- =========================================================
-- 4. NIVEAU 3
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id,
    account_code,
    account_label,
    operation_type_label,
    destination_country_label,
    commercial_country_label,
    account_level,
    level_depth,
    sort_order,
    is_postable,
    is_active,
    current_balance,
    created_at,
    updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    x.operation_type_label,
    x.destination_country_label,
    NULL,
    3,
    3,
    x.sort_order,
    0,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '70611' AS account_code, 'FRAIS DE SERVICE AVI ALLEMAGNE' AS account_label, 'FRAIS_DE_SERVICE' AS operation_type_label, 'Allemagne' AS destination_country_label, 110 AS sort_order
    UNION ALL SELECT '70612', 'FRAIS DE SERVICES AVI BELGIQUE', 'FRAIS_DE_SERVICE', 'Belgique', 120
    UNION ALL SELECT '70613', 'FRAIS DE SERVICES AVI FRANCE', 'FRAIS_DE_SERVICE', 'France', 130
    UNION ALL SELECT '70641', 'CA DEBOURS LOGEMENT', 'VIREMENT_INTERNE', NULL, 410
    UNION ALL SELECT '70642', 'CA DEBOURS ASSURANCE', 'VIREMENT_INTERNE', NULL, 420
    UNION ALL SELECT '70643', 'FRAIS DEBOURS MICROFINANCE', 'VIREMENT_INTERNE', NULL, 430
    UNION ALL SELECT '70644', 'CA COURTAGE PRÊT', 'VIREMENT_INTERNE', NULL, 440
    UNION ALL SELECT '70645', 'CA LOGEMENT', 'VIREMENT_INTERNE', NULL, 450
) x
WHERE p.account_code = LEFT(x.account_code, LENGTH(x.account_code) - 1);

-- =========================================================
-- 5. 70611 : AVI ALLEMAGNE
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id, account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    account_level, level_depth, sort_order,
    is_postable, is_active, current_balance, created_at, updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    'FRAIS_DE_SERVICE',
    'Allemagne',
    x.commercial_country_label,
    4,
    4,
    x.sort_order,
    1,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '7061101' AS account_code, 'FRAIS DE SERVICE AVI ALLEMAGNE-France' AS account_label, 'France' AS commercial_country_label, 1101 AS sort_order
    UNION ALL SELECT '7061102', 'FRAIS DE SERVICE AVI ALLEMAGNE-Allemagne', 'Allemagne', 1102
    UNION ALL SELECT '7061103', 'FRAIS DE SERVICE AVI ALLEMAGNE-Belgique', 'Belgique', 1103
    UNION ALL SELECT '7061104', 'FRAIS DE SERVICE AVI ALLEMAGNE-Cameroun', 'Cameroun', 1104
    UNION ALL SELECT '7061105', 'FRAIS DE SERVICE AVI ALLEMAGNE-Sénégal', 'Sénégal', 1105
    UNION ALL SELECT '7061106', 'FRAIS DE SERVICE AVI ALLEMAGNE-Côte d''Ivoire', 'Côte d''Ivoire', 1106
    UNION ALL SELECT '7061107', 'FRAIS DE SERVICE AVI ALLEMAGNE-Benin', 'Benin', 1107
    UNION ALL SELECT '7061108', 'FRAIS DE SERVICE AVI ALLEMAGNE-Burkina Faso', 'Burkina Faso', 1108
    UNION ALL SELECT '7061109', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Brazzaville', 'Congo Brazzaville', 1109
    UNION ALL SELECT '7061110', 'FRAIS DE SERVICE AVI ALLEMAGNE-Congo Kinshasa', 'Congo Kinshasa', 1110
    UNION ALL SELECT '7061111', 'FRAIS DE SERVICE AVI ALLEMAGNE-Gabon', 'Gabon', 1111
    UNION ALL SELECT '7061112', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tchad', 'Tchad', 1112
    UNION ALL SELECT '7061113', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mali', 'Mali', 1113
    UNION ALL SELECT '7061114', 'FRAIS DE SERVICE AVI ALLEMAGNE-Togo', 'Togo', 1114
    UNION ALL SELECT '7061115', 'FRAIS DE SERVICE AVI ALLEMAGNE-Mexique', 'Mexique', 1115
    UNION ALL SELECT '7061116', 'FRAIS DE SERVICE AVI ALLEMAGNE-Inde', 'Inde', 1116
    UNION ALL SELECT '7061117', 'FRAIS DE SERVICE AVI ALLEMAGNE-Algérie', 'Algérie', 1117
    UNION ALL SELECT '7061118', 'FRAIS DE SERVICE AVI ALLEMAGNE-Guinée', 'Guinée', 1118
    UNION ALL SELECT '7061119', 'FRAIS DE SERVICE AVI ALLEMAGNE-Tunisie', 'Tunisie', 1119
    UNION ALL SELECT '7061120', 'FRAIS DE SERVICE AVI ALLEMAGNE-Maroc', 'Maroc', 1120
    UNION ALL SELECT '7061121', 'FRAIS DE SERVICE AVI ALLEMAGNE-Niger', 'Niger', 1121
    UNION ALL SELECT '7061122', 'FRAIS DE SERVICE AVI ALLEMAGNE-Afrique de l''est', 'Afrique de l''est', 1122
    UNION ALL SELECT '7061123', 'FRAIS DE SERVICE AVI ALLEMAGNE-Autres pays', 'Autres pays', 1123
) x
WHERE p.account_code = '70611';

-- =========================================================
-- 6. 70612 : AVI BELGIQUE
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id, account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    account_level, level_depth, sort_order,
    is_postable, is_active, current_balance, created_at, updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    'FRAIS_DE_SERVICE',
    'Belgique',
    x.commercial_country_label,
    4,
    4,
    x.sort_order,
    1,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '7061201' AS account_code, 'FRAIS DE SERVICES AVI BELGIQUE-France' AS account_label, 'France' AS commercial_country_label, 1201 AS sort_order
    UNION ALL SELECT '7061202', 'FRAIS DE SERVICES AVI BELGIQUE-Allemagne', 'Allemagne', 1202
    UNION ALL SELECT '7061203', 'FRAIS DE SERVICES AVI BELGIQUE-Belgique', 'Belgique', 1203
    UNION ALL SELECT '7061204', 'FRAIS DE SERVICES AVI BELGIQUE-Cameroun', 'Cameroun', 1204
    UNION ALL SELECT '7061205', 'FRAIS DE SERVICES AVI BELGIQUE-Sénégal', 'Sénégal', 1205
    UNION ALL SELECT '7061206', 'FRAIS DE SERVICES AVI BELGIQUE-Côte d''Ivoire', 'Côte d''Ivoire', 1206
    UNION ALL SELECT '7061207', 'FRAIS DE SERVICES AVI BELGIQUE-Benin', 'Benin', 1207
    UNION ALL SELECT '7061208', 'FRAIS DE SERVICES AVI BELGIQUE-Burkina Faso', 'Burkina Faso', 1208
    UNION ALL SELECT '7061209', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Brazzaville', 'Congo Brazzaville', 1209
    UNION ALL SELECT '7061210', 'FRAIS DE SERVICES AVI BELGIQUE-Congo Kinshasa', 'Congo Kinshasa', 1210
    UNION ALL SELECT '7061211', 'FRAIS DE SERVICES AVI BELGIQUE-Gabon', 'Gabon', 1211
    UNION ALL SELECT '7061212', 'FRAIS DE SERVICES AVI BELGIQUE-Tchad', 'Tchad', 1212
    UNION ALL SELECT '7061213', 'FRAIS DE SERVICES AVI BELGIQUE-Mali', 'Mali', 1213
    UNION ALL SELECT '7061214', 'FRAIS DE SERVICES AVI BELGIQUE-Togo', 'Togo', 1214
    UNION ALL SELECT '7061215', 'FRAIS DE SERVICES AVI BELGIQUE-Mexique', 'Mexique', 1215
    UNION ALL SELECT '7061216', 'FRAIS DE SERVICES AVI BELGIQUE-Inde', 'Inde', 1216
    UNION ALL SELECT '7061217', 'FRAIS DE SERVICES AVI BELGIQUE-Algérie', 'Algérie', 1217
    UNION ALL SELECT '7061218', 'FRAIS DE SERVICES AVI BELGIQUE-Guinée', 'Guinée', 1218
    UNION ALL SELECT '7061219', 'FRAIS DE SERVICES AVI BELGIQUE-Tunisie', 'Tunisie', 1219
    UNION ALL SELECT '7061220', 'FRAIS DE SERVICES AVI BELGIQUE-Maroc', 'Maroc', 1220
    UNION ALL SELECT '7061221', 'FRAIS DE SERVICES AVI BELGIQUE-Niger', 'Niger', 1221
    UNION ALL SELECT '7061222', 'FRAIS DE SERVICES AVI BELGIQUE-Afrique de l''est', 'Afrique de l''est', 1222
    UNION ALL SELECT '7061223', 'FRAIS DE SERVICES AVI BELGIQUE-Autres pays', 'Autres pays', 1223
) x
WHERE p.account_code = '70612';

-- =========================================================
-- 7. 70613 : AVI FRANCE
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id, account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    account_level, level_depth, sort_order,
    is_postable, is_active, current_balance, created_at, updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    'FRAIS_DE_SERVICE',
    'France',
    x.commercial_country_label,
    4,
    4,
    x.sort_order,
    1,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '7061301' AS account_code, 'FRAIS DE SERVICES AVI France-France' AS account_label, 'France' AS commercial_country_label, 1301 AS sort_order
    UNION ALL SELECT '7061302', 'FRAIS DE SERVICES AVI France-Allemagne', 'Allemagne', 1302
    UNION ALL SELECT '7061303', 'FRAIS DE SERVICES AVI France-Belgique', 'Belgique', 1303
    UNION ALL SELECT '7061304', 'FRAIS DE SERVICES AVI France-Cameroun', 'Cameroun', 1304
    UNION ALL SELECT '7061305', 'FRAIS DE SERVICES AVI France-Sénégal', 'Sénégal', 1305
    UNION ALL SELECT '7061306', 'FRAIS DE SERVICES AVI France-Côte d''Ivoire', 'Côte d''Ivoire', 1306
    UNION ALL SELECT '7061307', 'FRAIS DE SERVICES AVI France-Benin', 'Benin', 1307
    UNION ALL SELECT '7061308', 'FRAIS DE SERVICES AVI France-Burkina Faso', 'Burkina Faso', 1308
    UNION ALL SELECT '7061309', 'FRAIS DE SERVICES AVI France-Congo Brazzaville', 'Congo Brazzaville', 1309
    UNION ALL SELECT '7061310', 'FRAIS DE SERVICES AVI France-Congo Kinshasa', 'Congo Kinshasa', 1310
    UNION ALL SELECT '7061311', 'FRAIS DE SERVICES AVI France-Gabon', 'Gabon', 1311
    UNION ALL SELECT '7061312', 'FRAIS DE SERVICES AVI France-Tchad', 'Tchad', 1312
    UNION ALL SELECT '7061313', 'FRAIS DE SERVICES AVI France-Mali', 'Mali', 1313
    UNION ALL SELECT '7061314', 'FRAIS DE SERVICES AVI France-Togo', 'Togo', 1314
    UNION ALL SELECT '7061315', 'FRAIS DE SERVICES AVI France-Mexique', 'Mexique', 1315
    UNION ALL SELECT '7061316', 'FRAIS DE SERVICES AVI France-Inde', 'Inde', 1316
    UNION ALL SELECT '7061317', 'FRAIS DE SERVICES AVI France-Algérie', 'Algérie', 1317
    UNION ALL SELECT '7061318', 'FRAIS DE SERVICES AVI France-Guinée', 'Guinée', 1318
    UNION ALL SELECT '7061319', 'FRAIS DE SERVICES AVI France-Tunisie', 'Tunisie', 1319
    UNION ALL SELECT '7061320', 'FRAIS DE SERVICES AVI France-Maroc', 'Maroc', 1320
    UNION ALL SELECT '7061321', 'FRAIS DE SERVICES AVI France-Niger', 'Niger', 1321
    UNION ALL SELECT '7061322', 'FRAIS DE SERVICES AVI France-Afrique de l''est', 'Afrique de l''est', 1322
    UNION ALL SELECT '7061323', 'FRAIS DE SERVICES AVI France-Autres pays', 'Autres pays', 1323
) x
WHERE p.account_code = '70613';

-- =========================================================
-- 8. 7062 : FRAIS DE GESTION
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id, account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    account_level, level_depth, sort_order,
    is_postable, is_active, current_balance, created_at, updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    'FRAIS_BANCAIRES',
    NULL,
    x.commercial_country_label,
    3,
    3,
    x.sort_order,
    1,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '706201' AS account_code, 'FRAIS DE GESTION-France' AS account_label, 'France' AS commercial_country_label, 201 AS sort_order
    UNION ALL SELECT '706202', 'FRAIS DE GESTION-Allemagne', 'Allemagne', 202
    UNION ALL SELECT '706203', 'FRAIS DE GESTION-Belgique', 'Belgique', 203
    UNION ALL SELECT '706204', 'FRAIS DE GESTION-Cameroun', 'Cameroun', 204
    UNION ALL SELECT '706205', 'FRAIS DE GESTION-Sénégal', 'Sénégal', 205
    UNION ALL SELECT '706206', 'FRAIS DE GESTION-Côte d''Ivoire', 'Côte d''Ivoire', 206
    UNION ALL SELECT '706207', 'FRAIS DE GESTION-Benin', 'Benin', 207
    UNION ALL SELECT '706208', 'FRAIS DE GESTION-Burkina Faso', 'Burkina Faso', 208
    UNION ALL SELECT '706209', 'FRAIS DE GESTION-Congo Brazzaville', 'Congo Brazzaville', 209
    UNION ALL SELECT '706210', 'FRAIS DE GESTION-Congo Kinshasa', 'Congo Kinshasa', 210
    UNION ALL SELECT '706211', 'FRAIS DE GESTION-Gabon', 'Gabon', 211
    UNION ALL SELECT '706212', 'FRAIS DE GESTION-Tchad', 'Tchad', 212
    UNION ALL SELECT '706213', 'FRAIS DE GESTION-Mali', 'Mali', 213
    UNION ALL SELECT '706214', 'FRAIS DE GESTION-Togo', 'Togo', 214
    UNION ALL SELECT '706215', 'FRAIS DE GESTION-Mexique', 'Mexique', 215
    UNION ALL SELECT '706216', 'FRAIS DE GESTION-Inde', 'Inde', 216
    UNION ALL SELECT '706217', 'FRAIS DE GESTION-Algérie', 'Algérie', 217
    UNION ALL SELECT '706218', 'FRAIS DE GESTION-Guinée', 'Guinée', 218
    UNION ALL SELECT '706219', 'FRAIS DE GESTION-Tunisie', 'Tunisie', 219
    UNION ALL SELECT '706220', 'FRAIS DE GESTION-Maroc', 'Maroc', 220
    UNION ALL SELECT '706221', 'FRAIS DE GESTION-Niger', 'Niger', 221
    UNION ALL SELECT '706222', 'FRAIS DE GESTION-Afrique de l''est', 'Afrique de l''est', 222
    UNION ALL SELECT '706223', 'FRAIS DE GESTION-Autres pays', 'Autres pays', 223
) x
WHERE p.account_code = '7062';

-- =========================================================
-- 9. 7063 : COMMISSION DE TRANSFERT
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id, account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    account_level, level_depth, sort_order,
    is_postable, is_active, current_balance, created_at, updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    'VIREMENT_EXCEPTIONEL',
    NULL,
    x.commercial_country_label,
    3,
    3,
    x.sort_order,
    1,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '706301' AS account_code, 'COMMISSION DE TRANSFERT-France' AS account_label, 'France' AS commercial_country_label, 301 AS sort_order
    UNION ALL SELECT '706302', 'COMMISSION DE TRANSFERT-Allemagne', 'Allemagne', 302
    UNION ALL SELECT '706303', 'COMMISSION DE TRANSFERT-Belgique', 'Belgique', 303
    UNION ALL SELECT '706304', 'COMMISSION DE TRANSFERT-Cameroun', 'Cameroun', 304
    UNION ALL SELECT '706305', 'COMMISSION DE TRANSFERT-Sénégal', 'Sénégal', 305
    UNION ALL SELECT '706306', 'COMMISSION DE TRANSFERT-Côte d''Ivoire', 'Côte d''Ivoire', 306
    UNION ALL SELECT '706307', 'COMMISSION DE TRANSFERT-Benin', 'Benin', 307
    UNION ALL SELECT '706308', 'COMMISSION DE TRANSFERT-Burkina Faso', 'Burkina Faso', 308
    UNION ALL SELECT '706309', 'COMMISSION DE TRANSFERT-Congo Brazzaville', 'Congo Brazzaville', 309
    UNION ALL SELECT '706310', 'COMMISSION DE TRANSFERT-Congo Kinshasa', 'Congo Kinshasa', 310
    UNION ALL SELECT '706311', 'COMMISSION DE TRANSFERT-Gabon', 'Gabon', 311
    UNION ALL SELECT '706312', 'COMMISSION DE TRANSFERT-Tchad', 'Tchad', 312
    UNION ALL SELECT '706313', 'COMMISSION DE TRANSFERT-Mali', 'Mali', 313
    UNION ALL SELECT '706314', 'COMMISSION DE TRANSFERT-Togo', 'Togo', 314
    UNION ALL SELECT '706315', 'COMMISSION DE TRANSFERT-Mexique', 'Mexique', 315
    UNION ALL SELECT '706316', 'COMMISSION DE TRANSFERT-Inde', 'Inde', 316
    UNION ALL SELECT '706317', 'COMMISSION DE TRANSFERT-Algérie', 'Algérie', 317
    UNION ALL SELECT '706318', 'COMMISSION DE TRANSFERT-Guinée', 'Guinée', 318
    UNION ALL SELECT '706319', 'COMMISSION DE TRANSFERT-Tunisie', 'Tunisie', 319
    UNION ALL SELECT '706320', 'COMMISSION DE TRANSFERT-Maroc', 'Maroc', 320
    UNION ALL SELECT '706321', 'COMMISSION DE TRANSFERT-Niger', 'Niger', 321
    UNION ALL SELECT '706322', 'COMMISSION DE TRANSFERT-Afrique de l''est', 'Afrique de l''est', 322
    UNION ALL SELECT '706323', 'COMMISSION DE TRANSFERT-Autres pays', 'Autres pays', 323
) x
WHERE p.account_code = '7063';

-- =========================================================
-- 10. 7065 : FRAIS DE SERVICE ATS
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id, account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    account_level, level_depth, sort_order,
    is_postable, is_active, current_balance, created_at, updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    'FRAIS_DE_SERVICE',
    NULL,
    x.commercial_country_label,
    3,
    3,
    x.sort_order,
    1,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '706501' AS account_code, 'FRAIS DE SERVICE ATS-France' AS account_label, 'France' AS commercial_country_label, 501 AS sort_order
    UNION ALL SELECT '706502', 'FRAIS DE SERVICE ATS-Allemagne', 'Allemagne', 502
    UNION ALL SELECT '706503', 'FRAIS DE SERVICE ATS-Belgique', 'Belgique', 503
    UNION ALL SELECT '706504', 'FRAIS DE SERVICE ATS-Cameroun', 'Cameroun', 504
    UNION ALL SELECT '706505', 'FRAIS DE SERVICE ATS-Sénégal', 'Sénégal', 505
    UNION ALL SELECT '706506', 'FRAIS DE SERVICE ATS-Côte d''Ivoire', 'Côte d''Ivoire', 506
    UNION ALL SELECT '706507', 'FRAIS DE SERVICE ATS-Benin', 'Benin', 507
    UNION ALL SELECT '706508', 'FRAIS DE SERVICE ATS-Burkina Faso', 'Burkina Faso', 508
    UNION ALL SELECT '706509', 'FRAIS DE SERVICE ATS-Congo Brazzaville', 'Congo Brazzaville', 509
    UNION ALL SELECT '706510', 'FRAIS DE SERVICE ATS-Congo Kinshasa', 'Congo Kinshasa', 510
    UNION ALL SELECT '706511', 'FRAIS DE SERVICE ATS-Gabon', 'Gabon', 511
    UNION ALL SELECT '706512', 'FRAIS DE SERVICE ATS-Tchad', 'Tchad', 512
    UNION ALL SELECT '706513', 'FRAIS DE SERVICE ATS-Mali', 'Mali', 513
    UNION ALL SELECT '706514', 'FRAIS DE SERVICE ATS-Togo', 'Togo', 514
    UNION ALL SELECT '706515', 'FRAIS DE SERVICE ATS-Mexique', 'Mexique', 515
    UNION ALL SELECT '706516', 'FRAIS DE SERVICE ATS-Inde', 'Inde', 516
    UNION ALL SELECT '706517', 'FRAIS DE SERVICE ATS-Algérie', 'Algérie', 517
    UNION ALL SELECT '706518', 'FRAIS DE SERVICE ATS-Guinée', 'Guinée', 518
    UNION ALL SELECT '706519', 'FRAIS DE SERVICE ATS-Tunisie', 'Tunisie', 519
    UNION ALL SELECT '706520', 'FRAIS DE SERVICE ATS-Maroc', 'Maroc', 520
    UNION ALL SELECT '706521', 'FRAIS DE SERVICE ATS-Niger', 'Niger', 521
    UNION ALL SELECT '706522', 'FRAIS DE SERVICE ATS-Afrique de l''est', 'Afrique de l''est', 522
    UNION ALL SELECT '706523', 'FRAIS DE SERVICE ATS-Autres pays', 'Autres pays', 523
) x
WHERE p.account_code = '7065';

-- =========================================================
-- 11. 7066 : CA PLACEMENT
-- =========================================================
INSERT INTO service_accounts (
    parent_account_id, account_code, account_label, operation_type_label,
    destination_country_label, commercial_country_label,
    account_level, level_depth, sort_order,
    is_postable, is_active, current_balance, created_at, updated_at
)
SELECT
    p.id,
    x.account_code,
    x.account_label,
    'VIREMENT_INTERNE',
    NULL,
    x.commercial_country_label,
    3,
    3,
    x.sort_order,
    1,
    1,
    0.00,
    NOW(),
    NOW()
FROM service_accounts p
JOIN (
    SELECT '706601' AS account_code, 'CA PLACEMENT-France' AS account_label, 'France' AS commercial_country_label, 601 AS sort_order
    UNION ALL SELECT '706602', 'CA PLACEMENT-Allemagne', 'Allemagne', 602
    UNION ALL SELECT '706603', 'CA PLACEMENT-Belgique', 'Belgique', 603
    UNION ALL SELECT '706604', 'CA PLACEMENT-Cameroun', 'Cameroun', 604
    UNION ALL SELECT '706605', 'CA PLACEMENT-Sénégal', 'Sénégal', 605
    UNION ALL SELECT '706606', 'CA PLACEMENT-Côte d''Ivoire', 'Côte d''Ivoire', 606
    UNION ALL SELECT '706607', 'CA PLACEMENT-Benin', 'Benin', 607
    UNION ALL SELECT '706608', 'CA PLACEMENT-Burkina Faso', 'Burkina Faso', 608
    UNION ALL SELECT '706609', 'CA PLACEMENT-Congo Brazzaville', 'Congo Brazzaville', 609
    UNION ALL SELECT '706610', 'CA PLACEMENT-Congo Kinshasa', 'Congo Kinshasa', 610
    UNION ALL SELECT '706611', 'CA PLACEMENT-Gabon', 'Gabon', 611
    UNION ALL SELECT '706612', 'CA PLACEMENT-Tchad', 'Tchad', 612
    UNION ALL SELECT '706613', 'CA PLACEMENT-Mali', 'Mali', 613
    UNION ALL SELECT '706614', 'CA PLACEMENT-Togo', 'Togo', 614
    UNION ALL SELECT '706615', 'CA PLACEMENT-Mexique', 'Mexique', 615
    UNION ALL SELECT '706616', 'CA PLACEMENT-Inde', 'Inde', 616
    UNION ALL SELECT '706617', 'CA PLACEMENT-Algérie', 'Algérie', 617
    UNION ALL SELECT '706618', 'CA PLACEMENT-Guinée', 'Guinée', 618
    UNION ALL SELECT '706619', 'CA PLACEMENT-Tunisie', 'Tunisie', 619
    UNION ALL SELECT '706620', 'CA PLACEMENT-Maroc', 'Maroc', 620
    UNION ALL SELECT '706621', 'CA PLACEMENT-Niger', 'Niger', 621
    UNION ALL SELECT '706622', 'CA PLACEMENT-Afrique de l''est', 'Afrique de l''est', 622
    UNION ALL SELECT '706623', 'CA PLACEMENT-Autres pays', 'Autres pays', 623
) x
WHERE p.account_code = '7066';

-- =========================================================
-- 12. CONTROLES
-- =========================================================
SELECT
    account_code,
    account_label,
    parent_account_id,
    account_level,
    level_depth,
    sort_order,
    is_postable,
    operation_type_label,
    destination_country_label,
    commercial_country_label
FROM service_accounts
WHERE account_code LIKE '706%'
ORDER BY account_code;