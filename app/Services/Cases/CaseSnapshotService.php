<?php

namespace App\Services\Cases;

use App\Models\PerformanceCase;
use App\Models\Survey;
use App\Services\Metrics\PerformanceCalculatorService;
use Carbon\Carbon;

class CaseSnapshotService
{
    public function __construct(
        protected PerformanceCalculatorService $calculator
    ) {}

    /**
     * Capture an immutable snapshot of metrics for the case up to the current moment.
     */
    public function capture(PerformanceCase $case): array
    {
        $fromDate = $case->opened_at->toDateString();
        $toDate = now()->toDateString();
        $member = $case->workforceMember;

        $surveysQuery = Survey::whereBetween('survey_date', [$fromDate, $toDate]);
        if ($member->role === 'supervisor') {
            $surveysQuery->where(function ($q) use ($member) {
                $q->where('supervisor_id', $member->id)
                    ->orWhere('supervisor', $member->name);
            });
        } else {
            $surveysQuery->where(function ($q) use ($member) {
                $q->where('agent_id', $member->id);
                if ($member->external_id) {
                    $q->orWhere('agent_bms', $member->external_id);
                }
            });
        }

        $metrics = $this->calculator->computeMetrics($surveysQuery);

        return [
            'captured_at' => now()->toIso8601String(),
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'days_open' => Carbon::parse($case->opened_at)->diffInDays(now()),
            'metrics' => [
                'has_sample' => $metrics['has_sample'],
                'volume' => $metrics['survey_volume'],
                'nps' => $metrics['nps_score'],
                'csat' => $metrics['csat_score'],
                'professionalism' => $metrics['professionalism_score'],
                'goals_comparison' => $metrics['goals_comparison'],
            ],
            'baseline_diff' => [
                'nps_delta' => ($metrics['nps_score'] !== null && isset($case->baseline['nps']))
                    ? round($metrics['nps_score'] - (float) $case->baseline['nps'], 4)
                    : null,
                'csat_delta' => ($metrics['csat_score'] !== null && isset($case->baseline['csat']))
                    ? round($metrics['csat_score'] - (float) $case->baseline['csat'], 4)
                    : null,
                'professionalism_delta' => ($metrics['professionalism_score'] !== null && isset($case->baseline['professionalism']))
                    ? round($metrics['professionalism_score'] - (float) $case->baseline['professionalism'], 4)
                    : null,
            ],
        ];
    }
}
