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
            $projects = Project::where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('location', 'like', "%{$q}%")
                ->take(5)->get();

            $materials = Material::where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->take(5)->get();

            $employees = Employee::where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->take(5)->get();

            $invoices = ClientInvoice::where('invoice_number', 'like', "%{$q}%")
                ->take(5)->get();

            $suppliers = Supplier::where('name', 'like', "%{$q}%")
                ->take(5)->get();
        }

        return view('search.results', compact('q', 'projects', 'materials', 'employees', 'invoices', 'suppliers'));
    }
}
