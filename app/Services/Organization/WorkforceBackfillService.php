<?php

namespace App\Services\Organization;

use App\Models\Survey;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\WorkforceMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkforceBackfillService
{
    /**
     * Run the backfill process idempotently from existing survey records.
     *
     * @return array{
     *     agents_processed: int,
     *     supervisors_processed: int,
     *     teams_created: int,
     *     memberships_created: int,
     *     surveys_linked: int,
     *     conflicts: array<int, array{type: string, description: string, agent_bms?: string, supervisor?: string, details?: array}>
     * }
     */
    public function run(): array
    {
        $conflicts = [];
        $agentsCount = 0;
        $supervisorsCount = 0;
        $teamsCreated = 0;
        $membershipsCreated = 0;
        $surveysLinked = 0;

        DB::transaction(function () use (
            &$conflicts,
            &$agentsCount,
            &$supervisorsCount,
            &$teamsCreated,
            &$membershipsCreated,
            &$surveysLinked
        ) {
            // 1. Process Supervisors and Teams
            $supervisorRecords = Survey::whereNotNull('supervisor')
                ->where('supervisor', '!=', '')
                ->select('supervisor')
                ->distinct()
                ->pluck('supervisor');

            $supervisorMap = []; // [supervisor_name => ['member' => WorkforceMember, 'team' => Team]]

            foreach ($supervisorRecords as $supName) {
                $trimmedSup = trim($supName);
                if ($trimmedSup === '') {
                    continue;
                }

                $supCode = substr('SUP-'.Str::upper(Str::slug($trimmedSup, '')), 0, 50);
                $supervisorMember = WorkforceMember::firstOrCreate(
                    ['name' => $trimmedSup, 'role' => 'supervisor'],
                    [
                        'external_id' => $supCode,
                        'is_active' => true,
                        'metadata' => ['created_from_backfill' => true],
                    ]
                );

                $teamCode = substr('TEAM-'.Str::upper(Str::slug($trimmedSup, '')), 0, 50);
                $team = Team::firstOrCreate(
                    ['code' => $teamCode],
                    [
                        'name' => 'Equipo '.$trimmedSup,
                        'supervisor_id' => $supervisorMember->id,
                        'is_active' => true,
                        'metadata' => ['created_from_backfill' => true],
                    ]
                );

                if (! $team->supervisor_id) {
                    $team->update(['supervisor_id' => $supervisorMember->id]);
                }

                if ($team->wasRecentlyCreated) {
                    $teamsCreated++;
                }

                $supervisorMap[$trimmedSup] = [
                    'member' => $supervisorMember,
                    'team' => $team,
                ];
                $supervisorsCount++;
            }

            // 2. Process Agents by BMS
            $agentBmsList = Survey::whereNotNull('agent_bms')
                ->where('agent_bms', '!=', '')
                ->select('agent_bms')
                ->distinct()
                ->pluck('agent_bms');

            foreach ($agentBmsList as $bms) {
                $trimmedBms = trim($bms);
                if ($trimmedBms === '') {
                    continue;
                }

                // Check for name variations
                $distinctNames = Survey::where('agent_bms', $trimmedBms)
                    ->whereNotNull('agent_name')
                    ->where('agent_name', '!=', '')
                    ->distinct()
                    ->pluck('agent_name')
                    ->map(fn ($n) => trim($n))
                    ->unique()
                    ->values()
                    ->toArray();

                $canonicalName = ! empty($distinctNames) ? $distinctNames[0] : 'Agente '.$trimmedBms;

                if (count($distinctNames) > 1) {
                    $conflicts[] = [
                        'type' => 'name_divergence',
                        'agent_bms' => $trimmedBms,
                        'description' => "El agente con BMS '{$trimmedBms}' posee múltiples nombres registrados: ".implode(', ', $distinctNames),
                        'details' => [
                            'names' => $distinctNames,
                            'chosen_canonical' => $canonicalName,
                        ],
                    ];
                }

                $agentMember = WorkforceMember::where('external_id', $trimmedBms)->first();
                if (! $agentMember) {
                    $agentMember = WorkforceMember::create([
                        'external_id' => $trimmedBms,
                        'name' => $canonicalName,
                        'role' => 'agent',
                        'is_active' => true,
                        'metadata' => [
                            'distinct_names_found' => $distinctNames,
                            'created_from_backfill' => true,
                        ],
                    ]);
                } else {
                    // Update name if currently generic and we now have a real name
                    if (str_starts_with($agentMember->name, 'Agente ') && ! empty($distinctNames)) {
                        $agentMember->update(['name' => $canonicalName]);
                    }
                }
                $agentsCount++;

                // 3. Reconstruct team memberships from chronological survey dates & supervisors
                $surveysByDate = Survey::where('agent_bms', $trimmedBms)
                    ->whereNotNull('survey_date')
                    ->orderBy('survey_date', 'asc')
                    ->select('id', 'survey_date', 'supervisor')
                    ->get();

                if ($surveysByDate->isEmpty()) {
                    continue;
                }

                // Check for same-day multiple supervisors conflict
                $datesWithSupervisors = [];
                foreach ($surveysByDate as $s) {
                    $sDate = Carbon::parse($s->survey_date)->toDateString();
                    $sSup = trim($s->supervisor ?? '');
                    if ($sSup !== '') {
                        $datesWithSupervisors[$sDate][$sSup] = true;
                    }
                }

                foreach ($datesWithSupervisors as $dt => $sups) {
                    if (count($sups) > 1) {
                        $conflicts[] = [
                            'type' => 'same_date_multiple_supervisors',
                            'agent_bms' => $trimmedBms,
                            'description' => "El agente con BMS '{$trimmedBms}' registra encuestas con supervisores distintos el mismo día ({$dt}): ".implode(', ', array_keys($sups)),
                            'details' => [
                                'date' => $dt,
                                'supervisors' => array_keys($sups),
                            ],
                        ];
                    }
                }

                // Build contiguous membership intervals
                $intervals = [];
                $currentInterval = null;

                foreach ($surveysByDate as $surveyRecord) {
                    $surveyDate = Carbon::parse($surveyRecord->survey_date)->toDateString();
                    $supName = trim($surveyRecord->supervisor ?? '');

                    if ($supName === '' || ! isset($supervisorMap[$supName])) {
                        continue;
                    }

                    $team = $supervisorMap[$supName]['team'];

                    if ($currentInterval === null) {
                        $currentInterval = [
                            'team_id' => $team->id,
                            'effective_from' => $surveyDate,
                            'effective_to' => $surveyDate,
                        ];
                    } elseif ($currentInterval['team_id'] === $team->id) {
                        // Same team, extend interval end
                        $currentInterval['effective_to'] = $surveyDate;
                    } else {
                        // Changed team! Close previous interval
                        $intervals[] = $currentInterval;
                        $currentInterval = [
                            'team_id' => $team->id,
                            'effective_from' => $surveyDate,
                            'effective_to' => $surveyDate,
                        ];
                    }
                }

                if ($currentInterval !== null) {
                    $intervals[] = $currentInterval;
                }

                // Save memberships (the last interval gets effective_to = null to stay currently active)
                $intervalCount = count($intervals);
                for ($idx = 0; $idx < $intervalCount; $idx++) {
                    $interval = $intervals[$idx];
                    $isLast = ($idx === $intervalCount - 1);
                    $effectiveTo = $isLast ? null : $interval['effective_to'];

                    $existingMembership = TeamMembership::where('workforce_member_id', $agentMember->id)
                        ->where('team_id', $interval['team_id'])
                        ->whereDate('effective_from', $interval['effective_from'])
                        ->first();

                    if (! $existingMembership) {
                        TeamMembership::create([
                            'team_id' => $interval['team_id'],
                            'workforce_member_id' => $agentMember->id,
                            'role' => 'agent',
                            'effective_from' => $interval['effective_from'],
                            'effective_to' => $effectiveTo,
                        ]);
                        $membershipsCreated++;
                    } else {
                        // Ensure effective_to is properly synced
                        $currentEffectiveToStr = $existingMembership->effective_to?->toDateString();
                        if ($currentEffectiveToStr !== $effectiveTo) {
                            $existingMembership->update(['effective_to' => $effectiveTo]);
                        }
                    }
                }

                // 4. Link all surveys of this agent to agent_id, supervisor_id, and team_id
                foreach ($surveysByDate as $surveyRecord) {
                    $surveyDate = Carbon::parse($surveyRecord->survey_date)->toDateString();
                    $supName = trim($surveyRecord->supervisor ?? '');
                    $resolvedSup = $supervisorMap[$supName] ?? null;

                    // Resolve team based on active membership at that date
                    $resolvedTeam = $agentMember->resolveTeamAtDate($surveyDate)
                        ?? ($resolvedSup ? $resolvedSup['team'] : null);

                    $updated = Survey::where('id', $surveyRecord->id)->update([
                        'agent_id' => $agentMember->id,
                        'supervisor_id' => $resolvedSup ? $resolvedSup['member']->id : null,
                        'team_id' => $resolvedTeam ? $resolvedTeam->id : null,
                    ]);

                    if ($updated > 0) {
                        $surveysLinked += $updated;
                    }
                }
            }
        });

        return [
            'agents_processed' => $agentsCount,
            'supervisors_processed' => $supervisorsCount,
            'teams_created' => $teamsCreated,
            'memberships_created' => $membershipsCreated,
            'surveys_linked' => $surveysLinked,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Alias for run() to provide intuitive naming.
     *
     * @return array{
     *     agents_processed: int,
     *     supervisors_processed: int,
     *     teams_created: int,
     *     memberships_created: int,
     *     surveys_linked: int,
     *     conflicts: array<int, array{type: string, description: string, agent_bms?: string, supervisor?: string, details?: array}>
     * }
     */
    public function backfill(): array
    {
        return $this->run();
    }
}
