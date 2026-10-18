<?php

namespace Tests\Feature;

use App\Console\Commands\VerifyDisasterRecoveryCommand;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Replication\LocalSecondaryReplicationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupCryptographyAndRecoverySlaTest extends TestCase
{
    use RefreshDatabase;

    protected string $primaryBackupPath;
    protected string $secondaryBackupPath;
    protected BackupManager $manager;
    protected LocalSecondaryReplicationProvider $replicator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primaryBackupPath = storage_path('app/testing_primary_backups_' . time());
        $this->secondaryBackupPath = storage_path('app/testing_secondary_backups_' . time());

        File::ensureDirectoryExists($this->primaryBackupPath, 0700);
        File::ensureDirectoryExists($this->secondaryBackupPath, 0700);

        Config::set('backup.path', $this->primaryBackupPath);
        Config::set('backup.replication.local_secondary.path', $this->secondaryBackupPath);
        Config::set('backup.encryption.key', 'MasterTestEncryptionSecretPassphrase2026!');

        $this->manager = new BackupManager($this->primaryBackupPath);
        $this->replicator = new LocalSecondaryReplicationProvider($this->secondaryBackupPath, $this->primaryBackupPath);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->primaryBackupPath)) {
            File::deleteDirectory($this->primaryBackupPath);
        }
        if (File::exists($this->secondaryBackupPath)) {
            File::deleteDirectory($this->secondaryBackupPath);
        }

        parent::tearDown();
    }

    /**
     * 1. Test AEAD AES-256-GCM authenticated encryption and decryption round-trip.
     */
    public function test_aead_authenticated_encryption_and_decryption_round_trip(): void
    {
        $manifest = $this->manager->createBackup('hourly', true);
        $backupId = $manifest['backup_id'];

        $this->assertTrue($manifest['encryption']['enabled']);
        $this->assertSame('aes-256-gcm', $manifest['encryption']['cipher']);
        $this->assertTrue($manifest['encryption']['authenticated']);
        $this->assertSame(2, $manifest['encryption']['format_version']);
        $this->assertNotEmpty($manifest['encryption']['key_fingerprint']);

        $encSqlPath = "{$this->primaryBackupPath}/{$backupId}/database.sql.enc";
        $this->assertFileExists($encSqlPath);

        // Header check: starts with BFENC\x02
        $fileHeader = file_get_contents($encSqlPath, false, null, 0, 6);
        $this->assertSame("BFENC\x02", $fileHeader);

        // Decrypt using manager
        $decryptedPath = "{$this->primaryBackupPath}/{$backupId}/test_decrypted.sql";
        $key = 'MasterTestEncryptionSecretPassphrase2026!';
        $this->manager->decryptFile($encSqlPath, $decryptedPath, $key, $backupId, 'database.sql');

        $this->assertFileExists($decryptedPath);
        $this->assertSame($manifest['database']['dump_checksum'], hash_file('sha256', $decryptedPath));
        @unlink($decryptedPath);
    }

    /**
     * 2. Test tampered ciphertext is rejected by AEAD authentication tag.
     */
    public function test_tampered_ciphertext_rejected_by_aead_tag(): void
    {
        $manifest = $this->manager->createBackup('daily', true);
        $backupId = $manifest['backup_id'];
        $encSqlPath = "{$this->primaryBackupPath}/{$backupId}/database.sql.enc";

        // Flip one bit in the ciphertext (beyond the 37-byte header+IV+tag envelope)
        $data = file_get_contents($encSqlPath);
        $ciphertextOffset = 45;
        $data[$ciphertextOffset] = chr(ord($data[$ciphertextOffset]) ^ 0xFF);
        file_put_contents($encSqlPath, $data);

        // Decryption MUST fail with authenticated exception
        $decryptedPath = "{$this->primaryBackupPath}/{$backupId}/tampered.sql";
        $key = 'MasterTestEncryptionSecretPassphrase2026!';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AEAD decryption / authentication failed');

        $this->manager->decryptFile($encSqlPath, $decryptedPath, $key, $backupId, 'database.sql');
    }

    /**
     * 3. Test tampered authentication tag is rejected.
     */
    public function test_tampered_auth_tag_rejected(): void
    {
        $manifest = $this->manager->createBackup('daily', true);
        $backupId = $manifest['backup_id'];
        $encSqlPath = "{$this->primaryBackupPath}/{$backupId}/database.sql.enc";

        // Tamper with the tag located at bytes 21..36
        $data = file_get_contents($encSqlPath);
        $data[25] = chr(ord($data[25]) ^ 0x01);
        file_put_contents($encSqlPath, $data);

        $decryptedPath = "{$this->primaryBackupPath}/{$backupId}/tampered_tag.sql";
        $key = 'MasterTestEncryptionSecretPassphrase2026!';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AEAD decryption / authentication failed');

        $this->manager->decryptFile($encSqlPath, $decryptedPath, $key, $backupId, 'database.sql');
    }

    /**
     * 4. Test invalid/wrong encryption key fails decryption cleanly without leaking secret.
     */
    public function test_wrong_encryption_key_fails_aead_decryption(): void
    {
        $manifest = $this->manager->createBackup('hourly', true);
        $backupId = $manifest['backup_id'];
        $encSqlPath = "{$this->primaryBackupPath}/{$backupId}/database.sql.enc";

        $decryptedPath = "{$this->primaryBackupPath}/{$backupId}/wrong_key.sql";
        $wrongKey = 'WrongIncorrectSecretPassphrase1234!';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AEAD decryption / authentication failed');

        $this->manager->decryptFile($encSqlPath, $decryptedPath, $wrongKey, $backupId, 'database.sql');
    }

    /**
     * 5. Test truncated encrypted file is caught and rejected.
     */
    public function test_truncated_backup_file_detected_and_rejected(): void
    {
        $manifest = $this->manager->createBackup('hourly', true);
        $backupId = $manifest['backup_id'];
        $encSqlPath = "{$this->primaryBackupPath}/{$backupId}/database.sql.enc";

        // Truncate file to only 15 bytes
        file_put_contents($encSqlPath, "BFENC\x02\x01\x0C\x10123456");

        $decryptedPath = "{$this->primaryBackupPath}/{$backupId}/truncated.sql";
        $key = 'MasterTestEncryptionSecretPassphrase2026!';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('truncated or corrupted');

        $this->manager->decryptFile($encSqlPath, $decryptedPath, $key, $backupId, 'database.sql');
    }

    /**
     * 6. Test legacy Version 1 (AES-256-CBC) backward compatibility.
     */
    public function test_legacy_aes_256_cbc_backward_compatibility(): void
    {
        $key = 'MasterTestEncryptionSecretPassphrase2026!';
        $binaryKey = hash('sha256', $key, true);
        $iv = openssl_random_pseudo_bytes(16);
        $rawSql = "-- Legacy Database Dump\nSELECT 1;\n";
        $legacyCiphertext = openssl_encrypt($rawSql, 'AES-256-CBC', $binaryKey, OPENSSL_RAW_DATA, $iv);

        $legacyFile = "{$this->primaryBackupPath}/legacy_test.sql.enc";
        file_put_contents($legacyFile, $iv . $legacyCiphertext);

        // Decrypt legacy file
        $decrypted = "{$this->primaryBackupPath}/legacy_test_decrypted.sql";
        $this->manager->decryptFile($legacyFile, $decrypted, $key);

        $this->assertFileExists($decrypted);
        $this->assertSame($rawSql, file_get_contents($decrypted));
        @unlink($legacyFile);
        @unlink($decrypted);
    }

    /**
     * 7. Test RPO freshness thresholds: 60m target, 75m critical breach limit.
     */
    public function test_rpo_freshness_thresholds(): void
    {
        Config::set('backup.rpo.target_minutes', 60);
        Config::set('backup.rpo.critical_threshold_minutes', 75);

        // Under 60m: Satisfied
        $this->assertSame('satisfied', $this->manager->getRpoStatus(25));
        $this->assertSame('satisfied', $this->manager->getRpoStatus(60));

        // Between 61m and 75m: Warning approaching breach
        $this->assertSame('warning_approaching_breach', $this->manager->getRpoStatus(61));
        $this->assertSame('warning_approaching_breach', $this->manager->getRpoStatus(75));

        // Above 75m: Breached
        $this->assertSame('breached', $this->manager->getRpoStatus(76));
        $this->assertSame('breached', $this->manager->getRpoStatus(120));

        // Missing
        $this->assertSame('missing', $this->manager->getRpoStatus(null));
    }

    /**
     * 8. Test secondary backup replication and remote checksum verification.
     */
    public function test_secondary_backup_replication_and_checksum_verification(): void
    {
        $manifest = $this->manager->createBackup('hourly', true);
        $backupId = $manifest['backup_id'];
        $this->manager->validateBackup($backupId);

        // Replicate to secondary
        $result = $this->replicator->replicate($backupId, $manifest);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['checksum_verified']);
        $this->assertFileExists("{$this->secondaryBackupPath}/{$backupId}/database.sql.enc");
        $this->assertFileExists("{$this->secondaryBackupPath}/{$backupId}/manifest.json");
        $this->assertFileExists("{$this->secondaryBackupPath}/{$backupId}/replication_receipt.json");

        // Verify receipt contents
        $receipt = json_decode(file_get_contents("{$this->secondaryBackupPath}/{$backupId}/replication_receipt.json"), true);
        $this->assertSame($backupId, $receipt['backup_id']);
        $this->assertTrue($receipt['checksum_verified']);

        // Check replication status
        $status = $this->replicator->getReplicationStatus();
        $this->assertSame($backupId, $status['latest_replicated_id']);
        $this->assertSame(1, $status['total_replicas']);

        // Test Artisan command
        $exitCode = Artisan::call('buildflow:backup:replicate', ['backup_id' => $backupId]);
        $this->assertSame(0, $exitCode);
    }

    /**
     * 9. Test bounded protected-copy retention policy.
     */
    public function test_bounded_protected_copy_policy(): void
    {
        Config::set('backup.retention.max_protected_restore_copies', 2);
        Config::set('backup.retention.hourly', 2);

        // Create 4 hourly backups and mark each as restore-tested
        $b1 = $this->manager->createBackup('hourly', false);
        $this->manager->markRestoreTestPassed($b1['backup_id']);

        sleep(1);
        $b2 = $this->manager->createBackup('hourly', false);
        $this->manager->markRestoreTestPassed($b2['backup_id']);

        sleep(1);
        $b3 = $this->manager->createBackup('hourly', false);
        $this->manager->markRestoreTestPassed($b3['backup_id']);

        sleep(1);
        $b4 = $this->manager->createBackup('hourly', false);
        $this->manager->markRestoreTestPassed($b4['backup_id']);

        // Under max_protected = 2:
        // b4 and b3 must have is_protected = true
        $m4 = json_decode(file_get_contents("{$this->primaryBackupPath}/{$b4['backup_id']}/manifest.json"), true);
        $m3 = json_decode(file_get_contents("{$this->primaryBackupPath}/{$b3['backup_id']}/manifest.json"), true);
        $this->assertTrue($m4['is_protected']);
        $this->assertTrue($m3['is_protected']);

        // Older b2 had its protection rolled over to false (preventing unbounded retention growth)
        if (file_exists("{$this->primaryBackupPath}/{$b2['backup_id']}/manifest.json")) {
            $m2 = json_decode(file_get_contents("{$this->primaryBackupPath}/{$b2['backup_id']}/manifest.json"), true);
            $this->assertFalse($m2['is_protected']);
        }
    }

    /**
     * 10. Test prevention of destructive live-database restores.
     */
    public function test_prevention_of_destructive_live_database_restores(): void
    {
        Config::set('database.connections.mysql.database', 'buildflow_crm');
        $activeDb = 'buildflow_crm';

        // Targeting active database must fail immediately
        $exit1 = Artisan::call('buildflow:verify-recovery', ['--target-db' => $activeDb]);
        $output1 = Artisan::output();
        $this->assertSame(1, $exit1);
        $this->assertStringContainsString('CRITICAL SAFETY VIOLATION', $output1);

        // Targeting production database name must fail immediately
        $exit2 = Artisan::call('buildflow:verify-recovery', ['--target-db' => 'production']);
        $output2 = Artisan::output();
        $this->assertSame(1, $exit2);
        $this->assertStringContainsString('CRITICAL SAFETY VIOLATION', $output2);
    }

    /**
     * 11. Test health check reports separate creation, validation, restore, and replication states.
     */
    public function test_health_check_reports_separate_operational_states(): void
    {
        $manifest = $this->manager->createBackup('hourly', true);
        $this->manager->validateBackup($manifest['backup_id']);
        $this->manager->markRestoreTestPassed($manifest['backup_id']);
        $this->replicator->replicate($manifest['backup_id'], $manifest);

        $response = $this->getJson('/health');
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'checks' => [
                'durable_backups' => [
                    'status',
                    'latest_backup_id',
                    'age_minutes',
                    'rpo_target_minutes',
                    'rpo_status',
                    'creation' => ['status', 'created_at', 'frequency'],
                    'validation' => ['status', 'validated_at'],
                    'restore_verification' => ['status', 'tested_at', 'is_protected'],
                    'encryption' => ['enabled', 'cipher', 'authenticated', 'key_configured'],
                ],
                'secondary_replication' => [
                    'status',
                    'enabled',
                    'latest_replicated_id',
                    'total_replicas',
                ],
            ],
        ]);
    }
}
