@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Record Goods Receipt (GRN)</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 font-mono">
                    {{ $order->po_number }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Quality inspection & warehouse inventory credit for <strong>{{ $order->supplier->name }}</strong>.
            </p>
        </div>
        <a href="{{ route('procurement.show', $order) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to PO</a>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-xs text-red-700 space-y-1">
            <strong class="font-bold block">Please correct the following errors:</strong>
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('procurement.receive.store', $order) }}" class="space-y-6">
        @csrf

        <!-- Delivery & Warehouse Metadata -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Challan & Inward Details</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label for="warehouse_id" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Destination Warehouse *</label>
                    <select id="warehouse_id" name="warehouse_id" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="">Select Warehouse / Store</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ ucfirst(str_replace('_', ' ', $wh->type)) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="receipt_date" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Receipt Date *</label>
                    <input type="date" id="receipt_date" name="receipt_date" value="{{ old('receipt_date', date('Y-m-d')) }}" required
                           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                </div>

                <div>
                    <label for="delivery_challan_number" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Delivery Challan # *</label>
                    <input type="text" id="delivery_challan_number" name="delivery_challan_number" value="{{ old('delivery_challan_number') }}" required
                           placeholder="e.g. DC-2026-9912"
                           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none font-mono"/>
                </div>

                <div>
                    <label for="vehicle_number" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Vehicle / Truck #</label>
                    <input type="text" id="vehicle_number" name="vehicle_number" value="{{ old('vehicle_number') }}"
                           placeholder="e.g. KA-04-MK-8871"
                           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none font-mono uppercase"/>
                </div>
            </div>

            <div>
                <label for="remarks" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Inspection Notes / Gate Entry Remarks</label>
                <input type="text" id="remarks" name="remarks" value="{{ old('remarks') }}"
                       placeholder="e.g. Weighbridge slip verified, test certificate attached"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <!-- Material Quality Inspection Grid -->
        <div class="soft-card p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Item Quality Inspection & Quantities</h3>
                <span class="text-[11px] text-gray-400">Accepted quantity directly credits inventory ledger</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                            <th class="py-2.5 px-2">Material</th>
                            <th class="py-2.5 px-2 text-right">Ordered</th>
                            <th class="py-2.5 px-2 text-right">Prev. Received</th>
                            <th class="py-2.5 px-2 text-right">Balance Due</th>
                            <th class="py-2.5 px-2 w-28">Received Qty *</th>
                            <th class="py-2.5 px-2 w-28">Accepted Qty *</th>
                            <th class="py-2.5 px-2 w-28">Rejected Qty</th>
                            <th class="py-2.5 px-2">Rejection Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($order->items as $idx => $item)
                            @php
                                $balanceDue = max(0, (float) ($item->quantity_ordered - $item->quantity_received));
                            @endphp
                            <tr class="align-top">
                                <td class="py-3 px-2">
                                    <strong class="text-gray-900 block">{{ $item->material->name }}</strong>
                                    <span class="text-gray-400 text-[11px] font-mono">Rate: ₹{{ number_format($item->unit_price, 2) }}/{{ $item->material->unit_of_measure }}</span>
                                    <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $item->id }}"/>
                                </td>
                                <td class="py-3 px-2 text-right font-mono text-gray-600">
                                    {{ number_format($item->quantity_ordered, 2) }}
                                </td>
                                <td class="py-3 px-2 text-right font-mono text-blue-600 font-bold">
                                    {{ number_format($item->quantity_received, 2) }}
                                </td>
                                <td class="py-3 px-2 text-right font-mono text-gray-900 font-black">
                                    {{ number_format($balanceDue, 2) }}
                                </td>
                                <td class="py-3 px-2">
                                    <input type="number" step="0.001" min="0" max="{{ $balanceDue }}"
                                           name="items[{{ $idx }}][quantity_received]"
                                           value="{{ old("items.{$idx}.quantity_received", $balanceDue) }}"
                                           required
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono font-bold focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>
                                <td class="py-3 px-2">
                                    <input type="number" step="0.001" min="0" max="{{ $balanceDue }}"
                                           name="items[{{ $idx }}][quantity_accepted]"
                                           value="{{ old("items.{$idx}.quantity_accepted", $balanceDue) }}"
                                           required
                                           class="w-full px-2.5 py-1.5 bg-green-50 border border-green-200 rounded-lg text-xs font-mono font-bold text-green-800 focus:ring-1 focus:ring-green-600 focus:outline-none"/>
                                </td>
                                <td class="py-3 px-2">
                                    <input type="number" step="0.001" min="0"
                                           name="items[{{ $idx }}][quantity_rejected]"
                                           value="{{ old("items.{$idx}.quantity_rejected", 0) }}"
                                           class="w-full px-2.5 py-1.5 bg-red-50 border border-red-200 rounded-lg text-xs font-mono font-bold text-red-800 focus:ring-1 focus:ring-red-600 focus:outline-none"/>
                                </td>
                                <td class="py-3 px-2">
                                    <input type="text"
                                           name="items[{{ $idx }}][rejection_reason]"
                                           value="{{ old("items.{$idx}.rejection_reason") }}"
                                           placeholder="e.g. Moisture / damaged"
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('procurement.show', $order) }}" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Confirm Inspection & Credit Stock</span>
            </button>
        </div>
    </form>
</div>
@endsection
