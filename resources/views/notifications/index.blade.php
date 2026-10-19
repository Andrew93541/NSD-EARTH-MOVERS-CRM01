@extends('layouts.app', ['title' => 'Notification Centre - NSDCRM'])

@section('content')
<div class="space-y-6" id="notification-centre-container">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider bg-orange-100 text-orange-800 rounded-md border border-orange-200">
                    REAL-TIME COMMUNICATIONS
                </span>
                <span class="text-xs text-gray-500 font-medium">NSD Precision Ops</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                <span>Notification Centre</span>
                @if($unreadCount > 0)
                    <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-orange-600 text-white shadow-sm">
                        {{ $unreadCount }} Unread
                    </span>
                @endif
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Real-time operational alerts, procurement approvals, payroll audits, and financial transactions across your company projects.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($unreadCount > 0)
            <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                @csrf
                <button type="submit" id="mark-all-read-btn" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-2 shadow-xs transition cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Mark All as Read</span>
                </button>
            </form>
            @endif
            <a href="{{ route('dashboard') }}" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Metric Overview Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">TOTAL LOGGED</span>
            <div class="text-2xl font-bold text-slate-900 mt-1 tabular-nums">{{ $stats['total'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                <span>Active company audit events</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-orange-600 uppercase tracking-wider block">PENDING ACTION</span>
            <div id="centre-unread-counter" class="text-2xl font-bold text-orange-600 mt-1 tabular-nums">{{ $stats['unread'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                <span>Unread notifications</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-blue-600 uppercase tracking-wider block">PROCUREMENT & STOCKS</span>
            <div class="text-2xl font-bold text-blue-600 mt-1 tabular-nums">{{ $stats['procurement'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                <span>PRs, POs & GRN updates</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block">FINANCE & BILLING</span>
            <div class="text-2xl font-bold text-emerald-600 mt-1 tabular-nums">{{ $stats['finance'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Invoices, receipts & claims</span>
            </div>
        </div>
    </div>

    <!-- Filter Tabs & Main Content Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <!-- Filter Bar -->
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 bg-slate-50/70 overflow-x-auto">
            <div class="flex items-center gap-1.5">
                <a href="{{ route('notifications.index', ['filter' => 'all']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $filter === 'all' ? 'bg-white text-slate-900 border border-slate-300 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    All Events
                </a>
                <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $filter === 'unread' ? 'bg-white text-slate-900 border border-slate-300 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    <span>Unread</span>
                    @if($unreadCount > 0)
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">
                        {{ $unreadCount }}
                    </span>
                    @endif
                </a>
                <a href="{{ route('notifications.index', ['filter' => 'procurement']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $filter === 'procurement' ? 'bg-white text-slate-900 border border-slate-300 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    Procurement & PR
                </a>
                <a href="{{ route('notifications.index', ['filter' => 'attendance_payroll']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $filter === 'attendance_payroll' ? 'bg-white text-slate-900 border border-slate-300 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    Muster & Payroll
                </a>
                <a href="{{ route('notifications.index', ['filter' => 'billing_finance']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $filter === 'billing_finance' ? 'bg-white text-slate-900 border border-slate-300 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    Billing & Finance
                </a>
                <a href="{{ route('notifications.index', ['filter' => 'projects']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $filter === 'projects' ? 'bg-white text-slate-900 border border-slate-300 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    Projects & Sites
                </a>
            </div>

            <div class="text-xs text-slate-500 font-medium whitespace-nowrap pl-4">
                Showing {{ $notifications->firstItem() ?? 0 }} - {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }}
            </div>
        </div>

        <!-- Notifications List -->
        <div class="divide-y divide-slate-100">
            @forelse($notifications as $notification)
                <div class="notification-card p-4 transition flex items-start gap-4 {{ ! $notification->is_read ? 'bg-orange-50/20 border-l-4 border-l-orange-500' : 'hover:bg-slate-50/60' }}">
                    <!-- Status Icon Badge -->
                    <div class="mt-0.5 flex-shrink-0">
                        @if($notification->type === 'success')
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        @elseif($notification->type === 'danger')
                            <div class="w-8 h-8 rounded-lg bg-red-100 border border-red-200 flex items-center justify-center text-red-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                        @elseif($notification->type === 'warning')
                            <div class="w-8 h-8 rounded-lg bg-amber-100 border border-amber-200 flex items-center justify-center text-amber-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        @else
                            <div class="w-8 h-8 rounded-lg bg-blue-100 border border-blue-200 flex items-center justify-center text-blue-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        @endif
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-sm font-bold text-slate-900 {{ ! $notification->is_read ? 'text-slate-900 font-extrabold' : 'text-slate-700 font-semibold' }}">
                                {{ $notification->title }}
                            </h2>
                            @if(! $notification->is_read)
                                <span class="px-1.5 py-0.5 text-[10px] font-bold uppercase rounded-md bg-orange-100 text-orange-800 border border-orange-200">
                                    New
                                </span>
                            @endif
                            <span class="text-slate-400 text-xs">•</span>
                            <span class="text-xs text-slate-500 tabular-nums">
                                {{ $notification->created_at->format('d M Y, h:i A') }}
                            </span>
                            <span class="text-xs text-slate-400">
                                ({{ $notification->created_at->diffForHumans() }})
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                            {{ $notification->message }}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($notification->action_url)
                            <a href="{{ $notification->action_url }}"
                               class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold flex items-center gap-1 shadow-xs transition">
                                <span>Open Record</span>
                                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        @endif

                        @if(! $notification->is_read)
                            <form action="{{ route('notifications.read', $notification) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        title="Mark as read"
                                        class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center mx-auto text-slate-400 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">No notifications found</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        @if($filter !== 'all')
                            No events match the selected filter. Try selecting 'All Events' to review historical notifications.
                        @else
                            You are fully caught up with all operational workflows and company communications.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Pagination Footer -->
        @if($notifications->hasPages())
        <div class="px-4 py-3 border-t border-slate-200 bg-slate-50/50">
            {{ $notifications->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
