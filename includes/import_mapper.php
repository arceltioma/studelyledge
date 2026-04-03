<?php

if (!function_exists('sl_import_mapper_normalize')) {
    function sl_import_mapper_normalize(string $value): string
    {
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(
            ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','ù','û','ü','ç','œ','æ','/','\\','-','.'],
            ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c','oe','ae',' ',' ',' ',' '],
            $value
        );
        $value = preg_replace('/\s+/', '_', $value);
        $value = preg_replace('/[^a-z0-9_]/', '', $value);
        return trim($value, '_');
    }
}

if (!function_exists('sl_import_mapper_dictionary')) {
    function sl_import_mapper_dictionary(): array
    {
        return [
            'operation_date' => [
                'operation_date', 'date', 'date_operation', 'date valeur', 'value_date', 'booking_date'
            ],
            'amount' => [
                'amount', 'montant', 'somme', 'valeur', 'total'
            ],
            'currency_code' => [
                'currency_code', 'currency', 'devise', 'monnaie'
            ],
            'client_code' => [
                'client_code', 'code_client', 'client', 'reference_client'
            ],
            'operation_type' => [
                'operation_type', 'type_operation', 'type', 'nature_operation'
            ],
            'service' => [
                'service', 'type_service', 'service_code', 'service_label'
            ],
            'reference' => [
                'reference', 'ref', 'numero_reference'
            ],
            'label' => [
                'label', 'libelle', 'intitule', 'description'
            ],
            'notes' => [
                'notes', 'note', 'motif', 'commentaire', 'comment'
            ],
            'source_account_code' => [
                'source_account_code', 'compte_source', 'source_account', 'debit_account'
            ],
            'destination_account_code' => [
                'destination_account_code', 'compte_destination', 'destination_account', 'credit_account'
            ],
            'linked_bank_account_id' => [
                'linked_bank_account_id', 'compte_bancaire_lie', 'bank_account_id'
            ],
        ];
    }
}

if (!function_exists('sl_import_mapper_guess_field')) {
    function sl_import_mapper_guess_field(string $header): array
    {
        $normalizedHeader = sl_import_mapper_normalize($header);
        $dictionary = sl_import_mapper_dictionary();

        $bestField = '';
        $bestScore = 0;

        foreach ($dictionary as $targetField => $variants) {
            foreach ($variants as $variant) {
                $normalizedVariant = sl_import_mapper_normalize($variant);

                if ($normalizedHeader === $normalizedVariant) {
                    return [
                        'field' => $targetField,
                        'score' => 100,
                        'matched_on' => $variant,
                    ];
                }

                if (str_contains($normalizedHeader, $normalizedVariant) || str_contains($normalizedVariant, $normalizedHeader)) {
                    $score = 70;
                    if ($score > $bestScore) {
                        $bestField = $targetField;
                        $bestScore = $score;
                    }
                }
            }
        }

        return [
            'field' => $bestField,
            'score' => $bestScore,
            'matched_on' => $bestScore > 0 ? 'approx' : '',
        ];
    }
}

if (!function_exists('sl_import_mapper_suggest_mapping')) {
    function sl_import_mapper_suggest_mapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $guess = sl_import_mapper_guess_field((string)$header);
            $mapping[(string)$header] = $guess;
        }

        return $mapping;
    }
}

if (!function_exists('sl_import_mapper_required_fields')) {
    function sl_import_mapper_required_fields(): array
    {
        return [
            'operation_date',
            'amount',
            'operation_type',
            'service',
        ];
    }
}