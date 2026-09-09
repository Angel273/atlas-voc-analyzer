<?php

namespace App\Services\DataImport;

use App\Jobs\CategorizeVerbatimsJob;
use App\Models\Import;
use App\Models\Survey;
use App\Models\SurveyVersion;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportExecutionService
{
    public function __construct(
        protected ImportValidatorService $validator,
        protected AuditService $auditService
    ) {}

    /**
     * Execute full import of an Excel file.
     */
    public function executeImport(
        string $filePath,
        string $originalFilename,
        string $sheetName,
        int $headerRow,
        array $columnMapping,
        array $transformations = [],
        ?int $templateId = null,
        ?User $user = null
    ): Import {
        $fileHash = hash_file('sha256', $filePath);

        $import = Import::create([
            'original_filename' => $originalFilename,
            'file_hash' => $fileHash,
            'sheet_name' => $sheetName,
            'header_row' => $headerRow,
            'uploaded_by' => $user?->id,
            'mapping_template_id' => $templateId,
            'used_mapping' => [
                'columns' => $columnMapping,
                'transformations' => $transformations,
            ],
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $this->auditService->record(
            eventType: 'IMPORT_STARTED',
            payload: [
                'import_id' => $import->id,
                'filename' => $originalFilename,
                'file_hash' => $fileHash,
                'sheet_name' => $sheetName,
            ],
            auditableType: Import::class,
            auditableId: (string) $import->id,
            userId: $user?->id
        );

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly($sheetName);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            $highestCol = $worksheet->getHighestColumn();

            // Read header row
            $headerRowData = $worksheet->rangeToArray("A{$headerRow}:{$highestCol}{$headerRow}", null, true, true, true)[$headerRow] ?? [];

            $rowCount = 0;
            $acceptedCount = 0;
            $rejectedCount = 0;
            $duplicateCount = 0;
            $errors = [];

            $surveysToCategorize = [];

            // Process in chunks of 200 rows to optimize memory and performance
            $chunkSize = 200;
            $startRow = $headerRow + 1;

            for ($curr = $startRow; $curr <= $highestRow; $curr += $chunkSize) {
                $end = min($highestRow, $curr + $chunkSize - 1);
                $chunkData = $worksheet->rangeToArray("A{$curr}:{$highestCol}{$end}", null, true, true, true);

                DB::transaction(function () use (
                    $chunkData,
                    $columnMapping,
                    $transformations,
                    $import,
                    $user,
                    &$rowCount,
                    &$acceptedCount,
                    &$rejectedCount,
                    &$duplicateCount,
                    &$errors,
                    &$surveysToCategorize
                ) {
                    foreach ($chunkData as $rNum => $row) {
                        // Check if row is entirely empty
                        if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                            continue;
                        }

                        $rowCount++;
                        $validation = $this->validator->validateRow($row, $columnMapping, $transformations);

                        if (! $validation['valid']) {
                            $rejectedCount++;
                            $errors[] = [
                                'row' => $rNum,
                                'errors' => $validation['errors'],
                            ];

                            continue;
                        }

                        $data = $validation['normalized'];
                        $hash = Survey::computeHash($data);
                        $surveyId = $data['survey_id'];

                        // Check Idempotency
                        $existing = Survey::where('survey_id', $surveyId)->first();

                        if (! $existing) {
                            // New survey: Insert
                            $data['record_hash'] = $hash;
                            $data['import_id'] = $import->id;
                            Survey::create($data);
                            $acceptedCount++;
                            if (! empty($data['verbatim'])) {
                                $surveysToCategorize[] = $surveyId;
                            }
                        } elseif ($existing->record_hash === $hash) {
                            // Same survey + same hash: Duplicate / unchanged
                            $duplicateCount++;
                        } else {
                            // Same survey + different hash: Versioned update
                            SurveyVersion::create([
                                'survey_id' => $surveyId,
                                'previous_hash' => $existing->record_hash,
                                'new_hash' => $hash,
                                'import_id' => $import->id,
                                'changed_by' => $user?->id,
                                'diff' => array_diff_assoc($data, $existing->toArray()),
                            ]);

                            $data['record_hash'] = $hash;
                            $data['import_id'] = $import->id;
                            $existing->update($data);
                            $acceptedCount++;

                            if (! empty($data['verbatim'])) {
                                $surveysToCategorize[] = $surveyId;
                            }
                        }
                    }
                });
            }

            $import->update([
                'row_count' => $rowCount,
                'accepted_rows' => $acceptedCount,
                'rejected_rows' => $rejectedCount,
                'duplicate_rows' => $duplicateCount,
                'errors' => array_slice($errors, 0, 100), // store up to 100 error details
                'status' => $rejectedCount > 0 && $acceptedCount === 0 ? 'failed' : 'completed',
                'completed_at' => now(),
            ]);

            $this->auditService->record(
                eventType: 'IMPORT_COMPLETED',
                payload: [
                    'import_id' => $import->id,
                    'total_rows' => $rowCount,
                    'accepted' => $acceptedCount,
                    'rejected' => $rejectedCount,
                    'duplicates' => $duplicateCount,
                    'status' => $import->status,
                ],
                auditableType: Import::class,
                auditableId: (string) $import->id,
                userId: $user?->id
            );

            // Queue AI categorization job in manageable batches of 50 if verbatims exist
            if (! empty($surveysToCategorize)) {
                foreach (array_chunk($surveysToCategorize, 50) as $chunk) {
                    dispatch(new CategorizeVerbatimsJob($chunk));
                }
            }

            return $import;
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'errors' => [['system_error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);

            $this->auditService->record(
                eventType: 'IMPORT_FAILED',
                payload: [
                    'import_id' => $import->id,
                    'error' => $e->getMessage(),
                ],
                auditableType: Import::class,
                auditableId: (string) $import->id,
                userId: $user?->id
            );

            throw $e;
        }
    }
}
