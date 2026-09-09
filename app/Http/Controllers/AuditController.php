<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): Response
    {
        $query = AuditEvent::with('user')->orderBy('id', 'desc');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->query('event_type'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $events = $query->paginate(25)->withQueryString();
        $eventTypes = AuditEvent::distinct()->pluck('event_type')->toArray();
        $users = User::select('id', 'name', 'email')->get();

        // Run fast integrity check on latest 100 events
        $integrity = $this->auditService->verifyChainIntegrity();

        return Inertia::render('Audit/Index', [
            'events' => $events,
            'event_types' => $eventTypes,
            'users' => $users,
            'integrity' => $integrity,
            'filters' => $request->only(['event_type', 'user_id']),
        ]);
    }

    public function verify(): JsonResponse
    {
        $integrity = $this->auditService->verifyChainIntegrity();
        return response()->json($integrity);
    }
}
