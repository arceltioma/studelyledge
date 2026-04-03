<?php

if (!function_exists('sl_rules_normalize')) {
    function sl_rules_normalize(?string $value): string
    {
        $value = strtoupper(trim((string)$value));
        $value = str_replace(
            ['É','È','Ê','Ë','À','Â','Ä','Î','Ï','Ô','Ö','Ù','Û','Ü','Ç',' ', '-', '/', '\''],
            ['E','E','E','E','A','A','A','I','I','O','O','U','U','U','C','_','_','_',''],
            $value
        );
        $value = preg_replace('/[^A-Z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('sl_build_service_account_search_tokens')) {
    function sl_build_service_account_search_tokens(
        ?string $operationTypeCode,
        ?string $serviceCode,
        ?string $countryCommercial,
        ?string $countryDestination
    ): array {
        $operationTypeCode = sl_rules_normalize($operationTypeCode);
        $serviceCode = sl_rules_normalize($serviceCode);
        $countryCommercial = trim((string)$countryCommercial);
        $countryDestination = trim((string)$countryDestination);

        $tokens = [];

        if ($operationTypeCode === 'FRAIS_SERVICE' && $serviceCode === 'AVI') {
            $tokens = array_filter([
                'FRAIS DE SERVICE',
                'AVI',
                $countryDestination,
                $countryCommercial,
            ]);
        } elseif ($operationTypeCode === 'FRAIS_SERVICE' && $serviceCode === 'ATS') {
            $tokens = array_filter([
                'FRAIS DE SERVICE',
                'ATS',
                $countryCommercial,
            ]);
        } elseif ($operationTypeCode === 'FRAIS_GESTION' && $serviceCode === 'GESTION') {
            $tokens = array_filter([
                'FRAIS DE GESTION',
                $countryCommercial,
            ]);
        } elseif ($operationTypeCode === 'COMMISSION_DE_TRANSFERT' && $serviceCode === 'COMMISSION_DE_TRANSFERT') {
            $tokens = array_filter([
                'COMMISSION DE TRANSFERT',
                $countryCommercial,
            ]);
        } elseif ($operationTypeCode === 'CA_PLACEMENT' && $serviceCode === 'CA_PLACEMENT') {
            $tokens = array_filter([
                'CA PLACEMENT',
                $countryCommercial,
            ]);
        }

        return array_values($tokens);
    }
}

if (!function_exists('sl_rules_describe_account_search')) {
    function sl_rules_describe_account_search(
        ?string $operationTypeCode,
        ?string $serviceCode,
        ?string $countryCommercial,
        ?string $countryDestination
    ): string {
        $tokens = sl_build_service_account_search_tokens(
            $operationTypeCode,
            $serviceCode,
            $countryCommercial,
            $countryDestination
        );

        return $tokens ? implode(' | ', $tokens) : '';
    }
}

if (!function_exists('sl_rules_manual_account_required')) {
    function sl_rules_manual_account_required(?string $operationTypeCode, ?string $serviceCode): bool
    {
        $operationTypeCode = sl_rules_normalize($operationTypeCode);
        $serviceCode = sl_rules_normalize($serviceCode);

        $manualCases = [
            'VIREMENT::INTERNE',
            'CA_DIVERS::CA_DIVERS',
            'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE',
            'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE',
            'CA_COURTAGE_PRET::CA_COURTAGE_PRET',
            'CA_LOGEMENT::CA_LOGEMENT',
        ];

        return in_array($operationTypeCode . '::' . $serviceCode, $manualCases, true);
    }
}

if (!function_exists('sl_rules_linked_bank_required')) {
    function sl_rules_linked_bank_required(?string $operationTypeCode, ?string $serviceCode): bool
    {
        $operationTypeCode = sl_rules_normalize($operationTypeCode);
        $serviceCode = sl_rules_normalize($serviceCode);

        if ($operationTypeCode === 'VERSEMENT') {
            return true;
        }

        if ($operationTypeCode === 'REGULARISATION') {
            return true;
        }

        if ($operationTypeCode === 'VIREMENT' && $serviceCode !== 'INTERNE' && $serviceCode !== '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('sl_rules_client_required')) {
    function sl_rules_client_required(?string $operationTypeCode, ?string $serviceCode): bool
    {
        $operationTypeCode = sl_rules_normalize($operationTypeCode);
        $serviceCode = sl_rules_normalize($serviceCode);

        return !($operationTypeCode === 'VIREMENT' && $serviceCode === 'INTERNE');
    }
}

if (!function_exists('sl_rules_build_summary')) {
    function sl_rules_build_summary(
        ?string $operationTypeCode,
        ?string $serviceCode,
        ?string $countryCommercial,
        ?string $countryDestination
    ): array {
        return [
            'requires_client' => sl_rules_client_required($operationTypeCode, $serviceCode),
            'requires_linked_bank' => sl_rules_linked_bank_required($operationTypeCode, $serviceCode),
            'requires_manual_accounts' => sl_rules_manual_account_required($operationTypeCode, $serviceCode),
            'service_account_tokens' => sl_build_service_account_search_tokens(
                $operationTypeCode,
                $serviceCode,
                $countryCommercial,
                $countryDestination
            ),
            'service_account_search_text' => sl_rules_describe_account_search(
                $operationTypeCode,
                $serviceCode,
                $countryCommercial,
                $countryDestination
            ),
        ];
    }
}