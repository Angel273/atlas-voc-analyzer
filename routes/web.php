<?php

use App\Http\Controllers\Admin\AdminDslAssistantController;
use App\Http\Controllers\Admin\AdminDslToolsController;
use App\Http\Controllers\Admin\AdminRequestHistoryController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\KpiGoalController;
use App\Http\Controllers\PerformanceCaseController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamReportController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Healthcheck endpoint (Section 51)
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'application' => 'ATLAS VOC Analysis',
            'database' => 'connected',
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'status' => 'error',
            'database' => 'disconnected',
        ], 503);
    }
});

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // Operational KPI Goals (Metas de NPS, CSAT y Profesionalismo)
    Route::get('/kpi-goals', [KpiGoalController::class, 'index'])->name('kpi-goals.index');
    Route::put('/kpi-goals', [KpiGoalController::class, 'update'])->name('kpi-goals.update');
    Route::post('/kpi-goals', [KpiGoalController::class, 'update'])->name('kpi-goals.store');

    // 1. Dashboard Domain
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::put('/dashboard/{dashboard}', [DashboardController::class, 'update'])->name('dashboard.update');
        Route::post('/dashboard/query', [DashboardController::class, 'queryWidget'])->name('dashboard.query');
        Route::put('/dashboard/{dashboard}/layout', [DashboardController::class, 'updateLayout'])->name('dashboard.layout');
        Route::post('/dashboard/{dashboard}/widgets', [DashboardController::class, 'storeWidget'])->name('dashboard.widgets.store');
        Route::put('/dashboard/{dashboard}/widgets/{widget}', [DashboardController::class, 'updateWidget'])->name('dashboard.widgets.update');
        Route::delete('/dashboard/{dashboard}/widgets/{widget}', [DashboardController::class, 'destroyWidget'])->name('dashboard.widgets.destroy');
    });

    // 2. Data Import Domain
    Route::middleware('permission:data.view')->group(function () {
        Route::get('/data', [ImportController::class, 'index'])->name('data.index');
        Route::post('/data/upload', [ImportController::class, 'upload'])->middleware('permission:data.import')->name('data.upload');
        Route::post('/data/preview', [ImportController::class, 'preview'])->middleware('permission:data.import')->name('data.preview');
        Route::post('/data/validate', [ImportController::class, 'validateMapping'])->middleware('permission:data.import')->name('data.validate');
        Route::post('/data/execute', [ImportController::class, 'execute'])->middleware('permission:data.import')->name('data.execute');
        Route::post('/data/templates', [ImportController::class, 'saveTemplate'])->middleware('permission:data.import')->name('data.templates.store');
        Route::delete('/data/imports/{import}', [ImportController::class, 'destroy'])->middleware('permission:data.delete')->name('data.imports.destroy');
        Route::post('/data/clear-all', [ImportController::class, 'clearAll'])->middleware('permission:data.delete')->name('data.clear');
        Route::get('/data/categorization-status', [ImportController::class, 'categorizationStatus'])->name('data.categorization.status');
        Route::post('/data/process-categorization', [ImportController::class, 'triggerCategorization'])->middleware('permission:data.import')->name('data.categorization.trigger');
    });

    // 3. AI Assistant Domain
    Route::middleware('permission:ai.chat')->group(function () {
        Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
        Route::post('/assistant/conversations', [AssistantController::class, 'store'])->name('assistant.conversations.store');
        Route::delete('/assistant/conversations/{conversation}', [AssistantController::class, 'destroy'])->name('assistant.conversations.destroy');
        Route::post('/assistant/conversations/{conversation}/messages', [AssistantController::class, 'sendMessage'])->name('assistant.messages.store');
    });

    // 4. Forecast Domain
    Route::middleware('permission:forecast.view')->group(function () {
        Route::get('/forecast', [ForecastController::class, 'index'])->name('forecast.index');
        Route::post('/forecast/calculate', [ForecastController::class, 'calculate'])->middleware('permission:forecast.run')->name('forecast.calculate');
        Route::post('/forecast/drivers', [ForecastController::class, 'drivers'])->middleware('permission:forecast.run')->name('forecast.drivers');
        Route::post('/forecast/{forecast}/chat', [ForecastController::class, 'chat'])->name('forecast.chat');
    });

    // 5. Audit Domain
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::post('/audit/verify', [AuditController::class, 'verify'])->name('audit.verify');
    });

    // 6. Category Management Domain
    Route::middleware('permission:categories.manage')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // 7. Administration Domain
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/admin', [AdministrationController::class, 'index'])->name('admin.index');
        Route::post('/admin/users', [AdministrationController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/admin/users/{user}/roles', [AdministrationController::class, 'updateUserRoles'])->name('admin.users.roles');

        // AI Request History & Token Analytics (Vista 1)
        Route::get('/admin/requests', [AdminRequestHistoryController::class, 'index'])->name('admin.requests.index');
        Route::get('/admin/requests/{aiRun}', [AdminRequestHistoryController::class, 'show'])->name('admin.requests.show');

        // DSL Tools Catalog & Builder (Vista 2)
        Route::get('/admin/tools', [AdminDslToolsController::class, 'index'])->name('admin.tools.index');
        Route::post('/admin/tools', [AdminDslToolsController::class, 'store'])->name('admin.tools.store');
        Route::put('/admin/tools/{tool}', [AdminDslToolsController::class, 'update'])->name('admin.tools.update');
        Route::delete('/admin/tools/{tool}', [AdminDslToolsController::class, 'destroy'])->name('admin.tools.destroy');
        Route::post('/admin/tools/{tool}/toggle', [AdminDslToolsController::class, 'toggle'])->name('admin.tools.toggle');
        Route::post('/admin/tools/test', [AdminDslToolsController::class, 'test'])->name('admin.tools.test');

        // DSL Assistant Copilot (Chat Desplegable)
        Route::post('/admin/dsl-assistant/chat', [AdminDslAssistantController::class, 'chat'])->name('admin.dsl-assistant.chat');
        Route::post('/admin/dsl-assistant/execute', [AdminDslAssistantController::class, 'execute'])->name('admin.dsl-assistant.execute');
    });

    // 8. Performance Cases Tracking Domain (Seguimiento)
    Route::middleware('permission:cases.view')->group(function () {
        Route::get('/performance-cases', [PerformanceCaseController::class, 'index'])->name('performance-cases.index');
        Route::get('/performance-cases/create', [PerformanceCaseController::class, 'create'])->middleware('permission:cases.create')->name('performance-cases.create');
        Route::post('/performance-cases', [PerformanceCaseController::class, 'store'])->middleware('permission:cases.create')->name('performance-cases.store');
        Route::get('/performance-cases/{performanceCase}', [PerformanceCaseController::class, 'show'])->name('performance-cases.show');
        Route::post('/performance-cases/{performanceCase}/updates', [PerformanceCaseController::class, 'storeUpdate'])->middleware('permission:cases.update')->name('performance-cases.updates.store');
        Route::post('/performance-cases/{performanceCase}/recalculate', [PerformanceCaseController::class, 'recalculate'])->middleware('permission:cases.update')->name('performance-cases.recalculate');
        Route::get('/performance-cases/{performanceCase}/versions', [PerformanceCaseController::class, 'compareVersions'])->name('performance-cases.versions');
        Route::get('/performance-cases/{performanceCase}/updates/{update}/disciplinary', [PerformanceCaseController::class, 'viewDisciplinary'])->middleware('permission:cases.view_disciplinary')->name('performance-cases.disciplinary');
    });

    // 9. Supervisor Team PDF Reports Domain (Reportes)
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/teams', [TeamReportController::class, 'index'])->name('reports.teams.index');
        Route::post('/reports/teams/preview', [TeamReportController::class, 'preview'])->middleware('permission:reports.generate')->name('reports.teams.preview');
        Route::post('/reports/teams/generate', [TeamReportController::class, 'generate'])->middleware('permission:reports.generate')->name('reports.teams.generate');
        Route::post('/reports/teams/download-zip', [TeamReportController::class, 'downloadZip'])->middleware('permission:reports.download')->name('reports.teams.download-zip');
        Route::get('/reports/teams/{teamReport}', [TeamReportController::class, 'show'])->name('reports.teams.show');
        Route::post('/reports/teams/{teamReport}/retry', [TeamReportController::class, 'retry'])->middleware('permission:reports.generate')->name('reports.teams.retry');
        Route::get('/reports/teams/{teamReport}/download', [TeamReportController::class, 'download'])->middleware('permission:reports.download')->name('reports.teams.download');
    });

    // 10. Team Management Domain (Equipos)
    Route::middleware('permission:teams.manage')->group(function () {
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('/teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.add');
        Route::delete('/teams/{team}/members/{member}', [TeamController::class, 'removeMember'])->name('teams.members.remove');
        Route::post('/teams/sync-backfill', [TeamController::class, 'syncBackfill'])->name('teams.sync-backfill');
    });
});
