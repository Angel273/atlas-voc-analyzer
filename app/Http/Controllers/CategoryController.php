<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(): Response
    {
        $categories = Category::withCount('verbatimAnalyses')->orderBy('name', 'asc')->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
            'examples' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);

        $category = Category::create($validated);

        $this->auditService->record(
            eventType: 'CATEGORY_CHANGE',
            payload: [
                'action' => 'created',
                'category_id' => $category->id,
                'name' => $category->name,
            ],
            auditableType: Category::class,
            auditableId: (string) $category->id,
            userId: Auth::id()
        );

        return response()->json(['success' => true, 'category' => $category]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', "unique:categories,name,{$category->id}"],
            'description' => ['nullable', 'string'],
            'examples' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);

        $category->update($validated);

        $this->auditService->record(
            eventType: 'CATEGORY_CHANGE',
            payload: [
                'action' => 'updated',
                'category_id' => $category->id,
                'name' => $category->name,
            ],
            auditableType: Category::class,
            auditableId: (string) $category->id,
            userId: Auth::id()
        );

        return response()->json(['success' => true, 'category' => $category]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->auditService->record(
            eventType: 'CATEGORY_CHANGE',
            payload: [
                'action' => 'deleted',
                'category_id' => $category->id,
                'name' => $category->name,
            ],
            auditableType: Category::class,
            auditableId: (string) $category->id,
            userId: Auth::id()
        );

        $category->delete();

        return response()->json(['success' => true]);
    }
}
