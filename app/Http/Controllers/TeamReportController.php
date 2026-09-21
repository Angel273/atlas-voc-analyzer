<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTeamReportJob;
use App\Models\KpiGoal;
use App\Models\Team;
use App\Models\TeamReport;
use App\Services\Reports\TeamReportDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class TeamReportController extends Controller
{
    public function __construct(
        protected TeamReportDataService $dataService
    ) {}

    /**
     * Display a listing of generated reports and report generation studio.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TeamReport::class);

        $query = TeamReport::with(['team.supervisor', 'createdByUser'])
            ->latest();

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('period_from', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('period_to', '<=', $request->input('to_date'));
        }

        $reports = $query->paginate(15)->withQueryString();

        $teams = Team::with('supervisor')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Reports/Teams/Index', [
            'reports' => $reports,
            'teams' => $teams,
            'filters' => $request->only(['team_id', 'status', 'from_date', 'to_date']),
            'kpi_goals' => KpiGoal::getGoalsMap(),
        ]);
    }

    /**
     * Preview team report data live before queuing generation.
     */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('create', TeamReport::class);

        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'cutoff_date' => 'nullable|date',
            'include_verbatims' => 'nullable|boolean',
            'include_open_cases' => 'nullable|boolean',
            'compare_previous_period' => 'nullable|boolean',
            'goals' => 'nullable|array',
            'goals.nps.target_value' => 'nullable|numeric',
            'goals.nps.warning_threshold' => 'nullable|numeric',
            'goals.csat.target_value' => 'nullable|numeric',
            'goals.csat.warning_threshold' => 'nullable|numeric',
            'goals.professionalism.target_value' => 'nullable|numeric',
            'goals.professionalism.warning_threshold' => 'nullable|numeric',
        ]);

        $team = Team::with('supervisor')->findOrFail($validated['team_id']);

        $options = [
            'cutoff_date' => $validated['cutoff_date'] ?? now()->toDateString(),
            'include_verbatims' => (bool) ($validated['include_verbatims'] ?? false),
            'include_open_cases' => (bool) ($validated['include_open_cases'] ?? true),
            'compare_previous_period' => (bool) ($validated['compare_previous_period'] ?? false),
            'goals' => $validated['goals'] ?? null,
        ];

        $data = $this->dataService->buildReportData(
            $team,
            $validated['period_from'],
            $validated['period_to'],
            $options
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Queue generation of one or multiple team reports.
     */
    public function generate(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', TeamReport::class);

        $validated = $request->validate([
            'team_ids' => 'required|array|min:1',
            'team_ids.*' => 'exists:teams,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'cutoff_date' => 'nullable|date',
            'include_verbatims' => 'nullable|boolean',
            'include_open_cases' => 'nullable|boolean',
            'compare_previous_period' => 'nullable|boolean',
            'model' => 'nullable|string|max:100',
            'goals' => 'nullable|array',
            'goals.nps.target_value' => 'nullable|numeric',
            'goals.nps.warning_threshold' => 'nullable|numeric',
            'goals.csat.target_value' => 'nullable|numeric',
            'goals.csat.warning_threshold' => 'nullable|numeric',
            'goals.professionalism.target_value' => 'nullable|numeric',
            'goals.professionalism.warning_threshold' => 'nullable|numeric',
        ]);

        $periodFrom = $validated['period_from'];
        $periodTo = $validated['period_to'];
        $cutoffDate = $validated['cutoff_date'] ?? now()->toDateString();
        $model = $validated['model'] ?? config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest'));

        $parameters = [
            'cutoff_date' => $cutoffDate,
            'include_verbatims' => (bool) ($validated['include_verbatims'] ?? false),
            'include_open_cases' => (bool) ($validated['include_open_cases'] ?? true),
            'compare_previous_period' => (bool) ($validated['compare_previous_period'] ?? false),
            'goals' => $validated['goals'] ?? null,
        ];

        $createdReports = [];

        foreach ($validated['team_ids'] as $teamId) {
            $team = Team::findOrFail($teamId);

            // Find previous completed report for this team to link history
            $previousReport = TeamReport::where('team_id', $team->id)
                ->where('status', 'completed')
                ->latest()
                ->first();

            $report = TeamReport::create([
                'team_id' => $team->id,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'cutoff_date' => $cutoffDate,
                'data_version' => 'pending',
                'prompt_version' => 'team_report_v1',
                'model' => $model,
                'parameters' => $parameters,
                'status' => 'pending',
                'created_by_user_id' => $request->user()?->id,
                'previous_report_id' => $previousReport?->id,
            ]);

            GenerateTeamReportJob::dispatch($report);
            $createdReports[] = $report;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => count($createdReports).' reporte(s) encolado(s) para generación.',
                'reports' => $createdReports,
            ], 202);
        }

        return redirect()->route('reports.teams.index')
            ->with('success', count($createdReports).' reporte(s) de equipo encolados para generación con IA.');
    }

    /**
     * Display a specific report preview and details.
     */
    public function show(TeamReport $teamReport): Response
    {
        Gate::authorize('view', $teamReport);

        $teamReport->load(['team.supervisor', 'createdByUser', 'previousReport']);

        return Inertia::render('Reports/Teams/Show', [
            'report' => $teamReport,
        ]);
    }

    /**
     * Retry generation of a failed or pending report.
     */
    public function retry(TeamReport $teamReport): RedirectResponse
    {
        Gate::authorize('create', TeamReport::class);

        $teamReport->update([
            'status' => 'pending',
            'progress' => 0,
            'stage' => 'En cola de espera...',
            'error_message' => null,
        ]);

        GenerateTeamReportJob::dispatch($teamReport);

        return back()->with('success', 'Generación reintentada correctamente.');
    }

    /**
     * Download a single generated report PDF.
     */
    public function download(TeamReport $teamReport): StreamedResponse|RedirectResponse
    {
        Gate::authorize('download', $teamReport);

        if ($teamReport->status !== 'completed' || empty($teamReport->file_path)) {
            return back()->with('error', 'El reporte aún no se encuentra completado.');
        }

        if (! Storage::disk('local')->exists($teamReport->file_path)) {
            return back()->with('error', 'El archivo PDF del reporte no fue encontrado en el almacenamiento.');
        }

        $teamCode = $teamReport->team->code ?? 'TEAM';
        $fromStr = $teamReport->period_from->format('Ymd');
        $toStr = $teamReport->period_to->format('Ymd');
        $filename = "Reporte_Equipo_{$teamCode}_{$fromStr}_{$toStr}.pdf";

        return Storage::disk('local')->download($teamReport->file_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Download multiple reports bundled in a single ZIP archive.
     */
    public function downloadZip(Request $request): BinaryFileResponse|RedirectResponse|JsonResponse
    {
        Gate::authorize('download', TeamReport::class);

        $validated = $request->validate([
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'exists:team_reports,id',
        ]);

        $reports = TeamReport::with('team')
            ->whereIn('id', $validated['report_ids'])
            ->where('status', 'completed')
            ->whereNotNull('file_path')
            ->get();

        if ($reports->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'No hay reportes completados disponibles para descargar en ZIP.'], 404);
            }

            return back()->with('error', 'No hay reportes completados disponibles para descargar.');
        }

        $tempDir = storage_path('app/temp_zips');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFileName = 'Reportes_Equipos_VOC_'.now()->format('Ymd_His').'.zip';
        $zipPath = $tempDir.DIRECTORY_SEPARATOR.$zipFileName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No fue posible crear el archivo comprimido ZIP.');
        }

        $addedCount = 0;
        foreach ($reports as $report) {
            if (Storage::disk('local')->exists($report->file_path)) {
                $content = Storage::disk('local')->get($report->file_path);
                $teamCode = $report->team->code ?? 'TEAM';
                $entryName = "Reporte_Equipo_{$teamCode}_{$report->period_from->format('Ymd')}_{$report->period_to->format('Ymd')}_#{$report->id}.pdf";
                $zip->addFromString($entryName, $content);
                $addedCount++;
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($zipPath);

            return back()->with('error', 'No se encontraron archivos PDF físicos para los reportes seleccionados.');
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }
}
