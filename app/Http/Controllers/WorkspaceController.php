<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function select(): View
    {
        $user = Auth::user();
        $companies = $user->companies()->wherePivot('status', 'active')->get();

        return view('workspace.select', compact('companies', 'user'));
    }

    public function switch(Request $request, Company $company): RedirectResponse
    {
        $user = Auth::user();

        // Enforce server-side tenant membership verification with active status check
        if (! $user->companies()->where('companies.id', $company->id)->wherePivot('status', 'active')->exists()) {
            abort(403, 'You do not have an active membership in this company workspace.');
        }

        session(['current_company_id' => $company->id]);

        return redirect()->route('dashboard')->with('success', "Switched workspace to {$company->name}");
    }

    public function create(): View
    {
        return view('workspace.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'gstin' => 'nullable|string|max:15',
            'pan' => 'nullable|string|max:10',
            'address' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        $validated['currency'] = 'INR';
        $validated['currency_symbol'] = '₹';

        $company = Company::create($validated);

        // Create default owner role for this company
        $ownerRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $user = Auth::user();
        $company->users()->attach($user->id, [
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        session(['current_company_id' => $company->id]);

        return redirect()->route('dashboard')->with('success', "Workspace {$company->name} initialized successfully.");
    }
}
