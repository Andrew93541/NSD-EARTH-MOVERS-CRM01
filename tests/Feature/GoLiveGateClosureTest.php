<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\Security\ConfigurableFileScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoLiveGateClosureTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
            'status' => 'active',
        ]);

        $adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Super Administrator',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->adminUser = User::create([
            'name' => 'Executive Officer',
            'email' => 'executive@buildflow.local',
            'password' => bcrypt('SecretPass123!'),
        ]);

        $this->adminUser->companies()->attach($this->company->id, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Substation Earthworks',
            'code' => 'PRJ-SUB-001',
            'status' => 'in_progress',
        ]);
    }

    /**
     * 1. In production, scanner daemon unreachable forces fail-closed error and rejects upload.
     */
    public function test_production_environment_forces_fail_closed_and_blocks_unscanned_uploads(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('filesystems.scanner.enabled', true);
        Config::set('filesystems.scanner.host', '127.0.0.1');
        Config::set('filesystems.scanner.port', 39999); // Unreachable port

        $scanner = new ConfigurableFileScanner();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_scan_');
        file_put_contents($tempFile, 'dummy content');

        $result = $scanner->scan($tempFile);
        unlink($tempFile);

        $this->assertFalse($result->isClean);
        $this->assertSame('error', $result->status);
        $this->assertStringContainsString('Anti-malware scanner daemon is unreachable', $result->message);
    }

    /**
     * 2. In production, attempting to preview or download an unscanned document is blocked with HTTP 423.
     */
    public function test_production_blocks_preview_and_download_of_unscanned_files(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $filePath = 'companies/' . $this->company->id . '/project/' . $this->project->id . '/test_unscanned.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 test content');

        $document = Document::create([
            'company_id' => $this->company->id,
            'documentable_type' => Project::class,
            'documentable_id' => $this->project->id,
            'category' => 'general',
            'title' => 'Drawing Blueprint',
            'original_filename' => 'drawing.pdf',
            'storage_filename' => 'test_unscanned.pdf',
            'disk' => 'local',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'checksum' => hash('sha256', '%PDF-1.4 test content'),
            'uploaded_by' => $this->adminUser->id,
            'is_confidential' => false,
            'version' => 1,
            'status' => 'active',
            'scan_status' => 'unscanned',
        ]);

        $this->assertFalse($document->isCleanForAccess());

        $service = app(DocumentService::class);

        $this->actingAs($this->adminUser);

        $response = $this->get('/documents/' . $document->id . '/preview');
        $response->assertStatus(423);

        $downloadResp = $this->get('/documents/' . $document->id . '/download');
        $downloadResp->assertStatus(423);
    }

    /**
     * 3. In all environments, pending, error, quarantined, and infected files are strictly blocked (423).
     */
    public function test_all_environments_block_preview_and_download_of_flagged_files(): void
    {
        $this->actingAs($this->adminUser);

        $statuses = ['pending', 'error', 'quarantined', 'infected'];

        foreach ($statuses as $badStatus) {
            $filePath = 'companies/' . $this->company->id . '/project/' . $this->project->id . "/test_{$badStatus}.pdf";
            Storage::disk('local')->put($filePath, '%PDF-1.4 test content');

            $document = Document::create([
                'company_id' => $this->company->id,
                'documentable_type' => Project::class,
                'documentable_id' => $this->project->id,
                'category' => 'general',
                'title' => "Document {$badStatus}",
                'original_filename' => "doc_{$badStatus}.pdf",
                'storage_filename' => "test_{$badStatus}.pdf",
                'disk' => 'local',
                'file_path' => $filePath,
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
                'checksum' => hash('sha256', '%PDF-1.4 test content'),
                'uploaded_by' => $this->adminUser->id,
                'is_confidential' => false,
                'version' => 1,
                'status' => 'active',
                'scan_status' => $badStatus,
            ]);

            $this->assertFalse($document->isCleanForAccess());

            $previewResp = $this->get('/documents/' . $document->id . '/preview');
            $previewResp->assertStatus(423);

            $downloadResp = $this->get('/documents/' . $document->id . '/download');
            $downloadResp->assertStatus(423);
        }
    }

    /**
     * 4. In development mode with explicit allow_unscanned_dev=true, unscanned upload and preview is permitted.
     */
    public function test_development_allows_unscanned_only_with_explicit_configuration(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        Config::set('filesystems.scanner.fail_closed', false);
        Config::set('filesystems.scanner.allow_unscanned_dev', true);
        Config::set('filesystems.scanner.host', '127.0.0.1');
        Config::set('filesystems.scanner.port', 39999);

        $scanner = new ConfigurableFileScanner();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_scan_');
        file_put_contents($tempFile, 'dummy content');

        $result = $scanner->scan($tempFile);
        unlink($tempFile);

        $this->assertTrue($result->isClean);
        $this->assertSame('unscanned', $result->status);

        // When allow_unscanned_dev is toggled false in dev, it must fail closed
        Config::set('filesystems.scanner.allow_unscanned_dev', false);
        $tempFile2 = tempnam(sys_get_temp_dir(), 'test_scan_');
        file_put_contents($tempFile2, 'dummy content');

        $result2 = $scanner->scan($tempFile2);
        unlink($tempFile2);

        $this->assertFalse($result2->isClean);
        $this->assertSame('error', $result2->status);
    }

    /**
     * 5. Health check reflects effective fail_closed and returns 503 if scanner is offline in production.
     */
    public function test_health_check_accurately_reflects_production_effective_runtime(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('filesystems.scanner.enabled', true);
        Config::set('filesystems.scanner.host', '127.0.0.1');
        Config::set('filesystems.scanner.port', 39999); // Offline daemon

        $response = $this->getJson('/health');

        // In production with scanner offline under fail-closed, upload health fails -> 503
        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'unhealthy',
            'environment' => 'production',
            'checks' => [
                'malware_scanner' => [
                    'status' => 'error_blocking',
                    'effective_fail_closed' => true,
                    'allow_unscanned_dev' => false,
                ],
            ],
        ]);
    }

    /**
     * 6. Login page suppresses demo credentials pre-fill and banner in all environments.
     */
    public function test_login_page_masks_demo_credentials_in_production(): void
    {
        // 1. Production environment
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertDontSee('Demo Credentials Pre-filled');
        $response->assertDontSee('admin@buildflow.local');
        $response->assertDontSee('Password123!');

        // 2. Local environment
        $this->app->detectEnvironment(fn () => 'local');

        $localResponse = $this->get('/login');
        $localResponse->assertStatus(200);
        $localResponse->assertDontSee('Demo Credentials Pre-filled');
        $localResponse->assertDontSee('admin@buildflow.local');
        $localResponse->assertDontSee('Password123!');
    }
}
