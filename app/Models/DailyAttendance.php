<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAttendance extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'daily_attendance';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'worked_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'is_overtime_approved' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function overtimeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overtime_approved_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function corrections()
    {
        return $this->hasMany(DailyAttendanceCorrection::class, 'daily_attendance_id');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }
}
