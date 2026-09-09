<?php

namespace App\Services\DriverAnalysis;

use App\Models\DriverAnalysis;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class DriverAnalysisService
{
    public function __construct(
        protected NpsDriverEngine $npsEngine,
        protected LogisticDriverEngine $logisticEngine,
        protected AuditService $auditService
    ) {}

    /**
     * Execute Driver Analysis for a given VOC metric with optional filters.
     *
     * @param  string  $metric  'nps', 'csat', or 'professionalism'
     * @param  array{supervisor?: ?string, date_from?: ?string, date_to?: ?string, wave?: ?string}  $filters
     * @param  array{category?: ?string, supervisor?: ?string, wave?: ?string}  $referenceCategories
     */
    public function execute(string $metric = 'nps', array $filters = [], array $referenceCategories = [], ?User $user = null): array
    {
        // 1. Fetch survey-level records with categories
        $query = DB::table('surveys')
            ->leftJoin('verbatim_analyses', 'surveys.survey_id', '=', 'verbatim_analyses.survey_id')
            ->leftJoin('categories', 'verbatim_analyses.category_id', '=', 'categories.id')
            ->select([
                'surveys.id',
                'surveys.survey_id',
                'surveys.nps_score',
                'surveys.csat_score',
                'surveys.professionalism_score',
                'surveys.tenure_days',
                'surveys.wave',
                'surveys.supervisor',
                'surveys.survey_date',
                DB::raw("COALESCE(categories.name, 'Uncategorized') as category"),
            ]);

        // Apply filters
        if (! empty($filters['supervisor'])) {
            $query->where('surveys.supervisor', $filters['supervisor']);
        }
        if (! empty($filters['wave'])) {
            $query->where('surveys.wave', $filters['wave']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('surveys.survey_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('surveys.survey_date', '<=', $filters['date_to']);
        }

        $records = $query->get()->map(function ($r) use ($metric) {
            return [
                'nps_score' => (float) $r->nps_score,
                'target_value' => (float) match ($metric) {
                    'csat' => $r->csat_score,
                    'professionalism' => $r->professionalism_score,
                    default => $r->nps_score,
                },
                'category' => (string) $r->category,
                'tenure_days' => $r->tenure_days !== null ? (int) $r->tenure_days : 30,
                'wave' => (string) ($r->wave ?: 'General'),
                'supervisor' => (string) ($r->supervisor ?: 'General'),
                'survey_date' => (string) $r->survey_date,
            ];
        })->toArray();

        // 2. Dispatch to mathematical engine
        if ($metric === 'nps') {
            $result = $this->npsEngine->analyze($records, $referenceCategories);
        } else {
            $result = $this->logisticEngine->analyze($records, $metric, $referenceCategories);
        }

        // 3. Persist record in database
        $analysisRecord = DriverAnalysis::create([
            'metric' => $metric,
            'filters' => $filters,
            'sample_size' => $result['sample_size'] ?? count($records),
            'controlled_variables' => $result['controlled_variables'] ?? ['category', 'tenure_days', 'wave', 'supervisor', 'time'],
            'reference_categories' => $result['reference_categories'] ?? [],
            'drivers_data' => $result['drivers'] ?? [],
            'diagnostics' => $result['diagnostics'] ?? [],
            'generated_by' => $user?->id,
        ]);

        $result['id'] = $analysisRecord->id;
        $result['created_at'] = $analysisRecord->created_at->toIso8601String();

        // 4. Audit persistence
        $this->auditService->record(
            eventType: 'driver_analysis.executed',
            payload: [
                'driver_analysis_id' => $analysisRecord->id,
                'metric' => $metric,
                'sample_size' => $analysisRecord->sample_size,
                'drivers_count' => count($result['drivers'] ?? []),
                'model_type' => $result['diagnostics']['model_type'] ?? 'regression',
            ],
            auditableType: DriverAnalysis::class,
            auditableId: (string) $analysisRecord->id,
            userId: $user?->id
        );

        return $result;
    }
}
