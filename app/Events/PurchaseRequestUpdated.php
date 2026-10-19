<?php

namespace App\Events;

use App\Models\PurchaseRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public PurchaseRequest $purchaseRequest;

    public function __construct(PurchaseRequest $purchaseRequest)
    {
        $this->purchaseRequest = $purchaseRequest;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('company.' . $this->purchaseRequest->company_id),
        ];

        if ($this->purchaseRequest->requested_by) {
            $channels[] = new PrivateChannel('App.Models.User.' . $this->purchaseRequest->requested_by);
        }

        if ($this->purchaseRequest->project_id) {
            $channels[] = new PrivateChannel('project.' . $this->purchaseRequest->project_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'PurchaseRequestUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->purchaseRequest->id,
            'company_id' => $this->purchaseRequest->company_id,
            'pr_number' => $this->purchaseRequest->pr_number,
            'title' => $this->purchaseRequest->title,
            'status' => $this->purchaseRequest->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->purchaseRequest->status)),
            'current_approval_level' => $this->purchaseRequest->current_approval_level,
            'project_name' => $this->purchaseRequest->project?->name ?? 'General',
            'updated_at' => $this->purchaseRequest->updated_at?->toISOString() ?? now()->toISOString(),
            'updated_at_human' => $this->purchaseRequest->updated_at?->diffForHumans() ?? 'Just now',
        ];
    }
}
