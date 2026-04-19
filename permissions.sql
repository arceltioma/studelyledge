-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 19 avr. 2026 à 12:39
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

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `uq_permissions_code` (`code`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=989;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
