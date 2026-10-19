<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user', 'role_id', 'user_id');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->slug === 'owner' || $this->slug === 'admin') {
            return true;
        }

        $perms = $this->permissions ?? [];
        if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
            return true;
        }

        foreach ($perms as $p) {
            if (str_ends_with($p, '.*')) {
                $prefix = substr($p, 0, -2);
                if (str_starts_with($permission, $prefix . '.') || $permission === $prefix) {
                    return true;
                }
            }
        }

        return false;
    }
}
