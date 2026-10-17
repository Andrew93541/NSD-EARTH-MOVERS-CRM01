<?php

namespace App\Models\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && session()->has('current_company_id')) {
                $model->company_id = session()->get('current_company_id');
            }
        });

        static::addGlobalScope('company_scope', function (Builder $builder) {
            if (session()->has('current_company_id')) {
                $builder->where($builder->getModel()->getTable() . '.company_id', session()->get('current_company_id'));
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
