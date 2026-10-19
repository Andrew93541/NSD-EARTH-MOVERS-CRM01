<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManager;
use App\Services\Backup\Replication\BackupReplicationInterface;
use Illuminate\Console\Command;

class ReplicateBackupCommand extends Command
{
    protected $signature = 'nsdcrm:backup:replicate 
                            {backup_id? : ID of durable backup to replicate (defaults to latest validated backup)}
                            {--prune-only : Only prune expired secondary replicas}';

    protected $aliases = ['buildflow:backup:replicate'];

    protected $description = 'Replicate verified durable backups to secondary/off-host destination';

    public function handle(BackupManager $backupManager, BackupReplicationInterface $replicator): int
    {
        $this->info("=== NSDCRM Backup Replication Engine ===");

        if ($this->option('prune-only')) {
            $retention = (int) config('backup.replication.retention_count', 7);
            $pruned = $replicator->pruneSecondary($retention);
            $this->info("Secondary pruning complete. Pruned {$pruned} snapshots.");
            return Command::SUCCESS;
        }

        $backupId = $this->argument('backup_id');
        if (! $backupId) {
            $latest = $backupManager->getLatestValidatedBackup() ?? $backupManager->getLatestBackup();
            if (! $latest) {
                $this->error("No backups available for replication. Create a backup first.");
                return Command::FAILURE;
            }
            $backupId = $latest['backup_id'];
        }

        $manifestPath = "{$backupManager->getBasePath()}/{$backupId}/manifest.json";
        if (! file_exists($manifestPath)) {
            $this->error("Backup manifest not found for ID: {$backupId}");
            return Command::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->error("Corrupted backup manifest for ID: {$backupId}");
            return Command::FAILURE;
        }

        // Verify validation before replicating
        if (($manifest['validation_status'] ?? '') !== 'valid') {
            $this->warn("Backup [{$backupId}] has not been validated clean. Validating now...");
            $val = $backupManager->validateBackup($backupId);
            if (! $val['is_valid']) {
                $this->error("Cannot replicate unvalidated or corrupted backup [{$backupId}].");
                return Command::FAILURE;
            }
            $manifest = $val['manifest'];
        }

        $this->comment("Replicating backup [{$backupId}] to secondary destination...");
        $result = $replicator->replicate($backupId, $manifest);

        if (! empty($result['success'])) {
            $this->info("Replication successful for [{$backupId}].");
            $this->line("  Destination: {$result['destination']}");
            $this->line("  Replicated At: {$result['replicated_at']}");
            $this->line("  Remote Checksum Verified: YES");

            // Update local manifest with replication status
            $manifest['replication_status'] = 'replicated';
            $manifest['replicated_at'] = $result['replicated_at'];
            $manifest['replication_destination'] = $result['destination'];
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } else {
            $this->error("Replication failed for [{$backupId}]:");
            $errs = $result['errors'] ?? [$result['error'] ?? 'Unknown error'];
            foreach ($errs as $err) {
                $this->error("  - {$err}");
            }
            return Command::FAILURE;
        }
    }
}
