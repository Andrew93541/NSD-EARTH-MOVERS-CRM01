<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CreateBackupCommand extends Command
{
    protected $signature = 'buildflow:backup:create 
                            {--frequency=hourly : Frequency tier (hourly, daily, monthly)} 
                            {--encrypt : Encrypt snapshot with AES-256}';

    protected $description = 'Create a durable, persistent backup of the MySQL database and private document storage';

    public function handle(BackupManager $manager): int
    {
        $this->info("=== BuildFlow Durable Backup Engine ===");

        // Prevent overlapping backup jobs
        $lock = Cache::lock('buildflow_backup_running_lock', 600);
        if (! $lock->get()) {
            $this->warn("A backup process is already running. Aborting this run to prevent collisions.");
            return Command::SUCCESS;
        }

        try {
            $frequency = $this->option('frequency') ?: 'hourly';
            $encrypt = $this->option('encrypt') ? true : null;

            $this->comment("Creating durable [{$frequency}] backup...");
            $manifest = $manager->createBackup($frequency, $encrypt);

            $this->line("  [OK] Snapshot created: <info>{$manifest['backup_id']}</info>");
            $this->line("  [OK] Database dump: {$manifest['database']['dump_file']} (" . round($manifest['database']['dump_size_bytes'] / 1024, 2) . " KB)");
            if (! empty($manifest['storage']['archive_file'])) {
                $this->line("  [OK] Private storage: {$manifest['storage']['archive_file']} (" . round($manifest['storage']['archive_size_bytes'] / 1024, 2) . " KB)");
            }
            $this->line("  [OK] Ledger balanced: Dr ₹" . number_format($manifest['financial_ledger']['total_debit'], 2) . " == Cr ₹" . number_format($manifest['financial_ledger']['total_credit'], 2));

            // Validate the freshly created backup
            $this->comment("Validating backup checksums and archive readability...");
            $valResult = $manager->validateBackup($manifest['backup_id']);

            if ($valResult['is_valid']) {
                $this->info("Backup created and validated successfully: {$manifest['backup_id']}");
                return Command::SUCCESS;
            } else {
                $this->error("Backup validation failed for {$manifest['backup_id']}:");
                foreach ($valResult['errors'] as $err) {
                    $this->error("  - {$err}");
                }
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error("Backup creation failed: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
