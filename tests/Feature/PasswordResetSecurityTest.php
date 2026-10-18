<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
        ]);

        $this->user = User::create([
            'name' => 'Security Test User',
            'email' => 'operator@buildflow.local',
            'password' => Hash::make('InitialPassword123!'),
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.view'],
        ]);

        $this->company->users()->attach($this->user->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    public function test_forgot_password_endpoint_returns_generic_response_for_existing_user(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => $this->user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account exists for that email, a password reset link has been dispatched.');

        // Token must be registered in password_reset_tokens table
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $this->user->email,
        ]);
    }

    public function test_forgot_password_endpoint_returns_identical_generic_response_for_non_existent_email(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'doesnotexist@unknown-domain.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account exists for that email, a password reset link has been dispatched.');

        // Must NOT create token for non-existent email
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'doesnotexist@unknown-domain.com',
        ]);
    }

    public function test_reset_password_screen_can_be_rendered_with_valid_token(): void
    {
        $token = Password::broker()->createToken($this->user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $this->user->email,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Set New Password');
        $response->assertSee($this->user->email);
    }

    public function test_user_can_reset_password_with_valid_broker_token_and_matching_confirmation(): void
    {
        Event::fake([PasswordReset::class]);

        $token = Password::broker()->createToken($this->user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'NewSecurePassword2026!',
            'password_confirmation' => 'NewSecurePassword2026!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        // Confirm password was updated in database
        $this->user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword2026!', $this->user->password));

        // Token must be atomically consumed (single-use)
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
        ]);

        // Event must be dispatched
        Event::assertDispatched(PasswordReset::class, function ($event) {
            return $event->user->id === $this->user->id;
        });

        // AuditLog record created without leaking credentials or tokens
        $audit = AuditLog::where('action', 'password_reset')
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->auditable_id);
    }

    public function test_reset_password_fails_with_arbitrary_or_invalid_token(): void
    {
        $initialHash = $this->user->password;

        $response = $this->post(route('password.update'), [
            'token' => 'completely-fake-unauthorized-token-12345',
            'email' => $this->user->email,
            'password' => 'HackedPassword2026!',
            'password_confirmation' => 'HackedPassword2026!',
        ]);

        $response->assertSessionHasErrors('email');

        // Password must remain unchanged
        $this->user->refresh();
        $this->assertEquals($initialHash, $this->user->password);
    }

    public function test_reset_password_fails_when_token_is_presented_for_wrong_email(): void
    {
        $otherUser = User::create([
            'name' => 'Victim User',
            'email' => 'victim@buildflow.local',
            'password' => Hash::make('VictimOriginalPassword!'),
        ]);

        // Create token for otherUser
        $token = Password::broker()->createToken($otherUser);

        // Attempt using token to reset this->user's password
        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'CompromisedPassword123!',
            'password_confirmation' => 'CompromisedPassword123!',
        ]);

        $response->assertSessionHasErrors('email');

        // Neither user's password should have changed
        $this->user->refresh();
        $otherUser->refresh();
        $this->assertTrue(Hash::check('InitialPassword123!', $this->user->password));
        $this->assertTrue(Hash::check('VictimOriginalPassword!', $otherUser->password));
    }

    public function test_reset_password_fails_with_expired_token(): void
    {
        $token = Password::broker()->createToken($this->user);

        // Travel 61 minutes into future (beyond 60 min expiration)
        Carbon::setTestNow(now()->addMinutes(61));

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'ExpiredAttemptPassword123!',
            'password_confirmation' => 'ExpiredAttemptPassword123!',
        ]);

        $response->assertSessionHasErrors('email');

        $this->user->refresh();
        $this->assertTrue(Hash::check('InitialPassword123!', $this->user->password));

        Carbon::setTestNow();
    }

    public function test_reset_password_token_cannot_be_reused(): void
    {
        $token = Password::broker()->createToken($this->user);

        // First reset: succeeds
        $firstResponse = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'FirstNewPassword2026!',
            'password_confirmation' => 'FirstNewPassword2026!',
        ]);

        $firstResponse->assertRedirect(route('login'));

        // Second reset with the exact same token: must fail
        $secondResponse = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'SecondNewPassword2026!',
            'password_confirmation' => 'SecondNewPassword2026!',
        ]);

        $secondResponse->assertSessionHasErrors('email');

        $this->user->refresh();
        $this->assertTrue(Hash::check('FirstNewPassword2026!', $this->user->password));
    }
}
