<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Display the full Notification Centre.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id') ?? $user->currentCompany()?->id;

        $query = AppNotification::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            });

        $filter = $request->query('filter', 'all');

        if ($filter === 'unread') {
            $query->where('is_read', false);
        } elseif ($filter === 'procurement') {
            $query->where(function ($q) {
                $q->where('title', 'like', '%Material%')
                  ->orWhere('title', 'like', '%Purchase%')
                  ->orWhere('title', 'like', '%Goods Receipt%')
                  ->orWhere('title', 'like', '%Stock%');
            });
        } elseif ($filter === 'attendance_payroll') {
            $query->where(function ($q) {
                $q->where('title', 'like', '%Payroll%')
                  ->orWhere('title', 'like', '%Muster%')
                  ->orWhere('title', 'like', '%Attendance%');
            });
        } elseif ($filter === 'billing_finance') {
            $query->where(function ($q) {
                $q->where('title', 'like', '%Invoice%')
                  ->orWhere('title', 'like', '%Bill%')
                  ->orWhere('title', 'like', '%Payment%')
                  ->orWhere('title', 'like', '%Retention%');
            });
        } elseif ($filter === 'projects') {
            $query->where(function ($q) {
                $q->where('title', 'like', '%Project%')
                  ->orWhere('title', 'like', '%Site%');
            });
        }

        $notifications = $query->latest()->paginate(15)->withQueryString();

        $unreadCount = AppNotification::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->where('is_read', false)
            ->count();

        $stats = [
            'total' => AppNotification::where('company_id', $companyId)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhereNull('user_id');
                })->count(),
            'unread' => $unreadCount,
            'procurement' => AppNotification::where('company_id', $companyId)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhereNull('user_id');
                })
                ->where(function ($q) {
                    $q->where('title', 'like', '%Material%')
                      ->orWhere('title', 'like', '%Purchase%')
                      ->orWhere('title', 'like', '%Goods Receipt%')
                      ->orWhere('title', 'like', '%Stock%');
                })->count(),
            'finance' => AppNotification::where('company_id', $companyId)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhereNull('user_id');
                })
                ->where(function ($q) {
                    $q->where('title', 'like', '%Invoice%')
                      ->orWhere('title', 'like', '%Bill%')
                      ->orWhere('title', 'like', '%Payment%')
                      ->orWhere('title', 'like', '%Retention%');
                })->count(),
        ];

        return view('notifications.index', compact('notifications', 'unreadCount', 'stats', 'filter'));
    }

    /**
     * Get unread count and latest notifications for live dropdown & reconnection polling.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id') ?? $user->currentCompany()?->id;

        $unreadCount = AppNotification::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->where('is_read', false)
            ->count();

        $latest = AppNotification::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'message' => $item->message,
                    'type' => $item->type,
                    'action_url' => $item->action_url,
                    'is_read' => (bool) $item->is_read,
                    'created_at' => $item->created_at?->toISOString(),
                    'created_at_human' => $item->created_at?->diffForHumans() ?? 'Just now',
                ];
            });

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $latest,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, AppNotification $notification): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id') ?? $user->currentCompany()?->id;

        // Tenant and recipient authorization check
        if ((int) $notification->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized notification tenant access.');
        }

        if ($notification->user_id !== null && (int) $notification->user_id !== (int) $user->id) {
            abort(403, 'Unauthorized notification user access.');
        }

        $notification->update(['is_read' => true]);

        $unreadCount = AppNotification::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->where('is_read', false)
            ->count();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => $unreadCount,
            ]);
        }

        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read for current user and company.
     */
    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? session('current_company_id') ?? $user->currentCompany()?->id;

        AppNotification::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
