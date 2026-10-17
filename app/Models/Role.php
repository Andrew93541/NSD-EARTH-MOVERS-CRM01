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

    public function hasPermission(string $permission): bool
    {
        if ($this->slug === 'owner' || $this->slug === 'admin') {
            return true;
        }

        $perms = $this->permissions ?? [];
        return in_array($permission, $perms, true) || in_array('*', $perms, true);
    }
}
