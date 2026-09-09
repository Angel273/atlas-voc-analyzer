<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\Dsl\DslAssistantService;
use App\Services\Metrics\Dsl\QueryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminDslAssistantController extends Controller
{
    public function __construct(
        protected DslAssistantService $dslAssistantService,
        protected QueryEngine $queryEngine
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array'],
        ]);

        $result = $this->dslAssistantService->generateDslProposal(
            userMessage: $validated['message'],
            conversationHistory: $validated['history'] ?? []
        );

        return response()->json([
            'success' => true,
            'content' => $result['content'],
            'dsl' => $result['dsl'],
            'dsl_valid' => $result['dsl_valid'],
            'validation_error' => $result['validation_error'],
            'suggested_tool' => $result['suggested_tool'],
            'tokens_used' => $result['tokens_used'],
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl' => ['required', 'array'],
        ]);

        $start = hrtime(true);

        try {
            $dsl = $validated['dsl'];
            // Cap limit to 50 for quick interactive testing
            if (isset($dsl['limit'])) {
                $dsl['limit'] = min(50, (int) $dsl['limit']);
            } else {
                $dsl['limit'] = 20;
            }

            $result = $this->queryEngine->execute($dsl, Auth::user());
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);

            return response()->json([
                'success' => true,
                'count' => $result['count'],
                'data' => $result['data'],
                'duration_ms' => $durationMs,
                'dsl' => $result['dsl'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar Query DSL: '.$e->getMessage(),
                'duration_ms' => (int) round((hrtime(true) - $start) / 1e6),
            ], 422);
        }
    }
}
