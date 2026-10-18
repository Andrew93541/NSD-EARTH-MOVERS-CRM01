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
     * Get the active backup encryption key. Requires dedicated BACKUP_ENCRYPTION_KEY in production.
     */
    public function getEncryptionKey(): string
    {
        $key = (string) config('backup.encryption.key');
        if (empty($key)) {
            if (app()->isProduction()) {
                throw new \RuntimeException(
                    "Production backup policy violation: Dedicated BACKUP_ENCRYPTION_KEY is required in production and cannot fall back to APP_KEY."
                );
            }
            $key = (string) config('app.key');
        }
        return $key;
    }

    /**
     * Create an atomic, durable backup of the database and private storage.
     */
    public function createBackup(string $frequency = 'hourly', ?bool $encrypt = null): array
    {
        if ($encrypt === null) {
            $encrypt = app()->isProduction() ? true : (bool) config('backup.encryption.enabled', false);
        }

        if (app()->isProduction() && ! $encrypt) {
            throw new \RuntimeException("Production backup policy violation: Backups in production must be encrypted.");
        }

        $encryptionKey = $this->getEncryptionKey();

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
        $mysqlPass = (string) config('database.connections.mysql.password', '');

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

        // 2. Perform Atomic Database Dump with Secure Process Execution (No password in ps args)
        $rawSqlTemp = "{$backupDir}/database.sql.tmp";
        $dumpCmd = "mysqldump --single-transaction --quick --set-gtid-purged=OFF -h {$mysqlHost} -P {$mysqlPort} -u {$mysqlUser} " .
                   "{$sourceDb} > " . escapeshellarg($rawSqlTemp);

        $dumpCode = $this->runCliCommand($dumpCmd, $mysqlPass);
        if ($dumpCode !== 0 || ! file_exists($rawSqlTemp) || filesize($rawSqlTemp) === 0) {
            File::deleteDirectory($backupDir);
            throw new \RuntimeException("mysqldump failed with exit code {$dumpCode}");
        }

        $dumpChecksum = hash_file('sha256', $rawSqlTemp);
        $dumpSize = filesize($rawSqlTemp);

        $keyFingerprint = null;
        if ($encrypt) {
            $dumpFile = "database.sql.enc";
            $keyFingerprint = substr(hash('sha256', "buildflow-key-salt|" . $encryptionKey), 0, 12);
            $this->encryptFile($rawSqlTemp, "{$backupDir}/{$dumpFile}", $encryptionKey, $backupId, 'database.sql');
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
            $tarCode = $this->runCliCommand($tarCmd);

            if ($tarCode === 0 && file_exists($rawTarTemp) && filesize($rawTarTemp) > 0) {
                $archiveChecksum = hash_file('sha256', $rawTarTemp);
                $archiveSize = filesize($rawTarTemp);

                if ($encrypt) {
                    $archiveFile = "storage.tar.gz.enc";
                    $this->encryptFile($rawTarTemp, "{$backupDir}/{$archiveFile}", $encryptionKey, $backupId, 'storage.tar.gz');
                    @unlink($rawTarTemp);
                } else {
                    $archiveFile = "storage.tar.gz";
                    rename($rawTarTemp, "{$backupDir}/{$archiveFile}");
                }
                @chmod("{$backupDir}/{$archiveFile}", 0600);
            }
        }

        // 4. Generate Atomic Backup Manifest (Version 2 Schema)
        $manifest = [
            'manifest_version' => 2,
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
                'format_version' => $encrypt ? 2 : null,
                'cipher' => $encrypt ? 'aes-256-gcm' : null,
                'authenticated' => $encrypt ? true : false,
                'key_fingerprint' => $keyFingerprint,
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
            'aead' => $encrypt ? 'aes-256-gcm' : 'none',
        ]);

        // 5. Apply Retention Policy
        $this->pruneOldBackups();

        return $manifest;
    }

    /**
     * Validate the checksums, cryptographic authenticity, and readability of a backup snapshot.
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
        $encryptionKey = $this->getEncryptionKey();
        $isEncrypted = (bool) ($manifest['encryption']['enabled'] ?? false);

        // 1. Verify Database Dump File
        $dumpFileName = $manifest['database']['dump_file'];
        $dumpFilePath = "{$backupDir}/{$dumpFileName}";

        if (! file_exists($dumpFilePath) || filesize($dumpFilePath) === 0) {
            $errors[] = "Database dump file [{$dumpFileName}] is missing or empty.";
        } else {
            if ($isEncrypted) {
                // Test decrypting to temporary file to verify integrity, AEAD tag, and key validity
                $tempDecrypted = "{$dumpFilePath}.test_dec";
                try {
                    $this->decryptFile($dumpFilePath, $tempDecrypted, $encryptionKey, $backupId, 'database.sql');
                    $actualChecksum = hash_file('sha256', $tempDecrypted);
                    @unlink($tempDecrypted);

                    if ($actualChecksum !== $manifest['database']['dump_checksum']) {
                        $errors[] = "Decrypted database dump checksum mismatch: Expected {$manifest['database']['dump_checksum']}, got {$actualChecksum}";
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Database dump authentication / decryption failed: " . $e->getMessage();
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
                        $this->decryptFile($archiveFilePath, $tempDecryptedTar, $encryptionKey, $backupId, 'storage.tar.gz');
                        $actualTarChecksum = hash_file('sha256', $tempDecryptedTar);

                        // Test tar list readability
                        $tarCode = $this->runCliCommand("tar -tzf " . escapeshellarg($tempDecryptedTar) . " > /dev/null 2>&1");
                        @unlink($tempDecryptedTar);

                        if ($actualTarChecksum !== $manifest['storage']['archive_checksum']) {
                            $errors[] = "Decrypted storage archive checksum mismatch.";
                        }
                        if ($tarCode !== 0) {
                            $errors[] = "Decrypted storage archive is corrupted or unreadable by tar.";
                        }
                    } catch (\Throwable $e) {
                        $errors[] = "Storage archive authentication / decryption failed: " . $e->getMessage();
                    }
                } else {
                    $actualTarChecksum = hash_file('sha256', $archiveFilePath);
                    if ($actualTarChecksum !== $manifest['storage']['archive_checksum']) {
                        $errors[] = "Storage archive checksum mismatch.";
                    }
                    $tarCode = $this->runCliCommand("tar -tzf " . escapeshellarg($archiveFilePath) . " > /dev/null 2>&1");
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
     * Mark a backup as having passed an isolated disaster recovery restore test,
     * while enforcing bounded protection count.
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
        $manifest['is_protected'] = true; // Protect latest verified recovery copy

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Enforce Bounded Protection Policy:
        // Only the latest N restore-verified backups remain is_protected = true.
        $maxProtected = (int) config('backup.retention.max_protected_restore_copies', 2);
        $allBackups = $this->getAllBackups();

        $restoreTested = [];
        foreach ($allBackups as $b) {
            if (($b['restore_test_status'] ?? '') === 'passed') {
                $restoreTested[] = $b;
            }
        }

        if (count($restoreTested) > $maxProtected) {
            // Keep the latest $maxProtected as is_protected = true; older ones transition to is_protected = false.
            $unprotectCandidates = array_slice($restoreTested, $maxProtected);
            foreach ($unprotectCandidates as $cand) {
                $candManifestPath = "{$this->basePath}/{$cand['backup_id']}/manifest.json";
                if (file_exists($candManifestPath)) {
                    $candData = json_decode(file_get_contents($candManifestPath), true);
                    if (! empty($candData['is_protected'])) {
                        $candData['is_protected'] = false; // Bounded protection rollover
                        file_put_contents($candManifestPath, json_encode($candData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        Log::info("Protected baseline rolled over for older verified backup: {$cand['backup_id']}");
                    }
                }
            }
        }
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
     * Get the latest restore-tested backup manifest.
     */
    public function getLatestRestoreTestedBackup(): ?array
    {
        $backups = $this->getAllBackups();
        foreach ($backups as $b) {
            if (($b['restore_test_status'] ?? '') === 'passed') {
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
     * Determine RPO SLA status.
     * Stated RPO target is 60 minutes. Critical breach threshold is 75 minutes.
     */
    public function getRpoStatus(?int $ageMinutes = null): string
    {
        $age = $ageMinutes ?? $this->getLatestBackupAgeMinutes();
        if ($age === null) {
            return 'missing';
        }

        $target = (int) config('backup.rpo.target_minutes', 60);
        $critical = (int) config('backup.rpo.critical_threshold_minutes', 75);

        if ($age <= $target) {
            return 'satisfied';
        } elseif ($age <= $critical) {
            return 'warning_approaching_breach';
        } else {
            return 'breached';
        }
    }

    /**
     * Check if backups are stale relative to RPO SLA critical threshold.
     */
    public function isBackupStale(?int $maxMinutes = null): bool
    {
        $maxMinutes = $maxMinutes ?? (int) config('backup.rpo.critical_threshold_minutes', 75);
        $age = $this->getLatestBackupAgeMinutes();

        if ($age === null) {
            return true;
        }

        return $age > $maxMinutes;
    }

    /**
     * Apply retention pruning while strictly protecting bounded verified recovery copies
     * and preserving at least one valid backup baseline.
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

        $latestTested = $this->getLatestRestoreTestedBackup();
        $latestTestedId = $latestTested['backup_id'] ?? null;

        foreach ($grouped as $freq => $tierBackups) {
            $limit = $retentionLimits[$freq] ?? 24;
            if (count($tierBackups) > $limit) {
                // Tier backups are sorted newest to oldest. Oldest start at index $limit.
                $candidates = array_slice($tierBackups, $limit);
                foreach ($candidates as $cand) {
                    $candId = $cand['backup_id'];

                    // CRITICAL SAFETY RULES:
                    // 1. Never delete if is_protected is explicitly true
                    if (! empty($cand['is_protected'])) {
                        continue;
                    }
                    // 2. Never delete the single newest valid backup overall
                    if ($candId === $latestValidId) {
                        continue;
                    }
                    // 3. Never delete the single newest restore-tested backup overall
                    if ($candId === $latestTestedId) {
                        continue;
                    }

                    $targetDir = "{$this->basePath}/{$candId}";
                    if (is_dir($targetDir)) {
                        File::deleteDirectory($targetDir);
                        $prunedCount++;
                        Log::info("Pruned old backup snapshot: {$candId} [reason: retention_limit_exceeded]");
                    }
                }
            }
        }

        return $prunedCount;
    }

    /**
     * Encrypt a file using AEAD AES-256-GCM authenticated encryption.
     *
     * Known memory limitation: Uses single-pass OpenSSL AES-256-GCM. Backups exceeding
     * available memory_limit (configured default: 512MB) must be partitioned or streamed.
     */
    public function encryptFile(string $sourceFile, string $destFile, string $key, string $backupId = '', string $fileName = ''): void
    {
        if (empty($backupId) || empty($fileName)) {
            throw new \InvalidArgumentException("Backup ID and filename are required for AEAD context binding.");
        }

        if (! file_exists($sourceFile)) {
            throw new \RuntimeException("Source file for backup encryption does not exist: {$sourceFile}");
        }

        $plaintext = file_get_contents($sourceFile);
        if ($plaintext === false) {
            throw new \RuntimeException("Unable to read plaintext file for encryption: {$sourceFile}");
        }

        $binaryKey = hash('sha256', $key, true);
        $iv = random_bytes(12); // Cryptographically secure 12-byte nonce for GCM
        $tag = '';
        $aad = "BuildFlow-AEAD-v2|{$backupId}|{$fileName}";

        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $binaryKey, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new \RuntimeException("AEAD encryption failed for file: {$sourceFile}");
        }

        // Binary Envelope:
        // 'BFENC' (5 bytes) + Version 0x02 (1 byte) + Cipher 0x01 (1 byte) + IV len 0x0C (1 byte) + Tag len 0x10 (1 byte) = 9 bytes header
        // Followed by 12 bytes IV + 16 bytes Tag + Ciphertext
        $header = "BFENC\x02\x01\x0C\x10" . $iv . $tag;

        $destTemp = "{$destFile}.tmp_" . bin2hex(random_bytes(6));
        if (file_put_contents($destTemp, $header . $ciphertext) === false) {
            throw new \RuntimeException("Failed writing encrypted payload to temporary file: {$destTemp}");
        }
        if (! rename($destTemp, $destFile)) {
            @unlink($destTemp);
            throw new \RuntimeException("Failed renaming temporary encrypted file to {$destFile}");
        }
    }

    /**
     * Decrypt an encrypted file using AEAD AES-256-GCM, with legacy AES-256-CBC backward compatibility.
     *
     * Known memory limitation: Loads ciphertext into memory for tag verification before writing verified output.
     */
    public function decryptFile(string $sourceFile, string $destFile, string $key, ?string $backupId = null, ?string $fileName = null): void
    {
        if (! file_exists($sourceFile)) {
            throw new \RuntimeException("Encrypted backup source file not found: {$sourceFile}");
        }

        $data = file_get_contents($sourceFile);
        if ($data === false || $data === '') {
            throw new \RuntimeException("Encrypted backup payload is empty or unreadable: {$sourceFile}");
        }

        $binaryKey = hash('sha256', $key, true);

        // 1. Detect Version 2 AEAD Envelope ('BFENC\x02')
        if (str_starts_with($data, "BFENC\x02")) {
            if (strlen($data) < 37) { // 9 bytes header + 12 IV + 16 Tag = 37 min
                throw new \RuntimeException("Encrypted backup payload is truncated or corrupted (length < 37 bytes).");
            }

            if (substr($data, 0, 5) !== 'BFENC') {
                throw new \RuntimeException("Invalid backup encryption magic header.");
            }

            $version = ord($data[5]);
            if ($version !== 2) {
                throw new \RuntimeException("Unsupported envelope format version: {$version}");
            }

            $cipherId = ord($data[6]);
            if ($cipherId !== 1) { // 0x01 = AES-256-GCM
                throw new \RuntimeException("Unsupported cipher identifier in envelope: 0x" . dechex($cipherId));
            }

            $ivLen = ord($data[7]);
            if ($ivLen !== 12) {
                throw new \RuntimeException("Invalid AEAD IV length in envelope: {$ivLen} bytes (expected 12).");
            }

            $tagLen = ord($data[8]);
            if ($tagLen !== 16) {
                throw new \RuntimeException("Invalid AEAD tag length in envelope: {$tagLen} bytes (expected 16).");
            }

            $iv = substr($data, 9, 12);
            $tag = substr($data, 21, 16);
            $ciphertext = substr($data, 37);

            // Infer backupId and fileName from source file path if not explicitly provided
            if ($backupId === null) {
                $parent = basename(dirname($sourceFile));
                if (str_starts_with($parent, 'backup_')) {
                    $backupId = $parent;
                }
            }
            if ($fileName === null) {
                $base = basename($sourceFile);
                $fileName = str_ends_with($base, '.enc') ? substr($base, 0, -4) : $base;
            }

            if (empty($backupId) || empty($fileName)) {
                throw new \RuntimeException("AEAD context binding failure: Backup ID and filename are required to verify AAD.");
            }

            // Strict context binding: exact AAD required without permissive fallback
            $expectedAad = "BuildFlow-AEAD-v2|{$backupId}|{$fileName}";

            $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $binaryKey, OPENSSL_RAW_DATA, $iv, $tag, $expectedAad);

            if ($plaintext === false) {
                throw new \RuntimeException("AEAD decryption / authentication failed: Invalid authentication tag, altered ciphertext, mismatched context (AAD), or wrong key.");
            }

            // Atomic write to destination: write to .tmp then rename upon verified authentication
            $destTemp = "{$destFile}.tmp_" . bin2hex(random_bytes(6));
            if (file_put_contents($destTemp, $plaintext) === false) {
                throw new \RuntimeException("Failed writing decrypted payload to temporary file: {$destTemp}");
            }
            if (! rename($destTemp, $destFile)) {
                @unlink($destTemp);
                throw new \RuntimeException("Failed renaming verified decrypted file to {$destFile}");
            }
            return;
        }

        // 2. Legacy Version 1 (AES-256-CBC) Compatibility
        if (config('backup.encryption.allow_legacy_cbc_decrypt', true)) {
            if (strlen($data) < 16) {
                throw new \RuntimeException('Legacy encrypted backup payload too short (less than 16 bytes).');
            }

            $iv = substr($data, 0, 16);
            $ciphertext = substr($data, 16);
            $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $binaryKey, OPENSSL_RAW_DATA, $iv);

            if ($plaintext === false) {
                throw new \RuntimeException('Legacy CBC decryption failed: invalid key or corrupted payload.');
            }

            Log::warning("Decrypted legacy AES-256-CBC backup file: {$sourceFile}. Consider upgrading to AEAD AES-256-GCM.");

            // Atomic write
            $destTemp = "{$destFile}.tmp_" . bin2hex(random_bytes(6));
            if (file_put_contents($destTemp, $plaintext) === false) {
                throw new \RuntimeException("Failed writing legacy decrypted payload to temporary file: {$destTemp}");
            }
            if (! rename($destTemp, $destFile)) {
                @unlink($destTemp);
                throw new \RuntimeException("Failed renaming legacy decrypted file to {$destFile}");
            }
            return;
        }

        throw new \RuntimeException('Unsupported or corrupted backup encryption format.');
    }

    /**
     * Run a CLI command securely passing sensitive environment variables (such as MYSQL_PWD).
     */
    protected function runCliCommand(string $command, ?string $mysqlPassword = null): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = [
            'PATH' => getenv('PATH') ?: '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin',
        ];
        if ($mysqlPassword !== null) {
            $env['MYSQL_PWD'] = $mysqlPassword;
        }

        $proc = proc_open($command, $descriptors, $pipes, null, $env);
        if (! is_resource($proc)) {
            return -1;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return proc_close($proc);
    }
}
