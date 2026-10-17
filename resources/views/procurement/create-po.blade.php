@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Issue Purchase Order</h2>
            <p class="text-xs text-gray-500 mt-0.5">Generate an authorized material requisition to a vendor.</p>
        </div>
        <a href="{{ route('procurement.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back</a>
    </div>

    <form method="POST" action="{{ route('procurement.store') }}" class="soft-card p-6 space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Supplier Organization *</label>
                <select name="supplier_id" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Charge to Project *</label>
                <select name="project_id" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    @foreach ($projects as $prj)
                        <option value="{{ $prj->id }}">{{ $prj->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Material Specification *</label>
                <select name="material_id" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    @foreach ($materials as $mat)
                        <option value="{{ $mat->id }}">{{ $mat->name }} ({{ $mat->code }}) — Standard Cost: ₹{{ $mat->standard_cost }}/{{ $mat->unit_of_measure }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Order Quantity *</label>
                <input type="number" step="0.01" min="0.01" name="quantity" required value="500"
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Negotiated Unit Rate (INR) *</label>
                <input type="number" step="0.01" min="0" name="unit_price" required value="380.00"
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">PO Date *</label>
                <input type="date" name="po_date" value="{{ date('Y-m-d') }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Expected Delivery Date</label>
                <input type="date" name="delivery_due_date" value="{{ date('Y-m-d', strtotime('+7 days')) }}"
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Commercial Terms & Unloading Conditions</label>
            <textarea name="terms_and_conditions" rows="2" placeholder="Transit insurance, weighbridge tolerance, freight charges..."
                      class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">F.O.R Site. Material subject to test certificate and quality inspection upon unloading.</textarea>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('procurement.index') }}" class="px-4 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Generate Purchase Order
            </button>
        </div>
    </form>
</div>
@endsection
