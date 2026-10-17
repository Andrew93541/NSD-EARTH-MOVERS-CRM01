<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role_id', 'status', 'is_primary')
            ->withTimestamps();
    }

    public function currentCompany(): ?Company
    {
        $companyId = session('current_company_id');
        if ($companyId) {
            return $this->companies()->where('companies.id', $companyId)->first();
        }

        $primary = $this->companies()->wherePivot('is_primary', true)->first();
        if ($primary) {
            session(['current_company_id' => $primary->id]);
            return $primary;
        }

        $first = $this->companies()->first();
        if ($first) {
            session(['current_company_id' => $first->id]);
            return $first;
        }

        return null;
    }

    public function currentRole(): ?Role
    {
        $currentCompany = $this->currentCompany();
        if (! $currentCompany) {
            return null;
        }

        $pivot = $this->companies()->where('companies.id', $currentCompany->id)->first()?->pivot;
        if (! $pivot || ! $pivot->role_id) {
            return null;
        }

        return Role::find($pivot->role_id);
    }

    public function hasPermission(string $permission): bool
    {
        $role = $this->currentRole();
        return $role ? $role->hasPermission($permission) : false;
    }
}
