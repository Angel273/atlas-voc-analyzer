<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\WorkforceMember;
use App\Services\Organization\WorkforceBackfillService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display listing of teams and member management studio.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Team::class);

        $teams = Team::with([
            'supervisor',
            'members' => function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            },
        ])
            ->withCount('surveys')
            ->orderBy('name')
            ->get();

        $supervisors = WorkforceMember::whereIn('role', ['supervisor', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'external_id', 'role']);

        $agents = WorkforceMember::whereIn('role', ['agent', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'external_id', 'role']);

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
            'supervisors' => $supervisors,
            'agents' => $agents,
        ]);
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Team::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:teams,code'],
            'supervisor_id' => ['nullable', 'exists:workforce_members,id'],
            'is_active' => ['boolean'],
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'supervisor_id' => $validated['supervisor_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', "Equipo '{$team->name}' creado exitosamente.");
    }

    /**
     * Update an existing team.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', Rule::unique('teams', 'code')->ignore($team->id)],
            'supervisor_id' => ['nullable', 'exists:workforce_members,id'],
            'is_active' => ['boolean'],
        ]);

        $team->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'supervisor_id' => $validated['supervisor_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', "Equipo '{$team->name}' actualizado exitosamente.");
    }

    /**
     * Remove or deactivate a team.
     */
    public function destroy(Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        if ($team->surveys()->exists()) {
            $team->update(['is_active' => false]);

            return back()->with('warning', "El equipo '{$team->name}' posee encuestas asociadas; ha sido desactivado en lugar de eliminarse.");
        }

        $teamName = $team->name;
        $team->delete();

        return back()->with('success', "Equipo '{$teamName}' eliminado exitosamente.");
    }

    /**
     * Add an agent/member to a team.
     */
    public function addMember(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $validated = $request->validate([
            'workforce_member_id' => ['required', 'exists:workforce_members,id'],
            'effective_from' => ['required', 'date'],
        ]);

        $member = WorkforceMember::findOrFail($validated['workforce_member_id']);
        $fromDate = $validated['effective_from'];

        // End any current active membership in this or another team
        TeamMembership::where('workforce_member_id', $member->id)
            ->where(function ($q) use ($fromDate) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $fromDate);
            })
            ->update(['effective_to' => Carbon::parse($fromDate)->subDay()->toDateString()]);

        TeamMembership::create([
            'team_id' => $team->id,
            'workforce_member_id' => $member->id,
            'role' => $member->role === 'supervisor' ? 'lead' : 'agent',
            'effective_from' => $fromDate,
            'effective_to' => null,
        ]);

        return back()->with('success', "{$member->name} asignado al equipo '{$team->name}'.");
    }

    /**
     * Remove an agent/member from a team.
     */
    public function removeMember(Request $request, Team $team, WorkforceMember $member): RedirectResponse
    {
        Gate::authorize('update', $team);

        TeamMembership::where('team_id', $team->id)
            ->where('workforce_member_id', $member->id)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->update(['effective_to' => now()->toDateString()]);

        return back()->with('success', "{$member->name} desvinculado del equipo '{$team->name}'.");
    }

    /**
     * Trigger automatic workforce and teams sync/backfill from surveys data.
     */
    public function syncBackfill(WorkforceBackfillService $backfillService): RedirectResponse
    {
        Gate::authorize('create', Team::class);

        $result = $backfillService->backfill();

        return back()->with(
            'success',
            "Sincronización completada: {$result['teams_created']} equipos nuevos, {$result['supervisors_created']} supervisores y {$result['agents_created']} agentes procesados desde las encuestas."
        );
    }
}
