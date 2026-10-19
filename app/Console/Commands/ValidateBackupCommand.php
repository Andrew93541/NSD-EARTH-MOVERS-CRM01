<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;

class ValidateBackupCommand extends Command
{
    protected $signature = 'nsdcrm:backup:validate 
                            {backup_id? : Backup ID to validate (defaults to latest backup)}';

    protected $aliases = ['buildflow:backup:validate'];

    protected $description = 'Validate SHA-256 checksums, AEAD authentication, and archive integrity of a durable backup';

    public function handle(BackupManager $manager): int
    {
        $this->info("=== NSDCRM Backup Validation Engine ===");

        $backupId = $this->argument('backup_id');
        if (! $backupId) {
            $latest = $manager->getLatestBackup();
            if (! $latest) {
                $this->error("No backups found to validate. Run [php artisan buildflow:backup:create] first.");
                return Command::FAILURE;
            }
            $backupId = $latest['backup_id'];
        }

        $this->comment("Validating backup: <info>{$backupId}</info>...");

        try {
            $result = $manager->validateBackup($backupId);

            if ($result['is_valid']) {
                $m = $result['manifest'];
                $this->info("Validation PASSED for [{$backupId}].");
                $this->line("  Database Dump: {$m['database']['dump_file']} (Checksum verified: {$m['database']['dump_checksum']})");
                if (! empty($m['storage']['archive_file'])) {
                    $this->line("  Storage Archive: {$m['storage']['archive_file']} (Checksum verified: {$m['storage']['archive_checksum']})");
                }
                if (! empty($m['encryption']['enabled'])) {
                    $authStatus = ! empty($m['encryption']['authenticated']) ? 'AEAD Authenticated' : 'Unauthenticated (Legacy)';
                    $this->line("  Encrypted: Yes ({$m['encryption']['cipher']}, {$authStatus}, key fingerprint: " . ($m['encryption']['key_fingerprint'] ?? 'N/A') . ")");
                } else {
                    $this->line("  Encrypted: No");
                }
                $this->line("  Validated At: {$m['validated_at']}");
                return Command::SUCCESS;
            } else {
                $this->error("Validation FAILED for [{$backupId}]:");
                foreach ($result['errors'] as $err) {
                    $this->error("  - {$err}");
                }
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error("Validation error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
