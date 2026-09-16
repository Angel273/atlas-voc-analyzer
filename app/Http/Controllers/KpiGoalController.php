<?php

namespace App\Http\Controllers;

use App\Models\KpiGoal;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiGoalController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Retrieve the active KPI goals and formatted context.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'goals' => KpiGoal::getGoalsMap(),
            'formatted_context' => KpiGoal::getFormattedContext(),
        ]);
    }

    /**
     * Update or define the operational targets for VOC metrics.
     * Note: Metrics (NPS, CSAT, Professionalism) are internally stored in the range [-1.0, 1.0].
     * Input values greater than 1 or less than -1 are automatically normalized from percentages (e.g., 50 -> 0.50).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goals' => ['required', 'array'],
            'goals.nps' => ['sometimes', 'array'],
            'goals.nps.target_value' => ['required_with:goals.nps', 'numeric', 'min:-100', 'max:100'],
            'goals.nps.warning_threshold' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'goals.nps.description' => ['nullable', 'string', 'max:500'],

            'goals.csat' => ['sometimes', 'array'],
            'goals.csat.target_value' => ['required_with:goals.csat', 'numeric', 'min:-100', 'max:100'],
            'goals.csat.warning_threshold' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'goals.csat.description' => ['nullable', 'string', 'max:500'],

            'goals.professionalism' => ['sometimes', 'array'],
            'goals.professionalism.target_value' => ['required_with:goals.professionalism', 'numeric', 'min:-100', 'max:100'],
            'goals.professionalism.warning_threshold' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'goals.professionalism.description' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = Auth::id();
        $updatedMetrics = [];

        DB::transaction(function () use ($validated, $userId, &$updatedMetrics) {
            foreach (['nps', 'csat', 'professionalism'] as $metricKey) {
                if (! isset($validated['goals'][$metricKey])) {
                    continue;
                }

                $data = $validated['goals'][$metricKey];

                $target = (float) $data['target_value'];
                // Normalize percentage input > 1.0 or < -1.0 down to [-1.0, 1.0] scale
                if (abs($target) > 1.0) {
                    $target = round($target / 100, 4);
                }

                $warning = null;
                if (isset($data['warning_threshold']) && $data['warning_threshold'] !== null && $data['warning_threshold'] !== '') {
                    $warning = (float) $data['warning_threshold'];
                    if (abs($warning) > 1.0) {
                        $warning = round($warning / 100, 4);
                    }
                }

                $goal = KpiGoal::updateOrCreate(
                    ['metric' => $metricKey],
                    [
                        'target_value' => $target,
                        'warning_threshold' => $warning,
                        'unit' => 'score',
                        'description' => $data['description'] ?? null,
                        'updated_by_user_id' => $userId,
                    ]
                );

                $updatedMetrics[$metricKey] = [
                    'target_value' => $target,
                    'warning_threshold' => $warning,
                    'description' => $goal->description,
                ];
            }
        });

        // Audit the change
        $this->auditService->record(
            eventType: 'KPI_GOALS_UPDATED',
            payload: [
                'updated_metrics' => $updatedMetrics,
            ],
            auditableType: KpiGoal::class,
            userId: $userId
        );

        return response()->json([
            'success' => true,
            'message' => 'Metas operacionales actualizadas con éxito.',
            'goals' => KpiGoal::getGoalsMap(),
            'formatted_context' => KpiGoal::getFormattedContext(),
        ]);
    }
}
