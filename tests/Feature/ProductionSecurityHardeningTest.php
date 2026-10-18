<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\ConfigurableFileScanner;
use App\Services\Security\ScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerA;
    protected User $workerA;
    protected User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->companyA = Company::create([
            'name' => 'NSD Earth Movers Security Test A',
            'slug' => 'nsd-sec-a-' . uniqid(),
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'name' => 'Apex Infra Security Test B',
            'slug' => 'apex-sec-b-' . uniqid(),
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $ownerRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Owner A',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $workerRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Worker A',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*'],
            'is_system' => true,
        ]);

        $ownerRoleB = Role::create([
            'company_id' => $this->companyB->id,
            'name' => 'Owner B',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner_alpha_' . uniqid() . '@buildflow.test',
            'password' => Hash::make('Password123!'),
        ]);
        $this->ownerA->companies()->attach($this->companyA->id, [
            'role_id' => $ownerRoleA->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->workerA = User::create([
            'name' => 'Worker Alpha',
            'email' => 'worker_alpha_' . uniqid() . '@buildflow.test',
            'password' => Hash::make('Password123!'),
        ]);
        $this->workerA->companies()->attach($this->companyA->id, [
            'role_id' => $workerRoleA->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner_beta_' . uniqid() . '@buildflow.test',
            'password' => Hash::make('Password123!'),
        ]);
        $this->ownerB->companies()->attach($this->companyB->id, [
            'role_id' => $ownerRoleB->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    /**
     * 1. Health check endpoint returns JSON and contains zero sensitive secrets.
     */
    public function test_health_check_endpoint_returns_json_and_masks_secrets(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
            'total_latency_ms',
            'checks' => [
                'database' => ['status'],
                'storage' => ['status', 'free_space_gb'],
                'cache' => ['status'],
                'broadcasting' => ['status'],
                'malware_scanner' => ['status', 'enabled', 'fail_closed'],
            ],
        ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('secret', strtolower($content));
        $this->assertStringNotContainsString(config('database.connections.mysql.username'), $content);
    }

    /**
     * 2. Suspended / revoked company user cannot access workspace.
     */
    public function test_revoked_or_suspended_company_user_cannot_access_workspace(): void
    {
        // Deactivate worker membership
        $this->workerA->companies()->updateExistingPivot($this->companyA->id, ['status' => 'suspended']);

        // Worker attempts to switch to or access company A
        $response = $this->actingAs($this->workerA)
            ->post(route('workspace.switch', $this->companyA));

        $response->assertStatus(403);

        // Accessing dashboard should redirect to workspace selection
        $dashResponse = $this->actingAs($this->workerA)->get(route('dashboard'));
        $dashResponse->assertRedirect(route('workspace.select'));
    }

    /**
     * 3. Global search does not leak cross-company records via operator precedence.
     */
    public function test_global_search_does_not_leak_cross_company_records(): void
    {
        // Create matching project in Company A and Company B
        $projA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Metro Terminal Alpha Project',
            'code' => 'METRO-SEC-A',
            'location' => 'Sector 5 Alpha',
            'budget' => 5000000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'status' => 'in_progress',
        ]);

        $projB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Secret Tunnel Beta Project',
            'code' => 'METRO-SEC-B', // Matches 'METRO' code search!
            'location' => 'Sector 5 Beta',
            'budget' => 9000000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get('/search?q=METRO');

        $response->assertStatus(200);
        $response->assertSee('Metro Terminal Alpha Project');
        $response->assertDontSee('Secret Tunnel Beta Project');
    }

    /**
     * 4. Employee profile and salary masking from unauthorized roles.
     */
    public function test_employee_compensation_is_restricted_from_unauthorized_roles(): void
    {
        $employee = Employee::create([
            'company_id' => $this->companyA->id,
            'first_name' => 'Kiran',
            'last_name' => 'Kumar',
            'code' => 'EMP-SEC-001',
            'designation' => 'Heavy Excavator Operator',
            'employment_type' => 'daily_wage',
            'daily_wage_rate' => 1250.00,
            'monthly_salary' => 35000.00,
            'status' => 'active',
        ]);

        // Unauthorized worker (storekeeper) cannot access employee profile directly
        $unauthorized = $this->actingAs($this->workerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('employees.show', $employee));

        $unauthorized->assertStatus(403);

        // Authorized Owner can see full details
        $authorized = $this->actingAs($this->ownerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('employees.show', $employee));

        $authorized->assertStatus(200);
        $authorized->assertSee('₹1,250.00');
        $authorized->assertSee('₹35,000.00');
    }

    /**
     * 5. Scanner in fail-closed mode rejects uploads when scanner daemon is unavailable.
     */
    public function test_scanner_fail_closed_mode_rejects_uploads_when_scanner_unavailable(): void
    {
        Config::set('filesystems.scanner.enabled', true);
        Config::set('filesystems.scanner.fail_closed', true);
        Config::set('filesystems.scanner.host', '127.0.0.1');
        Config::set('filesystems.scanner.port', 39999); // Unused port

        $proj = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project For Scanner Test',
            'code' => 'SCAN-TEST-01',
            'location' => 'Yard 1',
            'budget' => 1000000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'status' => 'in_progress',
        ]);

        $file = UploadedFile::fake()->create('structural_drawing.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post('/documents/upload', [
                'documentable_type' => 'project',
                'documentable_id' => $proj->id,
                'category' => 'drawing',
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('documents', [
            'original_filename' => 'structural_drawing.pdf',
        ]);
    }

    /**
     * 6. Infected or quarantined files cannot be previewed or downloaded.
     */
    public function test_infected_or_quarantined_files_cannot_be_streamed_or_downloaded(): void
    {
        $proj = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Quarantine Test Project',
            'code' => 'QUAR-01',
            'location' => 'Yard 2',
            'budget' => 1000000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'status' => 'in_progress',
        ]);

        Storage::disk('local')->put('companies/' . $this->companyA->id . '/project/1/infected.pdf', '%PDF-1.4 sample content');

        $infectedDoc = Document::create([
            'company_id' => $this->companyA->id,
            'documentable_type' => Project::class,
            'documentable_id' => $proj->id,
            'category' => 'drawing',
            'original_filename' => 'infected.pdf',
            'storage_filename' => 'infected.pdf',
            'disk' => 'local',
            'file_path' => 'companies/' . $this->companyA->id . '/project/1/infected.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'checksum' => hash('sha256', '%PDF-1.4 sample content'),
            'uploaded_by' => $this->ownerA->id,
            'scan_status' => 'infected',
            'version' => 1,
            'status' => 'active',
        ]);

        $preview = $this->actingAs($this->ownerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('documents.preview', $infectedDoc));

        $preview->assertStatus(423);

        $download = $this->actingAs($this->ownerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('documents.download', $infectedDoc));

        $download->assertStatus(423);
    }
}
