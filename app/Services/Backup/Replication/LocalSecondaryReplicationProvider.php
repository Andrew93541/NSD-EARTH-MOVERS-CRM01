<?php

namespace App\Services\Backup\Replication;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LocalSecondaryReplicationProvider implements BackupReplicationInterface
{
    protected string $primaryPath;
    protected string $secondaryPath;
    protected int $maxRetries;
    protected int $retryDelayMs;

    public function __construct(?string $secondaryPath = null, ?string $primaryPath = null)
    {
        $this->secondaryPath = $secondaryPath ?? config('backup.replication.local_secondary.path', storage_path('app/backups_secondary'));
        $this->primaryPath = $primaryPath ?? config('backup.path', storage_path('app/backups'));
        $this->maxRetries = (int) config('backup.replication.max_retries', 3);
        $this->retryDelayMs = (int) config('backup.replication.retry_delay_ms', 100);
    }

    public function replicate(string $backupId, array $manifest): array
    {
        $sourceDir = "{$this->primaryPath}/{$backupId}";
        if (! is_dir($sourceDir)) {
            return [
                'success' => false,
                'error' => "Source backup directory not found: {$backupId}",
            ];
        }

        File::ensureDirectoryExists($this->secondaryPath, 0700);
        @chmod($this->secondaryPath, 0700);

        $destDir = "{$this->secondaryPath}/{$backupId}";
        $destTempDir = "{$this->secondaryPath}/{$backupId}.tmp";

        // Cleanup any partial prior attempt
        if (is_dir($destTempDir)) {
            File::deleteDirectory($destTempDir);
        }
        File::ensureDirectoryExists($destTempDir, 0700);
        @chmod($destTempDir, 0700);

        $filesToCopy = [];
        $filesToCopy[] = $manifest['database']['dump_file'];
        if (! empty($manifest['storage']['archive_file'])) {
            $filesToCopy[] = $manifest['storage']['archive_file'];
        }
        $filesToCopy[] = 'manifest.json';

        $errors = [];
        $verifiedFiles = [];

        foreach ($filesToCopy as $file) {
            $srcFile = "{$sourceDir}/{$file}";
            $dstFile = "{$destTempDir}/{$file}";

            if (! file_exists($srcFile)) {
                $errors[] = "Missing source file for replication: {$file}";
                break;
            }

            // Copy with retry mechanism
            $copied = false;
            for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
                if (@copy($srcFile, $dstFile)) {
                    $copied = true;
                    @chmod($dstFile, 0600);
                    break;
                }
                usleep($this->retryDelayMs * 1000);
            }

            if (! $copied) {
                $errors[] = "Failed to copy {$file} after {$this->maxRetries} attempts.";
                break;
            }

            // Verify remote checksum
            $expectedChecksum = null;
            if ($file === $manifest['database']['dump_file']) {
                $expectedChecksum = $manifest['database']['dump_checksum'];
            } elseif (! empty($manifest['storage']['archive_file']) && $file === $manifest['storage']['archive_file']) {
                $expectedChecksum = $manifest['storage']['archive_checksum'];
            }

            if ($expectedChecksum) {
                $actualChecksum = hash_file('sha256', $dstFile);
                if ($actualChecksum !== $expectedChecksum) {
                    $errors[] = "Checksum mismatch on replica {$file}: expected {$expectedChecksum}, got {$actualChecksum}";
                    break;
                }
                $verifiedFiles[$file] = $actualChecksum;
            }
        }

        if (count($errors) > 0) {
            File::deleteDirectory($destTempDir);
            Log::error("Backup replication failed for {$backupId}", ['errors' => $errors]);
            return [
                'success' => false,
                'backup_id' => $backupId,
                'errors' => $errors,
            ];
        }

        // Write atomic replication receipt
        $receipt = [
            'backup_id' => $backupId,
            'replicated_at' => now()->toIso8601String(),
            'destination_driver' => 'local_secondary',
            'destination_path' => $destDir,
            'verified_files' => $verifiedFiles,
            'checksum_verified' => true,
        ];
        file_put_contents("{$destTempDir}/replication_receipt.json", json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod("{$destTempDir}/replication_receipt.json", 0600);

        // Atomically rename temp directory to final destination
        if (is_dir($destDir)) {
            File::deleteDirectory($destDir);
        }
        rename($destTempDir, $destDir);

        Log::info("Backup replicated to secondary destination: {$backupId}", ['destination' => $destDir]);

        // Auto-prune secondary retention
        $retentionCount = (int) config('backup.replication.retention_count', 7);
        $this->pruneSecondary($retentionCount);

        return [
            'success' => true,
            'backup_id' => $backupId,
            'replicated_at' => $receipt['replicated_at'],
            'destination' => $destDir,
            'checksum_verified' => true,
        ];
    }

    public function verifyReplication(string $backupId): bool
    {
        $receiptPath = "{$this->secondaryPath}/{$backupId}/replication_receipt.json";
        if (! file_exists($receiptPath)) {
            return false;
        }

        $receipt = json_decode(file_get_contents($receiptPath), true);
        return is_array($receipt) && ! empty($receipt['checksum_verified']);
    }

    public function getReplicationStatus(): array
    {
        $enabled = (bool) config('backup.replication.enabled', false);

        if (! is_dir($this->secondaryPath)) {
            return [
                'enabled' => $enabled,
                'status' => $enabled ? 'uninitialized' : 'disabled',
                'secondary_path' => $this->secondaryPath,
                'latest_replicated_id' => null,
                'latest_replicated_at' => null,
                'total_replicas' => 0,
            ];
        }

        $dirs = File::directories($this->secondaryPath);
        $replicas = [];

        foreach ($dirs as $d) {
            $receiptPath = "{$d}/replication_receipt.json";
            if (file_exists($receiptPath)) {
                $r = json_decode((string) @file_get_contents($receiptPath), true);
                if (is_array($r) && ! empty($r['backup_id'])) {
                    $replicas[] = $r;
                }
            }
        }

        usort($replicas, function ($a, $b) {
            return strcmp($b['replicated_at'] ?? '', $a['replicated_at'] ?? '');
        });

        $latest = $replicas[0] ?? null;

        return [
            'enabled' => $enabled,
            'status' => count($replicas) > 0 ? 'active' : ($enabled ? 'pending_sync' : 'disabled'),
            'secondary_path' => $this->secondaryPath,
            'latest_replicated_id' => $latest['backup_id'] ?? null,
            'latest_replicated_at' => $latest['replicated_at'] ?? null,
            'total_replicas' => count($replicas),
        ];
    }

    public function pruneSecondary(int $keepCount): int
    {
        if (! is_dir($this->secondaryPath)) {
            return 0;
        }

        $dirs = File::directories($this->secondaryPath);
        $replicas = [];

        foreach ($dirs as $d) {
            $receiptPath = "{$d}/replication_receipt.json";
            if (file_exists($receiptPath)) {
                $r = json_decode((string) @file_get_contents($receiptPath), true);
                if (is_array($r) && ! empty($r['backup_id'])) {
                    $replicas[] = [
                        'dir' => $d,
                        'backup_id' => $r['backup_id'],
                        'replicated_at' => $r['replicated_at'] ?? '',
                    ];
                }
            }
        }

        if (count($replicas) <= $keepCount) {
            return 0;
        }

        usort($replicas, function ($a, $b) {
            return strcmp($b['replicated_at'], $a['replicated_at']);
        });

        $candidates = array_slice($replicas, $keepCount);
        $pruned = 0;

        foreach ($candidates as $cand) {
            File::deleteDirectory($cand['dir']);
            $pruned++;
            Log::info("Pruned secondary backup replica: {$cand['backup_id']}");
        }

        return $pruned;
    }
}
