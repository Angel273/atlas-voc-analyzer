<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Ai\Gateway\AiGateway;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function __construct(
        protected AiGateway $aiGateway,
        protected AuditService $auditService
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $conversations = Conversation::where('user_id', $user->id)
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->get();

        $activeConversation = null;
        $activeId = $request->query('conversation_id');

        if ($activeId) {
            $activeConversation = Conversation::where('id', $activeId)
                ->where('user_id', $user->id)
                ->with('messages')
                ->first();
        }

        if (! $activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = Conversation::with('messages')->find($conversations->first()->id);
        }

        $geminiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $activeModelInfo = [
            'provider' => ! empty($geminiKey) ? 'gemini' : 'mock',
            'provider_name' => ! empty($geminiKey) ? 'Google Gemini' : 'Mock AI Provider (Local)',
            'model' => config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest')),
            'is_mock' => empty($geminiKey),
        ];

        return Inertia::render('Assistant/Index', [
            'conversations' => $conversations,
            'active_conversation' => $activeConversation,
            'active_model' => $activeModelInfo,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
        ]);

        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'] ?? 'Nueva Consulta VOC',
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $conversation->load('messages'),
        ]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403, 'No autorizado para eliminar esta conversación.');
        }

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversación eliminada con éxito.',
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        // Ensure heavy analytical queries and large RAW JSON payloads have sufficient execution time
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ini_set('memory_limit', '512M');

        $user = Auth::user();
        $userInput = $validated['content'];

        // Build history from previous messages (sliding window of last 10 messages)
        $previousMessages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse();

        $history = [];
        foreach ($previousMessages as $pm) {
            $history[] = [
                'role' => $pm->sender_type === 'user' ? 'user' : 'assistant',
                'content' => $pm->display_content,
                'ai_content' => $pm->ai_content,
            ];
        }

        try {
            // Run through AiGateway (Pseudonymization -> Provider -> Tool Loop -> Minimization -> Reidentification)
            $result = $this->aiGateway->runAssistant(
                userInput: $userInput,
                conversationId: (string) $conversation->id,
                conversationHistory: $history,
                user: $user
            );

            // Persist User Message (Dual Transcript)
            $userMsg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'user',
                'display_content' => $userInput,
                'ai_content' => $result['sanitized_user_input'],
                'grounding_context' => [],
                'tokens_used' => 0,
            ]);

            // Persist Assistant Message (Dual Transcript & Grounding Citations)
            $assistantMsg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'assistant',
                'display_content' => $result['display_content'],
                'ai_content' => $result['ai_content'],
                'grounding_context' => $result['grounding_context'] ?? [],
                'tokens_used' => $result['tokens_used'] ?? 0,
                'tool_calls' => $result['tool_executions'] ?? [],
                'metadata' => [
                    'ai_run_id' => $result['ai_run_id'],
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'latency_ms' => $result['latency_ms'] ?? 0,
                    'grounding_context' => $result['grounding_context'] ?? [],
                    'tool_executions' => $result['tool_executions'] ?? [],
                    'provider' => $result['provider'] ?? 'gemini',
                    'model' => $result['model'] ?? 'gemini-flash-latest',
                ],
            ]);

            // Audit message sent
            $this->auditService->record(
                eventType: 'AI_MESSAGE_SENT',
                payload: [
                    'conversation_id' => $conversation->id,
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'latency_ms' => $result['latency_ms'] ?? 0,
                    'tools_executed_count' => count($result['tool_executions'] ?? []),
                    'citations_count' => count($result['grounding_context'] ?? []),
                ],
                auditableType: Message::class,
                auditableId: (string) $assistantMsg->id,
                userId: $user->id
            );

            // Update conversation title if it's the first message
            if ($conversation->messages()->count() <= 2 && ($conversation->title === 'New VOC Analysis Chat' || $conversation->title === 'Nueva Consulta VOC')) {
                $conversation->update([
                    'title' => substr($userInput, 0, 40).(strlen($userInput) > 40 ? '...' : ''),
                ]);
            } else {
                $conversation->touch();
            }

            return response()->json([
                'success' => true,
                'user_message' => $userMsg,
                'assistant_message' => $assistantMsg,
                'ai_run_id' => $result['ai_run_id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Assistant Error: '.$e->getMessage(), [
                'conversation_id' => $conversation->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al interactuar con el modelo de IA: '.$e->getMessage(),
            ], 500);
        }
    }
}
