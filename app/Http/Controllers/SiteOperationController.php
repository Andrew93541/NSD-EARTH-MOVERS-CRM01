<?php

namespace App\Http\Controllers;

use App\Models\DailySiteLog;
use App\Models\Project;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SiteOperationController extends Controller
{
    public function index(Request $request): View
    {
        $sites = Site::with(['project', 'supervisor', 'dailyLogs' => function ($q) {
            $q->latest('log_date')->take(5);
        }])->latest()->paginate(10);

        return view('sites.index', compact('sites'));
    }

    public function show(Site $site): View
    {
        $site->load(['project', 'supervisor', 'warehouses.ledgerEntries.material', 'dailyLogs.creator']);

        return view('sites.show', compact('site'));
    }

    public function createLog(Site $site): View
    {
        $site->load('project');
        return view('sites.create-log', compact('site'));
    }

    public function storeLog(Request $request, Site $site): RedirectResponse
    {
        $validated = $request->validate([
            'log_date' => 'required|date',
            'work_summary' => 'required|string',
            'delays_obstacles' => 'nullable|string',
            'weather_conditions' => 'nullable|string',
            'active_workers_count' => 'required|integer|min:0',
        ]);

        $validated['site_id'] = $site->id;
        $validated['project_id'] = $site->project_id;
        $validated['created_by'] = Auth::id();

        DailySiteLog::create($validated);

        return redirect()->route('sites.show', $site)->with('success', 'Daily site operation log recorded successfully.');
    }
}
