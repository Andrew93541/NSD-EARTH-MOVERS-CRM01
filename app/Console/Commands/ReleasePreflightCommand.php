<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Replication\BackupReplicationInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ReleasePreflightCommand extends Command
{
    protected $signature = 'nsdcrm:preflight 
                            {--strict : Enforce strict production rules even in non-production environments}';

    protected $aliases = ['buildflow:preflight'];

    protected $description = 'Perform a comprehensive production release preflight and go-live gate audit';

    public function handle(BackupManager $backupManager, BackupReplicationInterface $replicator): int
    {
        $strict = (bool) $this->option('strict') || app()->environment('production');

        $this->info("==========================================================================================");
        $this->info("  NSDCRM — PRODUCTION RELEASE PREFLIGHT & GO-LIVE AUDIT GATE");
        $this->info("  NSD Earth Movers & Construction Ltd | Runtime: PHP " . PHP_VERSION . " | Laravel " . app()->version());
        $this->info("  Mode: " . ($strict ? "<fg=red;options=bold>STRICT PRODUCTION</>" : "<fg=yellow>DEVELOPMENT / ADVISORY</>"));
        $this->info("==========================================================================================\n");

        $checks = [];
        $hasFailures = false;
        $hasWarnings = false;

        // 1. Environment & Debug
        $env = app()->environment();
        $debug = config('app.debug');
        if ($env === 'production' && ! $debug) {
            $checks[] = ['Environment', 'App Environment & Debug Mode', 'PASS', "Production mode active, debug disabled."];
        } elseif ($strict) {
            $checks[] = ['Environment', 'App Environment & Debug Mode', 'FAIL', "Environment is [{$env}] with debug=[" . ($debug ? 'true' : 'false') . "]. Production requires APP_ENV=production, APP_DEBUG=false."];
            $hasFailures = true;
        } else {
            $checks[] = ['Environment', 'App Environment & Debug Mode', 'WARNING', "Environment is [{$env}] (debug=" . ($debug ? 'true' : 'false') . "). Acceptable in local testing; must be production for go-live."];
            $hasWarnings = true;
        }

        // 2. Application Key (APP_KEY)
        $appKey = config('app.key');
        if (! empty($appKey) && str_starts_with($appKey, 'base64:') && strlen($appKey) > 40) {
            $checks[] = ['Security', 'Application Encryption Key (APP_KEY)', 'PASS', "Configured and valid 256-bit AES key."];
        } else {
            $checks[] = ['Security', 'Application Encryption Key (APP_KEY)', 'FAIL', "APP_KEY missing or invalid format. Run key:generate ONCE during initial greenfield provisioning."];
            $hasFailures = true;
        }

        // 3. Database Connectivity & Migrations
        try {
            DB::connection()->getPdo();
            $pendingMigrations = $this->getPendingMigrationsCount();
            if ($pendingMigrations === 0) {
                $checks[] = ['Database', 'Database Connectivity & Migrations', 'PASS', "Connected to MySQL. All migrations applied (0 pending)."];
            } else {
                $checks[] = ['Database', 'Database Connectivity & Migrations', 'FAIL', "{$pendingMigrations} pending migrations exist. Run [php artisan migrate --force]."];
                $hasFailures = true;
            }
        } catch (\Throwable $e) {
            $checks[] = ['Database', 'Database Connectivity & Migrations', 'FAIL', "Connection failed: " . $e->getMessage()];
            $hasFailures = true;
        }

        // 4. Financial Ledger Balance Invariant
        try {
            $tb = DB::selectOne("
                SELECT 
                    ROUND(SUM(jel.debit), 2) as total_debit,
                    ROUND(SUM(jel.credit), 2) as total_credit
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                WHERE je.status = 'posted'
            ");
            $dr = (float) ($tb->total_debit ?? 0.0);
            $cr = (float) ($tb->total_credit ?? 0.0);
            if (bccomp((string) $dr, (string) $cr, 2) === 0) {
                $checks[] = ['Finance', 'General Ledger Trial Balance Invariant', 'PASS', "Balanced: Debits = ₹" . number_format($dr, 2) . " == Credits = ₹" . number_format($cr, 2) . " (Diff: ₹0.00)."];
            } else {
                $checks[] = ['Finance', 'General Ledger Trial Balance Invariant', 'FAIL', "CRITICAL: General Ledger unbalanced! Debits ₹{$dr} != Credits ₹{$cr}."];
                $hasFailures = true;
            }
        } catch (\Throwable $e) {
            $checks[] = ['Finance', 'General Ledger Trial Balance Invariant', 'FAIL', "Audit query failed: " . $e->getMessage()];
            $hasFailures = true;
        }

        // 5. Document Storage Security
        $serveRoute = config('filesystems.disks.local.serve');
        $privatePath = storage_path('app/private');
        $isWritable = is_writable($privatePath) || is_writable(storage_path('app'));
        if ($serveRoute === false && $isWritable) {
            $checks[] = ['Storage', 'Private Document Storage Isolation', 'PASS', "Private disk serve=false, web bypass prevented, directory writable."];
        } else {
            $msg = $serveRoute !== false ? "Local disk serve route enabled (leak risk)." : "Private storage not writable.";
            $checks[] = ['Storage', 'Private Document Storage Isolation', 'FAIL', $msg];
            $hasFailures = true;
        }

        // 6. Anti-Malware Scanner & Fail-Closed
        $isProd = app()->environment('production');
        $effectiveFailClosed = (bool) config('filesystems.scanner.fail_closed', $isProd);
        $allowDev = (bool) config('filesystems.scanner.allow_unscanned_dev', ! $isProd);
        $clamHost = config('filesystems.scanner.host', '127.0.0.1');
        $clamPort = (int) config('filesystems.scanner.port', 3310);
        $clamSocket = config('filesystems.scanner.socket', '/var/run/clamav/clamd.ctl');

        $sock = null;
        if (file_exists($clamSocket)) {
            $sock = @fsockopen("unix://{$clamSocket}", -1, $e1, $e2, 0.5);
        } elseif ($clamHost) {
            $sock = @fsockopen($clamHost, $clamPort, $e1, $e2, 0.5);
        }

        if ($sock) {
            fclose($sock);
            $checks[] = ['Security', 'Anti-Malware Scanner (ClamAV)', 'PASS', "Daemon online and reachable. Fail-closed: " . ($effectiveFailClosed ? 'YES' : 'NO') . "."];
        } else {
            if ($strict || $isProd) {
                $checks[] = ['Security', 'Anti-Malware Scanner (ClamAV)', 'FAIL', "ClamAV daemon unreachable in production. Fail-closed policy blocks uploads."];
                $hasFailures = true;
            } else {
                $checks[] = ['Security', 'Anti-Malware Scanner (ClamAV)', 'WARNING', "Daemon unreachable; development unscanned fallback active. Install ClamAV on production host."];
                $hasWarnings = true;
            }
        }

        // 7. HTTPS & Secure Cookies
        $secureCookie = config('session.secure');
        $appUrl = config('app.url');
        $isHttps = str_starts_with($appUrl, 'https://');
        if ($isHttps && $secureCookie) {
            $checks[] = ['Security', 'HTTPS & Secure Session Cookies', 'PASS', "APP_URL is HTTPS; SESSION_SECURE_COOKIE enabled."];
        } elseif ($strict) {
            $checks[] = ['Security', 'HTTPS & Secure Session Cookies', 'FAIL', "Production requires HTTPS APP_URL and SESSION_SECURE_COOKIE=true."];
            $hasFailures = true;
        } else {
            $checks[] = ['Security', 'HTTPS & Secure Session Cookies', 'WARNING', "APP_URL is HTTP and secure cookies disabled. Required for production deployment."];
            $hasWarnings = true;
        }

        // 8. Trusted Proxies
        $trustedProxies = env('TRUSTED_PROXIES');
        if ($trustedProxies !== null && $trustedProxies !== '' && $trustedProxies !== '*') {
            $checks[] = ['Network', 'Trusted Proxy Boundaries', 'PASS', "TRUSTED_PROXIES explicitly configured to authorized upstream CIDR/IPs."];
        } elseif ($strict) {
            $checks[] = ['Network', 'Trusted Proxy Boundaries', 'FAIL', "Production requires explicit TRUSTED_PROXIES matching your load balancer / Nginx."];
            $hasFailures = true;
        } else {
            $checks[] = ['Network', 'Trusted Proxy Boundaries', 'WARNING', "TRUSTED_PROXIES not configured; local fallback active. Configure upstream reverse proxy for live deployment."];
            $hasWarnings = true;
        }

        // 9. Reverb WebSocket Broadcast Configuration
        $reverbHost = config('reverb.servers.reverb.host', '127.0.0.1');
        $reverbPort = config('reverb.servers.reverb.port', 8080);
        $reverbOrigins = env('REVERB_ALLOWED_ORIGINS');
        $revSock = @fsockopen($reverbHost, $reverbPort, $e1, $e2, 0.5);

        if ($revSock) {
            fclose($revSock);
            if ($reverbOrigins && ! str_contains($reverbOrigins, '*')) {
                $checks[] = ['Broadcasting', 'Laravel Reverb Daemon & Origins', 'PASS', "Reverb daemon running on port {$reverbPort}; allowed origins restricted."];
            } else {
                $checks[] = ['Broadcasting', 'Laravel Reverb Daemon & Origins', $strict ? 'FAIL' : 'WARNING', "Reverb daemon online, but REVERB_ALLOWED_ORIGINS contains wildcard or is empty."];
                if ($strict) $hasFailures = true; else $hasWarnings = true;
            }
        } else {
            $checks[] = ['Broadcasting', 'Laravel Reverb Daemon & Origins', 'WARNING', "Reverb daemon offline. Graceful HTTP fallback active. Start via supervisor."];
            $hasWarnings = true;
        }

        // 10. Durable Backup Creation & RPO SLA (60m Target, 75m Critical Limit)
        $latestBackup = $backupManager->getLatestBackup();
        if ($latestBackup) {
            $age = $backupManager->getLatestBackupAgeMinutes();
            $rpoStatus = $backupManager->getRpoStatus($age);

            if ($rpoStatus === 'satisfied') {
                $checks[] = ['Disaster Recovery', 'Durable Automated Backups', 'PASS', "Latest snapshot [{$latestBackup['backup_id']}] age {$age}m <= 60m RPO target."];
            } elseif ($rpoStatus === 'warning_approaching_breach') {
                $checks[] = ['Disaster Recovery', 'Durable Automated Backups', 'WARNING', "Backup [{$latestBackup['backup_id']}] age {$age}m exceeds 60m target (within 75m grace window)."];
                $hasWarnings = true;
            } else {
                $checks[] = ['Disaster Recovery', 'Durable Automated Backups', $strict ? 'FAIL' : 'WARNING', "RPO BREACH: Latest backup [{$latestBackup['backup_id']}] age {$age}m > 75m critical SLA limit."];
                if ($strict) $hasFailures = true; else $hasWarnings = true;
            }
        } else {
            $checks[] = ['Disaster Recovery', 'Durable Automated Backups', 'FAIL', "No durable backups exist. Run [php artisan buildflow:backup:create]."];
            $hasFailures = true;
        }

        // 11. Backup Validation & Cryptographic Integrity
        if ($latestBackup) {
            $isValid = ($latestBackup['validation_status'] ?? '') === 'valid';
            if ($isValid) {
                $checks[] = ['Disaster Recovery', 'Backup Cryptographic Integrity Validation', 'PASS', "Validated clean: SHA-256 digests and archive format verified."];
            } else {
                $checks[] = ['Disaster Recovery', 'Backup Cryptographic Integrity Validation', $strict ? 'FAIL' : 'WARNING', "Latest backup [{$latestBackup['backup_id']}] validation status is [" . ($latestBackup['validation_status'] ?? 'pending') . "]."];
                if ($strict) $hasFailures = true; else $hasWarnings = true;
            }
        } else {
            $checks[] = ['Disaster Recovery', 'Backup Cryptographic Integrity Validation', 'FAIL', "No backup available to validate."];
            $hasFailures = true;
        }

        // 12. Backup Encryption & Key Enforcement
        $hasDedicatedKey = ! empty(config('backup.encryption.key'));
        $isEncrypted = (bool) ($latestBackup['encryption']['enabled'] ?? false);
        $cipher = $latestBackup['encryption']['cipher'] ?? 'unknown';

        if ($isEncrypted && $hasDedicatedKey) {
            $checks[] = ['Disaster Recovery', 'Backup Encryption & Key Configuration', 'PASS', "Encrypted with {$cipher} (AEAD v2) using dedicated BACKUP_ENCRYPTION_KEY."];
        } elseif ($strict) {
            if (! $isEncrypted) {
                $checks[] = ['Disaster Recovery', 'Backup Encryption & Key Configuration', 'FAIL', "Latest backup is UNENCRYPTED. Production mandates AEAD AES-256-GCM encryption."];
            } else {
                $checks[] = ['Disaster Recovery', 'Backup Encryption & Key Configuration', 'FAIL', "BACKUP_ENCRYPTION_KEY is unconfigured or falling back to APP_KEY in production."];
            }
            $hasFailures = true;
        } else {
            $msg = ! $isEncrypted ? "Latest backup is unencrypted. Enable BACKUP_ENCRYPT in production." : "Dedicated BACKUP_ENCRYPTION_KEY recommended for production.";
            $checks[] = ['Disaster Recovery', 'Backup Encryption & Key Configuration', 'WARNING', $msg];
            $hasWarnings = true;
        }

        // 13. Isolated Restore Verification
        $latestTested = $backupManager->getLatestRestoreTestedBackup();
        if ($latestTested) {
            $checks[] = ['Disaster Recovery', 'Isolated Disaster Recovery Restore Test', 'PASS', "Verified baseline: [{$latestTested['backup_id']}] passed isolated restore test (protected: " . (! empty($latestTested['is_protected']) ? 'YES' : 'NO') . ")."];
        } elseif ($strict) {
            $checks[] = ['Disaster Recovery', 'Isolated Disaster Recovery Restore Test', 'FAIL', "No backup has passed an isolated restore test. Run [php artisan buildflow:verify-recovery]."];
            $hasFailures = true;
        } else {
            $checks[] = ['Disaster Recovery', 'Isolated Disaster Recovery Restore Test', 'WARNING', "Isolated restore verification pending. Run [php artisan buildflow:verify-recovery]."];
            $hasWarnings = true;
        }

        // 14. Secondary Off-Host Backup Replication
        $repStatus = $replicator->getReplicationStatus();
        $isOffHost = (bool) config('backup.replication.is_off_host', false);
        if ($repStatus['status'] === 'active' && $repStatus['total_replicas'] > 0) {
            if ($isOffHost) {
                $checks[] = ['Disaster Recovery', 'Secondary Off-Host Replication', 'PASS', "Off-host replication verified: {$repStatus['total_replicas']} secondary replicas ({$repStatus['latest_replicated_id']})."];
            } elseif ($strict) {
                $checks[] = ['Disaster Recovery', 'Secondary Off-Host Replication', 'FAIL', "Replicas exist locally ({$repStatus['total_replicas']}), but off-host/remote boundary is unverified. Single-host storage violates disaster recovery requirements."];
                $hasFailures = true;
            } else {
                $checks[] = ['Disaster Recovery', 'Secondary Off-Host Replication', 'WARNING', "Local secondary copy verified ({$repStatus['total_replicas']} replicas); off-host remote cloud storage unverified."];
                $hasWarnings = true;
            }
        } elseif ($strict) {
            $checks[] = ['Disaster Recovery', 'Secondary Off-Host Replication', 'FAIL', "Off-host replication is unconfigured. Required for production resilience."];
            $hasFailures = true;
        } else {
            $checks[] = ['Disaster Recovery', 'Secondary Off-Host Replication', 'WARNING', "Secondary replication not yet configured on this host. Run [buildflow:backup:replicate]."];
            $hasWarnings = true;
        }

        // 15. Frontend Production Asset Compilation
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath) && filesize($manifestPath) > 0) {
            $checks[] = ['Frontend', 'Vite Production Asset Compilation', 'PASS', "public/build/manifest.json verified. Production bundle ready."];
        } else {
            $checks[] = ['Frontend', 'Vite Production Asset Compilation', 'FAIL', "Assets uncompiled. Run [npm run build]."];
            $hasFailures = true;
        }

        // 16. Default Demo Accounts
        $knownDemoEmails = [
            'admin@buildflow.local',
            'pm@buildflow.local',
            'site@buildflow.local',
            'accounts@buildflow.local',
            'hr@buildflow.local',
            'procurement@buildflow.local',
            'storekeeper@buildflow.local',
            'karan@buildflow.local',
        ];
        $existingDemoUsers = User::whereIn('email', $knownDemoEmails)->pluck('email')->all();
        $defaultUsersCount = count($existingDemoUsers);

        if ($defaultUsersCount === 0) {
            $checks[] = ['Access Control', 'Demo User Accounts & Credentials', 'PASS', "Zero default demo accounts detected."];
        } elseif ($strict) {
            $emails = implode(', ', $existingDemoUsers);
            $checks[] = ['Access Control', 'Demo User Accounts & Credentials', 'FAIL', "{$defaultUsersCount} default demo accounts exist: [{$emails}]. Operator action required: deactivate or replace credentials before live production cutover."];
            $hasFailures = true;
        } else {
            $checks[] = ['Access Control', 'Demo User Accounts & Credentials', 'WARNING', "{$defaultUsersCount} demo accounts active. Login pre-fill is masked in production; passwords must be rotated."];
            $hasWarnings = true;
        }

        // 17. Mail Delivery Infrastructure
        $defaultMailer = config('mail.default');
        if ($defaultMailer && ! in_array($defaultMailer, ['log', 'array'], true)) {
            $checks[] = ['Email & Notifications', 'Mail Transport Configuration', 'PASS', "Configured mail transport: [{$defaultMailer}]."];
        } elseif ($strict) {
            $checks[] = ['Email & Notifications', 'Mail Transport Configuration', 'FAIL', "Mail transport is [{$defaultMailer}]. Production requires configured delivery (SMTP, SES, Postmark) for password reset emails."];
            $hasFailures = true;
        } else {
            $checks[] = ['Email & Notifications', 'Mail Transport Configuration', 'WARNING', "Mail transport is [{$defaultMailer}]. Password resets will be logged locally. Configure SMTP/SES for production."];
            $hasWarnings = true;
        }

        // 18. External Host Infrastructure Checks (Pending Verification)
        $checks[] = ['Infrastructure', 'Supervisor Process Manager (Workers)', 'NOT VERIFIED', "Host supervisorctl running queue:work and reverb:start must be confirmed on server."];
        $checks[] = ['Infrastructure', 'System Crontab (schedule:run)', 'NOT VERIFIED', "Host crontab entry (* * * * * php artisan schedule:run) must be configured on server."];
        $checks[] = ['Infrastructure', 'Off-Host Cloud Cold Storage Archive', 'NOT VERIFIED', "Long-term cold storage (S3 Glacier/Azure) for statutory 6-8 yr audit compliance required."];

        // Format and render console table
        $formatted = array_map(function ($c) {
            $status = match ($c[2]) {
                'PASS' => '<fg=green;options=bold>PASS</>',
                'WARNING' => '<fg=yellow;options=bold>WARNING</>',
                'FAIL' => '<fg=red;options=bold>FAIL</>',
                'NOT VERIFIED' => '<fg=cyan>NOT VERIFIED</>',
            };
            return [$c[0], $c[1], $status, $c[3]];
        }, $checks);

        $this->table(['Domain', 'Check Item', 'Status', 'Remediation / Diagnostic Details'], $formatted);

        $this->line("");
        if ($hasFailures) {
            $this->error("==========================================================================================");
            $this->error("  PREFLIGHT RESULT: FAILED (Mandatory requirements unresolved)");
            $this->error("  Resolve all [FAIL] items before deploying live traffic.");
            $this->error("==========================================================================================\n");
            return Command::FAILURE;
        } elseif ($hasWarnings) {
            $this->warn("==========================================================================================");
            $this->warn("  PREFLIGHT RESULT: READY WITH STATED LIMITATIONS (Advisories noted)");
            $this->warn("  Local development verified; review [WARNING] and [NOT VERIFIED] items prior to host cutover.");
            $this->warn("==========================================================================================\n");
            return Command::SUCCESS;
        } else {
            $this->info("==========================================================================================");
            $this->info("  PREFLIGHT RESULT: 100% READY (All checks passed)");
            $this->info("==========================================================================================\n");
            return Command::SUCCESS;
        }
    }

    protected function getPendingMigrationsCount(): int
    {
        $migrationFiles = File::glob(database_path('migrations/*.php'));
        $appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

        $pending = 0;
        foreach ($migrationFiles as $file) {
            $name = basename($file, '.php');
            if (! in_array($name, $appliedMigrations, true)) {
                $pending++;
            }
        }

        return $pending;
    }
}
