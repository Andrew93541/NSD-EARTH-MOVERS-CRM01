<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $query = Project::with(['client', 'manager', 'sites']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('project_type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $projects = $query->latest()->paginate(10)->withQueryString();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();
        $managers = User::orderBy('name')->get();

        return view('projects.create', compact('clients', 'managers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'client_id' => 'nullable|exists:clients,id',
            'project_type' => 'required|in:civil,interior,maintenance,earthmoving',
            'location' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
            'contract_value' => 'required|numeric|min:0',
            'approved_budget' => 'required|numeric|min:0',
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $validated['status'] = 'on_track';
        $validated['progress_percent'] = 0;

        $project = Project::create($validated);

        return redirect()->route('projects.show', $project)->with('success', 'Project created successfully.');
    }

    public function show(Project $project): View
    {
        $project->load(['client', 'manager', 'sites.dailyLogs', 'milestones', 'tasks.assignee', 'invoices', 'purchaseOrders.supplier']);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        $clients = Client::orderBy('name')->get();
        $managers = User::orderBy('name')->get();

        return view('projects.edit', compact('project', 'clients', 'managers'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'project_type' => 'required|in:civil,interior,maintenance,earthmoving',
            'location' => 'nullable|string|max:255',
            'target_completion_date' => 'nullable|date',
            'contract_value' => 'required|numeric|min:0',
            'approved_budget' => 'required|numeric|min:0',
            'status' => 'required|in:planned,active,on_track,at_risk,delayed,completed,on_hold,cancelled',
            'progress_percent' => 'required|integer|between:0,100',
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $project->update($validated);

        return redirect()->route('projects.show', $project)->with('success', 'Project updated successfully.');
    }
}
