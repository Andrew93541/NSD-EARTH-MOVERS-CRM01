<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'employee_id',
        'days_worked',
        'days_absent',
        'overtime_hours',
        'basic_pay',
        'allowances',
        'overtime_pay',
        'deductions',
        'net_pay',
        'status',
    ];

    protected $casts = [
        'days_worked' => 'integer',
        'days_absent' => 'integer',
        'overtime_hours' => 'decimal:2',
        'basic_pay' => 'decimal:2',
        'allowances' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
