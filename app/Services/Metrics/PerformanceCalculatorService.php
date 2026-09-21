<?php

namespace App\Services\Metrics;

use App\Models\KpiGoal;
use App\Models\Survey;
use App\Models\Team;
use App\Models\WorkforceMember;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class PerformanceCalculatorService
{
    /**
     * Compute aggregated metrics for an array or collection of survey records or a query builder/relation.
     *
     * @param  iterable<Survey>|Builder|Relation  $surveys
     * @param  array<string, array<string, mixed>>|null  $customGoals
     * @return array{
     *     has_sample: bool,
     *     survey_volume: int,
     *     nps_score: float|null,
     *     csat_score: float|null,
     *     professionalism_score: float|null,
     *     goals_comparison?: array
     * }
     */
    public function computeMetrics(iterable|Builder|Relation $surveys, ?array $customGoals = null): array
    {
        if ($surveys instanceof Builder || $surveys instanceof Relation) {
            $items = $surveys->get();
        } elseif ($surveys instanceof Collection) {
            $items = $surveys;
        } else {
            $items = collect($surveys);
        }

        $volume = $items->count();

        if ($volume === 0) {
            return [
                'has_sample' => false,
                'survey_volume' => 0,
                'nps_score' => null,
                'csat_score' => null,
                'professionalism_score' => null,
                'goals_comparison' => $this->compareWithGoals(null, null, null, $customGoals),
            ];
        }

        $npsSum = 0.0;
        $csatPositiveSum = 0.0;
        $profPositiveSum = 0.0;

        foreach ($items as $s) {
            $npsSum += (float) $s->nps_score;

            // CSAT top-box logic consistent with QueryPlanner
            $c = (float) $s->csat_score;
            $csatPositiveSum += ($c >= 1.0) ? 1.0 : ($c > 0 ? $c : 0.0);

            // Professionalism top-box logic consistent with QueryPlanner
            $p = (float) $s->professionalism_score;
            $profPositiveSum += ($p >= 1.0) ? 1.0 : ($p > 0 ? $p : 0.0);
        }

        $nps = round($npsSum / $volume, 4);
        $csat = round($csatPositiveSum / $volume, 4);
        $prof = round($profPositiveSum / $volume, 4);

        return [
            'has_sample' => true,
            'survey_volume' => $volume,
            'nps_score' => $nps,
            'csat_score' => $csat,
            'professionalism_score' => $prof,
            'goals_comparison' => $this->compareWithGoals($nps, $csat, $prof, $customGoals),
        ];
    }

    /**
     * Compute day-by-day metrics for a workforce member between two dates.
     * Days without surveys will have has_sample = false, survey_volume = 0, and null scores.
     *
     * @return array<int, array{
     *     date: string,
     *     has_sample: bool,
     *     survey_volume: int,
     *     nps_score: float|null,
     *     csat_score: float|null,
     *     professionalism_score: float|null,
     *     applicable_goals: array,
     *     effective_team_id: int|null,
     *     effective_team_name: string|null,
     *     effective_supervisor_id: int|null,
     *     effective_supervisor_name: string|null
     * }>
     */
    public function calculateDailyMetricsForMember(
        WorkforceMember $member,
        string $fromDate,
        string $toDate
    ): array {
        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->startOfDay();

        if ($end->lessThan($start)) {
            $end = $start->copy();
        }

        // Fetch all surveys for this member in the date range
        $query = Survey::query()
            ->whereBetween('survey_date', [$start->toDateString(), $end->toDateString()]);

        if ($member->role === 'supervisor') {
            $query->where(function ($q) use ($member) {
                $q->where('supervisor_id', $member->id)
                    ->orWhere('supervisor', $member->name);
            });
        } else {
            $query->where(function ($q) use ($member) {
                $q->where('agent_id', $member->id);
                if ($member->external_id) {
                    $q->orWhere('agent_bms', $member->external_id);
                }
            });
        }

        $surveys = $query->get()->groupBy(fn ($s) => Carbon::parse($s->survey_date)->toDateString());
        $goals = KpiGoal::getGoalsMap();

        $dailyResults = [];
        $period = CarbonPeriod::create($start, '1 day', $end);

        foreach ($period as $day) {
            $dateStr = $day->toDateString();
            $daySurveys = $surveys->get($dateStr, collect());
            $effectiveTeam = $member->resolveTeamAtDate($dateStr);
            $effectiveSupervisor = $effectiveTeam?->supervisor;

            if ($daySurveys->isEmpty()) {
                $dailyResults[] = [
                    'date' => $dateStr,
                    'has_sample' => false,
                    'survey_volume' => 0,
                    'nps_score' => null,
                    'csat_score' => null,
                    'professionalism_score' => null,
                    'applicable_goals' => $goals,
                    'effective_team_id' => $effectiveTeam?->id,
                    'effective_team_name' => $effectiveTeam?->name,
                    'effective_supervisor_id' => $effectiveSupervisor?->id,
                    'effective_supervisor_name' => $effectiveSupervisor?->name,
                ];
            } else {
                $metrics = $this->computeMetrics($daySurveys);
                $dailyResults[] = [
                    'date' => $dateStr,
                    'has_sample' => true,
                    'survey_volume' => $metrics['survey_volume'],
                    'nps_score' => $metrics['nps_score'],
                    'csat_score' => $metrics['csat_score'],
                    'professionalism_score' => $metrics['professionalism_score'],
                    'applicable_goals' => $goals,
                    'effective_team_id' => $effectiveTeam?->id,
                    'effective_team_name' => $effectiveTeam?->name,
                    'effective_supervisor_id' => $effectiveSupervisor?->id,
                    'effective_supervisor_name' => $effectiveSupervisor?->name,
                ];
            }
        }

        return $dailyResults;
    }

    /**
     * Compute aggregated metrics for a Team in a given date range.
     */
    public function calculateForTeam(Team $team, string $fromDate, string $toDate): array
    {
        $surveys = Survey::where('team_id', $team->id)
            ->whereBetween('survey_date', [$fromDate, $toDate])
            ->get();

        // Fallback to supervisor name matching if team_id not populated
        if ($surveys->isEmpty() && $team->supervisor) {
            $surveys = Survey::where('supervisor', $team->supervisor->name)
                ->whereBetween('survey_date', [$fromDate, $toDate])
                ->get();
        }

        return $this->computeMetrics($surveys);
    }

    /**
     * Compare metrics against current or custom KPI goals.
     *
     * @param  array<string, array<string, mixed>>|null  $customGoals
     */
    public function compareWithGoals(?float $nps, ?float $csat, ?float $prof, ?array $customGoals = null): array
    {
        $goals = $customGoals ?: KpiGoal::getGoalsMap();

        $npsTarget = (float) ($goals['nps']['target_value'] ?? 0.50);
        $npsWarning = (float) ($goals['nps']['warning_threshold'] ?? 0.20);

        $csatTarget = (float) ($goals['csat']['target_value'] ?? 0.80);
        $csatWarning = (float) ($goals['csat']['warning_threshold'] ?? 0.70);

        $profTarget = (float) ($goals['professionalism']['target_value'] ?? 0.85);
        $profWarning = (float) ($goals['professionalism']['warning_threshold'] ?? 0.75);

        return [
            'nps' => [
                'value' => $nps,
                'target' => $npsTarget,
                'warning' => $npsWarning,
                'gap' => $nps !== null ? round($nps - $npsTarget, 4) : null,
                'meets_goal' => $nps !== null && $nps >= $npsTarget,
                'status' => $this->evaluateStatus($nps, $npsTarget, $npsWarning),
            ],
            'csat' => [
                'value' => $csat,
                'target' => $csatTarget,
                'warning' => $csatWarning,
                'gap' => $csat !== null ? round($csat - $csatTarget, 4) : null,
                'meets_goal' => $csat !== null && $csat >= $csatTarget,
                'status' => $this->evaluateStatus($csat, $csatTarget, $csatWarning),
            ],
            'professionalism' => [
                'value' => $prof,
                'target' => $profTarget,
                'warning' => $profWarning,
                'gap' => $prof !== null ? round($prof - $profTarget, 4) : null,
                'meets_goal' => $prof !== null && $prof >= $profTarget,
                'status' => $this->evaluateStatus($prof, $profTarget, $profWarning),
            ],
        ];
    }

    protected function evaluateStatus(?float $val, float $target, float $warning): string
    {
        if ($val === null) {
            return 'no_sample';
        }
        if ($val >= $target) {
            return 'on_target';
        }
        if ($val >= $warning) {
            return 'warning';
        }

        return 'critical';
    }
}
