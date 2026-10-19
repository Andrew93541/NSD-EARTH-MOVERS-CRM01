<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->project->id),
            new PrivateChannel('company.' . $this->project->company_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProjectStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->project->id,
            'company_id' => $this->project->company_id,
            'name' => $this->project->name,
            'code' => $this->project->code,
            'status' => $this->project->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->project->status)),
            'progress_percent' => (int) $this->project->progress_percent,
            'updated_at' => $this->project->updated_at?->toISOString() ?? now()->toISOString(),
            'updated_at_human' => $this->project->updated_at?->diffForHumans() ?? 'Just now',
        ];
    }
}
