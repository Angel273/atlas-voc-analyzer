<?php

namespace App\Http\Controllers;

use App\Models\PerformanceCase;
use App\Models\PerformanceCaseUpdate;
use App\Models\User;
use App\Models\WorkforceMember;
use App\Services\Audit\AuditService;
use App\Services\Cases\CaseRecalculationService;
use App\Services\Cases\CaseSnapshotService;
use App\Services\Metrics\PerformanceCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceCaseController extends Controller
{
    public function __construct(
        protected CaseRecalculationService $recalculationService,
        protected CaseSnapshotService $snapshotService,
        protected PerformanceCalculatorService $calculator,
        protected AuditService $auditService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PerformanceCase::class);

        $query = PerformanceCase::with(['workforceMember', 'assignedTo', 'latestRecalculation']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('responsible_id')) {
            $query->where('assigned_to_user_id', $request->input('responsible_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('workforceMember', function ($mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%")
                            ->orWhere('external_id', 'like', "%{$search}%");
                    });
            });
        }

        $cases = $query->orderBy('opened_at', 'desc')->paginate(15)->withQueryString();

        $stats = [
            'open_count' => PerformanceCase::where('status', 'open')->count(),
            'under_review_count' => PerformanceCase::where('status', 'under_review')->count(),
            'high_priority_count' => PerformanceCase::where('priority', 'high')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'resolved_count' => PerformanceCase::whereIn('status', ['resolved', 'closed'])->count(),
        ];

        $users = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('PerformanceCases/Index', [
            'cases' => $cases,
            'filters' => $request->only(['search', 'status', 'priority', 'type', 'responsible_id']),
            'stats' => $stats,
            'users' => $users,
            'responsibles' => $users,
            'can_create' => Auth::user()?->hasPermission('cases.create') ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', PerformanceCase::class);

        $members = WorkforceMember::where('is_active', true)
            ->select('id', 'name', 'external_id', 'role')
            ->orderBy('name')
            ->get();

        $users = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('PerformanceCases/Create', [
            'members' => $members,
            'users' => $users,
            'responsibles' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PerformanceCase::class);

        $validated = $request->validate([
            'workforce_member_id' => ['required', 'exists:workforce_members,id'],
            'type' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:1000'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'opened_at' => ['required', 'date'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'target_nps' => ['nullable', 'numeric', 'between:-1,1'],
            'target_csat' => ['nullable', 'numeric', 'between:0,1'],
            'target_professionalism' => ['nullable', 'numeric', 'between:0,1'],
            'next_review_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
        ]);

        $member = WorkforceMember::findOrFail($validated['workforce_member_id']);

        // Calculate initial baseline metrics for 30 days prior to opened_at
        $baselineStart = Carbon::parse($validated['opened_at'])->subDays(30)->toDateString();
        $baselineEnd = Carbon::parse($validated['opened_at'])->toDateString();
        $baselineSurveys = $member->role === 'supervisor'
            ? $member->supervisedSurveys()->whereBetween('survey_date', [$baselineStart, $baselineEnd])
            : $member->surveys()->whereBetween('survey_date', [$baselineStart, $baselineEnd]);
        $baselineMetrics = $this->calculator->computeMetrics($baselineSurveys);

        $baseline = [
            'nps' => $baselineMetrics['nps_score'],
            'csat' => $baselineMetrics['csat_score'],
            'professionalism' => $baselineMetrics['professionalism_score'],
            'volume' => $baselineMetrics['survey_volume'],
            'period_from' => $baselineStart,
            'period_to' => $baselineEnd,
        ];

        $objectives = [
            'target_nps' => $validated['target_nps'] ?? 0.50,
            'target_csat' => $validated['target_csat'] ?? 0.80,
            'target_professionalism' => $validated['target_professionalism'] ?? 0.85,
        ];

        $case = PerformanceCase::create([
            'case_number' => PerformanceCase::generateCaseNumber(),
            'workforce_member_id' => $member->id,
            'target_type' => $member->role === 'supervisor' ? 'supervisor' : 'agent',
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'opened_at' => $validated['opened_at'],
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? Auth::id(),
            'baseline' => $baseline,
            'objectives' => $objectives,
            'next_review_at' => $validated['next_review_at'] ?? null,
        ]);

        // Generate initial Version 1 of daily results
        $this->recalculationService->recalculate(
            $case,
            Auth::user(),
            'case_creation',
            'Cálculo inicial al momento de la apertura del caso'
        );

        $this->auditService->record(
            eventType: 'PERFORMANCE_CASE_CREATED',
            payload: [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
                'workforce_member_id' => $member->id,
                'priority' => $case->priority,
                'status' => $case->status,
            ],
            auditableType: PerformanceCase::class,
            auditableId: (string) $case->id,
            userId: Auth::id()
        );

        return redirect()->route('performance-cases.show', $case->id)
            ->with('success', "Caso {$case->case_number} abierto exitosamente con cálculo inicial versión 1.");
    }

    public function show(Request $request, PerformanceCase $performanceCase): Response
    {
        Gate::authorize('view', $performanceCase);

        $performanceCase->load([
            'workforceMember',
            'assignedTo',
            'updates.createdBy',
            'recalculations' => fn ($q) => $q->with('dailyResults')->orderBy('version_number', 'desc'),
        ]);

        // Latest recalculation with its daily results
        $latestRecalculation = $performanceCase->recalculations->first();
        $performanceCase->setRelation('latestRecalculation', $latestRecalculation);

        // Get available version numbers for comparison selector
        $availableVersions = $performanceCase->recalculations->map(fn ($r) => [
            'id' => $r->id,
            'version_number' => $r->version_number,
            'recalculated_at' => $r->recalculated_at?->toIso8601String(),
            'reason' => $r->reason,
            'survey_volume' => $r->survey_volume,
            'nps_score' => $r->nps_score,
            'csat_score' => $r->csat_score,
        ])->values();

        $canViewDisciplinary = Auth::user()?->hasPermission('cases.view_disciplinary') ?? false;
        $canUpdate = Auth::user()?->hasPermission('cases.update') ?? false;
        $canClose = Auth::user()?->hasPermission('cases.close') ?? false;

        return Inertia::render('PerformanceCases/Show', [
            'performanceCase' => $performanceCase,
            'caseItem' => $performanceCase,
            'latestRecalculation' => $latestRecalculation,
            'availableVersions' => $availableVersions,
            'can_view_disciplinary' => $canViewDisciplinary,
            'canViewDisciplinary' => $canViewDisciplinary,
            'can_update' => $canUpdate,
            'canUpdate' => $canUpdate,
            'can_close' => $canClose,
            'canClose' => $canClose,
        ]);
    }

    public function storeUpdate(Request $request, PerformanceCase $performanceCase): RedirectResponse
    {
        Gate::authorize('update', $performanceCase);

        $validated = $request->validate([
            'resulting_status' => ['required', 'in:open,monitoring,action_plan,improving,resolved,closed'],
            'summary' => ['required', 'string', 'max:500'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'actions' => ['nullable', 'string', 'max:2000'],
            'commitments' => ['nullable', 'string', 'max:2000'],
            'next_review_at' => ['nullable', 'date'],
            'disciplinary_details' => ['nullable', 'string'],
        ]);

        $previousStatus = $performanceCase->status;

        // Capture immutable snapshot of metrics at this exact session
        $snapshot = $this->snapshotService->capture($performanceCase);

        $update = PerformanceCaseUpdate::create([
            'performance_case_id' => $performanceCase->id,
            'created_by_user_id' => Auth::id(),
            'previous_status' => $previousStatus,
            'resulting_status' => $validated['resulting_status'],
            'summary' => $validated['summary'],
            'observations' => $validated['observations'] ?? null,
            'actions' => $validated['actions'] ?? null,
            'commitments' => $validated['commitments'] ?? null,
            'next_review_at' => $validated['next_review_at'] ?? null,
            'metrics_snapshot' => $snapshot,
            'disciplinary_details' => $validated['disciplinary_details'] ?? null,
        ]);

        // Update case status and next review date
        $updateData = [
            'status' => $validated['resulting_status'],
            'next_review_at' => $validated['next_review_at'] ?? $performanceCase->next_review_at,
        ];

        if ($validated['resulting_status'] === 'closed' && ! $performanceCase->closed_at) {
            $updateData['closed_at'] = now()->toDateString();
        } elseif ($validated['resulting_status'] !== 'closed' && $performanceCase->closed_at) {
            $updateData['closed_at'] = null;
        }

        $performanceCase->update($updateData);

        // Audit update record (disciplinary content is NEVER audited)
        $this->auditService->record(
            eventType: 'PERFORMANCE_CASE_UPDATE_LOGGED',
            payload: [
                'case_id' => $performanceCase->id,
                'update_id' => $update->id,
                'previous_status' => $previousStatus,
                'resulting_status' => $validated['resulting_status'],
                'has_disciplinary_notes' => ! empty($validated['disciplinary_details']),
            ],
            auditableType: PerformanceCase::class,
            auditableId: (string) $performanceCase->id,
            userId: Auth::id()
        );

        return redirect()->route('performance-cases.show', $performanceCase->id)
            ->with('success', 'Sesión de seguimiento registrada exitosamente con snapshot de métricas inmutable.');
    }

    public function recalculate(Request $request, PerformanceCase $performanceCase): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $performanceCase);

        $notes = $request->input('notes', 'Recálculo manual solicitado desde el expediente.');
        $newRecalculation = $this->recalculationService->recalculate(
            $performanceCase,
            Auth::user(),
            'manual',
            $notes
        );

        $this->auditService->record(
            eventType: 'CASE_METRICS_RECALCULATED',
            payload: [
                'case_id' => $performanceCase->id,
                'new_version' => $newRecalculation->version_number,
                'reason' => 'manual',
            ],
            auditableType: PerformanceCase::class,
            auditableId: (string) $performanceCase->id,
            userId: Auth::id()
        );

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'version' => $newRecalculation->version_number,
                'recalculation' => $newRecalculation,
            ]);
        }

        return redirect()->route('performance-cases.show', $performanceCase->id)
            ->with('success', "Métricas recalculadas exitosamente. Nueva versión: {$newRecalculation->version_number}.");
    }

    public function compareVersions(Request $request, PerformanceCase $performanceCase): JsonResponse
    {
        Gate::authorize('view', $performanceCase);

        $validated = $request->validate([
            'version_a' => ['required', 'integer'],
            'version_b' => ['required', 'integer'],
        ]);

        try {
            $comparison = $this->recalculationService->compareVersions(
                $performanceCase,
                (int) $validated['version_a'],
                (int) $validated['version_b']
            );

            return response()->json([
                'success' => true,
                'comparison' => $comparison,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function viewDisciplinary(
        Request $request,
        PerformanceCase $performanceCase,
        PerformanceCaseUpdate $update
    ): JsonResponse {
        if ($update->performance_case_id !== $performanceCase->id) {
            abort(404, 'Update does not belong to this case.');
        }

        Gate::authorize('viewDisciplinary', $performanceCase);

        // Record audit event safely: NEVER log sensitive disciplinary text
        $this->auditService->record(
            eventType: 'DISCIPLINARY_DETAIL_ACCESSED',
            payload: [
                'case_id' => $performanceCase->id,
                'case_number' => $performanceCase->case_number,
                'update_id' => $update->id,
            ],
            auditableType: PerformanceCaseUpdate::class,
            auditableId: (string) $update->id,
            userId: Auth::id()
        );

        return response()->json([
            'success' => true,
            'update_id' => $update->id,
            'disciplinary_details' => $update->disciplinary_details,
        ]);
    }
}
