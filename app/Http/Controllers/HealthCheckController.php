<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HealthCheckController extends Controller
{
    /**
     * Perform comprehensive system health verification.
     */
    public function check(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $checks = [];
        $isHealthy = true;

        // 1. Database Connectivity & Latency
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);
            $checks['database'] = [
                'status' => 'ok',
                'latency_ms' => $dbLatency,
            ];
        } catch (\Throwable $e) {
            $isHealthy = false;
            $checks['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
            Log::error('Health check: Database failure: ' . $e->getMessage());
        }

        // 2. Private Storage Writability & Capacity
        try {
            $storageStart = microtime(true);
            $testFile = 'health_check_' . Str::random(8) . '.tmp';
            $disk = Storage::disk('local');
            $disk->put($testFile, 'buildflow_health_ok');
            $content = $disk->get($testFile);
            $disk->delete($testFile);

            $freeBytes = @disk_free_space(storage_path('app/private')) ?: 0;
            $freeGb = round($freeBytes / (1024 * 1024 * 1024), 2);

            if ($content !== 'buildflow_health_ok') {
                throw new \RuntimeException('Storage write verification failed.');
            }

            $checks['storage'] = [
                'status' => 'ok',
                'free_space_gb' => $freeGb,
                'latency_ms' => round((microtime(true) - $storageStart) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $isHealthy = false;
            $checks['storage'] = [
                'status' => 'error',
                'message' => 'Private storage disk read/write failed',
            ];
            Log::error('Health check: Storage failure: ' . $e->getMessage());
        }

        // 3. Cache Subsystem
        try {
            $cacheKey = 'buildflow_health_' . Str::random(8);
            Cache::put($cacheKey, 'ok', 10);
            $cacheValue = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            $checks['cache'] = [
                'status' => $cacheValue === 'ok' ? 'ok' : 'degraded',
            ];
        } catch (\Throwable $e) {
            $checks['cache'] = [
                'status' => 'degraded',
                'message' => 'Cache access error',
            ];
        }

        // 4. Real-time Broadcasting (Reverb)
        $broadcastDriver = config('broadcasting.default');
        $reverbHost = config('reverb.servers.reverb.host', '127.0.0.1');
        $reverbPort = config('reverb.servers.reverb.port', 8080);
        $reverbSocket = @fsockopen($reverbHost, $reverbPort, $errno, $errstr, 0.5);

        if ($reverbSocket) {
            fclose($reverbSocket);
            $reverbStatus = 'ok';
        } else {
            $reverbStatus = 'unreachable_fallback_active';
        }

        $checks['broadcasting'] = [
            'status' => $reverbStatus,
            'driver' => $broadcastDriver,
        ];

        // 5. Anti-Malware File Scanner
        $isProduction = app()->environment('production');
        $scannerConfigEnabled = (bool) config('filesystems.scanner.enabled', true);
        $scannerConfigFailClosed = (bool) config('filesystems.scanner.fail_closed', $isProduction);
        $allowUnscannedDev = ! $isProduction && (bool) config('filesystems.scanner.allow_unscanned_dev', false);

        // Effective fail-closed is strictly enforced in production or if configured
        $effectiveFailClosed = $isProduction || $scannerConfigFailClosed || ! $allowUnscannedDev;

        $clamHost = config('filesystems.scanner.host', '127.0.0.1');
        $clamPort = (int) config('filesystems.scanner.port', 3310);
        $clamSocket = config('filesystems.scanner.socket', '/var/run/clamav/clamd.ctl');

        $socket = null;
        if (file_exists($clamSocket)) {
            $socket = @fsockopen("unix://{$clamSocket}", -1, $errno, $errstr, 0.5);
        } elseif ($clamHost) {
            $socket = @fsockopen($clamHost, $clamPort, $errno, $errstr, 0.5);
        }

        if ($socket) {
            fclose($socket);
            $scannerStatus = 'ok';
            $scannerMessage = 'ClamAV daemon reachable and operational';
        } else {
            if ($effectiveFailClosed) {
                $scannerStatus = 'error_blocking';
                $scannerMessage = 'ClamAV daemon unreachable; system is in fail-closed mode (uploads and unverified files will be rejected)';
                if ($isProduction) {
                    $isHealthy = false;
                }
            } else {
                $scannerStatus = 'dev_unscanned_active';
                $scannerMessage = 'ClamAV daemon unreachable; explicit development unscanned mode active';
            }
        }

        $checks['malware_scanner'] = [
            'status' => $scannerStatus,
            'enabled' => $scannerConfigEnabled,
            'fail_closed' => $effectiveFailClosed,
            'effective_fail_closed' => $effectiveFailClosed,
            'allow_unscanned_dev' => $allowUnscannedDev,
            'message' => $scannerMessage,
        ];

        // 6. Durable Automated Backups (Separate Creation, Validation, and Restore Verification)
        try {
            $backupManager = app(\App\Services\Backup\BackupManager::class);
            $latestBackup = $backupManager->getLatestBackup();

            if ($latestBackup) {
                $backupAge = $backupManager->getLatestBackupAgeMinutes();
                $rpoStatus = $backupManager->getRpoStatus($backupAge);
                $isValid = ($latestBackup['validation_status'] ?? '') === 'valid';
                $isTested = ($latestBackup['restore_test_status'] ?? '') === 'passed';
                $isEncrypted = (bool) ($latestBackup['encryption']['enabled'] ?? false);

                $backupCheckStatus = 'ok';
                if ($rpoStatus === 'breached') {
                    $backupCheckStatus = 'breached';
                    if ($isProduction) {
                        $isHealthy = false;
                    }
                } elseif ($rpoStatus === 'warning_approaching_breach') {
                    $backupCheckStatus = 'approaching_breach';
                } elseif (! $isValid) {
                    $backupCheckStatus = 'invalid';
                    if ($isProduction) {
                        $isHealthy = false;
                    }
                }

                $checks['durable_backups'] = [
                    'status' => $backupCheckStatus,
                    'latest_backup_id' => $latestBackup['backup_id'],
                    'age_minutes' => $backupAge,
                    'rpo_target_minutes' => 60,
                    'rpo_status' => $rpoStatus,
                    'validation_status' => $latestBackup['validation_status'] ?? 'pending',
                    'restore_test_status' => $latestBackup['restore_test_status'] ?? 'untested',
                    'creation' => [
                        'status' => 'ok',
                        'created_at' => $latestBackup['created_at'],
                        'frequency' => $latestBackup['frequency'],
                    ],
                    'validation' => [
                        'status' => $latestBackup['validation_status'] ?? 'pending',
                        'validated_at' => $latestBackup['validated_at'] ?? null,
                    ],
                    'restore_verification' => [
                        'status' => $latestBackup['restore_test_status'] ?? 'untested',
                        'tested_at' => $latestBackup['tested_at'] ?? null,
                        'is_protected' => (bool) ($latestBackup['is_protected'] ?? false),
                    ],
                    'encryption' => [
                        'enabled' => $isEncrypted,
                        'cipher' => $isEncrypted ? ($latestBackup['encryption']['cipher'] ?? 'unknown') : 'none',
                        'authenticated' => (bool) ($latestBackup['encryption']['authenticated'] ?? false),
                        'key_configured' => ! empty(config('backup.encryption.key')),
                    ],
                ];
            } else {
                $checks['durable_backups'] = [
                    'status' => 'missing',
                    'message' => 'No durable backups found in storage.',
                    'rpo_status' => 'missing',
                ];
                if ($isProduction) {
                    $isHealthy = false;
                }
            }
        } catch (\Throwable $e) {
            $checks['durable_backups'] = [
                'status' => 'error',
                'message' => 'Failed to audit backup registry: ' . $e->getMessage(),
            ];
        }

        // 7. Secondary Backup Replication
        try {
            $replicator = app(\App\Services\Backup\Replication\BackupReplicationInterface::class);
            $repStatus = $replicator->getReplicationStatus();
            $checks['secondary_replication'] = [
                'status' => $repStatus['status'],
                'enabled' => $repStatus['enabled'],
                'latest_replicated_id' => $repStatus['latest_replicated_id'],
                'latest_replicated_at' => $repStatus['latest_replicated_at'],
                'total_replicas' => $repStatus['total_replicas'],
            ];
        } catch (\Throwable $e) {
            $checks['secondary_replication'] = [
                'status' => 'unverified',
                'message' => 'Secondary replication audit error: ' . $e->getMessage(),
            ];
        }

        $totalLatency = round((microtime(true) - $startTime) * 1000, 2);

        $payload = [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'total_latency_ms' => $totalLatency,
            'checks' => $checks,
        ];

        return response()->json($payload, $isHealthy ? 200 : 503);
    }
}
