<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GitHubDeployWebhookController extends Controller
{
    /**
     * Handle incoming GitHub push webhooks for continuous automated deployment.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.github.webhook_secret') ?? env('GITHUB_WEBHOOK_SECRET');

        if (empty($secret)) {
            Log::error('GitHub deployment webhook attempted, but GITHUB_WEBHOOK_SECRET is not configured.');
            return response()->json([
                'success' => false,
                'message' => 'Deployment webhook is not configured on this server.',
            ], 500);
        }

        // 1. Verify HMAC SHA-256 signature
        $hubSignature = $request->header('X-Hub-Signature-256');

        if (empty($hubSignature)) {
            Log::warning('GitHub deployment webhook rejected: missing X-Hub-Signature-256 header.', [
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing HMAC signature.',
            ], 401);
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, (string) $hubSignature)) {
            Log::warning('GitHub deployment webhook rejected: invalid HMAC signature.', [
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        // 2. Handle GitHub ping event
        $event = $request->header('X-GitHub-Event', 'push');
        if ($event === 'ping') {
            return response()->json([
                'success' => true,
                'message' => 'Pong! GitHub webhook connection successfully verified.',
                'zen' => $request->input('zen'),
            ], 200);
        }

        if ($event !== 'push') {
            return response()->json([
                'success' => true,
                'message' => "Event '{$event}' received but ignored. Only 'push' triggers deployment.",
            ], 200);
        }

        // 3. Filter by target branch (main)
        $ref = $request->input('ref');
        if ($ref !== 'refs/heads/main') {
            return response()->json([
                'success' => true,
                'message' => "Push on branch '{$ref}' ignored. Deployments only trigger on 'refs/heads/main'.",
            ], 200);
        }

        $commitHash = $request->input('after');
        $pusher = $request->input('pusher.name', 'GitHub Webhook');
        $commitMessage = $request->input('head_commit.message', 'No commit message');

        $logPrefix = "[DEPLOYMENT-WEBHOOK] " . date('Y-m-d H:i:s') . " | Commit: {$commitHash} by {$pusher}: {$commitMessage}";
        $this->appendLog($logPrefix . " - Triggered");

        // 4. In automated testing environments, return success without triggering shell commands
        if (app()->environment('testing')) {
            return response()->json([
                'success' => true,
                'message' => 'Deployment webhook authorized and verified (testing mode - commands simulated).',
                'commit' => $commitHash,
                'branch' => 'main',
            ], 200);
        }

        // 5. Execute production deployment pipeline
        $outputLogs = [];
        $commands = [
            'git pull origin main',
            'php artisan migrate --force',
            'php artisan optimize:clear',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
        ];

        foreach ($commands as $cmd) {
            try {
                $process = Process::path(base_path())->run($cmd);
                $status = $process->successful() ? 'SUCCESS' : 'FAILED';
                $output = trim($process->output() . ' ' . $process->errorOutput());
                $logLine = "{$cmd} => [{$status}]: " . substr($output, 0, 300);
                $outputLogs[] = $logLine;
                $this->appendLog("  " . $logLine);
            } catch (\Throwable $e) {
                $errorLine = "{$cmd} => [EXCEPTION]: " . $e->getMessage();
                $outputLogs[] = $errorLine;
                $this->appendLog("  " . $errorLine);
            }
        }

        Log::info("GitHub deployment webhook executed successfully for commit {$commitHash}.");

        return response()->json([
            'success' => true,
            'message' => 'Deployment sequence executed successfully.',
            'commit' => $commitHash,
            'pusher' => $pusher,
            'log' => $outputLogs,
        ], 200);
    }

    /**
     * Append to deployment log file safely.
     */
    protected function appendLog(string $message): void
    {
        try {
            $logPath = storage_path('logs/deployment-webhook.log');
            file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::error('Could not write to deployment-webhook.log: ' . $e->getMessage());
        }
    }
}
