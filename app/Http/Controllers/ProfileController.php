<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile and account settings.
     */
    public function show(Request $request): View
    {
        $user = Auth::user();
        $company = $user->currentCompany();
        $role = $user->currentRole();
        $roleSlug = $role?->slug ?? '';

        $assignedProjects = collect();
        $assignedSites = collect();
        $permittedScope = 'Standard Access';

        if ($company) {
            if ($user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin'], true)) {
                $permittedScope = 'Full Company & Executive Authority';
            } elseif ($roleSlug === 'project_manager') {
                $assignedProjects = Project::where('company_id', $company->id)
                    ->where(function ($q) use ($user) {
                        $q->where('manager_id', $user->id)
                          ->orWhere('assigned_team', 'like', "%\"{$user->id}\"%")
                          ->orWhere('assigned_team', 'like', "%{$user->id}%");
                    })
                    ->orderBy('name')
                    ->get();
                $permittedScope = $assignedProjects->count() . ' Assigned Projects';
            } elseif ($roleSlug === 'site_supervisor' || str_contains($roleSlug, 'supervisor')) {
                $assignedSites = Site::where('company_id', $company->id)
                    ->where('supervisor_id', $user->id)
                    ->with('project')
                    ->orderBy('name')
                    ->get();
                $permittedScope = $assignedSites->count() . ' Assigned Sites';
            } else {
                $permittedScope = $role?->name ?? 'Operational Member';
            }
        }

        $activeTab = $request->query('tab', 'overview');

        return view('profile.index', compact(
            'user',
            'company',
            'role',
            'permittedScope',
            'assignedProjects',
            'assignedSites',
            'activeTab'
        ));
    }

    /**
     * Update user's permitted profile information.
     * Prevents self-escalation (cannot update email, role, or company).
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            // Delete previous avatar file if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->name = $validated['name'];
        $user->phone = $validated['phone'] ?? null;
        $user->save();

        // Synchronize phone with linked employee profile if exists
        if ($user->employee) {
            $user->employee->update(['phone' => $user->phone]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Profile information updated successfully.',
                'user' => [
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_url,
                    'initials' => $user->initials,
                ],
            ]);
        }

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }

    /**
     * Update user password with current password verification.
     */
    public function updatePassword(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Your password has been securely changed.',
            ]);
        }

        return redirect()->route('profile.show', ['tab' => 'security'])->with('success', 'Password updated successfully.');
    }

    /**
     * Update persistent user preferences (theme, notifications).
     */
    public function updatePreferences(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'theme' => 'required|in:light,dark,system',
            'notifications_email' => 'nullable|boolean',
            'notifications_browser' => 'nullable|boolean',
        ]);

        $existing = $user->preferences ?? [];
        $user->preferences = array_merge($existing, [
            'theme' => $validated['theme'],
            'notifications_email' => (bool) ($validated['notifications_email'] ?? false),
            'notifications_browser' => (bool) ($validated['notifications_browser'] ?? true),
        ]);
        $user->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Display and notification preferences saved.',
                'preferences' => $user->preferences,
            ]);
        }

        return redirect()->route('profile.show', ['tab' => 'preferences'])->with('success', 'Preferences saved.');
    }
}
