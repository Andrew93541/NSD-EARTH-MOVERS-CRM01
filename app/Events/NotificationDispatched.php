<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationDispatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Determines whether the broadcast should wait for transaction commit.
     */
    public bool $afterCommit = true;

    /**
     * The notification instance.
     */
    public AppNotification $notification;

    /**
     * Optional custom channel names to broadcast on.
     *
     * @var array<int, Channel>
     */
    public array $extraChannels;

    /**
     * Create a new event instance.
     *
     * @param  array<int, Channel>  $extraChannels
     */
    public function __construct(AppNotification $notification, array $extraChannels = [])
    {
        $this->notification = $notification;
        $this->extraChannels = $extraChannels;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // If target user is defined, broadcast to the user's private channel
        if ($this->notification->user_id) {
            $channels[] = new PrivateChannel('App.Models.User.' . $this->notification->user_id);
        } else {
            // Broad company-wide notification strictly isolated to the company tenant
            $channels[] = new PrivateChannel('company.' . $this->notification->company_id);
        }

        foreach ($this->extraChannels as $ch) {
            $channels[] = $ch;
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'NotificationDispatched';
    }

    /**
     * Minimal, non-sensitive payload data to broadcast over WebSocket.
     * Sensitive details (payroll salaries, bank accounts, confidential unit rates)
     * are never broadcast on the channel. Full authorized details are retrieved
     * via authenticated HTTP endpoints.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'company_id' => $this->notification->company_id,
            'user_id' => $this->notification->user_id,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'type' => $this->notification->type,
            'action_url' => $this->notification->action_url,
            'is_read' => (bool) $this->notification->is_read,
            'created_at' => $this->notification->created_at?->toISOString() ?? now()->toISOString(),
            'created_at_human' => $this->notification->created_at?->diffForHumans() ?? 'Just now',
        ];
    }
}
