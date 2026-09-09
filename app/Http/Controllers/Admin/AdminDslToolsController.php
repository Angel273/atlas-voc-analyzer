<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiToolCall;
use App\Models\DslTool;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDslToolsController extends Controller
{
    public function __construct(
        protected ToolRegistry $toolRegistry,
        protected AuditService $auditService
    ) {}

    public function index(): Response
    {
        $tools = DslTool::with('creator:id,name,email')
            ->orderBy('is_builtin', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        // Calculate usage analytics per tool from ai_tool_calls
        $toolStats = AiToolCall::selectRaw('tool_name, COUNT(*) as invocations, AVG(duration_ms) as avg_duration, SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as errors')
            ->groupBy('tool_name')
            ->get()
            ->keyBy('tool_name');

        $formattedTools = $tools->map(function (DslTool $t) use ($toolStats) {
            $stats = $toolStats->get($t->name);

            return [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
                'description' => $t->description,
                'is_builtin' => $t->is_builtin,
                'is_active' => $t->is_active,
                'execution_mode' => $t->execution_mode,
                'parameters_schema' => $t->parameters_schema,
                'dsl_template' => $t->dsl_template,
                'sort_order' => $t->sort_order,
                'created_by' => $t->creator?->name,
                'created_at' => $t->created_at?->format('d/m/Y H:i'),
                'stats' => [
                    'invocations' => $stats ? (int) $stats->invocations : 0,
                    'avg_duration_ms' => $stats ? (int) round($stats->avg_duration) : 0,
                    'errors' => $stats ? (int) $stats->errors : 0,
                ],
            ];
        });

        return Inertia::render('Admin/Tools/Index', [
            'tools' => $formattedTools,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:dsl_tools,name'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'parameters_schema' => ['required', 'array'],
            'dsl_template' => ['nullable', 'array'],
            'execution_mode' => ['required', 'string', Rule::in(['dsl_query', 'multi_metric', 'system'])],
            'is_active' => ['boolean'],
        ]);

        $tool = DslTool::create([
            'name' => $validated['name'],
            'label' => $validated['label'],
            'description' => $validated['description'],
            'parameters_schema' => $validated['parameters_schema'],
            'dsl_template' => $validated['dsl_template'] ?? null,
            'execution_mode' => $validated['execution_mode'],
            'is_builtin' => false,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => Auth::id(),
            'sort_order' => DslTool::max('sort_order') + 1,
        ]);

        $this->auditService->record(
            eventType: 'DSL_TOOL_CREATED',
            payload: [
                'tool_id' => $tool->id,
                'tool_name' => $tool->name,
                'label' => $tool->label,
            ],
            auditableType: DslTool::class,
            auditableId: (string) $tool->id,
            userId: Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => "Tool DSL '{$tool->label}' creada con éxito.",
            'tool' => $tool,
        ]);
    }

    public function update(Request $request, DslTool $tool): JsonResponse
    {
        $rules = [
            'label' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'parameters_schema' => ['required', 'array'],
            'dsl_template' => ['nullable', 'array'],
            'execution_mode' => ['required', 'string', Rule::in(['dsl_query', 'multi_metric', 'system'])],
            'is_active' => ['boolean'],
        ];

        if (! $tool->is_builtin) {
            $rules['name'] = ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('dsl_tools', 'name')->ignore($tool->id)];
        }

        $validated = $request->validate($rules);

        $tool->update($validated);

        $this->auditService->record(
            eventType: 'DSL_TOOL_UPDATED',
            payload: [
                'tool_id' => $tool->id,
                'tool_name' => $tool->name,
                'label' => $tool->label,
            ],
            auditableType: DslTool::class,
            auditableId: (string) $tool->id,
            userId: Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => "Tool DSL '{$tool->label}' actualizada correctamente.",
            'tool' => $tool,
        ]);
    }

    public function destroy(DslTool $tool): JsonResponse
    {
        if ($tool->is_builtin) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible eliminar una herramienta nativa del sistema. Puede desactivarla para que la IA no la utilice.',
            ], 422);
        }

        $toolName = $tool->name;
        $toolLabel = $tool->label;
        $toolId = $tool->id;

        $tool->delete();

        $this->auditService->record(
            eventType: 'DSL_TOOL_DELETED',
            payload: [
                'tool_id' => $toolId,
                'tool_name' => $toolName,
                'label' => $toolLabel,
            ],
            auditableType: DslTool::class,
            auditableId: (string) $toolId,
            userId: Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => "Tool DSL '{$toolLabel}' eliminada del sistema.",
        ]);
    }

    public function toggle(DslTool $tool): JsonResponse
    {
        $tool->update(['is_active' => ! $tool->is_active]);

        $this->auditService->record(
            eventType: 'DSL_TOOL_STATUS_TOGGLED',
            payload: [
                'tool_id' => $tool->id,
                'tool_name' => $tool->name,
                'is_active' => $tool->is_active,
            ],
            auditableType: DslTool::class,
            auditableId: (string) $tool->id,
            userId: Auth::id()
        );

        return response()->json([
            'success' => true,
            'is_active' => $tool->is_active,
            'message' => "Tool '{$tool->label}' ".($tool->is_active ? 'activada' : 'desactivada').' para el agente IA.',
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool_name' => ['required', 'string'],
            'arguments' => ['nullable', 'array'],
        ]);

        $toolName = $validated['tool_name'];
        $arguments = $validated['arguments'] ?? [];

        $start = hrtime(true);

        try {
            $execution = $this->toolRegistry->executeToolWithGrounding(
                toolName: $toolName,
                arguments: $arguments,
                scopeId: 'sandbox_test_scope',
                user: Auth::user()
            );

            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $jsonOutput = json_encode($execution['result']);
            $outputBytes = strlen($jsonOutput);
            // Approximate token calculation (~4 chars per token)
            $estimatedTokens = (int) ceil($outputBytes / 4);

            return response()->json([
                'success' => true,
                'tool_name' => $toolName,
                'status' => $execution['execution_log']['status'],
                'duration_ms' => $durationMs,
                'output_size_bytes' => $outputBytes,
                'estimated_tokens' => $estimatedTokens,
                'result' => $execution['result'],
                'citations' => $execution['citations'],
                'arguments_used' => $arguments,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar herramienta de prueba: '.$e->getMessage(),
                'duration_ms' => (int) round((hrtime(true) - $start) / 1e6),
            ], 500);
        }
    }
}
