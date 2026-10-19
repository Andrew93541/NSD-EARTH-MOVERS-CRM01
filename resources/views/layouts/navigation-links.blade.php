@php
    $u = auth()->user();
    $canClients = $u && ($u->hasPermission('*') || $u->hasPermission('clients.*') || $u->hasPermission('clients.view') || $u->hasPermission('projects.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'accountant']));
    $canQuotations = $u && ($u->hasPermission('*') || $u->hasPermission('quotations.*') || $u->hasPermission('quotations.view') || $u->hasPermission('projects.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'accountant']));
    $canProjects = $u && ($u->hasPermission('*') || $u->hasPermission('projects.*') || $u->hasPermission('projects.view'));
    $canSites = $u && ($u->hasPermission('*') || $u->hasPermission('sites.*') || $u->hasPermission('sites.view'));
    $canSuppliers = $u && ($u->hasPermission('*') || $u->hasPermission('suppliers.*') || $u->hasPermission('suppliers.view') || $u->hasPermission('procurement.*') || $u->hasPermission('materials.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'procurement_officer', 'storekeeper', 'accountant']));
    $canInventory = $u && ($u->hasPermission('*') || $u->hasPermission('inventory.*') || $u->hasPermission('materials.*') || $u->hasPermission('materials.request'));
    $canMaterialIssues = $u && ($u->hasPermission('*') || $u->hasPermission('inventory.*') || $u->hasPermission('materials.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'storekeeper', 'project_manager', 'procurement_officer']));
    $canMuster = $u && ($u->hasPermission('*') || $u->hasPermission('attendance.*') || $u->hasPermission('attendance.record') || $u->hasPermission('attendance.muster'));
    $canEmployees = $u && ($u->hasPermission('*') || $u->hasPermission('employees.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'hr_officer']));
    $canPayroll = $u && ($u->hasPermission('*') || $u->hasPermission('payroll.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'hr_officer', 'accountant']));

    $canBilling = $u && ($u->hasPermission('*') || $u->hasPermission('billing.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant']));
    $canRetention = $u && ($u->hasPermission('*') || $u->hasPermission('retention.*') || $u->hasPermission('retention.view') || $u->hasPermission('billing.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant', 'project_manager']));
    $canPR = $u && ($u->hasPermission('*') || $u->hasPermission('materials.request') || $u->hasPermission('procurement.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'site_supervisor', 'procurement_officer']));
    $canProcurement = $u && ($u->hasPermission('*') || $u->hasPermission('procurement.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'procurement_officer', 'storekeeper']));
    $canExpenses = $u && ($u->hasPermission('*') || $u->hasPermission('expenses.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant', 'project_manager']));
    $canLoans = $u && ($u->hasPermission('*') || $u->hasPermission('loans.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant']));
    $canAssets = $u && ($u->hasPermission('*') || $u->hasPermission('assets.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant', 'project_manager']));
    $canAccounting = $u && ($u->hasPermission('*') || $u->hasPermission('accounting.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant']));

    $canSubcontractors = $u && ($u->hasPermission('*') || $u->hasPermission('subcontractors.*') || $u->hasPermission('subcontractors.view') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant', 'procurement_officer']));
    $canWorkOrders = $u && ($u->hasPermission('*') || $u->hasPermission('work_orders.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant', 'procurement_officer']));
    $canMB = $u && ($u->hasPermission('*') || $u->hasPermission('measurement_book.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant']));
    $canSubconBills = $u && ($u->hasPermission('*') || $u->hasPermission('subcontractor_bills.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'accountant']));
@endphp

<nav class="space-y-6 text-xs" aria-label="Main Navigation">

    <!-- Group 1: Overview & Projects -->
    <div>
        <div class="px-3 mb-2 flex items-center justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">1. Overview & Projects</span>
        </div>
        <div class="space-y-1">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('dashboard', 'home') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Dashboard</span>
            </a>

            @if($canClients)
            <a href="{{ route('clients.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('clients.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Client Directory</span>
            </a>
            @endif

            @if($canQuotations)
            <a href="{{ route('quotations.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('quotations.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Quotations & Estimates</span>
            </a>
            @endif

            @if($canProjects)
            <a href="{{ route('projects.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('projects.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span>Projects & Contracts</span>
            </a>
            @endif

            @if($canSites)
            <a href="{{ route('sites.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('sites.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Site Operations</span>
            </a>
            @endif
        </div>
    </div>

    <!-- Group 2: Workforce & Wages -->
    @if($canMuster || $canEmployees || $canPayroll)
    <div>
        <div class="px-3 mb-2 flex items-center justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">2. Workforce & Wages</span>
        </div>
        <div class="space-y-1">
            @if($canMuster)
            <a href="{{ route('attendance.muster') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('attendance.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span>Daily Attendance (Muster)</span>
            </a>
            @endif

            @if($canEmployees)
            <a href="{{ route('employees.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('employees.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Employees & Labor</span>
            </a>
            @endif

            @if($canPayroll)
            <a href="{{ route('payroll.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('payroll.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Payroll & Wages</span>
            </a>
            @endif
        </div>
    </div>
    @endif

    <!-- Group 3: Materials & Equipment -->
    @if($canPR || $canProcurement || $canSuppliers || $canInventory || $canAssets)
    <div>
        <div class="px-3 mb-2 flex items-center justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">3. Materials & Equipment</span>
        </div>
        <div class="space-y-1">
            @if($canSuppliers)
            <a href="{{ route('suppliers.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('suppliers.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Material Suppliers</span>
            </a>
            @endif

            @if($canPR)
            <a href="{{ route('purchase-requests.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('purchase-requests.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Material Requests (PR)</span>
            </a>
            @endif

            @if($canProcurement)
            <a href="{{ route('procurement.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('procurement.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span>Purchase Orders & GRN</span>
            </a>
            @endif

            @if($canInventory)
            <a href="{{ route('inventory.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('inventory.*') && ! request()->routeIs('material-issues.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span>Stock & Inventory</span>
            </a>
            @endif

            @if($canMaterialIssues)
            <a href="{{ route('material-issues.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('material-issues.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Site Material Issues</span>
            </a>
            @endif

            @if($canAssets)
            <a href="{{ route('assets.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('assets.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Machinery & Fixed Assets</span>
            </a>
            @endif
        </div>
    </div>
    @endif

    <!-- Group 4: Subcontractors -->
    @if($canSubcontractors || $canWorkOrders || $canMB || $canSubconBills)
    <div>
        <div class="px-3 mb-2 flex items-center justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">4. Subcontractors</span>
        </div>
        <div class="space-y-1">
            @if($canSubcontractors)
            <a href="{{ route('subcontractors.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('subcontractors.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Subcontractor Master</span>
            </a>
            @endif

            @if($canWorkOrders)
            <a href="{{ route('work-orders.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('work-orders.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Work Orders (WO)</span>
            </a>
            @endif

            @if($canMB)
            <a href="{{ route('measurement-books.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('measurement-books.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>Measurement Book (MB)</span>
            </a>
            @endif

            @if($canSubconBills)
            <a href="{{ route('subcontractor-bills.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('subcontractor-bills.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/></svg>
                <span>Contractor RA Bills</span>
            </a>
            @endif
        </div>
    </div>
    @endif

    <!-- Group 5: Money & Billing -->
    @if($canBilling || $canRetention || $canExpenses || $canLoans || $canAccounting)
    <div>
        <div class="px-3 mb-2 flex items-center justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">5. Money & Billing</span>
        </div>
        <div class="space-y-1">
            @if($canBilling)
            <a href="{{ route('billing.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('billing.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/></svg>
                <span>Client Invoices & RA Bills</span>
            </a>
            @endif

            @if($canRetention)
            <a href="{{ route('retention.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('retention.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Client Retention & DLP</span>
            </a>
            @endif

            @if($canExpenses)
            <a href="{{ route('expenses.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('expenses.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Petty Cash & Expenses</span>
            </a>
            @endif

            @if($canLoans)
            <a href="{{ route('loans.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('loans.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Loans & EMI Payments</span>
            </a>
            @endif

            @if($canAccounting)
            <a href="{{ route('accounting.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('accounting.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>General Ledger & Books</span>
            </a>
            @endif
        </div>
    </div>
    @endif

    <!-- Group 6: Company & Team -->
    <div>
        <div class="px-3 mb-2 flex items-center justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">6. Company & Team</span>
        </div>
        <div class="space-y-1">
            <a href="{{ route('documents.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('documents.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Document Centre</span>
            </a>

            <a href="{{ route('notifications.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('notifications.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span>Notification Log</span>
            </a>

            <a href="{{ route('company.profile') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition {{ request()->routeIs('company.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Company Profile</span>
            </a>
        </div>
    </div>

</nav>
