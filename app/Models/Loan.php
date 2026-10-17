<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'annual_interest_rate' => 'decimal:2',
        'monthly_emi' => 'decimal:2',
        'outstanding_principal' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function emiSchedules(): HasMany
    {
        return $this->hasMany(LoanEmiSchedule::class)->orderBy('installment_number');
    }
}
