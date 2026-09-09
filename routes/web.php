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

    // 1. Dashboard Domain
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
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
});
