<?php

namespace Tests\Feature;

use App\Console\Commands\ReleasePreflightCommand;
use App\Models\User;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Replication\BackupReplicationInterface;
use App\Services\Backup\Replication\LocalSecondaryReplicationProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionBackupEncryptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected BackupManager $backupManager;
    protected string $testKey;
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupManager = app(BackupManager::class);
        $this->testKey = 'super-secret-backup-test-key-32b!';
        $this->tempDir = storage_path('framework/testing/backup_crypto_' . uniqid());
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_aead_encrypt_and_decrypt_round_trip(): void
    {
        $sourceFile = "{$this->tempDir}/plain.sql";
        $encFile = "{$this->tempDir}/database.sql.enc";
        $decFile = "{$this->tempDir}/recovered.sql";

        $originalContent = "-- SQL DUMP 2026\nINSERT INTO users VALUES (1, 'Admin');";
        file_put_contents($sourceFile, $originalContent);

        $backupId = 'backup_20261018_120000_hourly';
        $fileName = 'database.sql';

        $this->backupManager->encryptFile($sourceFile, $encFile, $this->testKey, $backupId, $fileName);
        $this->assertFileExists($encFile);

        // Verify envelope header
        $encData = file_get_contents($encFile);
        $this->assertStringStartsWith("BFENC\x02\x01\x0C\x10", $encData);

        // Decrypt
        $this->backupManager->decryptFile($encFile, $decFile, $this->testKey, $backupId, $fileName);
        $this->assertFileExists($decFile);
        $this->assertEquals($originalContent, file_get_contents($decFile));
    }

    public function test_aead_context_binding_fails_if_backup_id_mismatched(): void
    {
        $sourceFile = "{$this->tempDir}/plain.sql";
        $encFile = "{$this->tempDir}/database.sql.enc";
        $decFile = "{$this->tempDir}/recovered.sql";

        file_put_contents($sourceFile, 'IMPORTANT_DATABASE_PAYLOAD');

        $this->backupManager->encryptFile($sourceFile, $encFile, $this->testKey, 'backup_original_123', 'database.sql');

        // Attempt decrypt with different backup_id
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AEAD decryption / authentication failed');

        $this->backupManager->decryptFile($encFile, $decFile, $this->testKey, 'backup_substituted_999', 'database.sql');
    }

    public function test_aead_context_binding_fails_if_filename_mismatched(): void
    {
        $sourceFile = "{$this->tempDir}/plain.sql";
        $encFile = "{$this->tempDir}/database.sql.enc";
        $decFile = "{$this->tempDir}/recovered.sql";

        file_put_contents($sourceFile, 'IMPORTANT_DATABASE_PAYLOAD');

        $this->backupManager->encryptFile($sourceFile, $encFile, $this->testKey, 'backup_2026_test', 'database.sql');

        // Attempt decrypt claiming it is storage.tar.gz
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AEAD decryption / authentication failed');

        $this->backupManager->decryptFile($encFile, $decFile, $this->testKey, 'backup_2026_test', 'storage.tar.gz');
    }

    public function test_tampered_authentication_tag_is_cryptographically_rejected(): void
    {
        $sourceFile = "{$this->tempDir}/plain.sql";
        $encFile = "{$this->tempDir}/database.sql.enc";
        $decFile = "{$this->tempDir}/recovered.sql";

        file_put_contents($sourceFile, 'DATA_FOR_TAG_TAMPER_TEST');

        $this->backupManager->encryptFile($sourceFile, $encFile, $this->testKey, 'backup_tag_test', 'database.sql');

        // Corrupt tag (bytes 21 to 36)
        $encData = file_get_contents($encFile);
        $encData[25] = chr(ord($encData[25]) ^ 0xFF);
        file_put_contents($encFile, $encData);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AEAD decryption / authentication failed');

        $this->backupManager->decryptFile($encFile, $decFile, $this->testKey, 'backup_tag_test', 'database.sql');
    }

    public function test_wrong_encryption_key_fails_decryption(): void
    {
        $sourceFile = "{$this->tempDir}/plain.sql";
        $encFile = "{$this->tempDir}/database.sql.enc";
        $decFile = "{$this->tempDir}/recovered.sql";

        file_put_contents($sourceFile, 'SECRET_PAYLOAD');

        $this->backupManager->encryptFile($sourceFile, $encFile, $this->testKey, 'backup_key_test', 'database.sql');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AEAD decryption / authentication failed');

        $this->backupManager->decryptFile($encFile, $decFile, 'wrong-key-value-9999999!', 'backup_key_test', 'database.sql');
    }

    public function test_atomic_write_prevents_partial_file_creation_on_failed_decryption(): void
    {
        $sourceFile = "{$this->tempDir}/plain.sql";
        $encFile = "{$this->tempDir}/database.sql.enc";
        $decFile = "{$this->tempDir}/recovered_atomic.sql";

        file_put_contents($sourceFile, 'ATOMIC_INTEGRITY_CHECK');

        $this->backupManager->encryptFile($sourceFile, $encFile, $this->testKey, 'backup_atomic', 'database.sql');

        // Attempt decrypt with wrong key
        try {
            $this->backupManager->decryptFile($encFile, $decFile, 'wrong-key!', 'backup_atomic', 'database.sql');
            $this->fail('Decryption should have thrown an exception.');
        } catch (\RuntimeException $e) {
            // Success: Exception thrown
        }

        // Destination file must NOT exist (no partial or unverified file left behind)
        $this->assertFileDoesNotExist($decFile);
    }

    public function test_malformed_envelope_headers_fail_closed(): void
    {
        $decFile = "{$this->tempDir}/recovered_malformed.sql";

        // Bad magic header
        $badMagic = "XXXXX\x02\x01\x0C\x10" . str_repeat('A', 12) . str_repeat('B', 16) . 'ciphertext';
        $badMagicFile = "{$this->tempDir}/bad_magic.enc";
        file_put_contents($badMagicFile, $badMagic);

        // When legacy CBC fallback is disabled or enabled, it shouldn't accept corrupted V2
        Config::set('backup.encryption.allow_legacy_cbc_decrypt', false);

        try {
            $this->backupManager->decryptFile($badMagicFile, $decFile, $this->testKey, 'backup_test', 'data.sql');
            $this->fail('Should have rejected bad magic header.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unsupported or corrupted', $e->getMessage());
        }

        // Truncated V2 payload (< 37 bytes)
        $truncated = "BFENC\x02\x01\x0C\x10" . str_repeat('A', 5);
        $truncatedFile = "{$this->tempDir}/truncated.enc";
        file_put_contents($truncatedFile, $truncated);

        try {
            $this->backupManager->decryptFile($truncatedFile, $decFile, $this->testKey, 'backup_test', 'data.sql');
            $this->fail('Should have rejected truncated payload.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('truncated or corrupted', $e->getMessage());
        }
    }

    public function test_legacy_aes_256_cbc_decryption_compatibility(): void
    {
        $decFile = "{$this->tempDir}/recovered_legacy.sql";
        $legacyContent = "-- LEGACY CBC BACKUP 2025\nSELECT * FROM projects;";

        // Encrypt with legacy AES-256-CBC format (16 byte IV + ciphertext)
        $binaryKey = hash('sha256', $this->testKey, true);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($legacyContent, 'AES-256-CBC', $binaryKey, OPENSSL_RAW_DATA, $iv);
        $legacyPayload = $iv . $ciphertext;

        $legacyFile = "{$this->tempDir}/legacy.sql.enc";
        file_put_contents($legacyFile, $legacyPayload);

        Config::set('backup.encryption.allow_legacy_cbc_decrypt', true);

        $this->backupManager->decryptFile($legacyFile, $decFile, $this->testKey);
        $this->assertFileExists($decFile);
        $this->assertEquals($legacyContent, file_get_contents($decFile));
    }

    public function test_production_fails_closed_when_backup_encryption_is_disabled(): void
    {
        // Mock production environment
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Production backup policy violation: Backups in production must be encrypted.');

        $this->backupManager->createBackup('hourly', false);
    }

    public function test_production_fails_closed_when_backup_encryption_key_is_missing(): void
    {
        // Mock production environment
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('backup.encryption.key', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dedicated BACKUP_ENCRYPTION_KEY is required in production and cannot fall back to APP_KEY.');

        $this->backupManager->getEncryptionKey();
    }

    public function test_database_seeder_throws_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DatabaseSeeder contains development fixtures and demo accounts. It cannot be executed in production.');

        (new DatabaseSeeder())->run();
    }

    public function test_health_check_reports_503_in_production_when_backup_is_unencrypted(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Create an unencrypted backup manifest mock
        $backupDir = storage_path('app/backups/backup_test_unenc');
        File::ensureDirectoryExists($backupDir);
        file_put_contents("{$backupDir}/manifest.json", json_encode([
            'manifest_version' => 2,
            'backup_id' => 'backup_test_unenc',
            'created_at' => now()->toIso8601String(),
            'frequency' => 'hourly',
            'validation_status' => 'valid',
            'encryption' => ['enabled' => false],
        ]));

        $response = $this->get('/health');
        $response->assertStatus(503);
        $response->assertJsonPath('status', 'unhealthy');
        $response->assertJsonPath('checks.durable_backups.status', 'unencrypted_in_production');

        File::deleteDirectory($backupDir);
    }

    public function test_local_secondary_replication_is_reported_as_unverified_off_host(): void
    {
        Config::set('backup.replication.is_off_host', false);

        $response = $this->get('/health');
        $response->assertJsonPath('checks.secondary_replication.is_off_host', false);
        $response->assertJsonPath('checks.secondary_replication.off_host_status', 'unverified_same_host');
    }

    public function test_strict_release_preflight_fails_when_demo_accounts_exist(): void
    {
        // Ensure a known demo account exists
        User::create([
            'name' => 'Demo PM',
            'email' => 'pm@buildflow.local',
            'password' => bcrypt('Password123!'),
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('buildflow:preflight', ['--strict' => true]);
        $this->assertEquals(1, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('default demo accounts exist', $output);
        $this->assertStringContainsString('Operator action required', $output);
    }
}
