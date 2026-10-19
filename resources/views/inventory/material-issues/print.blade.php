<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Issue Note {{ $materialIssue->issue_number }} — NSD Earth Movers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { font-size: 11pt; color: #0f172a; background: #ffffff !important; }
            .no-print { display: none !important; }
            @page { margin: 12mm 15mm; size: A4 portrait; }
        }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 p-4 sm:p-8">

    <!-- Print Control Bar -->
    <div class="max-w-4xl mx-auto mb-6 flex items-center justify-between no-print bg-white p-4 rounded-xl shadow-xs border border-slate-200">
        <div class="flex items-center gap-3">
            <a href="{{ route('material-issues.show', $materialIssue) }}" class="text-xs font-semibold text-blue-600 hover:underline">
                ← Back to Issue Note
            </a>
            <span class="text-slate-300">|</span>
            <span class="text-xs text-slate-600 font-mono font-bold">{{ $materialIssue->issue_number }}</span>
        </div>
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>Print Delivery Note</span>
        </button>
    </div>

    <!-- Printable Delivery Challan -->
    <div class="max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl shadow-sm border border-slate-200">

        <!-- Corporate Header -->
        <div class="border-b-2 border-slate-900 pb-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-700 text-white flex items-center justify-center font-bold text-lg">
                            NSD
                        </div>
                        <div>
                            <h1 class="text-lg font-extrabold text-slate-900">NSD EARTH MOVERS &amp; CONSTRUCTION LTD</h1>
                            <p class="text-xs text-slate-600">Goods Issue Note (GIN) &amp; Site Dispatch Delivery Challan</p>
                        </div>
                    </div>
                    <div class="text-[10px] text-slate-500 mt-2">
                        Plot 42, Peenya Industrial Area 2nd Phase, Bengaluru, Karnataka 560058 | GSTIN: 29AAACN1234F1Z5
                    </div>
                </div>

                <div class="text-right bg-slate-50 p-3 rounded-xl border border-slate-200/80 min-w-[200px]">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Issue Slip Number</div>
                    <div class="text-base font-mono font-extrabold text-blue-700 mt-0.5">{{ $materialIssue->issue_number }}</div>
                    <div class="text-[11px] text-slate-600 mt-1">
                        <div>Date: <span class="font-bold text-slate-800">{{ $materialIssue->issue_date->format('d/m/Y') }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Route & Delivery Details -->
        <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50/75 rounded-xl border border-slate-200/80 mb-6 text-xs">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Source Storage:</span>
                <div class="font-bold text-slate-900">{{ $materialIssue->warehouse->name }}</div>
                <div class="text-slate-500 text-[11px]">{{ $materialIssue->warehouse->location ?? 'Central Warehouse' }}</div>
                <div class="text-[11px] text-slate-500 mt-2">
                    <span class="text-slate-400">Issued By:</span> {{ $materialIssue->issuer->name ?? 'Storekeeper' }}
                </div>
            </div>

            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Destination Work Site:</span>
                <div class="font-bold text-blue-700">{{ $materialIssue->project->name }} ({{ $materialIssue->project->code }})</div>
                <div class="font-semibold text-slate-800 text-[11px]">📍 Site: {{ $materialIssue->site->name }}</div>
                <div class="text-[11px] text-slate-500 mt-2">
                    <span class="text-slate-400">Receiver / Operator:</span> {{ $materialIssue->received_by_name ?? 'Site Foreman' }}
                    @if($materialIssue->vehicle_number)
                    | <span class="text-slate-400">Vehicle:</span> <span class="font-mono font-semibold">{{ $materialIssue->vehicle_number }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Dispatched Items Table -->
        <div class="mb-6">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900 text-white font-bold text-[10px] uppercase tracking-wider">
                        <th class="py-2.5 px-3 w-10 text-center">#</th>
                        <th class="py-2.5 px-3">Material Description</th>
                        <th class="py-2.5 px-3 text-center w-28">Item Code</th>
                        <th class="py-2.5 px-3 text-right w-28">Issued Quantity</th>
                        <th class="py-2.5 px-3 text-center w-20">Unit</th>
                        <th class="py-2.5 px-3 text-right w-28">Standard Rate</th>
                        <th class="py-2.5 px-3 text-right w-32">Total Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($materialIssue->items as $index => $item)
                    <tr class="{{ $index % 2 === 1 ? 'bg-slate-50/50' : 'bg-white' }}">
                        <td class="py-2.5 px-3 font-mono text-center text-slate-500">{{ $index + 1 }}</td>
                        <td class="py-2.5 px-3 text-slate-900 font-medium">
                            {{ $item->material->name }}
                            @if($item->remarks)
                            <span class="block text-[10px] text-slate-500 italic mt-0.5">{{ $item->remarks }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-center font-mono text-slate-500">{{ $item->material->code }}</td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">{{ number_format($item->issued_quantity, 3) }}</td>
                        <td class="py-2.5 px-3 text-center text-slate-600 uppercase text-[11px]">{{ $item->unit_of_measure }}</td>
                        <td class="py-2.5 px-3 text-right font-mono text-slate-700">₹{{ number_format($item->unit_rate, 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 border-t-2 border-slate-900 font-bold text-xs">
                        <td colspan="6" class="py-2.5 px-3 text-right text-slate-800">Total Material Valuation:</td>
                        <td class="py-2.5 px-3 text-right font-mono text-blue-700 font-extrabold">₹{{ number_format($materialIssue->total_cost, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($materialIssue->remarks)
        <div class="mb-8 p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
            <span class="font-bold text-slate-700 block mb-0.5">Remarks / Work Allocation:</span>
            <p class="text-slate-600">{{ $materialIssue->remarks }}</p>
        </div>
        @endif

        <!-- Gate Pass & Verification Block -->
        <div class="mb-8 p-3 bg-slate-50 rounded-xl border border-dashed border-slate-300 text-[11px] text-slate-600">
            <div class="font-bold text-slate-800 uppercase tracking-wider mb-1 text-[10px]">Gate Pass &amp; Dispatch Clearance</div>
            <div>The above materials have been inspected, weighed/counted, and dispatched in sound commercial packaging from {{ $materialIssue->warehouse->name }}. Consignee is instructed to verify quantities on unloading.</div>
        </div>

        <!-- 3-Way Signatures -->
        <div class="pt-6 border-t border-slate-200 grid grid-cols-3 gap-6 text-xs text-center">
            <div>
                <div class="h-14 flex items-center justify-center text-slate-700 font-medium text-xs">
                    {{ $materialIssue->issuer->name ?? 'Storekeeper' }}
                </div>
                <div class="border-t border-slate-300 pt-1 font-semibold text-slate-800">Dispatched By</div>
                <div class="text-[10px] text-slate-400">Warehouse Storekeeper</div>
            </div>

            <div>
                <div class="h-14 flex items-center justify-center text-slate-700 font-medium text-xs font-mono">
                    {{ $materialIssue->vehicle_number ?? 'Internal Transfer' }}
                </div>
                <div class="border-t border-slate-300 pt-1 font-semibold text-slate-800">Carrier / Transporter</div>
                <div class="text-[10px] text-slate-400">Driver Signature</div>
            </div>

            <div>
                <div class="h-14 flex items-center justify-center text-slate-700 font-medium text-xs">
                    {{ $materialIssue->received_by_name ?? 'Site Supervisor' }}
                </div>
                <div class="border-t border-slate-300 pt-1 font-semibold text-slate-800">Received By</div>
                <div class="text-[10px] text-slate-400">Site Engineer / Supervisor</div>
            </div>
        </div>

    </div>
</body>
</html>
