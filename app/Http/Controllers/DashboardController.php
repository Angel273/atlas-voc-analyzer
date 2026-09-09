<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Survey;
use App\Services\Metrics\Dsl\QueryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected QueryEngine $queryEngine
    ) {}

    public function index(Request $request): Response
    {
        $dashboardId = $request->query('dashboard_id');

        if ($dashboardId) {
            $dashboard = Dashboard::with('widgets')->find($dashboardId);
        } else {
            $dashboard = Dashboard::where('is_default', true)->with('widgets')->first()
                ?: Dashboard::with('widgets')->first();
        }

        // If still no dashboard exists, create one
        if (!$dashboard) {
            $dashboard = Dashboard::create([
                'name' => 'VOC Master Overview',
                'is_default' => true,
                'description' => 'Default master analytical overview',
            ]);
        }

        $allDashboards = Dashboard::select('id', 'name', 'is_default')->get();

        // Extract available filter values
        $supervisors = Survey::distinct()->whereNotNull('supervisor')->pluck('supervisor')->toArray();
        $waves = Survey::distinct()->whereNotNull('wave')->pluck('wave')->toArray();
        $categories = Category::where('active', true)->pluck('name')->toArray();

        $totalVerbatims = Survey::whereNotNull('verbatim')->where('verbatim', '!=', '')->count();
        $completedVerbatims = \App\Models\VerbatimAnalysis::where('status', 'completed')->count();
        $catPercentage = $totalVerbatims > 0 ? round(($completedVerbatims / $totalVerbatims) * 100, 1) : 100.0;

        return Inertia::render('Dashboard/Index', [
            'dashboard' => $dashboard,
            'dashboards' => $allDashboards,
            'filter_options' => [
                'supervisors' => $supervisors,
                'waves' => $waves,
                'categories' => $categories,
            ],
            'categorization_status' => [
                'total' => $totalVerbatims,
                'completed' => $completedVerbatims,
                'percentage' => $catPercentage,
            ],
        ]);
    }

    /**
     * POST endpoint for widgets to query aggregated data via Query DSL.
     */
    public function queryWidget(Request $request): JsonResponse
    {
        $dsl = $request->validate([
            'metric' => ['nullable', 'string'],
            'metrics' => ['nullable', 'array'],
            'aggregation' => ['nullable', 'string'],
            'group_by' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'date_range' => ['nullable', 'array'],
            'limit' => ['nullable', 'integer'],
            'sort_by' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->queryEngine->execute($dsl, Auth::user());
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Persist updated widget positions and sizes (12-column grid).
     */
    public function updateLayout(Request $request, Dashboard $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*.id' => ['required', 'integer'],
            'widgets.*.x' => ['required', 'integer'],
            'widgets.*.y' => ['required', 'integer'],
            'widgets.*.w' => ['required', 'integer'],
            'widgets.*.h' => ['required', 'integer'],
            'widgets.*.sort_order' => ['nullable', 'integer'],
        ]);

        foreach ($validated['widgets'] as $item) {
            DashboardWidget::where('id', $item['id'])
                ->where('dashboard_id', $dashboard->id)
                ->update([
                    'x' => $item['x'],
                    'y' => $item['y'],
                    'w' => $item['w'],
                    'h' => $item['h'],
                    'sort_order' => $item['sort_order'] ?? 0,
                ]);
        }

        return response()->json(['success' => true]);
    }

    public function storeWidget(Request $request, Dashboard $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string'],
            'w' => ['required', 'integer', 'min:1', 'max:12'],
            'h' => ['required', 'integer', 'min:1', 'max:12'],
            'configuration' => ['required', 'array'],
        ]);

        $widget = $dashboard->widgets()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'x' => 0,
            'y' => 999, // place at bottom
            'w' => $validated['w'],
            'h' => $validated['h'],
            'configuration' => $validated['configuration'],
        ]);

        return response()->json(['success' => true, 'widget' => $widget]);
    }

    public function updateWidget(Request $request, Dashboard $dashboard, DashboardWidget $widget): JsonResponse
    {
        if ($widget->dashboard_id !== $dashboard->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'type' => ['sometimes', 'required', 'string'],
            'w' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'h' => ['sometimes', 'required', 'integer', 'min:1', 'max:24'],
            'configuration' => ['sometimes', 'required', 'array'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $widget->update($validated);

        return response()->json(['success' => true, 'widget' => $widget->fresh()]);
    }

    public function destroyWidget(Dashboard $dashboard, DashboardWidget $widget): JsonResponse
    {
        if ($widget->dashboard_id !== $dashboard->id) {
            abort(404);
        }

        $widget->delete();
        return response()->json(['success' => true]);
    }
}
