<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Backup\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AutomatedBackupAndReleaseAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected string $testBackupPath;
    protected BackupManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBackupPath = storage_path('app/testing_backups_' . time());
        File::ensureDirectoryExists($this->testBackupPath, 0700);
        Config::set('backup.path', $this->testBackupPath);

        $this->manager = new BackupManager($this->testBackupPath);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testBackupPath)) {
            File::deleteDirectory($this->testBackupPath);
        }

        parent::tearDown();
    }

    /**
     * 1. Test durable backup creation and manifest generation.
     */
    public function test_durable_backup_creation_and_manifest_generation(): void
    {
        $manifest = $this->manager->createBackup('hourly', false);

        $this->assertNotEmpty($manifest['backup_id']);
        $this->assertSame('hourly', $manifest['frequency']);
        $this->assertGreaterThan(0, $manifest['database']['tables_count']);
        $this->assertNotEmpty($manifest['database']['dump_checksum']);
        $this->assertFileExists("{$this->testBackupPath}/{$manifest['backup_id']}/database.sql");
        $this->assertFileExists("{$this->testBackupPath}/{$manifest['backup_id']}/manifest.json");

        // Verify manifest JSON on disk
        $diskManifest = json_decode(file_get_contents("{$this->testBackupPath}/{$manifest['backup_id']}/manifest.json"), true);
        $this->assertSame($manifest['backup_id'], $diskManifest['backup_id']);
        $this->assertSame('pending', $diskManifest['validation_status']);
    }

    /**
     * 2. Test backup validation verifies checksums and catches file tampering.
     */
    public function test_backup_validation_detects_checksum_tampering(): void
    {
        $manifest = $this->manager->createBackup('daily', false);
        $backupId = $manifest['backup_id'];

        // Initial validation passes
        $val1 = $this->manager->validateBackup($backupId);
        $this->assertTrue($val1['is_valid']);
        $this->assertEmpty($val1['errors']);

        // Tamper with the SQL dump file
        $sqlPath = "{$this->testBackupPath}/{$backupId}/database.sql";
        file_put_contents($sqlPath, "\n-- Tampered Line Injected --\n", FILE_APPEND);

        // Validation must now fail with checksum mismatch
        $val2 = $this->manager->validateBackup($backupId);
        $this->assertFalse($val2['is_valid']);
        $this->assertNotEmpty($val2['errors']);
        $this->assertStringContainsString('checksum mismatch', $val2['errors'][0]);
    }

    /**
     * 3. Test AES-256 encryption at rest and decryption recovery.
     */
    public function test_backup_encryption_at_rest_and_decryption_recovery(): void
    {
        $key = 'SecretEncryptionPassphrase2026!';
        Config::set('backup.encryption.key', $key);

        $manifest = $this->manager->createBackup('hourly', true);
        $backupId = $manifest['backup_id'];

        $this->assertTrue($manifest['encryption']['enabled']);
        $this->assertSame('AES-256-CBC', $manifest['encryption']['cipher']);
        $this->assertFileExists("{$this->testBackupPath}/{$backupId}/database.sql.enc");
        $this->assertFileDoesNotExist("{$this->testBackupPath}/{$backupId}/database.sql");

        // Validate encrypted backup (tests decryption & hash verification)
        $valResult = $this->manager->validateBackup($backupId);
        $this->assertTrue($valResult['is_valid']);

        // Test manual decryption matches original SHA-256
        $tempDecrypted = "{$this->testBackupPath}/{$backupId}/recovered.sql";
        $this->manager->decryptFile("{$this->testBackupPath}/{$backupId}/database.sql.enc", $tempDecrypted, $key);

        $recoveredHash = hash_file('sha256', $tempDecrypted);
        $this->assertSame($manifest['database']['dump_checksum'], $recoveredHash);
        @unlink($tempDecrypted);
    }

    /**
     * 4. Test retention pruning strictly protects the last verified recovery copy.
     */
    public function test_retention_pruning_protects_verified_recovery_copy(): void
    {
        Config::set('backup.retention.hourly', 2);

        // Create first backup and protect it immediately (simulating a tested recovery snapshot)
        $b1 = $this->manager->createBackup('hourly', false);
        $this->manager->markRestoreTestPassed($b1['backup_id']);

        sleep(1);
        $b2 = $this->manager->createBackup('hourly', false);
        sleep(1);
        $b3 = $this->manager->createBackup('hourly', false);
        sleep(1);
        $b4 = $this->manager->createBackup('hourly', false);

        // $b1 must be preserved because is_protected=true!
        $this->assertFileExists("{$this->testBackupPath}/{$b1['backup_id']}/manifest.json");

        // Unprotected older backup ($b2) was pruned when retention count was exceeded
        $this->assertFileDoesNotExist("{$this->testBackupPath}/{$b2['backup_id']}/manifest.json");

        // Most recent backup ($b4) must be preserved
        $this->assertFileExists("{$this->testBackupPath}/{$b4['backup_id']}/manifest.json");
    }

    /**
     * 5. Test overlap locking prevents concurrent backup runs.
     */
    public function test_backup_overlap_locking_prevents_concurrent_runs(): void
    {
        // Acquire lock beforehand
        $lock = Cache::lock('buildflow_backup_running_lock', 60);
        $this->assertTrue($lock->get());

        // Run command while locked
        $exitCode = Artisan::call('buildflow:backup:create');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('already running', $output);

        $lock->release();
    }

    /**
     * 6. Test health check endpoint reports accurate durable backup and RPO status.
     */
    public function test_health_check_reports_durable_backup_and_rpo_status(): void
    {
        $manifest = $this->manager->createBackup('hourly', false);
        $this->manager->validateBackup($manifest['backup_id']);
        $this->manager->markRestoreTestPassed($manifest['backup_id']);

        $response = $this->getJson('/health');
        $response->assertStatus(200);

        $response->assertJson([
            'checks' => [
                'durable_backups' => [
                    'status' => 'ok',
                    'latest_backup_id' => $manifest['backup_id'],
                    'validation_status' => 'valid',
                    'restore_test_status' => 'passed',
                    'rpo_status' => 'satisfied',
                ],
            ],
        ]);
    }

    /**
     * 7. Test release preflight command in development vs strict production mode.
     */
    public function test_release_preflight_command_distinguishes_development_from_strict(): void
    {
        // Create an initial validated backup in the test directory
        $manifest = $this->manager->createBackup('hourly', false);
        $this->manager->validateBackup($manifest['backup_id']);
        $this->manager->markRestoreTestPassed($manifest['backup_id']);

        // In local development mode without --strict, returns success with warnings
        $devExit = Artisan::call('buildflow:preflight');
        $devOutput = Artisan::output();

        $this->assertSame(0, $devExit);
        $this->assertStringContainsString('READY WITH STATED LIMITATIONS', $devOutput);
        $this->assertStringContainsString('Durable Automated Backups', $devOutput);

        // In strict mode, since environment is local and ClamAV is not on this dev host, it fails
        $strictExit = Artisan::call('buildflow:preflight', ['--strict' => true]);
        $strictOutput = Artisan::output();

        $this->assertSame(1, $strictExit);
        $this->assertStringContainsString('PREFLIGHT RESULT: FAILED', $strictOutput);
    }
}
