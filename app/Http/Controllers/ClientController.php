<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    /**
     * Client directory listing.
     */
    public function index(Request $request): View
    {
        $this->authorizeView();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        $query = Client::where('company_id', $companyId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('company_name', 'like', "%{$s}%")
                    ->orWhere('contact_person', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('gstin', 'like', "%{$s}%")
                    ->orWhere('pan', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%");
            });
        }

        $clients = $query->withCount(['projects', 'invoices'])
            ->with(['projects' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }, 'invoices' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $allClients = Client::where('company_id', $companyId)->with(['projects', 'invoices'])->get();
        $stats = [
            'total_count' => $allClients->count(),
            'active_count' => $allClients->where('status', 'active')->count(),
            'total_projects' => $allClients->sum(fn ($c) => $c->projects->count()),
            'total_contract_value' => (float) $allClients->sum(fn ($c) => $c->projects->sum('contract_value')),
            'total_billed' => (float) $allClients->sum(fn ($c) => $c->invoices->whereIn('status', ['submitted', 'approved', 'partially_paid', 'paid', 'overdue'])->sum('total_amount')),
            'total_received' => (float) $allClients->sum(fn ($c) => $c->invoices->sum('paid_amount')),
        ];
        $stats['outstanding_balance'] = max(0.0, $stats['total_billed'] - $stats['total_received']);

        return view('clients.index', compact('clients', 'stats'));
    }

    /**
     * Show create client form.
     */
    public function create(): View
    {
        $this->authorizeManage();

        return view('clients.create');
    }

    /**
     * Store newly created client.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $user = Auth::user();
        $companyId = session('current_company_id') ?? $user?->currentCompany()?->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'gstin' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients', 'gstin')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'pan' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['company_id'] = $companyId;

        $client = Client::create($validated);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'action' => 'create_client',
            'auditable_type' => Client::class,
            'auditable_id' => $client->id,
            'new_values' => [
                'name' => $client->name,
                'company_name' => $client->company_name,
                'gstin' => $client->gstin,
            ],
        ]);

        return redirect()->route('clients.show', $client)
            ->with('success', "Client '{$client->name}' registered successfully.");
    }

    /**
     * Show client profile and relationships.
     */
    public function show(Client $client): View
    {
        $this->authorizeView();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($client->company_id !== $companyId) {
            abort(404, 'Client not found in the active company workspace.');
        }

        $projects = $client->projects()
            ->where('company_id', $companyId)
            ->with(['manager', 'sites'])
            ->latest()
            ->get();

        $invoices = $client->invoices()
            ->where('company_id', $companyId)
            ->with(['project', 'creator'])
            ->latest()
            ->get();

        $contractValue = (float) $projects->sum('contract_value');
        $billsRaised = (float) $invoices->whereIn('status', ['submitted', 'approved', 'partially_paid', 'paid', 'overdue'])->sum('total_amount');
        $moneyReceived = (float) $invoices->sum('paid_amount');
        $outstandingBalance = max(0.0, $billsRaised - $moneyReceived);
        $unbilledContractValue = max(0.0, $contractValue - $billsRaised);

        $financials = [
            'contract_value' => $contractValue,
            'bills_raised' => $billsRaised,
            'money_received' => $moneyReceived,
            'outstanding_balance' => $outstandingBalance,
            'unbilled_contract_value' => $unbilledContractValue,
            'draft_bills_count' => $invoices->where('status', 'draft')->count(),
            'active_projects_count' => $projects->whereIn('status', ['planned', 'active', 'on_track', 'at_risk', 'delayed'])->count(),
        ];

        return view('clients.show', compact('client', 'projects', 'invoices', 'financials'));
    }

    /**
     * Show edit client form.
     */
    public function edit(Client $client): View
    {
        $this->authorizeManage();

        $companyId = session('current_company_id') ?? Auth::user()?->currentCompany()?->id;

        if ($client->company_id !== $companyId) {
            abort(404, 'Client not found in the active company workspace.');
        }

        return view('clients.edit', compact('client'));
    }

    /**
     * Update client details.
     */
    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeManage();

        $user = Auth::user();
        $companyId = session('current_company_id') ?? $user?->currentCompany()?->id;

        if ($client->company_id !== $companyId) {
            abort(404, 'Client not found in the active company workspace.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'gstin' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients', 'gstin')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($client->id),
            ],
            'pan' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $oldValues = [
            'name' => $client->name,
            'company_name' => $client->company_name,
            'gstin' => $client->gstin,
            'status' => $client->status,
        ];

        $client->update($validated);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'action' => 'update_client',
            'auditable_type' => Client::class,
            'auditable_id' => $client->id,
            'old_values' => $oldValues,
            'new_values' => [
                'name' => $client->name,
                'company_name' => $client->company_name,
                'gstin' => $client->gstin,
                'status' => $client->status,
            ],
        ]);

        return redirect()->route('clients.show', $client)
            ->with('success', "Client '{$client->name}' updated successfully.");
    }

    /**
     * Authorize viewing clients.
     */
    protected function authorizeView(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('clients.view')
            && ! $user->hasPermission('clients.*')
            && ! $user->hasPermission('projects.*')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'accountant']))) {
            abort(403, 'Unauthorized access to Client Directory.');
        }
    }

    /**
     * Authorize managing clients.
     */
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('clients.manage')
            && ! $user->hasPermission('clients.*')
            && ! $user->hasPermission('projects.*')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'accountant']))) {
            abort(403, 'Unauthorized to create or modify Client records.');
        }
    }
}
