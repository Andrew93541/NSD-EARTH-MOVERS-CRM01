<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'initials',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            return Storage::disk('public')->url($this->avatar);
        }
        return null;
    }

    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name));
        $initials = '';
        if (! empty($words[0])) {
            $initials .= mb_substr($words[0], 0, 1);
        }
        if (! empty($words[1])) {
            $initials .= mb_substr($words[1], 0, 1);
        } elseif (mb_strlen($words[0] ?? '') > 1) {
            $initials .= mb_substr($words[0], 1, 1);
        }
        return strtoupper($initials ?: 'U');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role_id', 'status', 'is_primary')
            ->withTimestamps();
    }

    public function currentCompany(): ?Company
    {
        if (config('company.single_company_mode', true)) {
            $configured = Company::configuredCompany();
            if ($configured) {
                $userCompany = $this->companies()
                    ->where('companies.id', $configured->id)
                    ->wherePivot('status', 'active')
                    ->first();
                if ($userCompany) {
                    session(['current_company_id' => $userCompany->id]);
                    return $userCompany;
                }
            }

            session()->forget('current_company_id');
            return null;
        }

        $companyId = session('current_company_id');
        if ($companyId) {
            $company = $this->companies()
                ->where('companies.id', $companyId)
                ->wherePivot('status', 'active')
                ->first();
            if ($company) {
                return $company;
            }
        }

        $primary = $this->companies()
            ->wherePivot('is_primary', true)
            ->wherePivot('status', 'active')
            ->first();
        if ($primary) {
            session(['current_company_id' => $primary->id]);
            return $primary;
        }

        $first = $this->companies()
            ->wherePivot('status', 'active')
            ->first();
        if ($first) {
            session(['current_company_id' => $first->id]);
            return $first;
        }

        session()->forget('current_company_id');
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
