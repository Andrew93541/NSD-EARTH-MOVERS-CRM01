<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;

class ValidateBackupCommand extends Command
{
    protected $signature = 'buildflow:backup:validate 
                            {--backup= : Backup ID to validate (defaults to latest backup)}';

    protected $description = 'Validate SHA-256 checksums and archive integrity of a durable backup';

    public function handle(BackupManager $manager): int
    {
        $this->info("=== BuildFlow Backup Validation Engine ===");

        $backupId = $this->option('backup');
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
                $this->line("  Encrypted: " . ($m['encryption']['enabled'] ? 'Yes (' . $m['encryption']['cipher'] . ')' : 'No'));
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
