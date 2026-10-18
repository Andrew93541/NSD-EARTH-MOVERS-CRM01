@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ reverseModalOpen: false, selectedJournal: null, selectedJournalNumber: '' }">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">General Journal Vouchers</h2>
            <p class="text-xs text-gray-500 mt-1">Double-entry accounting journal vouchers with debits and credits.</p>
        </div>
        <a href="{{ route('accounting.journals.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            + New Journal Entry
        </a>
    </div>

    @if (session('success'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-medium">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 font-medium">
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($entries as $je)
            <div class="soft-card p-5 space-y-3 {{ $je->status === 'reversed' ? 'border-l-4 border-l-rose-500 bg-rose-50/10' : '' }}">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="font-mono font-bold text-gray-900 text-sm">{{ $je->entry_number }}</span>
                        <span class="text-gray-400 font-medium">{{ $je->entry_date->format('d M, Y') }}</span>
                        @if ($je->reference_type)
                            <span class="text-[10px] font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                Ref: {{ $je->reference_type }} #{{ $je->reference_id }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-gray-900">Total: ₹{{ number_format($je->total_amount, 2) }}</span>
                        @if ($je->status === 'reversed')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200 uppercase">
                                Reversed
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">
                                {{ $je->status }}
                            </span>
                        @endif

                        @if ($je->status === 'posted' && $je->reference_type !== 'journal_entries_reversal' && $je->reference_type !== 'subcontractor_bills_reversal')
                            <button
                                type="button"
                                @click="selectedJournal = {{ $je->id }}; selectedJournalNumber = '{{ $je->entry_number }}'; reverseModalOpen = true;"
                                class="px-2 py-1 bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 rounded text-[10px] font-semibold transition"
                            >
                                Reverse Voucher
                            </button>
                        @endif
                    </div>
                </div>

                @if ($je->status === 'reversed')
                    <div class="p-2.5 bg-rose-50/80 border border-rose-200 rounded-lg text-xs text-rose-800 flex items-center justify-between">
                        <span><strong>Reversal Audit:</strong> Reversed on {{ $je->reversed_at?->format('d M, Y H:i') }} by {{ $je->reverser?->name ?? 'System' }}</span>
                        <span class="italic">Reason: "{{ $je->reversal_reason }}"</span>
                    </div>
                @endif

                <p class="text-xs text-gray-700 italic">"{{ $je->narration }}"</p>

                <!-- Lines Table -->
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-[10px] text-gray-400 font-bold uppercase">
                            <th class="py-1">Account</th>
                            <th class="py-1">Line Description</th>
                            <th class="py-1 text-right">Debit (INR)</th>
                            <th class="py-1 text-right">Credit (INR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($je->lines as $line)
                            <tr>
                                <td class="py-2 font-bold text-gray-900">{{ $line->account->code }} - {{ $line->account->name }}</td>
                                <td class="py-2 text-gray-500">{{ $line->description ?? '—' }}</td>
                                <td class="py-2 text-right font-mono font-semibold">{{ $line->debit > 0 ? '₹' . number_format($line->debit, 2) : '—' }}</td>
                                <td class="py-2 text-right font-mono font-semibold">{{ $line->credit > 0 ? '₹' . number_format($line->credit, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Supporting Voucher Attachments -->
                <div class="pt-2 border-t border-gray-100">
                    <x-document-manager 
                        :record="$je" 
                        type="journal_entry" 
                        title="Voucher Supporting Invoices & Bank Slips"
                        :categories="[
                            'voucher_support' => 'Signed Voucher / Payment Receipt',
                            'supplier_invoice' => 'Supplier Commercial Invoice Copy',
                            'bank_mandate' => 'Bank Debit Advice / Deposit Counterfoil',
                            'general' => 'Audit Justification Note',
                        ]"
                    />
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-gray-400 soft-card">No journal vouchers posted yet.</div>
        @endforelse

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
    </div>

    <!-- Reverse Voucher Modal -->
    <div
        x-show="reverseModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
    >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-gray-100" @click.outside="reverseModalOpen = false">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900">Authorize Journal Reversal</h3>
                <button type="button" @click="reverseModalOpen = false" class="text-gray-400 hover:text-gray-600 text-sm font-bold">&times;</button>
            </div>

            <p class="text-xs text-gray-600">
                You are about to reverse journal entry <span class="font-mono font-bold text-gray-900" x-text="selectedJournalNumber"></span>. This will permanently mark the entry as reversed and generate a balanced counter-journal entry.
            </p>

            <form :action="'{{ url('/accounting/journals') }}/' + selectedJournal + '/reverse'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Audit Reversal Reason (Mandatory min. 5 chars) *</label>
                    <textarea
                        name="reversal_reason"
                        rows="3"
                        required
                        minlength="5"
                        placeholder="Provide formal audit explanation for this financial reversal..."
                        class="w-full rounded-xl border border-gray-200 p-2.5 text-xs text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="reverseModalOpen = false"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                    >
                        Confirm & Reverse Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
