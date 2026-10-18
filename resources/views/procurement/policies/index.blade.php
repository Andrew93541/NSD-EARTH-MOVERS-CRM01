@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('procurement.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Procurement</a>
                <span class="text-xs text-gray-400">/</span>
                <span class="text-xs font-semibold text-gray-800">Approval Policies</span>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight mt-1">Approval Policies & Authorization Limits</h2>
            <p class="text-xs text-gray-500 mt-1">Configure company-wide threshold limits, required approval levels, over-budget safeguards, and segregation-of-duties rules.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="document.getElementById('create-policy-modal').classList.remove('hidden')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Approval Tier</span>
            </button>
        </div>
    </div>

    <!-- Overview Governance Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Configured Tiers</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $policies->count() }}</div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Active threshold levels</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Segregation of Duties</span>
            <div class="text-sm font-bold text-emerald-700 mt-1.5 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Enforced Server-Side</span>
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Self-approvals prohibited</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Over-Budget Governance</span>
            <div class="text-sm font-bold text-amber-700 mt-1.5 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Mandatory Override Reason</span>
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Requires Owner authorization</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Pricing Integrity</span>
            <div class="text-sm font-bold text-blue-700 mt-1.5 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Honest Labeling</span>
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Zero prices never assumed</span>
        </div>
    </div>

    <!-- Active Policies Table -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Configured Company Approval Tiers</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">Threshold evaluation proceeds sequentially from Level 1 up to the highest applicable tier.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-3">Level</th>
                        <th class="py-3 px-3">Tier Name & Scope</th>
                        <th class="py-3 px-3">Threshold Limits</th>
                        <th class="py-3 px-3">Authorized Approver</th>
                        <th class="py-3 px-3">Over-Budget Rule</th>
                        <th class="py-3 px-3">Pricing Rule</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($policies as $policy)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-3">
                                <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-700 font-bold text-xs flex items-center justify-center border border-blue-200">
                                    L{{ $policy->approval_level }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3">
                                <strong class="text-gray-900 block font-bold">{{ $policy->name }}</strong>
                                @if ($policy->description)
                                    <span class="text-gray-500 text-[11px] block mt-0.5">{{ $policy->description }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 font-mono">
                                <span class="font-bold text-gray-900">₹{{ number_format($policy->min_amount, 2) }}</span>
                                <span class="text-gray-400 text-[11px]">to</span>
                                @if ($policy->max_amount)
                                    <span class="font-bold text-gray-900">₹{{ number_format($policy->max_amount, 2) }}</span>
                                @else
                                    <span class="text-blue-600 font-bold text-[11px] uppercase tracking-wider">No Limit (Above)</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3">
                                @if ($policy->requires_owner)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-50 text-purple-700 border border-purple-200">
                                        Company Owner
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200">
                                        {{ $policy->role->name ?? ucwords(str_replace('_', ' ', $policy->approver_role_slug ?? 'Authorized Approver')) }}
                                    </span>
                                @endif
                                @if ($policy->prevent_self_approval)
                                    <span class="text-[10px] text-gray-400 block mt-1">No Self-Approval</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3">
                                @if ($policy->over_budget_requires_owner)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        Escalates to Owner
                                    </span>
                                @else
                                    <span class="text-gray-500 text-[11px]">Standard Tier</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3">
                                @if ($policy->unknown_price_rule === 'require_pricing')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                                        Require Pricing
                                    </span>
                                @elseif ($policy->unknown_price_rule === 'escalate_to_owner')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                        Escalate to Owner
                                    </span>
                                @else
                                    <span class="text-gray-500 text-[11px]">Allow Level 1</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $policy->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400' }}">
                                    {{ $policy->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3 text-right">
                                <form method="POST" action="{{ route('procurement.policies.destroy', $policy) }}" onsubmit="return confirm('Delete this approval policy tier?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 font-semibold text-xs ml-2 cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400">
                                No custom approval policy tiers configured. The system will use the default two-tier governance model.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Policy Modal -->
    <div id="create-policy-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Add Procurement Approval Policy Tier</h3>
                <button type="button" onclick="document.getElementById('create-policy-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
            </div>

            <form method="POST" action="{{ route('procurement.policies.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Tier Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Tier 2: Mid-value Procurement (₹50k - ₹5L)"
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Approval Level *</label>
                        <select name="approval_level" required class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                            <option value="1">Level 1 (Site/Project)</option>
                            <option value="2" selected>Level 2 (Finance/Manager)</option>
                            <option value="3">Level 3 (Senior Exec/Owner)</option>
                            <option value="4">Level 4 (Board/Audit)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Min Value (₹) *</label>
                        <input type="number" step="0.01" min="0" name="min_amount" value="50000.00" required
                               class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Max Value (₹)</label>
                        <input type="number" step="0.01" min="0" name="max_amount" placeholder="Empty for ∞"
                               class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Authorized Approver Role</label>
                        <select name="approver_role_id" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                            <option value="">— Select Authorized Role —</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->slug }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Unknown Pricing Rule</label>
                        <select name="unknown_price_rule" required class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                            <option value="escalate_to_owner" selected>Escalate to Owner / Admin</option>
                            <option value="require_pricing">Block submission until priced</option>
                            <option value="allow_tier1">Allow Project Manager to proceed</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2 pt-2 border-t border-gray-100">
                    <label class="flex items-center gap-2 text-xs text-gray-800 cursor-pointer">
                        <input type="checkbox" name="requires_owner" value="1" class="rounded text-blue-600 focus:ring-blue-500">
                        <span class="font-bold">Mandate Company Owner authorization for this tier</span>
                    </label>

                    <label class="flex items-center gap-2 text-xs text-gray-800 cursor-pointer">
                        <input type="checkbox" name="over_budget_requires_owner" value="1" checked class="rounded text-blue-600 focus:ring-blue-500">
                        <span class="font-bold">Over-Budget Safeguard: Require Owner approval with mandatory override reason</span>
                    </label>

                    <label class="flex items-center gap-2 text-xs text-gray-800 cursor-pointer">
                        <input type="checkbox" name="prevent_self_approval" value="1" checked class="rounded text-blue-600 focus:ring-blue-500">
                        <span class="font-bold">Segregation of Duties: Prevent requesters from approving their own PR</span>
                    </label>

                    <label class="flex items-center gap-2 text-xs text-gray-800 cursor-pointer">
                        <input type="checkbox" name="urgent_escalation" value="1" class="rounded text-blue-600 focus:ring-blue-500">
                        <span class="font-medium text-gray-600">Auto-escalate critical or urgent priority requisitions to this level</span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Description / Policy Purpose</label>
                    <textarea name="description" rows="2" placeholder="Policy justification or workflow instructions..."
                              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('create-policy-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition">
                        Save Policy Tier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
