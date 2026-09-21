<?php

namespace App\Services\Cases;

use App\Models\CaseDailyResult;
use App\Models\CaseMetricRecalculation;
use App\Models\PerformanceCase;
use App\Models\User;
use App\Services\Metrics\PerformanceCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CaseRecalculationService
{
    public function __construct(
        protected PerformanceCalculatorService $calculator
    ) {}

    /**
     * Generate a new recalculation version for the case from opened_at to the given or current date.
     */
    public function recalculate(
        PerformanceCase $case,
        ?User $user = null,
        string $reason = 'manual',
        ?string $notes = null,
        ?string $toDate = null
    ): CaseMetricRecalculation {
        return DB::transaction(function () use ($case, $user, $reason, $notes, $toDate) {
            $maxVersion = (int) $case->recalculations()->max('version_number');
            $nextVersion = $maxVersion + 1;

            $fromDate = $case->opened_at->toDateString();
            $effectiveToDate = $toDate ?: now()->toDateString();

            $member = $case->workforceMember;
            $dailyMetrics = $this->calculator->calculateDailyMetricsForMember(
                $member,
                $fromDate,
                $effectiveToDate
            );

            $sampledDays = array_filter($dailyMetrics, fn ($d) => $d['has_sample'] && $d['survey_volume'] > 0);
            $totalVolume = (int) array_sum(array_column($sampledDays, 'survey_volume'));
            $npsWeightedSum = 0.0;
            $csatWeightedSum = 0.0;
            $profWeightedSum = 0.0;

            foreach ($sampledDays as $d) {
                if ($d['nps_score'] !== null) {
                    $npsWeightedSum += ((float) $d['nps_score']) * $d['survey_volume'];
                }
                if ($d['csat_score'] !== null) {
                    $csatWeightedSum += ((float) $d['csat_score']) * $d['survey_volume'];
                }
                if ($d['professionalism_score'] !== null) {
                    $profWeightedSum += ((float) $d['professionalism_score']) * $d['survey_volume'];
                }
            }

            $summaryNps = $totalVolume > 0 ? round($npsWeightedSum / $totalVolume, 4) : null;
            $summaryCsat = $totalVolume > 0 ? round($csatWeightedSum / $totalVolume, 4) : null;
            $summaryProf = $totalVolume > 0 ? round($profWeightedSum / $totalVolume, 4) : null;

            $recalculation = CaseMetricRecalculation::create([
                'performance_case_id' => $case->id,
                'version_number' => $nextVersion,
                'recalculated_at' => now(),
                'triggered_by_user_id' => $user?->id,
                'reason' => $reason,
                'notes' => $notes,
                'metadata' => [
                    'from_date' => $fromDate,
                    'to_date' => $effectiveToDate,
                    'total_days' => count($dailyMetrics),
                    'sampled_days' => count($sampledDays),
                    'survey_volume' => $totalVolume,
                    'nps_score' => $summaryNps,
                    'csat_score' => $summaryCsat,
                    'professionalism_score' => $summaryProf,
                ],
            ]);

            foreach ($dailyMetrics as $day) {
                CaseDailyResult::create([
                    'case_metric_recalculation_id' => $recalculation->id,
                    'performance_case_id' => $case->id,
                    'date' => $day['date'],
                    'nps_score' => $day['nps_score'],
                    'csat_score' => $day['csat_score'],
                    'professionalism_score' => $day['professionalism_score'],
                    'survey_volume' => $day['survey_volume'],
                    'has_sample' => $day['has_sample'],
                    'applicable_goals' => $day['applicable_goals'],
                    'effective_team_id' => $day['effective_team_id'],
                    'effective_team_name' => $day['effective_team_name'],
                    'effective_supervisor_id' => $day['effective_supervisor_id'],
                    'effective_supervisor_name' => $day['effective_supervisor_name'],
                ]);
            }

            return $recalculation->load('dailyResults');
        });
    }

    /**
     * Compare two recalculation versions of a case and return detailed day-by-day diffs.
     */
    public function compareVersions(PerformanceCase $case, int $versionA, int $versionB): array
    {
        $recalcA = $case->recalculations()->where('version_number', $versionA)->with('dailyResults')->firstOrFail();
        $recalcB = $case->recalculations()->where('version_number', $versionB)->with('dailyResults')->firstOrFail();

        $resultsA = $recalcA->dailyResults->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        $resultsB = $recalcB->dailyResults->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $allDates = $resultsA->keys()->merge($resultsB->keys())->unique()->sort()->values();

        $diffDays = [];
        $hasDifferences = false;

        foreach ($allDates as $date) {
            $rowA = $resultsA->get($date);
            $rowB = $resultsB->get($date);

            $volA = $rowA ? (int) $rowA->survey_volume : 0;
            $volB = $rowB ? (int) $rowB->survey_volume : 0;
            $npsA = $rowA ? $rowA->nps_score : null;
            $npsB = $rowB ? $rowB->nps_score : null;
            $csatA = $rowA ? $rowA->csat_score : null;
            $csatB = $rowB ? $rowB->csat_score : null;
            $profA = $rowA ? $rowA->professionalism_score : null;
            $profB = $rowB ? $rowB->professionalism_score : null;

            $isDifferent = ($volA !== $volB)
                || ($npsA !== $npsB)
                || ($csatA !== $csatB)
                || ($profA !== $profB);

            if ($isDifferent) {
                $hasDifferences = true;
            }

            $diffDays[] = [
                'date' => $date,
                'is_different' => $isDifferent,
                'version_a' => [
                    'has_sample' => $rowA ? $rowA->has_sample : false,
                    'volume' => $volA,
                    'nps' => $npsA,
                    'csat' => $csatA,
                    'professionalism' => $profA,
                ],
                'version_b' => [
                    'has_sample' => $rowB ? $rowB->has_sample : false,
                    'volume' => $volB,
                    'nps' => $npsB,
                    'csat' => $csatB,
                    'professionalism' => $profB,
                ],
                'diff' => [
                    'volume_delta' => $volB - $volA,
                    'nps_delta' => ($npsA !== null && $npsB !== null) ? round($npsB - $npsA, 4) : null,
                    'csat_delta' => ($csatA !== null && $csatB !== null) ? round($csatB - $csatA, 4) : null,
                    'professionalism_delta' => ($profA !== null && $profB !== null) ? round($profB - $profA, 4) : null,
                ],
            ];
        }

        return [
            'case_id' => $case->id,
            'version_a' => [
                'version_number' => $recalcA->version_number,
                'recalculated_at' => $recalcA->recalculated_at->toIso8601String(),
                'reason' => $recalcA->reason,
            ],
            'version_b' => [
                'version_number' => $recalcB->version_number,
                'recalculated_at' => $recalcB->recalculated_at->toIso8601String(),
                'reason' => $recalcB->reason,
            ],
            'has_differences' => $hasDifferences,
            'total_days_compared' => count($diffDays),
            'days' => $diffDays,
        ];
    }
}
