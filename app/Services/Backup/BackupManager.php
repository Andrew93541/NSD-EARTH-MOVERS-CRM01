<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PDO;

class BackupManager
{
    protected string $basePath;
    protected array $config;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? config('backup.path', storage_path('app/backups'));
        $this->config = config('backup', []);
    }

    /**
     * Get the base storage path for durable backups.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Create an atomic, durable backup of the database and private storage.
     */
    public function createBackup(string $frequency = 'hourly', ?bool $encrypt = null): array
    {
        $encrypt = $encrypt ?? (bool) config('backup.encryption.enabled', false);
        $encryptionKey = config('backup.encryption.key', config('app.key'));

        File::ensureDirectoryExists($this->basePath, 0700);
        @chmod($this->basePath, 0700);

        $timestamp = date('Ymd_His');
        $backupId = "backup_{$timestamp}_{$frequency}";
        $backupDir = "{$this->basePath}/{$backupId}";

        File::ensureDirectoryExists($backupDir, 0700);
        @chmod($backupDir, 0700);

        $sourceDb = (config('database.connections.mysql.database') && config('database.connections.mysql.database') !== ':memory:')
            ? config('database.connections.mysql.database')
            : 'buildflow_crm';
        $mysqlHost = config('database.connections.mysql.host', '127.0.0.1');
        $mysqlPort = config('database.connections.mysql.port', 3306);
        $mysqlUser = config('database.connections.mysql.username', 'root');
        $mysqlPass = config('database.connections.mysql.password', '');

        // 1. Audit Live Source State & Invariants via PDO
        $sourcePdo = new PDO(
            "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$sourceDb};charset=utf8mb4",
            $mysqlUser,
            $mysqlPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $tablesStmt = $sourcePdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        $tablesCount = count($tables);

        $totalRows = 0;
        foreach ($tables as $t) {
            $totalRows += (int) $sourcePdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        }

        $migCount = (int) $sourcePdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn();

        $tb = $sourcePdo->query("
            SELECT 
                ROUND(SUM(jel.debit), 2) as total_debit,
                ROUND(SUM(jel.credit), 2) as total_credit
            FROM journal_entry_lines jel
            JOIN journal_entries je ON je.id = jel.journal_entry_id
            WHERE je.status = 'posted'
        ")->fetch(PDO::FETCH_ASSOC);

        $totalDebit = (float) ($tb['total_debit'] ?? 0.0);
        $totalCredit = (float) ($tb['total_credit'] ?? 0.0);
        $diff = round($totalDebit - $totalCredit, 2);

        // 2. Perform Atomic Database Dump
        $rawSqlTemp = "{$backupDir}/database.sql.tmp";
        $dumpCmd = "mysqldump --single-transaction --quick --set-gtid-purged=OFF -h {$mysqlHost} -P {$mysqlPort} -u {$mysqlUser} " .
                   ($mysqlPass ? "-p" . escapeshellarg($mysqlPass) . " " : "") .
                   "{$sourceDb} > " . escapeshellarg($rawSqlTemp);

        exec($dumpCmd, $dumpOutput, $dumpCode);
        if ($dumpCode !== 0 || ! file_exists($rawSqlTemp) || filesize($rawSqlTemp) === 0) {
            File::deleteDirectory($backupDir);
            throw new \RuntimeException("mysqldump failed with exit code {$dumpCode}");
        }

        $dumpChecksum = hash_file('sha256', $rawSqlTemp);
        $dumpSize = filesize($rawSqlTemp);

        if ($encrypt) {
            $dumpFile = "database.sql.enc";
            $this->encryptFile($rawSqlTemp, "{$backupDir}/{$dumpFile}", $encryptionKey);
            @unlink($rawSqlTemp);
        } else {
            $dumpFile = "database.sql";
            rename($rawSqlTemp, "{$backupDir}/{$dumpFile}");
        }
        @chmod("{$backupDir}/{$dumpFile}", 0600);

        // 3. Perform Atomic Private Storage Archive
        $privateStoragePath = storage_path('app/private');
        $archiveFile = null;
        $archiveSize = 0;
        $archiveChecksum = null;
        $filesCount = 0;

        if (is_dir($privateStoragePath)) {
            $filesCount = (int) $sourcePdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL")->fetchColumn();
            $rawTarTemp = "{$backupDir}/storage.tar.gz.tmp";
            $tarCmd = "tar -czf " . escapeshellarg($rawTarTemp) . " -C " . escapeshellarg($privateStoragePath) . " .";
            exec($tarCmd, $tarOutput, $tarCode);

            if ($tarCode === 0 && file_exists($rawTarTemp) && filesize($rawTarTemp) > 0) {
                $archiveChecksum = hash_file('sha256', $rawTarTemp);
                $archiveSize = filesize($rawTarTemp);

                if ($encrypt) {
                    $archiveFile = "storage.tar.gz.enc";
                    $this->encryptFile($rawTarTemp, "{$backupDir}/{$archiveFile}", $encryptionKey);
                    @unlink($rawTarTemp);
                } else {
                    $archiveFile = "storage.tar.gz";
                    rename($rawTarTemp, "{$backupDir}/{$archiveFile}");
                }
                @chmod("{$backupDir}/{$archiveFile}", 0600);
            }
        }

        // 4. Generate Atomic Backup Manifest
        $manifest = [
            'backup_id' => $backupId,
            'created_at' => now()->toIso8601String(),
            'frequency' => $frequency,
            'database' => [
                'name' => $sourceDb,
                'tables_count' => $tablesCount,
                'rows_count' => $totalRows,
                'migrations_count' => $migCount,
                'dump_file' => $dumpFile,
                'dump_size_bytes' => $dumpSize,
                'dump_checksum' => $dumpChecksum,
            ],
            'storage' => [
                'archive_file' => $archiveFile,
                'archive_size_bytes' => $archiveSize,
                'archive_checksum' => $archiveChecksum,
                'documents_count' => $filesCount,
            ],
            'financial_ledger' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'diff' => $diff,
                'is_balanced' => ($diff === 0.0),
            ],
            'encryption' => [
                'enabled' => $encrypt,
                'cipher' => $encrypt ? ($this->config['encryption']['cipher'] ?? 'AES-256-CBC') : null,
            ],
            'validation_status' => 'pending',
            'validated_at' => null,
            'restore_test_status' => 'untested',
            'tested_at' => null,
            'is_protected' => false,
        ];

        $manifestTemp = "{$backupDir}/manifest.json.tmp";
        file_put_contents($manifestTemp, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($manifestTemp, "{$backupDir}/manifest.json");
        @chmod("{$backupDir}/manifest.json", 0600);

        Log::info("Durable backup created successfully: {$backupId}", [
            'frequency' => $frequency,
            'tables' => $tablesCount,
            'rows' => $totalRows,
            'encrypted' => $encrypt,
        ]);

        // 5. Apply Retention Policy
        $this->pruneOldBackups();

        return $manifest;
    }

    /**
     * Validate the checksums and readability of a backup snapshot.
     */
    public function validateBackup(string $backupId): array
    {
        $backupDir = "{$this->basePath}/{$backupId}";
        $manifestPath = "{$backupDir}/manifest.json";

        if (! file_exists($manifestPath)) {
            throw new \RuntimeException("Backup manifest not found for ID: {$backupId}");
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            throw new \RuntimeException("Corrupted backup manifest JSON for ID: {$backupId}");
        }

        $errors = [];
        $encryptionKey = config('backup.encryption.key', config('app.key'));
        $isEncrypted = (bool) ($manifest['encryption']['enabled'] ?? false);

        // 1. Verify Database Dump File
        $dumpFileName = $manifest['database']['dump_file'];
        $dumpFilePath = "{$backupDir}/{$dumpFileName}";

        if (! file_exists($dumpFilePath) || filesize($dumpFilePath) === 0) {
            $errors[] = "Database dump file [{$dumpFileName}] is missing or empty.";
        } else {
            if ($isEncrypted) {
                // Test decrypting to temporary file to verify integrity and key validity
                $tempDecrypted = "{$dumpFilePath}.test_dec";
                try {
                    $this->decryptFile($dumpFilePath, $tempDecrypted, $encryptionKey);
                    $actualChecksum = hash_file('sha256', $tempDecrypted);
                    @unlink($tempDecrypted);

                    if ($actualChecksum !== $manifest['database']['dump_checksum']) {
                        $errors[] = "Decrypted database dump checksum mismatch: Expected {$manifest['database']['dump_checksum']}, got {$actualChecksum}";
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Failed to decrypt database dump: " . $e->getMessage();
                }
            } else {
                $actualChecksum = hash_file('sha256', $dumpFilePath);
                if ($actualChecksum !== $manifest['database']['dump_checksum']) {
                    $errors[] = "Database dump checksum mismatch: Expected {$manifest['database']['dump_checksum']}, got {$actualChecksum}";
                }
            }
        }

        // 2. Verify Storage Archive
        if (! empty($manifest['storage']['archive_file'])) {
            $archiveFileName = $manifest['storage']['archive_file'];
            $archiveFilePath = "{$backupDir}/{$archiveFileName}";

            if (! file_exists($archiveFilePath) || filesize($archiveFilePath) === 0) {
                $errors[] = "Storage archive file [{$archiveFileName}] is missing or empty.";
            } else {
                if ($isEncrypted) {
                    $tempDecryptedTar = "{$archiveFilePath}.test_dec";
                    try {
                        $this->decryptFile($archiveFilePath, $tempDecryptedTar, $encryptionKey);
                        $actualTarChecksum = hash_file('sha256', $tempDecryptedTar);

                        // Test tar list readability
                        exec("tar -tzf " . escapeshellarg($tempDecryptedTar) . " > /dev/null 2>&1", $tarOut, $tarCode);
                        @unlink($tempDecryptedTar);

                        if ($actualTarChecksum !== $manifest['storage']['archive_checksum']) {
                            $errors[] = "Decrypted storage archive checksum mismatch.";
                        }
                        if ($tarCode !== 0) {
                            $errors[] = "Decrypted storage archive is corrupted or unreadable by tar.";
                        }
                    } catch (\Throwable $e) {
                        $errors[] = "Failed to decrypt storage archive: " . $e->getMessage();
                    }
                } else {
                    $actualTarChecksum = hash_file('sha256', $archiveFilePath);
                    if ($actualTarChecksum !== $manifest['storage']['archive_checksum']) {
                        $errors[] = "Storage archive checksum mismatch.";
                    }
                    exec("tar -tzf " . escapeshellarg($archiveFilePath) . " > /dev/null 2>&1", $tarOut, $tarCode);
                    if ($tarCode !== 0) {
                        $errors[] = "Storage archive is corrupted or unreadable by tar.";
                    }
                }
            }
        }

        $isValid = count($errors) === 0;
        $manifest['validation_status'] = $isValid ? 'valid' : 'invalid';
        $manifest['validated_at'] = now()->toIso8601String();
        $manifest['validation_errors'] = $errors;

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'manifest' => $manifest,
        ];
    }

    /**
     * Mark a backup as having passed an isolated disaster recovery restore test.
     */
    public function markRestoreTestPassed(string $backupId): void
    {
        $manifestPath = "{$this->basePath}/{$backupId}/manifest.json";
        if (! file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $manifest['restore_test_status'] = 'passed';
        $manifest['tested_at'] = now()->toIso8601String();
        $manifest['is_protected'] = true; // Protect verified recovery copy from retention pruning!

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the latest backup manifest.
     */
    public function getLatestBackup(): ?array
    {
        $backups = $this->getAllBackups();
        return $backups[0] ?? null;
    }

    /**
     * Get the latest validated backup manifest.
     */
    public function getLatestValidatedBackup(): ?array
    {
        $backups = $this->getAllBackups();
        foreach ($backups as $b) {
            if (($b['validation_status'] ?? '') === 'valid') {
                return $b;
            }
        }
        return null;
    }

    /**
     * Get all backups ordered newest to oldest.
     */
    public function getAllBackups(): array
    {
        if (! is_dir($this->basePath)) {
            return [];
        }

        $dirs = File::directories($this->basePath);
        $manifests = [];

        foreach ($dirs as $dir) {
            $manifestPath = "{$dir}/manifest.json";
            if (file_exists($manifestPath)) {
                $content = @file_get_contents($manifestPath);
                $json = json_decode((string) $content, true);
                if (is_array($json)) {
                    $manifests[] = $json;
                }
            }
        }

        usort($manifests, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        return $manifests;
    }

    /**
     * Calculate age of the latest backup in minutes.
     */
    public function getLatestBackupAgeMinutes(): ?int
    {
        $latest = $this->getLatestBackup();
        if (! $latest || empty($latest['created_at'])) {
            return null;
        }

        $created = strtotime($latest['created_at']);
        if (! $created) {
            return null;
        }

        return (int) max(0, round((time() - $created) / 60));
    }

    /**
     * Check if backups are stale relative to RPO SLA threshold.
     */
    public function isBackupStale(?int $maxMinutes = null): bool
    {
        $maxMinutes = $maxMinutes ?? (int) config('backup.rpo_stale_minutes', 120);
        $age = $this->getLatestBackupAgeMinutes();

        if ($age === null) {
            return true; // No backup exists -> stale/missing
        }

        return $age > $maxMinutes;
    }

    /**
     * Apply retention pruning while strictly protecting the latest valid backup and any protected snapshots.
     */
    public function pruneOldBackups(): int
    {
        $backups = $this->getAllBackups();
        if (count($backups) <= 1) {
            return 0; // Always retain at least one backup
        }

        $retentionLimits = [
            'hourly' => (int) config('backup.retention.hourly', 24),
            'daily' => (int) config('backup.retention.daily', 30),
            'monthly' => (int) config('backup.retention.monthly', 12),
        ];

        // Group by frequency
        $grouped = [];
        foreach ($backups as $b) {
            $freq = $b['frequency'] ?? 'hourly';
            $grouped[$freq][] = $b;
        }

        $prunedCount = 0;
        $latestValid = $this->getLatestValidatedBackup();
        $latestValidId = $latestValid['backup_id'] ?? null;

        foreach ($grouped as $freq => $tierBackups) {
            $limit = $retentionLimits[$freq] ?? 24;
            if (count($tierBackups) > $limit) {
                // Tier backups are sorted newest to oldest. Oldest start at index $limit.
                $candidates = array_slice($tierBackups, $limit);
                foreach ($candidates as $cand) {
                    $candId = $cand['backup_id'];
                    // CRITICAL SAFETY RULES:
                    // 1. Never delete if is_protected is true
                    if (! empty($cand['is_protected'])) {
                        continue;
                    }
                    // 2. Never delete the latest valid backup
                    if ($candId === $latestValidId) {
                        continue;
                    }

                    $targetDir = "{$this->basePath}/{$candId}";
                    if (is_dir($targetDir)) {
                        File::deleteDirectory($targetDir);
                        $prunedCount++;
                        Log::info("Pruned old backup snapshot: {$candId}");
                    }
                }
            }
        }

        return $prunedCount;
    }

    /**
     * Encrypt a file using OpenSSL AES-256-CBC.
     */
    public function encryptFile(string $sourceFile, string $destFile, string $key): void
    {
        $iv = openssl_random_pseudo_bytes(16);
        $plaintext = file_get_contents($sourceFile);
        $binaryKey = hash('sha256', $key, true);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $binaryKey, OPENSSL_RAW_DATA, $iv);

        file_put_contents($destFile, $iv . $ciphertext);
    }

    /**
     * Decrypt an encrypted file using OpenSSL AES-256-CBC.
     */
    public function decryptFile(string $sourceFile, string $destFile, string $key): void
    {
        $data = file_get_contents($sourceFile);
        if (strlen($data) < 16) {
            throw new \RuntimeException('Encrypted file payload too short.');
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $binaryKey = hash('sha256', $key, true);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $binaryKey, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt backup file: invalid key or corrupted payload.');
        }

        file_put_contents($destFile, $plaintext);
    }
}
