<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Role $ownerRole;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'legal_name' => 'NSD Earth Movers & Construction Limited',
            'slug' => 'nsd-earth-movers',
            'code' => 'NSD',
            'status' => 'active',
        ]);

        config(['company.default_company_id' => $this->company->id]);
        config(['company.single_company_mode' => true]);

        $this->ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Dharani Sundaram',
            'email' => 'dharani@nsdearthmovers.com',
            'password' => Hash::make('Secret123!'),
            'phone' => '+91 98765 43210',
        ]);

        $this->user->companies()->attach($this->company->id, [
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    public function test_guest_cannot_access_profile(): void
    {
        $response = $this->get('/profile');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('Dharani Sundaram');
        $response->assertSee('dharani@nsdearthmovers.com');
        $response->assertSee('+91 98765 43210');
        $response->assertSee('Account Settings');
    }

    public function test_user_can_update_name_and_phone_via_ajax(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile', [
                'name' => 'Dharani S. (Managing Director)',
                'phone' => '+91 99999 88888',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'user' => [
                'name' => 'Dharani S. (Managing Director)',
                'phone' => '+91 99999 88888',
            ],
        ]);

        $this->user->refresh();
        $this->assertEquals('Dharani S. (Managing Director)', $this->user->name);
        $this->assertEquals('+91 99999 88888', $this->user->phone);
    }

    public function test_user_can_upload_avatar_and_old_avatar_is_cleaned_up(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->image('avatar1.jpg', 200, 200);

        $response1 = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->put('/profile', [
                'name' => 'Dharani Sundaram',
                'avatar' => $file1,
            ]);

        $response1->assertRedirect('/profile');
        $this->user->refresh();

        $this->assertNotNull($this->user->avatar);
        Storage::disk('public')->assertExists($this->user->avatar);
        $oldAvatar = $this->user->avatar;

        // Upload second avatar
        $file2 = UploadedFile::fake()->image('avatar2.png', 300, 300);
        $response2 = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->put('/profile', [
                'name' => 'Dharani Sundaram',
                'avatar' => $file2,
            ]);

        $response2->assertRedirect('/profile');
        $this->user->refresh();

        $this->assertNotEquals($oldAvatar, $this->user->avatar);
        Storage::disk('public')->assertExists($this->user->avatar);
        Storage::disk('public')->assertMissing($oldAvatar);
    }

    public function test_avatar_validation_rejects_oversized_files_or_invalid_mimes(): void
    {
        Storage::fake('public');

        // Text file disguised
        $textDoc = UploadedFile::fake()->create('malicious.php', 100, 'text/plain');

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile', [
                'name' => 'Dharani Sundaram',
                'avatar' => $textDoc,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_user_cannot_tamper_with_email_or_role_through_profile_update(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile', [
                'name' => 'Dharani Sundaram',
                'email' => 'hacked@nsdearthmovers.com',
                'role_id' => 999,
                'is_admin' => true,
            ]);

        $response->assertOk();
        $this->user->refresh();

        // Email remains strictly unchanged
        $this->assertEquals('dharani@nsdearthmovers.com', $this->user->email);
    }

    public function test_user_can_change_password_with_current_password_verification(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile/password', [
                'current_password' => 'Secret123!',
                'password' => 'NewSecurePass999!',
                'password_confirmation' => 'NewSecurePass999!',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass999!', $this->user->password));
    }

    public function test_password_change_fails_with_wrong_current_password(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile/password', [
                'current_password' => 'WrongPassword!',
                'password' => 'NewSecurePass999!',
                'password_confirmation' => 'NewSecurePass999!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_fails_when_confirmation_mismatches(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile/password', [
                'current_password' => 'Secret123!',
                'password' => 'NewSecurePass999!',
                'password_confirmation' => 'CompletelyDifferentPass!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_update_and_persist_preferences(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->putJson('/profile/preferences', [
                'theme' => 'dark',
                'notifications_email' => true,
                'notifications_browser' => false,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'preferences' => [
                'theme' => 'dark',
                'notifications_email' => true,
                'notifications_browser' => false,
            ],
        ]);

        $this->user->refresh();
        $this->assertEquals('dark', $this->user->preferences['theme']);
        $this->assertTrue($this->user->preferences['notifications_email']);
        $this->assertFalse($this->user->preferences['notifications_browser']);
    }
}
