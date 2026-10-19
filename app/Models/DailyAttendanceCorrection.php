<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAttendanceCorrection extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'previous_worked_hours' => 'decimal:2',
        'proposed_worked_hours' => 'decimal:2',
        'previous_overtime_hours' => 'decimal:2',
        'proposed_overtime_hours' => 'decimal:2',
        'actioned_at' => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(DailyAttendance::class, 'daily_attendance_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
