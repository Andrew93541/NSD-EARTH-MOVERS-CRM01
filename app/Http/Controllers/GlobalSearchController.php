<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function search(Request $request): View
    {
        $q = trim($request->input('q', ''));

        $projects = collect();
        $materials = collect();
        $employees = collect();
        $invoices = collect();
        $suppliers = collect();

        if (strlen($q) >= 2) {
            $user = $request->user();

            $projects = Project::where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            })->take(5)->get();

            $materials = Material::where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            })->take(5)->get();

            if ($user && ($user->hasPermission('employees.view') || $user->hasPermission('employees.*') || $user->hasPermission('*') || in_array($user->currentRole()?->slug, ['owner', 'admin', 'hr_officer', 'project_manager']))) {
                $employees = Employee::where(function ($query) use ($q) {
                    $query->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                })->take(5)->get();
            }

            if ($user && ($user->hasPermission('billing.view') || $user->hasPermission('billing.*') || $user->hasPermission('finance.*') || $user->hasPermission('*') || in_array($user->currentRole()?->slug, ['owner', 'admin', 'accountant']))) {
                $invoices = ClientInvoice::where('invoice_number', 'like', "%{$q}%")
                    ->take(5)->get();
            }

            $suppliers = Supplier::where('name', 'like', "%{$q}%")
                ->take(5)->get();
        }

        return view('search.results', compact('q', 'projects', 'materials', 'employees', 'invoices', 'suppliers'));
    }
}
