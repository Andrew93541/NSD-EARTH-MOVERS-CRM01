<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = Auth::user();
        $company = Company::configuredCompany() ?? $user->currentCompany();

        if (! $company) {
            abort(404, 'Configured company profile not found.');
        }

        $canEdit = $user && ($user->hasPermission('*') || in_array($user->currentRole()?->slug, ['owner', 'admin']));

        $stats = [
            'total_projects' => $company->projects()->count(),
            'total_sites' => $company->sites()->count(),
            'total_employees' => $company->employees()->count(),
            'total_clients' => $company->clients()->count(),
            'total_suppliers' => $company->suppliers()->count(),
        ];

        return view('company.profile', compact('company', 'canEdit', 'stats'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! ($user->hasPermission('*') || in_array($user->currentRole()?->slug, ['owner', 'admin']))) {
            abort(403, 'You do not have permission to update the company profile.');
        }

        $company = Company::configuredCompany() ?? $user->currentCompany();

        if (! $company) {
            abort(404, 'Configured company profile not found.');
        }

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'address' => 'required|string|max:500',
            'gstin' => 'nullable|string|max:15|regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/',
            'pan' => 'nullable|string|max:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
        ]);

        $before = $company->only(['legal_name', 'email', 'phone', 'address', 'gstin', 'pan']);
        $company->update($validated);

        AuditLog::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'action' => 'company.profile.updated',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
            'old_values' => $before,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('company.profile')->with('success', 'Corporate profile updated successfully.');
    }
}
