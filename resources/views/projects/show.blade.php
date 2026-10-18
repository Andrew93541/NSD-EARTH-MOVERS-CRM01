@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header with status and actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $project->name }}</h2>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold font-mono bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $project->code }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Client: {{ $project->client->name ?? 'Direct Contract' }} • Location: {{ $project->location ?? 'Site Yard' }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('projects.edit', $project) }}" class="px-3.5 py-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 rounded-xl text-xs font-semibold shadow-sm transition">
                Edit Parameters
            </a>
            <a href="{{ route('billing.create', ['project_id' => $project->id]) }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                + Raise RA Bill
            </a>
        </div>
    </div>

    <!-- 4 Stats Cards for Project Budget & Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Contract Value</span>
            <div class="text-xl font-extrabold text-gray-900 mt-1">₹{{ number_format($project->contract_value, 2) }}</div>
            <span class="text-[11px] text-gray-500">Fixed lump-sum / EPC</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Approved Budget</span>
            <div class="text-xl font-extrabold text-gray-900 mt-1">₹{{ number_format($project->approved_budget, 2) }}</div>
            <span class="text-[11px] text-gray-500">Cap limit for materials & labour</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Completion Status</span>
            <div class="text-xl font-extrabold text-blue-600 mt-1">{{ $project->progress_percent }}%</div>
            <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden mt-1.5">
                <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $project->progress_percent }}%"></div>
            </div>
        </div>

        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Project Manager</span>
            <div class="text-sm font-bold text-gray-900 mt-1">{{ $project->manager->name ?? 'Unassigned' }}</div>
            <span class="text-[11px] text-gray-500">{{ $project->manager->email ?? '—' }}</span>
        </div>
    </div>

    <!-- Description & Sites -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 soft-card p-5 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Engineering Scope & Deliverables</h3>
            <p class="text-xs text-gray-600 leading-relaxed">{{ $project->description ?? 'No detailed engineering notes uploaded.' }}</p>

            <div class="pt-4 border-t border-gray-100">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Associated Operational Sites</h4>
                <div class="space-y-2">
                    @forelse ($project->sites as $site)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                            <div>
                                <span class="font-bold text-gray-900">{{ $site->name }}</span>
                                <span class="block text-[11px] text-gray-400">{{ $site->location ?? 'General Site Boundary' }}</span>
                            </div>
                            <a href="{{ route('sites.show', $site) }}" class="text-blue-600 font-semibold hover:text-blue-800">Site Monitor →</a>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No active work sites defined for this project.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Invoices & Purchase Orders Summary -->
        <div class="soft-card p-5 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Financial Documents</h3>

            <div>
                <span class="text-[11px] font-bold text-gray-400 block mb-2">Client RA Bills ({{ $project->invoices->count() }})</span>
                <div class="space-y-2">
                    @forelse ($project->invoices as $inv)
                        <div class="flex items-center justify-between text-xs p-2 bg-gray-50 rounded-lg">
                            <span class="font-mono font-semibold">{{ $inv->invoice_number }}</span>
                            <strong class="text-gray-900">₹{{ number_format($inv->total_amount, 2) }}</strong>
                        </div>
                    @empty
                        <p class="text-[11px] text-gray-400">No invoices submitted yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="pt-3 border-t border-gray-100">
                <span class="text-[11px] font-bold text-gray-400 block mb-2">Purchase Orders ({{ $project->purchaseOrders->count() }})</span>
                <div class="space-y-2">
                    @forelse ($project->purchaseOrders as $po)
                        <div class="flex items-center justify-between text-xs p-2 bg-gray-50 rounded-lg">
                            <span class="font-mono font-semibold">{{ $po->po_number }}</span>
                            <strong class="text-gray-900">₹{{ number_format($po->total_amount, 2) }}</strong>
                        </div>
                    @empty
                        <p class="text-[11px] text-gray-400">No purchase orders issued.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Project Documents & Engineering Vault -->
    <x-document-manager 
        :record="$project" 
        type="project" 
        title="Project Documentation & Engineering Files"
        :categories="[
            'contract' => 'Client EPC Contract / Work Order',
            'drawing' => 'Engineering Drawing / Blueprints',
            'specification' => 'Technical Specifications / BOQ',
            'permit' => 'Statutory Permit / NOC / Clearance',
            'inspection_report' => 'QA/QC Inspection Report',
            'progress_photo' => 'Site Progress Photograph',
            'general' => 'General Project Correspondence',
        ]"
    />
</div>
@endsection
