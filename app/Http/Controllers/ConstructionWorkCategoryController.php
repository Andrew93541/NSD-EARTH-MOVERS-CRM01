<?php

namespace App\Http\Controllers;

use App\Models\ConstructionWorkCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConstructionWorkCategoryController extends Controller
{
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! in_array($roleSlug, ['owner', 'admin', 'project_manager']))) {
            abort(403, 'Unauthorized. Construction work category configuration requires administrator or project manager privileges.');
        }
    }

    public function index(Request $request): View|JsonResponse
    {
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $categories = ConstructionWorkCategory::where('company_id', $companyId)
            ->whereNull('parent_id')
            ->with(['subcategories'])
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json(['categories' => $categories]);
        }

        return view('construction-categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('construction_work_categories', 'code')->where('company_id', $companyId),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('construction_work_categories', 'id')->where('company_id', $companyId),
            ],
            'unit_of_measure' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['company_id'] = $companyId;
        $category = ConstructionWorkCategory::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Construction work category created successfully.',
                'category' => $category,
            ], 201);
        }

        return back()->with('success', 'Construction work category created successfully.');
    }

    public function update(Request $request, ConstructionWorkCategory $category): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($category->company_id !== $companyId) {
            abort(403, 'Unauthorized company access.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('construction_work_categories', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($category->id),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('construction_work_categories', 'id')->where('company_id', $companyId),
            ],
            'unit_of_measure' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'category' => $category,
            ]);
        }

        return back()->with('success', 'Construction work category updated successfully.');
    }

    public function destroy(Request $request, ConstructionWorkCategory $category): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();
        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($category->company_id !== $companyId) {
            abort(403, 'Unauthorized company access.');
        }

        $category->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ]);
        }

        return back()->with('success', 'Category deleted successfully.');
    }
}
