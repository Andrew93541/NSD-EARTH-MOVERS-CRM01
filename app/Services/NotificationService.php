<?php

namespace App\Services;

use App\Events\NotificationDispatched;
use App\Models\AppNotification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Dispatch and persist notification for a specific user.
     */
    public static function sendToUser(
        int $companyId,
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $actionUrl = null
    ): AppNotification {
        return AppNotification::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'action_url' => $actionUrl,
            'is_read' => false,
        ]);
    }

    /**
     * Dispatch and persist a tenant-isolated company notification.
     */
    public static function sendToCompany(
        int $companyId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $actionUrl = null
    ): AppNotification {
        return AppNotification::create([
            'company_id' => $companyId,
            'user_id' => null,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'action_url' => $actionUrl,
            'is_read' => false,
        ]);
    }

    /**
     * Dispatch notification to all active users with a specified role slug in the company.
     *
     * @return array<int, AppNotification>
     */
    public static function sendToRole(
        int $companyId,
        string $roleSlug,
        string $title,
        string $message,
        string $type = 'info',
        ?string $actionUrl = null
    ): array {
        $role = Role::where('slug', $roleSlug)->first();

        $query = User::whereHas('companies', function ($q) use ($companyId, $role, $roleSlug) {
            $q->where('companies.id', $companyId)
                ->wherePivot('status', 'active');
            if ($role) {
                $q->where(function ($sub) use ($role, $roleSlug) {
                    $sub->wherePivot('role_id', $role->id);
                });
            }
        });

        $users = $query->get();
        $notifications = [];

        foreach ($users as $user) {
            $notifications[] = static::sendToUser(
                $companyId,
                $user->id,
                $title,
                $message,
                $type,
                $actionUrl
            );
        }

        return $notifications;
    }

    /**
     * Broadcast notification after active DB transaction commits.
     * Keeps standard operations functional if Reverb is down.
     */
    public static function broadcastNotification(AppNotification $notification, array $extraChannels = []): void
    {
        $broadcast = function () use ($notification, $extraChannels) {
            try {
                broadcast(new NotificationDispatched($notification, $extraChannels));
            } catch (\Throwable $e) {
                Log::warning('WebSocket broadcast failed for notification #' . $notification->id . ': ' . $e->getMessage());
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($broadcast);
        } else {
            $broadcast();
        }
    }

    /**
     * Mark a single notification as read if authorized for the user.
     */
    public static function markAsRead(int $notificationId, User $user): bool
    {
        $notification = AppNotification::where('id', $notificationId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere(function ($cq) use ($user) {
                      $cq->whereNull('user_id')
                         ->whereIn('company_id', $user->companies()->wherePivot('status', 'active')->pluck('companies.id'));
                  });
            })
            ->first();

        if (! $notification) {
            return false;
        }

        $notification->update(['is_read' => true]);
        return true;
    }

    /**
     * Mark all notifications as read for the user in their active company.
     */
    public static function markAllAsRead(User $user, ?int $companyId = null): int
    {
        $companyId = $companyId ?: $user->currentCompany()?->id;

        $query = AppNotification::where('is_read', false)
            ->where(function ($q) use ($user, $companyId) {
                $q->where('user_id', $user->id);
                if ($companyId) {
                    $q->orWhere(function ($cq) use ($companyId) {
                        $cq->whereNull('user_id')
                           ->where('company_id', $companyId);
                    });
                }
            });

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->update(['is_read' => true]);
    }
}
