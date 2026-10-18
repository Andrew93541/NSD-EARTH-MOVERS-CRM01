<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DisasterRecoveryRestoreTest extends TestCase
{
    /**
     * Test disaster recovery backup and restore audit against an isolated disposable database.
     */
    public function test_disaster_recovery_backup_and_restore_verification(): void
    {
        $exitCode = Artisan::call('buildflow:verify-recovery');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, "Disaster recovery command failed with output:\n" . $output);
        $this->assertStringContainsString('DISASTER RECOVERY VERIFICATION: 100% SUCCESS', $output);
        $this->assertStringContainsString('All 72 tables have exact 100% row count parity', $output);
        $this->assertStringContainsString('Restored Financial Balance exact parity', $output);
        $this->assertStringContainsString('File metadata and SHA-256 binary checksums verified', $output);
    }
}
