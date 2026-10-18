<?php

namespace Tests\Feature;

use App\Events\NotificationDispatched;
use App\Models\AppNotification;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealTimeNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company1;
    protected Company $company2;
    protected User $user1;
    protected User $user2;
    protected User $crossCompanyUser;
    protected Role $pmRole;
    protected Role $supervisorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company1 = Company::create(['name' => 'NSD Earth Movers Ltd', 'slug' => 'nsd-earth-movers']);
        $this->company2 = Company::create(['name' => 'Competitor Infrastructure', 'slug' => 'competitor-infra']);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '12345',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        require base_path('routes/channels.php');

        $this->pmRole = Role::create([
            'company_id' => $this->company1->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'procurement.requests.view'],
            'is_system' => false,
        ]);

        $this->supervisorRole = Role::create([
            'company_id' => $this->company1->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['projects.view', 'attendance.*'],
            'is_system' => false,
        ]);

        $this->user1 = User::factory()->create(['name' => 'Project Manager User']);
        $this->user1->companies()->attach($this->company1->id, [
            'role_id' => $this->pmRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->user2 = User::factory()->create(['name' => 'Site Supervisor User']);
        $this->user2->companies()->attach($this->company1->id, [
            'role_id' => $this->supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->crossCompanyUser = User::factory()->create(['name' => 'Cross Company User']);
        $this->crossCompanyUser->companies()->attach($this->company2->id, [
            'role_id' => null,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    /**
     * 1. Authorized user receives the appropriate notification event.
     */
    public function test_authorized_user_receives_targeted_notification_event(): void
    {
        Event::fake([NotificationDispatched::class]);

        $notification = NotificationService::sendToUser(
            $this->company1->id,
            $this->user1->id,
            'Material Request Submitted',
            'PR-2026-001 submitted for your approval.',
            'info',
            '/purchase-requests/1'
        );

        $this->assertDatabaseHas('app_notifications', [
            'id' => $notification->id,
            'company_id' => $this->company1->id,
            'user_id' => $this->user1->id,
            'title' => 'Material Request Submitted',
            'is_read' => false,
        ]);

        Event::assertDispatched(NotificationDispatched::class, function ($event) use ($notification) {
            $channels = $event->broadcastOn();
            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertEquals('private-App.Models.User.' . $this->user1->id, $channels[0]->name);
            return $event->notification->id === $notification->id;
        });
    }

    /**
     * 2. Channel authorization for private user channels.
     */
    public function test_user_private_channel_authorization(): void
    {
        // User 1 authorized on their own channel
        $response = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-App.Models.User.' . $this->user1->id,
                'socket_id' => '1234.5678',
            ]);
        $response->assertStatus(200);

        // User 1 cannot subscribe to User 2's channel
        $responseForbidden = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-App.Models.User.' . $this->user2->id,
                'socket_id' => '1234.5678',
            ]);
        $responseForbidden->assertStatus(403);
    }

    /**
     * 3. Cross-company events and tenant isolation:
     * Unauthorized users from another company cannot subscribe to company private channel.
     */
    public function test_cross_company_tenant_channel_isolation(): void
    {
        // User 1 belongs to Company 1 -> Authorized
        $resAuth = $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-company.' . $this->company1->id,
                'socket_id' => '1234.5678',
            ]);
        $resAuth->assertStatus(200);

        // Cross-company user belongs to Company 2 -> Forbidden on Company 1 channel
        $resForbidden = $this->actingAs($this->crossCompanyUser)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-company.' . $this->company1->id,
                'socket_id' => '1234.5678',
            ]);
        $resForbidden->assertStatus(403);
    }

    /**
     * 4. Revocation of access when user membership is revoked.
     */
    public function test_revoked_company_membership_denies_channel_subscription(): void
    {
        // Revoke user2's status in company1
        $this->user2->companies()->updateExistingPivot($this->company1->id, [
            'status' => 'inactive',
        ]);

        $res = $this->actingAs($this->user2)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-company.' . $this->company1->id,
                'socket_id' => '1234.5678',
            ]);
        $res->assertStatus(403);
    }

    /**
     * 5. Project and Site channel authorization checks.
     */
    public function test_project_and_site_channel_authorization(): void
    {
        $project = Project::create([
            'company_id' => $this->company1->id,
            'name' => 'Highway Overpass Phase 2',
            'code' => 'HW-02',
            'project_type' => 'civil',
            'contract_value' => 5000000.00,
            'approved_budget' => 4500000.00,
            'manager_id' => $this->user1->id,
            'status' => 'active',
            'progress_percent' => 20,
        ]);

        $site = Site::create([
            'company_id' => $this->company1->id,
            'project_id' => $project->id,
            'name' => 'Pier 4 North Site',
            'code' => 'P4-N',
            'supervisor_id' => $this->user2->id,
            'status' => 'active',
        ]);

        // User 1 is Project Manager -> Allowed on project channel
        $this->actingAs($this->user1)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-project.' . $project->id,
                'socket_id' => '1234.5678',
            ])
            ->assertStatus(200);

        // User 2 is Site Supervisor -> Allowed on site channel
        $this->actingAs($this->user2)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-site.' . $site->id,
                'socket_id' => '1234.5678',
            ])
            ->assertStatus(200);

        // Cross company user -> Forbidden on both
        $this->actingAs($this->crossCompanyUser)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-project.' . $project->id,
                'socket_id' => '1234.5678',
            ])
            ->assertStatus(403);

        $this->actingAs($this->crossCompanyUser)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-site.' . $site->id,
                'socket_id' => '1234.5678',
            ])
            ->assertStatus(403);
    }

    /**
     * 6. Financial and payroll details are not exposed in broad event payloads.
     */
    public function test_payload_contains_no_sensitive_payroll_or_financial_data(): void
    {
        $notification = AppNotification::create([
            'company_id' => $this->company1->id,
            'user_id' => $this->user1->id,
            'title' => 'Payroll Batch Submitted for Review',
            'message' => 'Payroll period October 2026 submitted for authorized review (42 employees).',
            'type' => 'info',
            'action_url' => '/payroll/5',
        ]);

        $event = new NotificationDispatched($notification);
        $payload = $event->broadcastWith();

        // Verify standard safe metadata is present
        $this->assertEquals($notification->id, $payload['id']);
        $this->assertEquals('Payroll Batch Submitted for Review', $payload['title']);
        $this->assertEquals('/payroll/5', $payload['action_url']);
        $this->assertArrayHasKey('created_at', $payload);
        $this->assertArrayHasKey('created_at_human', $payload);

        // Verify sensitive keys are NOT present
        $this->assertArrayNotHasKey('salary', $payload);
        $this->assertArrayNotHasKey('net_salary', $payload);
        $this->assertArrayNotHasKey('bank_account', $payload);
        $this->assertArrayNotHasKey('unit_price', $payload);
        $this->assertArrayNotHasKey('amount', $payload);
    }

    /**
     * 7. Event broadcasting occurs only after a transaction commits.
     * Failed or rolled back transactions do not emit successful business events.
     */
    public function test_broadcasting_occurs_only_after_transaction_commit(): void
    {
        Event::fake([NotificationDispatched::class]);

        // Scenario A: Rolled back transaction
        try {
            DB::transaction(function () {
                AppNotification::create([
                    'company_id' => $this->company1->id,
                    'user_id' => $this->user1->id,
                    'title' => 'Failed Transaction PR',
                    'message' => 'This should roll back.',
                    'type' => 'info',
                ]);
                throw new \RuntimeException('Simulated transaction failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        Event::assertNotDispatched(NotificationDispatched::class);
        $this->assertDatabaseMissing('app_notifications', ['title' => 'Failed Transaction PR']);

        // Scenario B: Successfully committed transaction
        DB::transaction(function () {
            AppNotification::create([
                'company_id' => $this->company1->id,
                'user_id' => $this->user1->id,
                'title' => 'Committed PR Notification',
                'message' => 'This committed cleanly.',
                'type' => 'success',
            ]);
        });

        Event::assertDispatched(NotificationDispatched::class, function ($event) {
            return $event->notification->title === 'Committed PR Notification';
        });
        $this->assertDatabaseHas('app_notifications', ['title' => 'Committed PR Notification']);
    }

    /**
     * 8. Read/Unread state persistence and feed reconciliation.
     */
    public function test_read_unread_state_and_feed_reconciliation(): void
    {
        $notif1 = AppNotification::create([
            'company_id' => $this->company1->id,
            'user_id' => $this->user1->id,
            'title' => 'First Notification',
            'message' => 'Msg 1',
            'is_read' => false,
        ]);

        $notif2 = AppNotification::create([
            'company_id' => $this->company1->id,
            'user_id' => $this->user1->id,
            'title' => 'Second Notification',
            'message' => 'Msg 2',
            'is_read' => false,
        ]);

        // Check unread feed
        $feed = $this->actingAs($this->user1)
            ->withSession(['current_company_id' => $this->company1->id])
            ->getJson('/notifications/feed');

        $feed->assertStatus(200)
            ->assertJson([
                'unread_count' => 2,
            ]);

        // Mark single as read
        $markSingle = $this->actingAs($this->user1)
            ->withSession(['current_company_id' => $this->company1->id])
            ->patchJson("/notifications/{$notif1->id}/read");

        $markSingle->assertStatus(200)
            ->assertJson([
                'success' => true,
                'unread_count' => 1,
            ]);

        $this->assertTrue($notif1->fresh()->is_read);
        $this->assertFalse($notif2->fresh()->is_read);

        // Mark all as read
        $markAll = $this->actingAs($this->user1)
            ->withSession(['current_company_id' => $this->company1->id])
            ->postJson('/notifications/mark-all-read');

        $markAll->assertStatus(200)
            ->assertJson([
                'success' => true,
                'unread_count' => 0,
            ]);

        $this->assertTrue($notif2->fresh()->is_read);
    }

    /**
     * 9. Users cannot mark notifications from other companies or users as read.
     */
    public function test_unauthorized_user_cannot_mark_cross_company_notification(): void
    {
        $notif = AppNotification::create([
            'company_id' => $this->company1->id,
            'user_id' => $this->user1->id,
            'title' => 'Company 1 Secret Notif',
            'message' => 'Confidential info',
            'is_read' => false,
        ]);

        // 1. Cross company user cannot even view/find notification due to tenant scope -> 404
        $resTenant = $this->actingAs($this->crossCompanyUser)
            ->withSession(['current_company_id' => $this->company2->id])
            ->patchJson("/notifications/{$notif->id}/read");

        $resTenant->assertStatus(404);
        $this->assertFalse($notif->fresh()->is_read);

        // 2. User 2 (in same company) attempts to mark User 1's targeted notification -> 403 Forbidden
        $resUser = $this->actingAs($this->user2)
            ->withSession(['current_company_id' => $this->company1->id])
            ->patchJson("/notifications/{$notif->id}/read");

        $resUser->assertStatus(403);
        $this->assertFalse($notif->fresh()->is_read);
    }

    /**
     * 10. Notification handling remains fully operational when Reverb is unavailable.
     */
    public function test_notification_dispatch_resilience_when_broadcaster_fails(): void
    {
        // Mock a failure by pointing broadcast to invalid host or throwing in listener
        $notification = NotificationService::sendToUser(
            $this->company1->id,
            $this->user1->id,
            'Resilience Test Notification',
            'This should safely save even if socket is disconnected.',
            'info'
        );

        $this->assertDatabaseHas('app_notifications', [
            'id' => $notification->id,
            'title' => 'Resilience Test Notification',
        ]);
    }
}
