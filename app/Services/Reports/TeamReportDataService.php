<?php

namespace App\Services\Reports;

use App\Models\KpiGoal;
use App\Models\PerformanceCase;
use App\Models\Survey;
use App\Models\Team;
use App\Services\Metrics\PerformanceCalculatorService;
use Carbon\Carbon;

class TeamReportDataService
{
    public function __construct(
        protected PerformanceCalculatorService $calculator
    ) {}

    /**
     * Build the complete report dataset for a team and date period.
     *
     * @param array{
     *     include_verbatims?: bool,
     *     include_open_cases?: bool,
     *     compare_previous_period?: bool,
     *     cutoff_date?: string,
     *     goals?: array
     * } $options
     */
    public function buildReportData(Team $team, string $fromDate, string $toDate, array $options = []): array
    {
        $includeVerbatims = (bool) ($options['include_verbatims'] ?? false);
        $includeOpenCases = (bool) ($options['include_open_cases'] ?? true);
        $comparePrev = (bool) ($options['compare_previous_period'] ?? false);
        $cutoffDate = $options['cutoff_date'] ?? now()->toDateString();
        $goals = ! empty($options['goals']) ? $this->normalizeGoals($options['goals']) : KpiGoal::getGoalsMap();

        // 1. Scope surveys for current period
        $surveysQuery = Survey::with(['agent', 'verbatimAnalysis.category'])
            ->where(function ($q) use ($team) {
                $q->where('team_id', $team->id);
                if ($team->supervisor) {
                    $q->orWhere('supervisor_id', $team->supervisor->id)
                        ->orWhere('supervisor', $team->supervisor->name);
                }
            })->whereBetween('survey_date', [$fromDate, $toDate]);

        $surveys = $surveysQuery->get();
        $teamMetrics = $this->calculator->computeMetrics($surveys, $goals);

        // Low sample warning threshold: < 15 responses
        $isLowSample = $teamMetrics['survey_volume'] < 15;

        // 2. Previous period comparison
        $comparison = null;
        if ($comparePrev) {
            $daysCount = Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) + 1;
            $prevEnd = Carbon::parse($fromDate)->subDay()->toDateString();
            $prevStart = Carbon::parse($prevEnd)->subDays($daysCount - 1)->toDateString();

            $prevSurveys = Survey::where(function ($q) use ($team) {
                $q->where('team_id', $team->id);
                if ($team->supervisor) {
                    $q->orWhere('supervisor_id', $team->supervisor->id)
                        ->orWhere('supervisor', $team->supervisor->name);
                }
            })->whereBetween('survey_date', [$prevStart, $prevEnd])->get();

            $prevMetrics = $this->calculator->computeMetrics($prevSurveys, $goals);

            $comparison = [
                'previous_period' => [
                    'from' => $prevStart,
                    'to' => $prevEnd,
                ],
                'previous_metrics' => $prevMetrics,
                'deltas' => [
                    'volume_delta' => $teamMetrics['survey_volume'] - $prevMetrics['survey_volume'],
                    'nps_delta' => ($teamMetrics['nps_score'] !== null && $prevMetrics['nps_score'] !== null)
                        ? round($teamMetrics['nps_score'] - $prevMetrics['nps_score'], 4)
                        : null,
                    'csat_delta' => ($teamMetrics['csat_score'] !== null && $prevMetrics['csat_score'] !== null)
                        ? round($teamMetrics['csat_score'] - $prevMetrics['csat_score'], 4)
                        : null,
                    'professionalism_delta' => ($teamMetrics['professionalism_score'] !== null && $prevMetrics['professionalism_score'] !== null)
                        ? round($teamMetrics['professionalism_score'] - $prevMetrics['professionalism_score'], 4)
                        : null,
                ],
            ];
        }

        // 3. Daily trends
        $surveysByDate = $surveys->groupBy(fn ($s) => Carbon::parse($s->survey_date)->toDateString())->sortKeys();
        $dailyTrends = [];
        foreach ($surveysByDate as $dt => $dayGroup) {
            $dm = $this->calculator->computeMetrics($dayGroup, $goals);
            $dailyTrends[] = [
                'date' => $dt,
                'volume' => $dm['survey_volume'],
                'nps' => $dm['nps_score'],
                'csat' => $dm['csat_score'],
                'professionalism' => $dm['professionalism_score'],
            ];
        }

        // 4. Individual Agent Reviews
        $agentSurveys = $surveys->groupBy(function ($s) {
            return $s->agent_id ?: ($s->agent_bms ?: 'UNKNOWN');
        });

        $agentReviews = [];
        $agentsNeedingAttention = [];

        foreach ($agentSurveys as $agentKey => $group) {
            $sampleAgent = $group->first();
            $am = $this->calculator->computeMetrics($group, $goals);
            $agentName = $sampleAgent->agent?->name ?: ($sampleAgent->agent_name ?: "Agente {$sampleAgent->agent_bms}");
            $agentBms = $sampleAgent->agent?->external_id ?: $sampleAgent->agent_bms;

            $status = 'on_target';
            if ($am['survey_volume'] < 5) {
                $status = 'low_sample';
            } elseif (
                ($am['nps_score'] !== null && $am['nps_score'] < (float) $goals['nps']['warning_threshold']) ||
                ($am['csat_score'] !== null && $am['csat_score'] < (float) $goals['csat']['warning_threshold']) ||
                ($am['professionalism_score'] !== null && $am['professionalism_score'] < (float) $goals['professionalism']['warning_threshold'])
            ) {
                $status = 'critical';
                $agentsNeedingAttention[] = [
                    'agent_id' => $sampleAgent->agent_id,
                    'name' => $agentName,
                    'bms' => $agentBms,
                    'reason' => 'NPS, CSAT o Profesionalismo en rango crítico de atención',
                    'metrics' => $am,
                ];
            } elseif (
                ($am['nps_score'] !== null && $am['nps_score'] < (float) $goals['nps']['target_value']) ||
                ($am['csat_score'] !== null && $am['csat_score'] < (float) $goals['csat']['target_value']) ||
                ($am['professionalism_score'] !== null && $am['professionalism_score'] < (float) $goals['professionalism']['target_value'])
            ) {
                $status = 'warning';
            }

            // Collect all verbatims and segment distributions for this agent
            $agentVerbatims = [];
            $promotersCount = 0;
            $passivesCount = 0;
            $detractorsCount = 0;

            foreach ($group as $s) {
                $nps = $s->nps_score !== null ? (float) $s->nps_score : null;
                $sentiment = 'passive';
                if ($nps !== null) {
                    if ($nps > 1.0) {
                        // Standard 0-10 consumer scale
                        if ($nps >= 9.0) {
                            $promotersCount++;
                            $sentiment = 'promoter';
                        } elseif ($nps <= 6.0) {
                            $detractorsCount++;
                            $sentiment = 'detractor';
                        } else {
                            $passivesCount++;
                        }
                    } else {
                        // Normalized [-1.0, 1.0] scale (+1 = promoter, 0 = neutral/passive, -1 = detractor)
                        if ($nps > 0.0) {
                            $promotersCount++;
                            $sentiment = 'promoter';
                        } elseif ($nps < 0.0) {
                            $detractorsCount++;
                            $sentiment = 'detractor';
                        } else {
                            $passivesCount++;
                        }
                    }
                }

                $rawVerbatim = trim((string) ($s->verbatim ?? ''));
                if ($rawVerbatim !== '') {
                    $agentVerbatims[] = [
                        'survey_id' => $s->survey_id,
                        'date' => $s->survey_date ? Carbon::parse($s->survey_date)->toDateString() : null,
                        'verbatim' => $rawVerbatim,
                        'nps_score' => $nps,
                        'csat_score' => $s->csat_score !== null ? (float) $s->csat_score : null,
                        'professionalism_score' => $s->professionalism_score !== null ? (float) $s->professionalism_score : null,
                        'sentiment' => $sentiment,
                        'category' => $s->verbatimAnalysis?->category?->name ?: 'Sin categoría',
                    ];
                }
            }

            // Category counts for this agent
            $agentCategoryCounts = [];
            foreach ($agentVerbatims as $vb) {
                $cat = $vb['category'];
                $agentCategoryCounts[$cat] = ($agentCategoryCounts[$cat] ?? 0) + 1;
            }
            arsort($agentCategoryCounts);

            // Daily trends for this agent (chronological)
            $agentSurveysByDate = $group->groupBy(fn ($s) => Carbon::parse($s->survey_date)->toDateString())->sortKeys();
            $agentDailyTrends = [];
            foreach ($agentSurveysByDate as $dt => $dayGroup) {
                $adm = $this->calculator->computeMetrics($dayGroup, $goals);
                $agentDailyTrends[] = [
                    'date' => $dt,
                    'volume' => $adm['survey_volume'],
                    'nps' => $adm['nps_score'],
                    'csat' => $adm['csat_score'],
                    'professionalism' => $adm['professionalism_score'],
                ];
            }

            $agentReviews[] = [
                'agent_id' => $sampleAgent->agent_id,
                'agent_bms' => $agentBms,
                'agent_name' => $agentName,
                'volume' => $am['survey_volume'],
                'nps' => $am['nps_score'],
                'csat' => $am['csat_score'],
                'professionalism' => $am['professionalism_score'],
                'promoters_count' => $promotersCount,
                'passives_count' => $passivesCount,
                'detractors_count' => $detractorsCount,
                'status' => $status,
                'goals_comparison' => $am['goals_comparison'],
                'verbatims' => $agentVerbatims,
                'daily_trends' => $agentDailyTrends,
                'top_categories' => array_slice(array_keys($agentCategoryCounts), 0, 3),
            ];
        }

        // Sort agent reviews by volume descending
        usort($agentReviews, fn ($a, $b) => $b['volume'] <=> $a['volume']);

        // 5. Verbatims Categories Breakdown
        $verbatimCategories = [];
        if ($includeVerbatims) {
            $categoryCounts = [];
            foreach ($surveys as $s) {
                if ($s->verbatimAnalysis && $s->verbatimAnalysis->category) {
                    $catName = $s->verbatimAnalysis->category->name;
                    $categoryCounts[$catName] = ($categoryCounts[$catName] ?? 0) + 1;
                }
            }

            foreach ($categoryCounts as $catName => $count) {
                $verbatimCategories[] = [
                    'category' => $catName,
                    'count' => $count,
                    'percentage' => round(($count / max(1, $teamMetrics['survey_volume'])) * 100, 1),
                ];
            }
            usort($verbatimCategories, fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        // 6. Open Cases (STRICT: Strip any disciplinary notes!)
        $openCases = [];
        if ($includeOpenCases) {
            $memberIds = $surveys->pluck('agent_id')->filter()->unique()->toArray();
            if ($team->supervisor_id) {
                $memberIds[] = $team->supervisor_id;
            }

            if (! empty($memberIds)) {
                $cases = PerformanceCase::with('workforceMember')
                    ->whereIn('workforce_member_id', $memberIds)
                    ->where('status', '!=', 'closed')
                    ->get();

                foreach ($cases as $c) {
                    // Safe presentation: NO disciplinary notes exposed!
                    $openCases[] = [
                        'case_number' => $c->case_number,
                        'member_name' => $c->workforceMember->name,
                        'member_role' => $c->workforceMember->role,
                        'type' => $c->type,
                        'priority' => $c->priority,
                        'status' => $c->status,
                        'opened_at' => $c->opened_at->toDateString(),
                        'next_review_at' => $c->next_review_at?->toDateString(),
                    ];
                }
            }
        }

        // 7. Deterministic Data Version Hash
        $hashesString = $surveys->pluck('record_hash')->sort()->implode(':');
        $dataVersion = hash('sha256', "{$team->id}:{$fromDate}:{$toDate}:{$hashesString}");

        return [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'code' => $team->code,
                'supervisor_name' => $team->supervisor?->name ?: 'No asignado',
            ],
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'cutoff_date' => $cutoffDate,
            ],
            'data_version' => $dataVersion,
            'is_low_sample' => $isLowSample,
            'metrics' => $teamMetrics,
            'comparison' => $comparison,
            'daily_trends' => $dailyTrends,
            'agent_reviews' => $agentReviews,
            'agents_needing_attention' => $agentsNeedingAttention,
            'verbatim_categories' => $verbatimCategories,
            'open_cases' => $openCases,
            'goals' => $goals,
            'methodology' => [
                'nps_scale' => sprintf('-1.0 a +1.0 (Meta: %+.2f / %+.1f%%)', $goals['nps']['target_value'], $goals['nps']['target_percentage']),
                'csat_scale' => sprintf('0.0 a 1.0 (Meta: %.2f / %.1f%% Top-Box)', $goals['csat']['target_value'], $goals['csat']['target_percentage']),
                'professionalism_scale' => sprintf('0.0 a 1.0 (Meta: %.2f / %.1f%% Top-Box)', $goals['professionalism']['target_value'], $goals['professionalism']['target_percentage']),
                'sample_caution_threshold' => '15 encuestas a nivel equipo / 5 encuestas a nivel agente',
            ],
        ];
    }

    /**
     * Normalize input goals array and ensure required keys, scales, and percentages exist.
     *
     * @param  array<string, mixed>  $customGoals
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeGoals(array $customGoals): array
    {
        $defaults = KpiGoal::getGoalsMap();
        $normalized = [];

        foreach (['nps', 'csat', 'professionalism'] as $metric) {
            $base = $defaults[$metric];
            $provided = $customGoals[$metric] ?? [];

            $targetVal = isset($provided['target_value'])
                ? (float) $provided['target_value']
                : (float) $base['target_value'];

            // Percentage input > 1.0 or < -1.0 normalization down to decimal
            if (abs($targetVal) > 1.0) {
                $targetVal = round($targetVal / 100, 4);
            }

            $warnVal = null;
            if (isset($provided['warning_threshold']) && $provided['warning_threshold'] !== null && $provided['warning_threshold'] !== '') {
                $warnVal = (float) $provided['warning_threshold'];
                if (abs($warnVal) > 1.0) {
                    $warnVal = round($warnVal / 100, 4);
                }
            } else {
                $warnVal = (float) ($base['warning_threshold'] ?? 0);
            }

            $normalized[$metric] = [
                'metric' => $metric,
                'name' => $base['name'],
                'target_value' => $targetVal,
                'target_percentage' => round($targetVal * 100, 1),
                'warning_threshold' => $warnVal,
                'warning_percentage' => round($warnVal * 100, 1),
                'unit' => $base['unit'],
                'scale' => $base['scale'],
                'description' => $provided['description'] ?? $base['description'],
            ];
        }

        return $normalized;
    }
}
