<?php

namespace App\Events;

use App\Models\PayrollPeriod;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public PayrollPeriod $period;

    public function __construct(PayrollPeriod $period)
    {
        $this->period = $period;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.' . $this->period->company_id . '.role.accountant'),
            new PrivateChannel('company.' . $this->period->company_id . '.role.owner'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PayrollStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->period->company_id,
            'period_id' => $this->period->id,
            'period_name' => $this->period->title,
            'status' => $this->period->status,
            'status_label' => ucfirst($this->period->status),
            'total_disbursement' => (float) ($this->period->total_disbursement ?? 0.0),
            'total_employees' => (int) $this->period->records()->count(),
            'updated_at' => $this->period->updated_at?->toISOString() ?? now()->toISOString(),
        ];
    }
}
