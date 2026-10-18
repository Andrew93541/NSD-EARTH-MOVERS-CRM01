<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// 1. User-specific private channel: enforces strictly that user can only listen to their own channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Alias for user channel
Broadcast::channel('user.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

// 2. Company tenant channel: enforces that the user has an active membership in the specified company
Broadcast::channel('company.{companyId}', function (User $user, $companyId) {
    return $user->companies()
        ->where('companies.id', (int) $companyId)
        ->wherePivot('status', 'active')
        ->exists();
});

// 3. Company role channel: restricts to users possessing specific active roles within the company
Broadcast::channel('company.{companyId}.role.{roleSlug}', function (User $user, $companyId, $roleSlug) {
    $membership = $user->companies()
        ->where('companies.id', (int) $companyId)
        ->wherePivot('status', 'active')
        ->first();

    if (! $membership || ! $membership->pivot->role_id) {
        return false;
    }

    $role = Role::find($membership->pivot->role_id);
    if (! $role) {
        return false;
    }

    if (in_array($role->slug, ['owner', 'admin'], true)) {
        return true;
    }

    return $role->slug === $roleSlug;
});

// 4. Project channel: restricts to authorized project managers, site supervisors, and admins
Broadcast::channel('project.{projectId}', function (User $user, $projectId) {
    $project = Project::find($projectId);
    if (! $project) {
        return false;
    }

    $membership = $user->companies()
        ->where('companies.id', $project->company_id)
        ->wherePivot('status', 'active')
        ->first();

    if (! $membership) {
        return false;
    }

    if ((int) $project->manager_id === (int) $user->id) {
        return true;
    }

    $role = Role::find($membership->pivot->role_id);
    if ($role && (in_array($role->slug, ['owner', 'admin'], true) || $role->hasPermission('projects.*') || $role->hasPermission('projects.view'))) {
        return true;
    }

    return $project->sites()->where('supervisor_id', $user->id)->exists();
});

// 5. Site channel: restricts to assigned site supervisors, project managers, and company leadership
Broadcast::channel('site.{siteId}', function (User $user, $siteId) {
    $site = Site::with('project')->find($siteId);
    if (! $site) {
        return false;
    }

    $membership = $user->companies()
        ->where('companies.id', $site->company_id)
        ->wherePivot('status', 'active')
        ->first();

    if (! $membership) {
        return false;
    }

    if ((int) $site->supervisor_id === (int) $user->id) {
        return true;
    }

    if ($site->project && (int) $site->project->manager_id === (int) $user->id) {
        return true;
    }

    $role = Role::find($membership->pivot->role_id);
    return $role && (in_array($role->slug, ['owner', 'admin'], true) || $role->hasPermission('projects.*') || $role->hasPermission('attendance.*'));
});
