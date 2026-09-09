<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(): Response
    {
        $users = User::with(['roles', 'directPermissions'])->get();
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return Inertia::render('Admin/Index', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role_ids' => ['nullable', 'array'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (!empty($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        }

        $this->auditService->record(
            eventType: 'USER_CREATED',
            payload: ['user_id' => $user->id, 'email' => $user->email],
            auditableType: User::class,
            auditableId: (string) $user->id,
            userId: Auth::id()
        );

        return response()->json(['success' => true, 'user' => $user->load('roles')]);
    }

    public function updateUserRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
        ]);

        $user->roles()->sync($validated['role_ids']);

        $this->auditService->record(
            eventType: 'PERMISSION_CHANGE',
            payload: [
                'target_user_id' => $user->id,
                'assigned_roles' => $validated['role_ids'],
            ],
            auditableType: User::class,
            auditableId: (string) $user->id,
            userId: Auth::id()
        );

        return response()->json(['success' => true, 'user' => $user->load('roles')]);
    }
}
