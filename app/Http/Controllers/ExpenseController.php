<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = Expense::with(['category', 'project', 'site', 'submitter'])
            ->latest('expense_date')
            ->paginate(15);

        $totalExpenses = Expense::where('status', 'approved')->sum('amount');
        $categories = ExpenseCategory::all();

        return view('expenses.index', compact('expenses', 'totalExpenses', 'categories'));
    }

    public function create(): View
    {
        $categories = ExpenseCategory::orderBy('name')->get();
        $projects = Project::whereIn('status', ['on_track', 'at_risk', 'delayed'])->orderBy('name')->get();
        $sites = Site::orderBy('name')->get();

        return view('expenses.create', compact('categories', 'projects', 'sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:expense_categories,id',
            'project_id' => 'nullable|exists:projects,id',
            'site_id' => 'nullable|exists:sites,id',
            'title' => 'required|string|max:255',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'payment_mode' => 'required|in:cash,bank_transfer,company_card,upi',
            'receipt_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $validated['expense_number'] = 'EXP-' . date('Y') . '-' . strtoupper(Str::random(5));
        $validated['status'] = 'approved';
        $validated['submitted_by'] = Auth::id();
        $validated['approved_by'] = Auth::id();
        $validated['approved_at'] = Carbon::now();

        Expense::create($validated);

        return redirect()->route('expenses.index')->with('success', 'Operational expense recorded successfully.');
    }
}
