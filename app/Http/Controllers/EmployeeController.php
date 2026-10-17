<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Employee::with(['assignedSite', 'shift']);

        if ($request->filled('type')) {
            $query->where('employment_type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        $employees = $query->latest()->paginate(12)->withQueryString();

        return view('employees.index', compact('employees'));
    }

    public function create(): View
    {
        $sites = Site::orderBy('name')->get();
        $shifts = Shift::orderBy('name')->get();

        return view('employees.create', compact('sites', 'shifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'code' => 'required|string|max:50',
            'designation' => 'required|string|max:100',
            'department' => 'nullable|string|max:100',
            'employment_type' => 'required|in:full_time,contract,daily_wage,machine_operator',
            'joining_date' => 'nullable|date',
            'assigned_site_id' => 'nullable|exists:sites,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'daily_wage_rate' => 'nullable|numeric|min:0',
            'monthly_salary' => 'nullable|numeric|min:0',
            'phone' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
        ]);

        $employee = Employee::create($validated);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee profile created successfully.');
    }

    public function show(Employee $employee): View
    {
        $employee->load(['assignedSite', 'shift', 'attendanceRecords' => function ($q) {
            $q->latest('attendance_date')->take(30);
        }]);

        return view('employees.show', compact('employee'));
    }
}
