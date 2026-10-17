@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Post Balanced Journal Voucher</h2>
            <p class="text-xs text-gray-500 mt-0.5">Strict double-entry validation requires equal total debits and credits.</p>
        </div>
        <a href="{{ route('accounting.journals') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back</a>
    </div>

    <form method="POST" action="{{ route('accounting.journals.store') }}" class="soft-card p-6 space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Entry Date *</label>
                <input type="date" name="entry_date" value="{{ date('Y-m-d') }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Narration / Justification *</label>
                <input type="text" name="narration" placeholder="e.g. Bank charge adjustment or site imprest replenishment" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <!-- Line 1: Debit -->
        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-2">
            <span class="text-[10px] font-bold uppercase text-blue-600">Line 1 (Debit Side)</span>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <select name="lines[0][account_id]" required class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs">
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ $acc->account_type }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <input type="number" step="0.01" min="0" name="lines[0][debit]" placeholder="Debit Amount" value="50000.00" required
                           class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-mono font-bold"/>
                    <input type="hidden" name="lines[0][credit]" value="0.00"/>
                </div>
            </div>
            <input type="text" name="lines[0][description]" placeholder="Line description..." class="w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs"/>
        </div>

        <!-- Line 2: Credit -->
        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-2">
            <span class="text-[10px] font-bold uppercase text-emerald-600">Line 2 (Credit Side)</span>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <select name="lines[1][account_id]" required class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs">
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $loop->last ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }} ({{ $acc->account_type }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <input type="number" step="0.01" min="0" name="lines[1][credit]" placeholder="Credit Amount" value="50000.00" required
                           class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-mono font-bold"/>
                    <input type="hidden" name="lines[1][debit]" value="0.00"/>
                </div>
            </div>
            <input type="text" name="lines[1][description]" placeholder="Line description..." class="w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs"/>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('accounting.journals') }}" class="px-4 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Validate & Post Journal
            </button>
        </div>
    </form>
</div>
@endsection
