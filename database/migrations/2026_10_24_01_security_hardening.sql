-- StudelyLedger - Migration sécurité (2026-10-24)
-- Journal des tentatives de connexion utilisé pour limiter le brute-force sur login.php.
-- Idempotente : peut être rejouée sans risque.

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL DEFAULT '',
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_user` (`username`, `attempted_at`),
  KEY `idx_login_attempts_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sl_schema_versions` (
  `version` varchar(64) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `sl_schema_versions` (`version`, `applied_at`) VALUES ('20261024_01', NOW());
