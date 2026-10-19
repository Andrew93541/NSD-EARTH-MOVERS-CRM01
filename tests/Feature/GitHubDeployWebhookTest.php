<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GitHubDeployWebhookTest extends TestCase
{
    protected string $testSecret = 'test_webhook_secret_key_12345';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.github.webhook_secret', $this->testSecret);
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        $payload = json_encode(['ref' => 'refs/heads/main']);

        $response = $this->postJson(route('webhooks.github.deploy'), json_decode($payload, true));

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Missing HMAC signature.',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['ref' => 'refs/heads/main']);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => 'sha256=invalid_hash_signature_value',
            'X-GitHub-Event' => 'push',
        ])->postJson(route('webhooks.github.deploy'), json_decode($payload, true));

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid signature.',
        ]);
    }

    public function test_webhook_accepts_and_responds_to_ping_event(): void
    {
        $payload = json_encode([
            'zen' => 'Approachable is better than simple.',
            'hook_id' => 12345678,
        ]);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->testSecret);

        $response = $this->call(
            'POST',
            route('webhooks.github.deploy'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'ping',
            ],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pong! GitHub webhook connection successfully verified.',
            'zen' => 'Approachable is better than simple.',
        ]);
    }

    public function test_webhook_ignores_non_main_branch_push(): void
    {
        $payload = json_encode([
            'ref' => 'refs/heads/feature/new-ui',
            'after' => 'a1b2c3d4e5f6',
            'pusher' => ['name' => 'developer'],
        ]);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->testSecret);

        $response = $this->call(
            'POST',
            route('webhooks.github.deploy'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
            ],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertStringContainsString('ignored', strtolower($response->json('message')));
    }

    public function test_webhook_successfully_triggers_deployment_on_main_push(): void
    {
        $payload = json_encode([
            'ref' => 'refs/heads/main',
            'after' => '789abc123def456',
            'pusher' => ['name' => 'Andrew'],
            'head_commit' => ['message' => 'feat: update core workflows'],
        ]);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->testSecret);

        $response = $this->call(
            'POST',
            route('webhooks.github.deploy'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
            ],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'branch' => 'main',
            'commit' => '789abc123def456',
        ]);
    }
}
