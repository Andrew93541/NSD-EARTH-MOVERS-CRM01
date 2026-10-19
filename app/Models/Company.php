<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public static function configuredCompany(): ?Company
    {
        $slug = config('company.default_slug', 'nsd-earth-movers');
        $name = config('company.default_name', 'NSD Earth Movers & Construction Ltd');

        $company = static::where('slug', $slug)->first()
            ?? ($name ? static::where('name', 'like', '%' . $name . '%')->first() : null);

        if ($company) {
            return $company;
        }

        // In testing environment only, provide fallback if legacy multi-company mode is explicitly enabled
        if (app()->environment('testing') && ! config('company.single_company_mode', true)) {
            return static::orderBy('id')->first();
        }

        return null;
    }

    public static function configuredCompanyId(): ?int
    {
        return static::configuredCompany()?->id;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot('role_id', 'status', 'is_primary')
            ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function clientInvoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
