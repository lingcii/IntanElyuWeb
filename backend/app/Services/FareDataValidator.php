<?php

namespace App\Services;

use App\Models\ValidationError;
use Exception;

class FareDataValidator
{
    private int $uploadId;
    private array $errors    = [];
    private int   $validCount = 0;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function validate(array $data): array
    {
        $this->errors     = [];
        $this->validCount = 0;

        $this->validateMetadata($data);
        $this->validateFares($data['fares'] ?? []);

        return [
            'is_valid'    => empty($this->errors),
            'errors'      => $this->errors,
            'valid_count' => $this->validCount,
            'total_count' => count($data['fares'] ?? []),
        ];
    }

    private function validateMetadata(array $data): void
    {
        if (empty($data['title'])) {
            $this->addError(null, 'title', 'missing', 'Title is required');
        }

        $validTypes = ['PUB_Aircon', 'PUB_Ordinary', 'PUJ_Aircon', 'PUJ_Ordinary', 'Tricycle', 'Van'];
        if (empty($data['vehicle_type'])) {
            $this->addError(null, 'vehicle_type', 'missing', 'Vehicle type is required');
        } elseif (!in_array($data['vehicle_type'], $validTypes)) {
            $this->addError(null, 'vehicle_type', 'invalid', 'Invalid vehicle type', $data['vehicle_type']);
        }

        if (empty($data['region'])) {
            $this->addError(null, 'region', 'missing', 'Region is required');
        }

        if (empty($data['effective_date'])) {
            $this->addError(null, 'effective_date', 'missing', 'Effective date is required');
        } elseif (!strtotime($data['effective_date'])) {
            $this->addError(null, 'effective_date', 'invalid', 'Invalid date format', $data['effective_date']);
        }
    }

    private function validateFares(array $fares): void
    {
        if (empty($fares)) {
            $this->addError(null, 'fares', 'missing', 'No fare records found');
            return;
        }

        foreach ($fares as $fare) {
            $row     = $fare['row_number'] ?? null;
            $isValid = true;

            // distance
            if (!isset($fare['distance']) || $fare['distance'] === null) {
                $this->addError($row, 'distance', 'missing', 'Distance is required');
                $isValid = false;
            } elseif (!is_numeric($fare['distance'])) {
                $this->addError($row, 'distance', 'invalid', 'Distance must be a number', $fare['distance']);
                $isValid = false;
            } elseif ($fare['distance'] <= 0) {
                $this->addError($row, 'distance', 'invalid', 'Distance must be greater than 0', $fare['distance']);
                $isValid = false;
            } elseif ($fare['distance'] > 999.99) {
                $this->addError($row, 'distance', 'invalid', 'Distance exceeds maximum limit', $fare['distance']);
                $isValid = false;
            }

            // regular fare
            if (!isset($fare['regular']) || $fare['regular'] === null) {
                $this->addError($row, 'regular_fare', 'missing', 'Regular fare is required');
                $isValid = false;
            } elseif (!is_numeric($fare['regular'])) {
                $this->addError($row, 'regular_fare', 'invalid', 'Regular fare must be a number', $fare['regular']);
                $isValid = false;
            } elseif ($fare['regular'] < 0) {
                $this->addError($row, 'regular_fare', 'invalid', 'Regular fare cannot be negative', $fare['regular']);
                $isValid = false;
            } elseif ($fare['regular'] > 999999.99) {
                $this->addError($row, 'regular_fare', 'invalid', 'Regular fare exceeds maximum limit', $fare['regular']);
                $isValid = false;
            }

            // discounted fare (optional)
            if (isset($fare['discounted']) && $fare['discounted'] !== null) {
                if (!is_numeric($fare['discounted'])) {
                    $this->addError($row, 'discounted_fare', 'invalid', 'Discounted fare must be a number', $fare['discounted']);
                    $isValid = false;
                } elseif ($fare['discounted'] < 0) {
                    $this->addError($row, 'discounted_fare', 'invalid', 'Discounted fare cannot be negative', $fare['discounted']);
                    $isValid = false;
                }
            }

            if ($isValid) {
                $this->validCount++;
            }
        }
    }

    private function addError(?int $rowNumber, string $fieldName, string $errorType, string $errorMessage, mixed $invalidValue = null): void
    {
        $composedError = "{$errorType}: {$errorMessage}";
        if ($invalidValue !== null) {
            $composedError .= ' (value: ' . (is_scalar($invalidValue) ? $invalidValue : json_encode($invalidValue)) . ')';
        }

        $this->errors[] = [
            'row_number'    => $rowNumber,
            'field_name'    => $fieldName,
            'error_type'    => $errorType,
            'error_message' => $errorMessage,
            'invalid_value' => $invalidValue,
        ];

        try {
            ValidationError::create([
                'fare_upload_id' => $this->uploadId,
                'row_number'     => $rowNumber,
                'field'          => $fieldName,
                'error'          => $composedError,
            ]);
        } catch (Exception) {}
    }

    public function getErrors(): array  { return $this->errors; }
    public function getValidCount(): int { return $this->validCount; }
}
