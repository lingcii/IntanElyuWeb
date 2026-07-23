<?php

namespace App\Services;

use App\Models\ImportLog;
use Exception;

class FareCSVParser
{
    private ?int $uploadId;

    public function __construct(?int $uploadId = null)
    {
        $this->uploadId = $uploadId;
    }

    public function parseCSV(string $filePath, int $uploadId, ?string $originalFileName = null, array $formMetadata = []): array
    {
        $this->uploadId = $uploadId;
        $this->log('info', 'Starting CSV parsing', "File: {$filePath}, Original: {$originalFileName}");

        if (!file_exists($filePath)) {
            throw new Exception("CSV file not found: {$filePath}");
        }

        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new Exception("Failed to open CSV file: {$filePath}");
        }

        // Read all rows
        $rawRows = [];
        while (($row = fgetcsv($file)) !== false) {
            $rawRows[] = $row;
        }
        fclose($file);

        $this->log('info', 'Raw rows read', 'Count: ' . count($rawRows));

        // Filter empty rows first (rows where all columns are empty or only spaces)
        $cleanRows = [];
        foreach ($rawRows as $rowIndex => $row) {
            $rowText = trim(implode('', $row));
            if ($rowText !== '') {
                $cleanRows[] = array_map('trim', $row);
            }
        }

        if (empty($cleanRows)) {
            throw new Exception("The uploaded CSV file is empty.");
        }

        $metadata = [
            'title'          => $formMetadata['title'] ?? null,
            'vehicle_type'   => $formMetadata['vehicle_type'] ?? null,
            'region'         => $formMetadata['region'] ?? null,
            'effective_date' => $formMetadata['effective_date'] ?? null,
            'fares'          => []
        ];

        // Scan all rows to extract metadata anywhere in the file (e.g. top or bottom)
        foreach ($cleanRows as $row) {
            $lineStr = implode(' ', $row);
            
            if (empty($metadata['title']) && preg_match('/(PUB|PUJ|Tricycle|Van|Taxi).*FARE/i', $lineStr)) {
                $metadata['title'] = trim($lineStr);
            }
            if (empty($metadata['vehicle_type'])) {
                if (stripos($lineStr, 'MPUJ') !== false) {
                    $metadata['vehicle_type'] = 'MPUJ';
                } elseif (stripos($lineStr, 'TPUJ') !== false) {
                    $metadata['vehicle_type'] = 'TPUJ';
                } elseif (stripos($lineStr, 'TAXI') !== false || stripos($lineStr, 'Taxi') !== false) {
                    $metadata['vehicle_type'] = 'TAXI';
                } elseif (stripos($lineStr, 'UVE') !== false || stripos($lineStr, 'UV Express') !== false) {
                    $metadata['vehicle_type'] = 'UVE';
                } elseif (stripos($lineStr, 'PUB') !== false && (stripos($lineStr, 'Aircon') !== false || stripos($lineStr, 'Air-con') !== false)) {
                    $metadata['vehicle_type'] = 'PUB_Aircon';
                } elseif (stripos($lineStr, 'PUB') !== false && (stripos($lineStr, 'Regular') !== false || stripos($lineStr, 'Ordinary') !== false)) {
                    $metadata['vehicle_type'] = 'PUB_Regular';
                } elseif (stripos($lineStr, 'PUB') !== false) {
                    $metadata['vehicle_type'] = 'PUB_Regular';
                } elseif (stripos($lineStr, 'PUJ') !== false && (stripos($lineStr, 'Aircon') !== false || stripos($lineStr, 'Modern') !== false)) {
                    $metadata['vehicle_type'] = 'MPUJ';
                } elseif (stripos($lineStr, 'PUJ') !== false) {
                    $metadata['vehicle_type'] = 'TPUJ';
                } elseif (stripos($lineStr, 'Tricycle') !== false) {
                    $metadata['vehicle_type'] = 'Tricycle';
                } elseif (stripos($lineStr, 'Van') !== false) {
                    $metadata['vehicle_type'] = 'Van';
                }
            }
            if (empty($metadata['region']) && preg_match('/(Metro Manila|Provincial|Region|La Union)/i', $lineStr)) {
                $metadata['region'] = trim($lineStr);
            }
            if (empty($metadata['effective_date']) && preg_match('/(?:EFFECTIVE|EFFECTIVITY)\s*(?:DATE)?[:\s]+([a-zA-Z0-9\s,\/\.-]+)/i', $lineStr, $m)) {
                try {
                    $ts = strtotime(trim($m[1]));
                    if ($ts !== false) {
                        $metadata['effective_date'] = date('Y-m-d', $ts);
                    }
                } catch (Exception $e) {}
            }
        }

        // Find the header row index
        $headerRowIndex = 0;
        $isTabularHeader = $this->isHeaderRow($cleanRows[0]);
        if (!$isTabularHeader) {
            for ($i = 0; $i < min(10, count($cleanRows)); $i++) {
                if ($this->isHeaderRow($cleanRows[$i])) {
                    $headerRowIndex = $i;
                    break;
                }
            }
        }

        // Set fallbacks for metadata if still empty
        $name = strtolower($originalFileName ?? basename($filePath));
        if (empty($metadata['vehicle_type'])) {
            if (str_contains($name, 'mpuj')) {
                $metadata['vehicle_type'] = 'MPUJ';
            } elseif (str_contains($name, 'tpuj')) {
                $metadata['vehicle_type'] = 'TPUJ';
            } elseif (str_contains($name, 'taxi')) {
                $metadata['vehicle_type'] = 'TAXI';
            } elseif (str_contains($name, 'uve') || str_contains($name, 'uv_express')) {
                $metadata['vehicle_type'] = 'UVE';
            } elseif (str_contains($name, 'pub_aircon') || (str_contains($name, 'pub') && str_contains($name, 'aircon'))) {
                $metadata['vehicle_type'] = 'PUB_Aircon';
            } elseif (str_contains($name, 'pub_regular') || str_contains($name, 'pub_ordinary') || str_contains($name, 'pub')) {
                $metadata['vehicle_type'] = 'PUB_Regular';
            } elseif (str_contains($name, 'puj_aircon')) {
                $metadata['vehicle_type'] = 'MPUJ';
            } elseif (str_contains($name, 'puj')) {
                $metadata['vehicle_type'] = 'TPUJ';
            } elseif (str_contains($name, 'tricycle')) {
                $metadata['vehicle_type'] = 'Tricycle';
            } elseif (str_contains($name, 'van')) {
                $metadata['vehicle_type'] = 'Van';
            } else {
                $metadata['vehicle_type'] = 'MPUJ';
            }
        }
        if (empty($metadata['title'])) {
            $labels = [
                'MPUJ'        => 'MPUJ (Modern PUJ)',
                'TPUJ'        => 'TPUJ (Traditional PUJ)',
                'PUB_Aircon'  => 'PUB Aircon',
                'PUB_Regular' => 'PUB Regular',
                'PUB_Ordinary' => 'PUB Ordinary',
                'TAXI'        => 'TAXI',
                'UVE'         => 'UVE (UV Express)',
                'Tricycle'    => 'Tricycle',
                'Van'         => 'Van',
            ];
            $type = $metadata['vehicle_type'] ?? 'MPUJ';
            $vehicleLabel = $labels[$type] ?? str_replace('_', ' ', $type);
            $metadata['title'] = $vehicleLabel . " General Fare Guide";
        }
        if (empty($metadata['region'])) {
            $metadata['region'] = 'La Union';
        }
        if (empty($metadata['effective_date'])) {
            $metadata['effective_date'] = now()->toDateString();
        }

        // Map column indices of the header row
        $headers = $cleanRows[$headerRowIndex];
        $distanceIdx = -1;
        $regularIdx = -1;
        $discountedIdx = -1;

        foreach ($headers as $idx => $header) {
            $h = strtolower(trim($header));
            if (str_contains($h, 'distance') || str_contains($h, 'km')) {
                $distanceIdx = $idx;
            } elseif (str_contains($h, 'regular') || str_contains($h, 'fare')) {
                if (str_contains($h, 'discount') || str_contains($h, 'student') || str_contains($h, 'senior') || str_contains($h, 'pwd') || str_contains($h, 'disabled') || str_contains($h, 'elderly')) {
                    $discountedIdx = $idx;
                } else {
                    $regularIdx = $idx;
                }
            } elseif (str_contains($h, 'student') || str_contains($h, 'senior') || str_contains($h, 'pwd') || str_contains($h, 'discount') || str_contains($h, 'disabled') || str_contains($h, 'elderly')) {
                $discountedIdx = $idx;
            }
        }

        if ($distanceIdx === -1) $distanceIdx = 0;
        if ($regularIdx === -1) $regularIdx = 1;
        if ($discountedIdx === -1) $discountedIdx = count($headers) > 2 ? 2 : -1;

        $this->log('info', 'Header indices mapped', "Distance: {$distanceIdx}, Regular: {$regularIdx}, Discounted: {$discountedIdx}");

        // Parse rows starting from row after header
        $rowNum = 0;
        $uniqueDistances = [];
        $duplicatesCount = 0;

        for ($i = $headerRowIndex + 1; $i < count($cleanRows); $i++) {
            $row = $cleanRows[$i];
            $distanceVal = isset($row[$distanceIdx]) ? $this->parseNumber($row[$distanceIdx]) : null;
            $regularVal = isset($row[$regularIdx]) ? $this->parseNumber($row[$regularIdx]) : null;
            $discountedVal = ($discountedIdx !== -1 && isset($row[$discountedIdx])) ? $this->parseNumber($row[$discountedIdx]) : null;

            $fileRowNumber = $i + 1;

            if ($distanceVal !== null) {
                $distanceStr = strval($distanceVal);
                if (in_array($distanceStr, $uniqueDistances)) {
                    $duplicatesCount++;
                    $this->log('warning', "Duplicate distance row skipped", "Row {$fileRowNumber}: Distance {$distanceVal} km already exists.");
                    continue;
                }
                $uniqueDistances[] = $distanceStr;

                $metadata['fares'][] = [
                    'row_number' => $fileRowNumber,
                    'distance'   => $distanceVal,
                    'regular'    => $regularVal,
                    'discounted' => $discountedVal !== null ? $discountedVal : round($regularVal * 0.8, 2),
                ];
            }
        }

        $metadata['duplicates_count'] = $duplicatesCount;
        return $metadata;
    }

    private function isHeaderRow(array $row): bool
    {
        $str = strtolower(implode(' ', $row));
        $hasDistance = str_contains($str, 'distance') || str_contains($str, 'km');
        $hasFare = str_contains($str, 'regular') || str_contains($str, 'fare') || str_contains($str, 'student') || str_contains($str, 'senior') || str_contains($str, 'pwd') || str_contains($str, 'elderly') || str_contains($str, 'disabled');
        return $hasDistance && $hasFare;
    }

    private function parseNumber(string $str): ?float
    {
        $trimmed = trim($str);
        if ($trimmed === '') return null;

        // If the cell contains more than 5 alphabetical characters, treat it as a text label/metadata
        $letterCount = strlen(preg_replace('/[^a-zA-Z]/', '', $trimmed));
        if ($letterCount > 5) {
            return null;
        }

        $clean = preg_replace('/[^\d\.]/', '', $trimmed);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function log(string $severity, string $action, string $details): void
    {
        if (!$this->uploadId) return;
        try {
            ImportLog::create([
                'fare_upload_id' => $this->uploadId,
                'action'         => $action,
                'details'        => $details,
                'severity'       => $severity,
                'message'        => "[{$severity}] {$action}"
            ]);
        } catch (Exception $e) {}
    }
}
