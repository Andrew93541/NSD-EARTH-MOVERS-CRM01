@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold mb-1">
                <a href="{{ route('assets.index') }}" class="hover:text-blue-600 transition">Fixed Assets</a>
                <span>/</span>
                <span class="text-gray-900 font-bold">Periodic Depreciation Review</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Fleet Depreciation Period Review</h1>
            <p class="text-xs text-gray-500 mt-1">Review, approve, and atomically post monthly non-cash depreciation entries to the General Ledger.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('assets.index') }}" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                ← Back to Assets
            </a>
        </div>
    </div>

    <!-- Feedback messages -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 bg-red-50 rounded-2xl border border-red-200 text-xs font-semibold text-red-800 space-y-1">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- Period Selector Bar & Summary -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('assets.period-review') }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Month</label>
                    <select name="month" class="text-xs border border-gray-200 rounded-xl p-2.5 bg-white font-bold" onchange="this.form.submit()">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 10)) }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Year</label>
                    <select name="year" class="text-xs border border-gray-200 rounded-xl p-2.5 bg-white font-bold font-mono" onchange="this.form.submit()">
                        @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div class="pt-4 text-xs font-semibold text-gray-500">
                    Period: <strong class="text-gray-900">{{ $summary['period_start']->format('d M Y') }}</strong> to <strong class="text-gray-900">{{ $summary['period_end']->format('d M Y') }}</strong>
                </div>
            </div>

            <!-- Batch Action Button -->
            @if($summary['total_potential_depreciation'] > 0)
                <form method="POST" action="{{ route('assets.period-review.post') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <button type="submit" onclick="return confirm('Post depreciation journal entries for all eligible fleet assets for {{ $summary['period_start']->format('F Y') }}?')" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Post All Journals (₹{{ number_format($summary['total_potential_depreciation'], 2) }})</span>
                    </button>
                </form>
            @else
                <div class="px-4 py-2 bg-gray-50 text-gray-500 rounded-xl text-xs font-semibold border border-gray-200">
                    All Period Depreciation Posted in GL
                </div>
            @endif
        </form>
    </div>

    <!-- 4 Review Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Fleet Units</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $summary['total_assets_count'] }} Assets</div>
            <span class="text-[10px] text-gray-400 mt-1 block">Active / operational machinery</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Unposted Depreciation</span>
            <div class="text-2xl font-black text-purple-600 mt-1 font-mono">₹{{ number_format($summary['total_potential_depreciation'], 2) }}</div>
            <span class="text-[10px] text-gray-400 mt-1 block">Awaiting GL journal posting</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Already Posted in GL</span>
            <div class="text-2xl font-black text-emerald-600 mt-1 font-mono">₹{{ number_format($summary['total_posted_depreciation'], 2) }}</div>
            <span class="text-[10px] text-gray-400 mt-1 block">Recognized in General Ledger</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Financial Year</span>
            <div class="text-2xl font-black text-blue-600 mt-1 font-mono">{{ $month >= 4 ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year }}</div>
            <span class="text-[10px] text-gray-400 mt-1 block">Indian Statutory Accounting Year</span>
        </div>
    </div>

    <!-- Fleet Review Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-900">Fleet Equipment Periodic Breakdown</h2>
            <span class="text-xs font-semibold text-gray-500">Period: {{ $summary['period_start']->format('M Y') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Asset Code / Model</th>
                        <th class="py-3 px-4">Method</th>
                        <th class="py-3 px-4 text-right">Opening Value (₹)</th>
                        <th class="py-3 px-4 text-right">Depreciation Charge (₹)</th>
                        <th class="py-3 px-4 text-right">Closing Value (₹)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">GL Journal Reference</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 font-sans">
                    @forelse($summary['items'] as $item)
                        @php
                            $asset = $item['asset'];
                            $entry = $item['entry'];
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition {{ $item['status'] === 'posted' ? 'bg-emerald-50/20' : '' }}">
                            <td class="py-3 px-4">
                                <a href="{{ route('assets.show', $asset) }}" class="font-bold text-gray-900 hover:text-blue-600 transition block">
                                    {{ $asset->name }}
                                </a>
                                <span class="font-mono text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.2 rounded font-semibold">{{ $asset->asset_code }}</span>
                            </td>
                            <td class="py-3 px-4 text-gray-600 capitalize">
                                {{ str_replace('_', ' ', $asset->depreciation_method) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-gray-600">
                                ₹{{ number_format($item['opening_book_value'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-purple-600">
                                ₹{{ number_format($item['depreciation_amount'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-emerald-600">
                                ₹{{ number_format($item['closing_book_value'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($item['status'] === 'posted')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        POSTED
                                    </span>
                                @elseif($item['status'] === 'calculated')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        CALCULATED
                                    </span>
                                @elseif(! $item['is_eligible'])
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500">
                                        SALVAGE REACHED
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700">
                                        READY TO POST
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($entry && $entry->journalEntry)
                                    <a href="{{ route('accounting.journals') }}" class="font-mono text-[11px] font-bold text-blue-600 hover:text-blue-800 transition">
                                        {{ $entry->journalEntry->entry_number }}
                                    </a>
                                @else
                                    <span class="text-gray-400 text-[10px]">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($entry && $entry->status === 'calculated')
                                    <form method="POST" action="{{ route('assets.depreciation.post', $entry) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition cursor-pointer">
                                            Post GL
                                        </button>
                                    </form>
                                @elseif(! $entry && $item['is_eligible'])
                                    <form method="POST" action="{{ route('assets.depreciation.calculate', $asset) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="period_start_date" value="{{ $summary['period_start']->format('Y-m-d') }}">
                                        <input type="hidden" name="period_end_date" value="{{ $summary['period_end']->format('Y-m-d') }}">
                                        <button type="submit" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-[10px] font-bold transition cursor-pointer">
                                            Calculate
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('assets.show', $asset) }}" class="text-gray-400 hover:text-gray-600 text-xs">
                                        View
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400 text-xs">
                                No active fixed assets found for this company.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
