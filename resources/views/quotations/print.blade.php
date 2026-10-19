<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation {{ $quotation->quotation_number }} — NSD Earth Movers & Construction Ltd</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { font-size: 11pt; color: #0f172a; background: #ffffff !important; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            @page { margin: 12mm 15mm; size: A4 portrait; }
        }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 p-4 sm:p-8">

    <!-- Print Control Bar -->
    <div class="max-w-4xl mx-auto mb-6 flex items-center justify-between no-print bg-white p-4 rounded-xl shadow-xs border border-slate-200">
        <div class="flex items-center gap-3">
            <a href="{{ route('quotations.show', $quotation) }}" class="text-xs font-semibold text-blue-600 hover:underline">
                ← Back to Quotation Details
            </a>
            <span class="text-slate-300">|</span>
            <span class="text-xs text-slate-600 font-mono font-bold">{{ $quotation->quotation_number }}</span>
        </div>
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>Print Commercial Estimate</span>
        </button>
    </div>

    <!-- Printable Quotation Sheet -->
    <div class="max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl shadow-sm border border-slate-200">

        <!-- Corporate Header / Letterhead -->
        <div class="border-b-2 border-slate-900 pb-6 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-blue-700 text-white flex items-center justify-center font-bold text-xl shadow-xs">
                            NSD
                        </div>
                        <div>
                            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">{{ $company->name ?? 'NSD EARTH MOVERS & CONSTRUCTION LTD' }}</h1>
                            <p class="text-xs font-semibold text-slate-600">{{ $company->legal_name ?? 'Heavy Civil Engineering, Earthworks & Infrastructure Contractors' }}</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-3 space-y-0.5">
                        <p>{{ $company->address ?? 'Registered Office Address Pending Formal Verification' }}</p>
                        <p>
                            <span class="font-semibold text-slate-700">Phone:</span> {{ $company->phone ?? 'Phone Not Specified' }} | 
                            <span class="font-semibold text-slate-700">Email:</span> {{ $company->email ?? 'Email Not Specified' }}
                        </p>
                        <p>
                            <span class="font-semibold text-slate-700">GSTIN:</span> {{ $company->gstin ?? 'Not Available / Unregistered' }} | 
                            <span class="font-semibold text-slate-700">PAN:</span> {{ $company->pan ?? 'Not Available' }}
                        </p>
                    </div>
                </div>

                <div class="sm:text-right bg-slate-50 p-4 rounded-xl border border-slate-200/80 min-w-[220px]">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Commercial Quotation</div>
                    <div class="text-lg font-mono font-extrabold text-blue-700 mt-0.5">{{ $quotation->quotation_number }}</div>
                    <div class="text-[11px] text-slate-600 mt-2 space-y-0.5">
                        <div><span class="text-slate-400">Date:</span> <span class="font-semibold text-slate-800">{{ $quotation->date->format('d/m/Y') }}</span></div>
                        <div><span class="text-slate-400">Valid Until:</span> <span class="font-semibold text-slate-800">{{ $quotation->valid_until->format('d/m/Y') }}</span></div>
                        <div><span class="text-slate-400">Status:</span> <span class="font-semibold uppercase text-slate-800">{{ $quotation->status }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Addressed Client & Project Title -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 p-4 bg-slate-50/50 rounded-xl border border-slate-100">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">To Client:</span>
                <div class="text-sm font-bold text-slate-900">{{ $quotation->client->name }}</div>
                <div class="text-xs text-slate-600 font-medium">{{ $quotation->client->company_name ?? 'Client Organization' }}</div>
                @if($quotation->client->contact_person)
                <div class="text-xs text-slate-500 mt-1">Attn: {{ $quotation->client->contact_person }}</div>
                @endif
                <div class="text-[11px] text-slate-500 mt-1">
                    @if($quotation->client->gstin)
                    <span class="font-mono">GSTIN: {{ $quotation->client->gstin }}</span>
                    @endif
                    @if($quotation->client->pan)
                    | <span class="font-mono">PAN: {{ $quotation->client->pan }}</span>
                    @endif
                </div>
            </div>

            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Subject / Project Title:</span>
                <div class="text-sm font-bold text-slate-900 leading-snug">{{ $quotation->project_title }}</div>
                @if($quotation->project_description)
                <div class="text-xs text-slate-600 mt-1 line-clamp-3 leading-relaxed">{{ $quotation->project_description }}</div>
                @endif
            </div>
        </div>

        <!-- Schedule of Quantities Table -->
        <div class="mb-6">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900 text-white font-bold text-[10px] uppercase tracking-wider">
                        <th class="py-2.5 px-3 w-10 text-center">#</th>
                        <th class="py-2.5 px-3">Item Description</th>
                        <th class="py-2.5 px-3 text-right w-24">Quantity</th>
                        <th class="py-2.5 px-3 text-center w-20">Unit</th>
                        <th class="py-2.5 px-3 text-right w-28">Rate (₹)</th>
                        <th class="py-2.5 px-3 text-right w-32">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($quotation->items as $index => $item)
                    <tr class="{{ $index % 2 === 1 ? 'bg-slate-50/50' : 'bg-white' }}">
                        <td class="py-2.5 px-3 font-mono text-center text-slate-500">{{ $index + 1 }}</td>
                        <td class="py-2.5 px-3 text-slate-900 font-medium">
                            {{ $item->description }}
                            @if($item->notes)
                            <span class="block text-[10px] text-slate-500 italic mt-0.5">{{ $item->notes }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono text-slate-800">{{ number_format($item->quantity, 3) }}</td>
                        <td class="py-2.5 px-3 text-center text-slate-600 uppercase text-[11px]">{{ $item->unit }}</td>
                        <td class="py-2.5 px-3 text-right font-mono text-slate-800">{{ number_format($item->unit_rate, 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">{{ number_format($item->amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Commercial Calculation Breakdown -->
        <div class="flex justify-end mb-6">
            <div class="w-full sm:w-80 space-y-2 text-xs border border-slate-200 rounded-xl p-4 bg-slate-50/50">
                <div class="flex justify-between text-slate-600">
                    <span>Taxable Subtotal:</span>
                    <span class="font-mono font-semibold text-slate-900">₹{{ number_format($quotation->subtotal, 2) }}</span>
                </div>

                @if($quotation->discount_amount > 0)
                <div class="flex justify-between text-rose-600">
                    <span>Discount {{ $quotation->discount_type === 'percentage' ? "({$quotation->discount_rate}%)" : '' }}:</span>
                    <span class="font-mono font-semibold">-₹{{ number_format($quotation->discount_amount, 2) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-slate-700 pt-1 border-t border-slate-200">
                    <span>Net Taxable:</span>
                    <span class="font-mono text-slate-900">₹{{ number_format($quotation->subtotal - $quotation->discount_amount, 2) }}</span>
                </div>
                @endif

                @if($quotation->tax_type === 'gst_18')
                <div class="flex justify-between text-slate-600">
                    <span>CGST @ 9.0%:</span>
                    <span class="font-mono">₹{{ number_format($quotation->tax_amount / 2, 2) }}</span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>SGST @ 9.0%:</span>
                    <span class="font-mono">₹{{ number_format($quotation->tax_amount / 2, 2) }}</span>
                </div>
                @elseif($quotation->tax_amount > 0)
                <div class="flex justify-between text-slate-600">
                    <span>Tax ({{ $quotation->tax_rate }}%):</span>
                    <span class="font-mono">₹{{ number_format($quotation->tax_amount, 2) }}</span>
                </div>
                @else
                <div class="flex justify-between text-slate-500 italic">
                    <span>Taxes:</span>
                    <span>Exempt / Zero-Rated</span>
                </div>
                @endif

                <div class="pt-2 border-t-2 border-slate-900 flex justify-between items-baseline">
                    <span class="font-extrabold text-slate-900 text-sm">Grand Total (INR):</span>
                    <span class="text-base font-extrabold font-mono text-blue-700">₹{{ number_format($quotation->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Bank Details & Terms & Conditions -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8 text-xs">
            @php
                $bankDetails = $company->settings['bank_details'] ?? null;
            @endphp
            @if($bankDetails && !empty($bankDetails['account_number']))
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Bank Payment Details:</span>
                <div class="text-[11px] text-slate-700 space-y-0.5 font-mono">
                    <p><span class="text-slate-500 font-sans">Beneficiary:</span> {{ $bankDetails['beneficiary_name'] ?? $company->legal_name ?? $company->name }}</p>
                    <p><span class="text-slate-500 font-sans">Bank:</span> {{ $bankDetails['bank_name'] ?? '—' }}</p>
                    <p><span class="text-slate-500 font-sans">Account No:</span> {{ $bankDetails['account_number'] ?? '—' }}</p>
                    <p><span class="text-slate-500 font-sans">IFSC Code:</span> {{ $bankDetails['ifsc'] ?? '—' }}</p>
                    @if(!empty($bankDetails['branch']))
                    <p><span class="text-slate-500 font-sans">Branch:</span> {{ $bankDetails['branch'] }}</p>
                    @endif
                </div>
            </div>
            @else
            <div class="p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Bank Payment Details:</span>
                <p class="text-[11px] text-slate-500 italic mt-1">Official bank remittance details will be furnished on formal commercial award or verified proforma invoice.</p>
            </div>
            @endif

            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Commercial Terms:</span>
                <div class="text-[11px] text-slate-700 whitespace-pre-line leading-relaxed">
                    {{ $quotation->terms_conditions ?? "1. Rates are valid for 30 calendar days from issue date.\n2. Payment terms: 30 days from invoice date.\n3. Mobilization advance: 10% on work order execution." }}
                </div>
            </div>
        </div>

        <!-- Authorized Signatures -->
        <div class="pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs text-center">
            <div>
                <div class="h-16"></div>
                <div class="border-t border-slate-300 pt-2 font-semibold text-slate-800">Client Acceptance Signature</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Authorized Signatory / Seal</div>
            </div>

            <div>
                <div class="h-16 flex items-center justify-center text-slate-700 font-serif italic text-sm">
                    {{ $quotation->creator->name ?? 'Authorized Representative' }}
                </div>
                <div class="border-t border-slate-300 pt-2 font-semibold text-slate-800">For {{ $company->name ?? 'NSD Earth Movers & Construction Ltd' }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">{{ $quotation->creator ? ($quotation->creator->currentRole()?->name ?? 'Authorized Signatory') : 'Authorized Signatory' }}</div>
            </div>
        </div>

    </div>
</body>
</html>
