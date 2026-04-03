<?php

require_once __DIR__ . '/rules_engine.php';

if (!function_exists('sl_detect_operation_anomalies')) {
    function sl_detect_operation_anomalies(array $payload): array
    {
        $anomalies = [];

        $amount = (float)($payload['amount'] ?? 0);
        $currencyCode = trim((string)($payload['currency_code'] ?? ''));
        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : 0;
        $operationTypeCode = (string)($payload['operation_type_code'] ?? '');
        $serviceCode = (string)($payload['service_code'] ?? '');
        $sourceAccount = trim((string)($payload['manual_debit_account_code'] ?? ''));
        $destinationAccount = trim((string)($payload['manual_credit_account_code'] ?? ''));
        $reference = trim((string)($payload['reference'] ?? ''));

        if ($amount <= 0) {
            $anomalies[] = [
                'level' => 'danger',
                'code' => 'INVALID_AMOUNT',
                'message' => 'Le montant est invalide ou nul.',
            ];
        }

        if ($amount >= 10000) {
            $anomalies[] = [
                'level' => 'warning',
                'code' => 'HIGH_AMOUNT',
                'message' => 'Montant élevé à contrôler.',
            ];
        }

        if ($currencyCode === '') {
            $anomalies[] = [
                'level' => 'warning',
                'code' => 'MISSING_CURRENCY',
                'message' => 'La devise est absente.',
            ];
        }

        if (sl_rules_client_required($operationTypeCode, $serviceCode) && $clientId <= 0) {
            $anomalies[] = [
                'level' => 'danger',
                'code' => 'MISSING_CLIENT',
                'message' => 'Le client est obligatoire pour cette opération.',
            ];
        }

        if (trim($operationTypeCode) === '') {
            $anomalies[] = [
                'level' => 'danger',
                'code' => 'MISSING_OPERATION_TYPE',
                'message' => 'Le type d’opération est manquant.',
            ];
        }

        if (trim($serviceCode) === '') {
            $anomalies[] = [
                'level' => 'danger',
                'code' => 'MISSING_SERVICE',
                'message' => 'Le type de service est manquant.',
            ];
        }

        if (sl_rules_manual_account_required($operationTypeCode, $serviceCode)) {
            if ($sourceAccount === '' || $destinationAccount === '') {
                $anomalies[] = [
                    'level' => 'danger',
                    'code' => 'MISSING_MANUAL_ACCOUNTS',
                    'message' => 'Les comptes source/destination sont obligatoires pour ce cas manuel.',
                ];
            }

            if ($sourceAccount !== '' && $destinationAccount !== '' && $sourceAccount === $destinationAccount) {
                $anomalies[] = [
                    'level' => 'warning',
                    'code' => 'SAME_MANUAL_ACCOUNTS',
                    'message' => 'Le compte source et le compte destination sont identiques.',
                ];
            }
        }

        if ($reference === '') {
            $anomalies[] = [
                'level' => 'info',
                'code' => 'MISSING_REFERENCE',
                'message' => 'La référence est vide.',
            ];
        }

        return $anomalies;
    }
}

if (!function_exists('sl_group_anomalies_by_level')) {
    function sl_group_anomalies_by_level(array $anomalies): array
    {
        $grouped = [
            'danger' => [],
            'warning' => [],
            'info' => [],
            'success' => [],
        ];

        foreach ($anomalies as $anomaly) {
            $level = strtolower((string)($anomaly['level'] ?? 'info'));
            if (!isset($grouped[$level])) {
                $grouped[$level] = [];
            }
            $grouped[$level][] = $anomaly;
        }

        return $grouped;
    }
}

if (!function_exists('sl_has_blocking_anomalies')) {
    function sl_has_blocking_anomalies(array $anomalies): bool
    {
        foreach ($anomalies as $anomaly) {
            if (($anomaly['level'] ?? '') === 'danger') {
                return true;
            }
        }
        return false;
    }
}