<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'category_id');
    }
}
