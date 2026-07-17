<?php

namespace App\Services;

use App\Models\FareGuide;
use App\Models\FareMatrix;
use App\Models\FareUpload;
use App\Models\ImportLog;
use Exception;

class FareDataProcessor
{
    private int   $userId;
    private ?int  $uploadId = null;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function processUpload(string $filePath, string $originalFileName, int $fileSize, string $fileType, ?string $allowedVehicleType = null, array $formMetadata = []): array
    {
        try {
            $this->uploadId = $this->createUploadRecord($originalFileName, $filePath, $fileSize, $fileType);
            $this->log('info', 'Upload record created', "Upload ID: {$this->uploadId}");

            $this->updateUploadStatus('processing');

            $parser          = new FareCSVParser($this->uploadId);
            $data            = $parser->parseCSV($filePath, $this->uploadId, $originalFileName, $formMetadata);

            $validator       = new FareDataValidator($this->uploadId);
            $validationResult = $validator->validate($data);

            $this->updateUploadStats(count($data['fares']), $validationResult['valid_count']);

            if (!$validationResult['is_valid']) {
                $this->log('warning', 'Validation errors found', 'Count: ' . count($validationResult['errors']));
            }

            if ($allowedVehicleType !== null && !empty($data['vehicle_type']) && $data['vehicle_type'] !== $allowedVehicleType) {
                $this->updateUploadStatus('failed', "Vehicle type '{$data['vehicle_type']}' is not allowed. Only '{$allowedVehicleType}' is permitted for your role.");
                $this->log('error', 'Vehicle type rejected', "Expected '{$allowedVehicleType}', got '{$data['vehicle_type']}'");
                return [
                    'success'       => false,
                    'upload_id'     => $this->uploadId,
                    'fare_guide_id' => null,
                    'total_records' => count($data['fares']),
                    'valid_records' => $validationResult['valid_count'],
                    'errors'        => $validationResult['errors'],
                    'error'         => "Only '{$allowedVehicleType}' vehicle type is allowed. The uploaded CSV contains '{$data['vehicle_type']}'.",
                ];
            }

            $fareGuideId = null;
            if ($validationResult['valid_count'] > 0) {
                \Illuminate\Support\Facades\DB::beginTransaction();
                try {
                    $fareGuideId = $this->saveFareGuide($data);
                    $this->saveFareMatrices($fareGuideId, $data['fares']);
                    \Illuminate\Support\Facades\DB::commit();
                    $this->log('info', 'Data saved', "Fare Guide ID: {$fareGuideId}");
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\DB::rollBack();
                    throw $e;
                }
            }

            $this->updateUploadStatus('completed');
            $this->log('info', 'Processing completed', '');

            return [
                'success'       => true,
                'upload_id'     => $this->uploadId,
                'fare_guide_id' => $fareGuideId,
                'total_records' => count($data['fares']),
                'valid_records' => $validationResult['valid_count'],
                'errors'        => $validationResult['errors'],
            ];
        } catch (Exception $e) {
            $this->updateUploadStatus('failed', $e->getMessage());
            $this->log('error', 'Processing failed', $e->getMessage());
            throw $e;
        }
    }

    private function createUploadRecord(string $fileName, string $filePath, int $fileSize, string $fileType): int
    {
        $upload = FareUpload::create([
            'file_name'   => $fileName,
            'file_path'   => $filePath,
            'file_size'   => $fileSize,
            'mime_type'   => $fileType,
            'file_type'   => $fileType,
            'uploaded_by' => $this->userId,
            'status'      => 'pending',
        ]);
        return $upload->id;
    }

    private function updateUploadStatus(string $status, ?string $errorMessage = null): void
    {
        $update = ['status' => $status];
        if ($status === 'completed') $update['processed_at'] = now();
        if ($errorMessage) $update['error_message'] = $errorMessage;

        FareUpload::where('id', $this->uploadId)->update($update);
    }

    private function updateUploadStats(int $total, int $valid): void
    {
        FareUpload::where('id', $this->uploadId)->update([
            'total_records' => $total,
            'valid_records' => $valid,
        ]);
    }

    private function saveFareGuide(array $data): int
    {
        // Archive existing active guide with same vehicle_type + region
        FareGuide::where('vehicle_type', $data['vehicle_type'])
            ->where('region', $data['region'])
            ->where('status', 'active')
            ->update(['status' => 'archived', 'updated_at' => now()]);

        $guide = FareGuide::create([
            'title'          => $data['title'],
            'vehicle_type'   => $data['vehicle_type'],
            'region'         => $data['region'],
            'effective_date' => $data['effective_date'],
            'status'         => 'active',
            'created_by'     => $this->userId,
        ]);

        return $guide->id;
    }

    private function saveFareMatrices(int $fareGuideId, array $fares): void
    {
        foreach ($fares as $fare) {
            try {
                FareMatrix::updateOrCreate(
                    ['fare_guide_id' => $fareGuideId, 'distance_km' => $fare['distance']],
                    ['regular_fare' => $fare['regular'], 'discounted_fare' => $fare['discounted'] ?? round($fare['regular'] * 0.8, 2)]
                );
            } catch (Exception) {
                $this->log('warning', 'Skipping invalid fare record', 'Row: ' . ($fare['row_number'] ?? 'unknown'));
            }
        }
    }

    private function log(string $severity, string $action, string $details): void
    {
        if (!$this->uploadId) return;
        try {
            ImportLog::create([
                'fare_upload_id' => $this->uploadId,
                'action'         => $action,
                'message'        => "[{$severity}] {$action}",
                'details'        => $details,
            ]);
        } catch (Exception) {}
    }
}
