<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryStructure extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'daily_wage_rate' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'hourly_overtime_rate' => 'decimal:2',
        'standard_allowance' => 'decimal:2',
        'statutory_deduction_percent' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
