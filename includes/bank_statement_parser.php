<?php

if (!function_exists('bsp_strip_accents')) {
    function bsp_strip_accents(string $value): string
    {
        $trans = [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a',
            'ç'=>'c',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n',
            'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ý'=>'y','ÿ'=>'y',
            'À'=>'A','Á'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','Å'=>'A',
            'Ç'=>'C',
            'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'Ñ'=>'N',
            'Ò'=>'O','Ó'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
            'Ý'=>'Y'
        ];
        return strtr($value, $trans);
    }
}

if (!function_exists('bsp_normalize_header')) {
    function bsp_normalize_header(string $value): string
    {
        $value = trim($value);
        $value = bsp_strip_accents($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('bsp_detect_delimiter')) {
    function bsp_detect_delimiter(string $line): string
    {
        $candidates = [';', ',', "\t", '|'];
        $bestDelimiter = ';';
        $bestCount = -1;

        foreach ($candidates as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }
}

if (!function_exists('bsp_normalize_amount')) {
    function bsp_normalize_amount(?string $raw): ?float
    {
        $value = trim((string)$raw);

        if ($value === '') {
            return null;
        }

        $value = str_replace(["\xc2\xa0", ' '], '', $value);
        $value = str_replace('€', '', $value);
        $value = str_replace("'", '', $value);

        // format français : 1.234,56 ou 1234,56
        if (preg_match('/^-?[0-9\.\,]+$/', $value)) {
            if (str_contains($value, ',') && str_contains($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } elseif (str_contains($value, ',')) {
                $value = str_replace(',', '.', $value);
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round((float)$value, 2);
    }
}

if (!function_exists('bsp_normalize_date')) {
    function bsp_normalize_date(?string $raw): ?string
    {
        $value = trim((string)$raw);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($value);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }
}

if (!function_exists('bsp_detect_columns')) {
    function bsp_detect_columns(array $headers): array
    {
        $map = [
            'operation_date' => null,
            'value_date' => null,
            'label' => null,
            'debit' => null,
            'credit' => null,
            'balance' => null,
            'amount' => null,
            'reference' => null,
            'account_code' => null,
            'client_code' => null,
        ];

        $dictionary = [
            'operation_date' => ['date_operation', 'date', 'date_op', 'date_ecriture', 'date_operation_comptable'],
            'value_date' => ['date_valeur', 'valeur', 'date_val'],
            'label' => ['libelle', 'intitule', 'motif', 'description', 'operation', 'libelle_operation'],
            'debit' => ['debit', 'montant_debit', 'sortie', 'debits'],
            'credit' => ['credit', 'montant_credit', 'entree', 'credits'],
            'balance' => ['solde', 'balance', 'solde_compte'],
            'amount' => ['montant', 'amount', 'somme'],
            'reference' => ['reference', 'ref', 'numero_operation', 'piece'],
            'account_code' => ['compte', 'account_code', 'compte_interne', 'compte_tresorerie'],
            'client_code' => ['client_code', 'code_client'],
        ];

        foreach ($headers as $original => $normalized) {
            foreach ($dictionary as $target => $aliases) {
                if ($map[$target] !== null) {
                    continue;
                }
                if (in_array($normalized, $aliases, true)) {
                    $map[$target] = $original;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('bsp_extract_client_code_from_label')) {
    function bsp_extract_client_code_from_label(string $label): ?string
    {
        if (preg_match('/\b([0-9]{9})\b/', $label, $m)) {
            return $m[1];
        }
        return null;
    }
}

if (!function_exists('bsp_guess_operation_type')) {
    function bsp_guess_operation_type(string $label, ?float $debit, ?float $credit, ?string $clientCode): string
    {
        $labelNorm = strtolower(bsp_strip_accents($label));

        if (str_contains($labelNorm, 'virement interne') || str_contains($labelNorm, 'vir interne')) {
            return 'VIREMENT_INTERNE';
        }

        if (str_contains($labelNorm, 'regularisation positive')) {
            return 'REGULARISATION_POSITIVE';
        }

        if (str_contains($labelNorm, 'regularisation negative')) {
            return 'REGULARISATION_NEGATIVE';
        }

        if (str_contains($labelNorm, 'frais bancaire')) {
            return 'FRAIS_BANCAIRES';
        }

        if (
            str_contains($labelNorm, 'frais') ||
            str_contains($labelNorm, 'commission') ||
            str_contains($labelNorm, 'avi') ||
            str_contains($labelNorm, 'ats')
        ) {
            return 'FRAIS_DE_SERVICE';
        }

        if ($credit !== null && $credit > 0 && $clientCode !== null) {
            return 'VERSEMENT';
        }

        if ($debit !== null && $debit > 0 && $clientCode !== null) {
            return 'VIREMENT_MENSUEL';
        }

        if ($debit !== null && $debit > 0) {
            return 'VIREMENT_INTERNE';
        }

        return 'VERSEMENT';
    }
}

if (!function_exists('bsp_guess_service_code')) {
    function bsp_guess_service_code(string $label): ?string
    {
        $labelNorm = strtolower(bsp_strip_accents($label));

        if (str_contains($labelNorm, 'avi')) {
            return 'AVI';
        }
        if (str_contains($labelNorm, 'gestion')) {
            return 'GESTION';
        }
        if (str_contains($labelNorm, 'transfert')) {
            return 'TRANSFERT';
        }
        if (str_contains($labelNorm, 'ats')) {
            return 'ATS';
        }
        if (str_contains($labelNorm, 'placement')) {
            return 'PLACEMENT';
        }
        if (str_contains($labelNorm, 'divers')) {
            return 'DIVERS';
        }

        return null;
    }
}

if (!function_exists('bsp_find_client_by_code')) {
    function bsp_find_client_by_code(PDO $pdo, ?string $clientCode): ?array
    {
        if ($clientCode === null || $clientCode === '') {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.client_code,
                c.first_name,
                c.last_name,
                c.full_name
            FROM clients c
            WHERE c.client_code = ?
            LIMIT 1
        ");
        $stmt->execute([$clientCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('bsp_find_treasury_by_id_or_code')) {
    function bsp_find_treasury_by_id_or_code(PDO $pdo, ?int $treasuryId = null, ?string $accountCode = null): ?array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return null;
        }

        if ($treasuryId) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM treasury_accounts
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$treasuryId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        if ($accountCode !== null && $accountCode !== '' && columnExists($pdo, 'treasury_accounts', 'account_code')) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM treasury_accounts
                WHERE account_code = ?
                LIMIT 1
            ");
            $stmt->execute([$accountCode]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('bsp_parse_statement_file')) {
    function bsp_parse_statement_file(PDO $pdo, string $tmpFile, ?int $forcedTreasuryId = null): array
    {
        $firstLine = file($tmpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0] ?? '';
        $delimiter = bsp_detect_delimiter($firstLine);

        $handle = fopen($tmpFile, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier importé.');
        }

        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (!$headerRow) {
            fclose($handle);
            throw new RuntimeException('Le fichier est vide.');
        }

        $headers = [];
        foreach ($headerRow as $header) {
            $headers[$header] = bsp_normalize_header((string)$header);
        }

        $detectedColumns = bsp_detect_columns($headers);
        $rows = [];
        $rowNo = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNo++;
            $assoc = [];

            $i = 0;
            foreach (array_keys($headers) as $originalHeader) {
                $assoc[$originalHeader] = $data[$i] ?? null;
                $i++;
            }

            $operationDate = bsp_normalize_date($assoc[$detectedColumns['operation_date']] ?? null);
            $valueDate = bsp_normalize_date($assoc[$detectedColumns['value_date']] ?? null);
            $label = trim((string)($assoc[$detectedColumns['label']] ?? ''));
            $debit = bsp_normalize_amount($assoc[$detectedColumns['debit']] ?? null);
            $credit = bsp_normalize_amount($assoc[$detectedColumns['credit']] ?? null);
            $balance = bsp_normalize_amount($assoc[$detectedColumns['balance']] ?? null);
            $amount = bsp_normalize_amount($assoc[$detectedColumns['amount']] ?? null);
            $reference = trim((string)($assoc[$detectedColumns['reference']] ?? ''));
            $accountCode = trim((string)($assoc[$detectedColumns['account_code']] ?? ''));
            $clientCode = trim((string)($assoc[$detectedColumns['client_code']] ?? ''));

            if ($clientCode === '') {
                $clientCode = bsp_extract_client_code_from_label($label) ?? '';
            }

            if ($amount !== null && ($debit === null && $credit === null)) {
                if ($amount < 0) {
                    $debit = abs($amount);
                } else {
                    $credit = $amount;
                }
            }

            $matchedClient = bsp_find_client_by_code($pdo, $clientCode !== '' ? $clientCode : null);
            $treasury = bsp_find_treasury_by_id_or_code($pdo, $forcedTreasuryId, $accountCode !== '' ? $accountCode : null);

            $operationTypeCode = bsp_guess_operation_type($label, $debit, $credit, $matchedClient['client_code'] ?? null);
            $serviceCode = bsp_guess_service_code($label);

            $status = 'ok';
            $statusReason = '';

            if (!$operationDate) {
                $status = 'rejected';
                $statusReason = 'Date opération introuvable';
            } elseif (($debit === null || $debit == 0) && ($credit === null || $credit == 0)) {
                $status = 'rejected';
                $statusReason = 'Montant introuvable';
            } elseif ($operationTypeCode !== 'VIREMENT_INTERNE' && !$matchedClient) {
                $status = 'ambiguous';
                $statusReason = 'Client non identifié automatiquement';
            } elseif (!$treasury) {
                $status = 'ambiguous';
                $statusReason = 'Compte interne non identifié';
            }

            $rows[] = [
                'row_no' => $rowNo,
                'raw' => $assoc,
                'operation_date' => $operationDate,
                'value_date' => $valueDate,
                'label' => $label,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'reference' => $reference,
                'client_code' => $matchedClient['client_code'] ?? $clientCode,
                'client_id' => $matchedClient['id'] ?? null,
                'client_name' => $matchedClient ? (($matchedClient['full_name'] ?: trim(($matchedClient['first_name'] ?? '') . ' ' . ($matchedClient['last_name'] ?? '')))) : null,
                'treasury_account_id' => $treasury['id'] ?? null,
                'treasury_account_code' => $treasury['account_code'] ?? null,
                'treasury_account_label' => $treasury['account_label'] ?? null,
                'operation_type_code' => $operationTypeCode,
                'service_code' => $serviceCode,
                'status' => $status,
                'status_reason' => $statusReason,
            ];
        }

        fclose($handle);

        return [
            'delimiter' => $delimiter,
            'headers' => $headers,
            'detected_columns' => $detectedColumns,
            'rows' => $rows,
        ];
    }
}