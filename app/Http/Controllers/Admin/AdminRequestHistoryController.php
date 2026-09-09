<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\AiToolCall;
use App\Models\DslTool;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRequestHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AiRun::query()->with(['user:id,name,email', 'toolCalls:id,ai_run_id,tool_name,status,duration_ms']);

        // 1. Filter by User
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // 2. Filter by Token Range / Tier
        $tokenTier = $request->input('token_tier');
        if ($tokenTier === 'low') {
            $query->where('tokens_used', '<', 10000);
        } elseif ($tokenTier === 'medium') {
            $query->whereBetween('tokens_used', [10000, 30000]);
        } elseif ($tokenTier === 'high') {
            $query->whereBetween('tokens_used', [30000, 60000]);
        } elseif ($tokenTier === 'critical') {
            $query->where('tokens_used', '>=', 60000);
        }

        if ($request->filled('min_tokens')) {
            $query->where('tokens_used', '>=', (int) $request->input('min_tokens'));
        }

        // 3. Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 4. Filter by Tool invoked
        if ($request->filled('tool_name')) {
            $toolName = $request->input('tool_name');
            $query->whereHas('toolCalls', function ($q) use ($toolName) {
                $q->where('tool_name', $toolName);
            });
        }

        // 5. Search in User Prompt
        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where('user_prompt', 'like', $search);
        }

        // 6. Filter by Date Range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // 7. Sorting (Default: tokens_used DESC)
        $sortBy = $request->input('sort_by', 'tokens_used');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSorts = ['tokens_used', 'latency_ms', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'tokens_used';
        }
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        // Nulls last handling for tokens_used / latency_ms
        if (in_array($sortBy, ['tokens_used', 'latency_ms'], true)) {
            $query->orderByRaw("{$sortBy} IS NULL ASC")->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $requests = $query->paginate(15)->withQueryString();

        // Transform collection to include tool call summary
        $requests->getCollection()->transform(function (AiRun $run) {
            $toolCounts = [];
            foreach ($run->toolCalls as $call) {
                $toolCounts[$call->tool_name] = ($toolCounts[$call->tool_name] ?? 0) + 1;
            }

            return [
                'id' => $run->id,
                'conversation_id' => $run->conversation_id,
                'user' => $run->user,
                'user_prompt' => $run->user_prompt ?: 'Consulta sin registro de texto',
                'provider' => $run->provider,
                'model' => $run->model,
                'status' => $run->status,
                'tokens_used' => $run->tokens_used ?? 0,
                'latency_ms' => $run->latency_ms ?? 0,
                'tool_calls_count' => $run->toolCalls->count(),
                'tool_summary' => $toolCounts,
                'created_at' => $run->created_at?->toIso8601String(),
                'formatted_date' => $run->created_at?->format('d/m/Y H:i:s'),
                'relative_date' => $run->created_at?->diffForHumans(),
            ];
        });

        // Calculate Overview KPIs
        $totalRuns = AiRun::count();
        $totalTokens = (int) AiRun::sum('tokens_used');
        $avgTokens = $totalRuns > 0 ? (int) round(AiRun::whereNotNull('tokens_used')->avg('tokens_used')) : 0;
        $maxTokens = (int) AiRun::max('tokens_used');
        $avgLatencyMs = (int) round(AiRun::whereNotNull('latency_ms')->avg('latency_ms'));
        $runsWithToolsCount = AiRun::has('toolCalls')->count();
        $toolCallRate = $totalRuns > 0 ? round(($runsWithToolsCount / $totalRuns) * 100, 1) : 0;

        $peakRun = AiRun::with('user:id,name,email')
            ->orderByDesc('tokens_used')
            ->first();

        // Available Filter Options
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        $availableTools = DslTool::pluck('name')->toArray();
        if (empty($availableTools)) {
            $availableTools = AiToolCall::distinct()->pluck('tool_name')->toArray();
        }

        return Inertia::render('Admin/Requests/Index', [
            'requests' => $requests,
            'kpis' => [
                'total_tokens' => $totalTokens,
                'avg_tokens' => $avgTokens,
                'max_tokens' => $maxTokens,
                'avg_latency_ms' => $avgLatencyMs,
                'total_runs' => $totalRuns,
                'tool_call_rate' => $toolCallRate,
                'peak_run' => $peakRun ? [
                    'id' => $peakRun->id,
                    'tokens_used' => $peakRun->tokens_used,
                    'user_name' => $peakRun->user?->name ?? 'Usuario',
                    'prompt' => $peakRun->user_prompt,
                ] : null,
            ],
            'filters' => [
                'user_id' => $request->input('user_id', ''),
                'token_tier' => $request->input('token_tier', ''),
                'min_tokens' => $request->input('min_tokens', ''),
                'status' => $request->input('status', ''),
                'tool_name' => $request->input('tool_name', ''),
                'search' => $request->input('search', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
            'users' => $users,
            'available_tools' => $availableTools,
        ]);
    }

    public function show(AiRun $aiRun): JsonResponse
    {
        $aiRun->load(['user', 'toolCalls']);

        // Find assistant message for this run to retrieve citations and final delivered content
        $assistantMsg = Message::where('sender_type', 'assistant')
            ->where('metadata->ai_run_id', $aiRun->id)
            ->first();

        // Diagnose potential tool inefficiencies
        $diagnostics = $this->analyzeRunEfficiency($aiRun);

        return response()->json([
            'success' => true,
            'run' => [
                'id' => $aiRun->id,
                'conversation_id' => $aiRun->conversation_id,
                'user' => $aiRun->user,
                'user_prompt' => $aiRun->user_prompt,
                'provider' => $aiRun->provider,
                'model' => $aiRun->model,
                'prompt_version' => $aiRun->prompt_version,
                'tool_schema_version' => $aiRun->tool_schema_version,
                'status' => $aiRun->status,
                'tokens_used' => $aiRun->tokens_used ?? 0,
                'latency_ms' => $aiRun->latency_ms ?? 0,
                'started_at' => $aiRun->started_at?->toIso8601String(),
                'completed_at' => $aiRun->completed_at?->toIso8601String(),
                'created_at' => $aiRun->created_at?->toIso8601String(),
                'tool_calls' => $aiRun->toolCalls->map(function (AiToolCall $tc) {
                    $resultDecoded = json_decode($tc->result_summary ?? '', true);

                    return [
                        'id' => $tc->id,
                        'tool_name' => $tc->tool_name,
                        'tool_version' => $tc->tool_version,
                        'arguments' => $tc->arguments_sanitized,
                        'query_dsl' => $tc->query_dsl,
                        'result' => $resultDecoded ?: $tc->result_summary,
                        'status' => $tc->status,
                        'duration_ms' => $tc->duration_ms,
                        'citations' => $tc->citations ?? [],
                        'requested_at' => $tc->requested_at?->toIso8601String(),
                        'executed_at' => $tc->executed_at?->toIso8601String(),
                    ];
                }),
                'assistant_response' => $assistantMsg?->display_content,
                'grounding_citations' => $assistantMsg?->grounding_context ?? [],
                'diagnostics' => $diagnostics,
            ],
        ]);
    }

    protected function analyzeRunEfficiency(AiRun $run): array
    {
        $toolCalls = $run->toolCalls;
        $toolCount = $toolCalls->count();
        $tokens = $run->tokens_used ?? 0;
        $warnings = [];
        $recommendations = [];

        if ($tokens > 50000) {
            $warnings[] = "Consumo crítico de tokens ({$tokens} tokens). La solicitud excedió el percentil 95 de gasto.";
        }

        if ($toolCount >= 5) {
            $warnings[] = "Bucle ReAct extendido ({$toolCount} herramientas ejecutadas en la misma petición).";
        }

        // Check for repeated query_data or calculate_metric calls
        $toolFrequencies = [];
        foreach ($toolCalls as $call) {
            $toolFrequencies[$call->tool_name] = ($toolFrequencies[$call->tool_name] ?? 0) + 1;
        }

        if (($toolFrequencies['query_data'] ?? 0) >= 4) {
            $recommendations[] = "La IA realizó {$toolFrequencies['query_data']} consultas separadas a 'query_data'. Se sugiere crear una Tool DSL consolidada que agrupe o pre-filtre estos datos en una única llamada.";
        }

        if (($toolFrequencies['calculate_metric'] ?? 0) >= 3) {
            $recommendations[] = "La IA ejecutó {$toolFrequencies['calculate_metric']} cálculos métricos independientes (ej: NPS, CSAT, Professionalism). Se recomienda utilizar una Tool DSL multi-métrica (Scorecard) para calcularlas conjuntamente.";
        }

        return [
            'is_high_consumption' => $tokens > 30000 || $toolCount >= 5,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'tool_frequency' => $toolFrequencies,
        ];
    }
}
