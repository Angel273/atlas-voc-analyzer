<?php

namespace App\Http\Controllers;

use App\Jobs\CategorizeVerbatimsJob;
use App\Models\Import;
use App\Models\ImportMappingTemplate;
use App\Models\Survey;
use App\Models\SurveyVersion;
use App\Models\VerbatimAnalysis;
use App\Services\Audit\AuditService;
use App\Services\DataImport\ExcelInspectorService;
use App\Services\DataImport\ImportExecutionService;
use App\Services\DataImport\ImportValidatorService;
use App\Services\DataImport\MappingSuggesterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    public function __construct(
        protected ExcelInspectorService $inspector,
        protected MappingSuggesterService $suggester,
        protected ImportValidatorService $validator,
        protected ImportExecutionService $executionService,
        protected AuditService $auditService
    ) {}

    public function index(): Response
    {
        $imports = Import::with('user')->orderBy('id', 'desc')->paginate(15);
        $templates = ImportMappingTemplate::with('fields')->get();

        $totalSurveys = Survey::count();
        $totalVerbatims = Survey::whereNotNull('verbatim')->where('verbatim', '!=', '')->count();
        $completed = VerbatimAnalysis::where('status', 'completed')->count();
        $failed = VerbatimAnalysis::where('status', 'failed')->count();
        $pending = max(0, $totalVerbatims - $completed - $failed);
        $percentage = $totalVerbatims > 0 ? round((($completed + $failed) / $totalVerbatims) * 100, 1) : 100.0;
        $jobsInQueue = DB::table('jobs')->count();
        $runningJobs = DB::table('jobs')->whereNotNull('reserved_at')->count();

        $categorizationStatus = [
            'total_surveys' => $totalSurveys,
            'total_verbatims' => $totalVerbatims,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => $pending,
            'percentage' => $percentage,
            'is_processing' => $jobsInQueue > 0,
            'jobs_in_queue' => $jobsInQueue,
            'running_jobs' => $runningJobs,
        ];

        return Inertia::render('Data/Index', [
            'imports' => $imports,
            'templates' => $templates,
            'categorization_status' => $categorizationStatus,
        ]);
    }

    /**
     * Step 1: Upload Excel file to temporary storage and inspect sheets.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'], // 50MB max
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $tempFilename = 'temp_'.Str::random(24).'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs('temp_imports', $tempFilename);
        $fullPath = Storage::path($path);

        try {
            $sheets = $this->inspector->inspectWorkbook($fullPath);

            return response()->json([
                'success' => true,
                'file_token' => $path,
                'original_filename' => $originalName,
                'sheets' => $sheets,
            ]);
        } catch (\Throwable $e) {
            Storage::delete($path);

            return response()->json([
                'success' => false,
                'error' => 'Failed to inspect workbook: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Step 2 & 3 & 4: Select Sheet, Header Row, and get Preview.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file_token' => ['required', 'string'],
            'sheet_name' => ['required', 'string'],
            'header_row' => ['required', 'integer', 'min:1'],
        ]);

        $fullPath = Storage::path($request->input('file_token'));
        if (! file_exists($fullPath)) {
            return response()->json(['error' => 'Temporary upload expired or not found.'], 404);
        }

        try {
            $preview = $this->inspector->getPreview(
                $fullPath,
                $request->input('sheet_name'),
                (int) $request->input('header_row'),
                20
            );

            // Generate deterministic mapping suggestions
            $suggestions = $this->suggester->suggestMapping($preview['headers']);

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'suggestions' => $suggestions,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Step 5 & 9: Validation Preview before persistence.
     */
    public function validateMapping(Request $request): JsonResponse
    {
        $request->validate([
            'file_token' => ['required', 'string'],
            'sheet_name' => ['required', 'string'],
            'header_row' => ['required', 'integer'],
            'column_mapping' => ['required', 'array'],
            'transformations' => ['nullable', 'array'],
        ]);

        $fullPath = Storage::path($request->input('file_token'));
        if (! file_exists($fullPath)) {
            return response()->json(['error' => 'Temporary upload not found.'], 404);
        }

        $sheetName = $request->input('sheet_name');
        $headerRow = (int) $request->input('header_row');
        $mapping = $request->input('column_mapping');
        $transformations = $request->input('transformations', []);

        try {
            $reader = IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly($sheetName);
            $spreadsheet = $reader->load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            $highestCol = $worksheet->getHighestColumn();

            // Validate a sample of up to 100 rows for instant preflight feedback
            $sampleMax = min($highestRow, $headerRow + 100);
            $sampleData = $worksheet->rangeToArray('A'.($headerRow + 1).":{$highestCol}{$sampleMax}", null, true, true, true);

            $readyCount = 0;
            $warningCount = 0;
            $errorCount = 0;
            $sampleErrors = [];

            foreach ($sampleData as $rNum => $row) {
                if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                $val = $this->validator->validateRow($row, $mapping, $transformations);
                if ($val['valid']) {
                    $readyCount++;
                    if (! empty($val['warnings'])) {
                        $warningCount += count($val['warnings']);
                    }
                } else {
                    $errorCount++;
                    if (count($sampleErrors) < 10) {
                        $sampleErrors[] = [
                            'row' => $rNum,
                            'errors' => $val['errors'],
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'sample_checked' => count($sampleData),
                'total_rows_estimate' => max(0, $highestRow - $headerRow),
                'ready_count' => $readyCount,
                'warning_count' => $warningCount,
                'error_count' => $errorCount,
                'sample_errors' => $sampleErrors,
                'can_import' => $errorCount === 0 && $readyCount > 0,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Step 10: Import Execution with Idempotency.
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'file_token' => ['required', 'string'],
            'original_filename' => ['required', 'string'],
            'sheet_name' => ['required', 'string'],
            'header_row' => ['required', 'integer'],
            'column_mapping' => ['required', 'array'],
            'transformations' => ['nullable', 'array'],
            'template_id' => ['nullable', 'integer'],
        ]);

        $fileToken = $request->input('file_token');
        $fullPath = Storage::path($fileToken);

        if (! file_exists($fullPath)) {
            return response()->json(['error' => 'File expired or missing.'], 404);
        }

        try {
            $import = $this->executionService->executeImport(
                filePath: $fullPath,
                originalFilename: $request->input('original_filename'),
                sheetName: $request->input('sheet_name'),
                headerRow: (int) $request->input('header_row'),
                columnMapping: $request->input('column_mapping'),
                transformations: $request->input('transformations', []),
                templateId: $request->input('template_id'),
                user: Auth::user()
            );

            // Clean up temporary file
            Storage::delete($fileToken);

            return response()->json([
                'success' => true,
                'import' => $import,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Mapping Template.
     */
    public function saveTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array'],
        ]);

        $template = ImportMappingTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by' => Auth::id(),
        ]);

        foreach ($validated['fields'] as $field) {
            $template->fields()->create([
                'internal_field' => $field['internal_field'],
                'source_column' => $field['source_column'],
                'transformations' => $field['transformations'] ?? null,
            ]);
        }

        return response()->json(['success' => true, 'template' => $template->load('fields')]);
    }

    /**
     * Delete an import and its associated surveys, versions, and verbatims.
     */
    public function destroy(Import $import): JsonResponse
    {
        $importId = $import->id;
        $filename = $import->original_filename;
        $surveyIds = Survey::where('import_id', $importId)->pluck('survey_id')->toArray();

        // 1. Delete verbatim analyses for these surveys
        VerbatimAnalysis::whereIn('survey_id', $surveyIds)->delete();

        // 2. Delete survey versions
        SurveyVersion::where('import_id', $importId)->delete();

        // 3. Delete surveys
        $deletedCount = Survey::where('import_id', $importId)->delete();

        // 4. Delete import record
        $import->delete();

        // 5. Audit log
        $this->auditService->record(
            eventType: 'IMPORT_DELETED',
            payload: [
                'import_id' => $importId,
                'filename' => $filename,
                'deleted_surveys' => $deletedCount,
            ],
            userId: Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => "Importación #{$importId} ({$filename}) y sus {$deletedCount} encuestas asociadas han sido eliminadas.",
        ]);
    }

    /**
     * Clear all survey datasets and imports completely.
     */
    public function clearAll(): JsonResponse
    {
        VerbatimAnalysis::query()->delete();
        SurveyVersion::query()->delete();
        $deletedSurveys = Survey::query()->delete();
        $deletedImports = Import::query()->delete();

        $this->auditService->record(
            eventType: 'DATASET_CLEARED',
            payload: [
                'deleted_surveys' => $deletedSurveys,
                'deleted_imports' => $deletedImports,
            ],
            userId: Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => "Se eliminaron todas las encuestas ({$deletedSurveys}) e importaciones ({$deletedImports}) exitosamente.",
        ]);
    }

    /**
     * Get live status of AI verbatim categorization.
     */
    public function categorizationStatus(): JsonResponse
    {
        $totalSurveys = Survey::count();
        $totalVerbatims = Survey::whereNotNull('verbatim')->where('verbatim', '!=', '')->count();
        $completed = VerbatimAnalysis::where('status', 'completed')->count();
        $failed = VerbatimAnalysis::where('status', 'failed')->count();
        $pending = max(0, $totalVerbatims - $completed - $failed);

        $progressPercentage = $totalVerbatims > 0
            ? round((($completed + $failed) / $totalVerbatims) * 100, 1)
            : 100.0;

        $jobsInQueue = DB::table('jobs')->count();
        $runningJobs = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $isProcessing = $jobsInQueue > 0;

        return response()->json([
            'total_surveys' => $totalSurveys,
            'total_verbatims' => $totalVerbatims,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => $pending,
            'percentage' => $progressPercentage,
            'is_processing' => $isProcessing,
            'jobs_in_queue' => $jobsInQueue,
            'running_jobs' => $runningJobs,
        ]);
    }

    /**
     * Trigger or resume categorization for any uncategorized verbatims.
     */
    public function triggerCategorization(): JsonResponse
    {
        $categorizedIds = VerbatimAnalysis::where('status', 'completed')->pluck('survey_id')->toArray();
        $uncategorized = Survey::whereNotNull('verbatim')
            ->where('verbatim', '!=', '')
            ->whereNotIn('survey_id', $categorizedIds)
            ->pluck('survey_id')
            ->toArray();

        if (! empty($uncategorized)) {
            foreach (array_chunk($uncategorized, 50) as $chunk) {
                dispatch(new CategorizeVerbatimsJob($chunk));
            }
        }

        return response()->json([
            'success' => true,
            'dispatched_count' => count($uncategorized),
        ]);
    }
}
