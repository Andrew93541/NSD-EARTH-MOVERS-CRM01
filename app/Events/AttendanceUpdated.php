<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public int $companyId;
    public int $siteId;
    public string $siteName;
    public string $date;
    public int $recordsCount;

    public function __construct(
        int $companyId,
        int $siteId,
        string $siteName,
        string $date,
        int $recordsCount = 1
    ) {
        $this->companyId = $companyId;
        $this->siteId = $siteId;
        $this->siteName = $siteName;
        $this->date = $date;
        $this->recordsCount = $recordsCount;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('site.' . $this->siteId),
            new PrivateChannel('company.' . $this->companyId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AttendanceUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->companyId,
            'site_id' => $this->siteId,
            'site_name' => $this->siteName,
            'date' => $this->date,
            'records_count' => $this->recordsCount,
            'updated_at' => now()->toISOString(),
        ];
    }
}
