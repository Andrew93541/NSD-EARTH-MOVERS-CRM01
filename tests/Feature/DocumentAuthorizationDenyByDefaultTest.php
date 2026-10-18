<?php

namespace Tests\Feature;

use App\Http\Controllers\DocumentController;
use App\Models\Company;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAuthorizationDenyByDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminUser;
    protected User $siteSupervisor;
    protected User $otherTenantUser;
    protected DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->companyA = Company::create([
            'name' => 'Company A Infrastructure',
            'slug' => 'company-a',
        ]);

        $this->companyB = Company::create([
            'name' => 'Company B Mining',
            'slug' => 'company-b',
        ]);

        // Roles
        $adminRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        $siteRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['projects.view', 'sites.view', 'daily_logs.*'],
        ]);

        $otherTenantRole = Role::create([
            'company_id' => $this->companyB->id,
            'name' => 'Admin B',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        // Users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_a@buildflow.local',
            'password' => bcrypt('Password123!'),
        ]);
        $this->companyA->users()->attach($this->adminUser->id, ['role_id' => $adminRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisor = User::create([
            'name' => 'Site Engineer',
            'email' => 'supervisor_a@buildflow.local',
            'password' => bcrypt('Password123!'),
        ]);
        $this->companyA->users()->attach($this->siteSupervisor->id, ['role_id' => $siteRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->otherTenantUser = User::create([
            'name' => 'Other Tenant Admin',
            'email' => 'admin_b@buildflow.local',
            'password' => bcrypt('Password123!'),
        ]);
        $this->companyB->users()->attach($this->otherTenantUser->id, ['role_id' => $otherTenantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->policy = app(DocumentPolicy::class);
    }

    protected function createDoc(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'company_id' => $this->companyA->id,
            'documentable_type' => Project::class,
            'documentable_id' => 1,
            'category' => 'general',
            'title' => 'Test Document',
            'original_filename' => 'test.pdf',
            'storage_filename' => 'uuid-1234.pdf',
            'disk' => 'local',
            'file_path' => 'private/documents/uuid-1234.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'checksum' => hash('sha256', 'test content'),
            'scan_status' => 'clean',
            'is_confidential' => false,
            'uploaded_by' => $this->adminUser->id,
        ], $attributes));
    }

    public function test_orphaned_document_is_denied_by_default(): void
    {
        // Document pointing to non-existent parent ID (orphaned)
        $doc = $this->createDoc([
            'documentable_type' => Project::class,
            'documentable_id' => 999999, // non-existent project
            'title' => 'Orphaned Document',
            'original_filename' => 'orphaned.pdf',
            'storage_filename' => 'orphaned-1234.pdf',
        ]);

        $this->assertNull($doc->documentable);

        $this->assertFalse($this->policy->view($this->adminUser, $doc));
        $this->assertFalse($this->policy->download($this->adminUser, $doc));
        $this->assertFalse($this->policy->replace($this->adminUser, $doc));
        $this->assertFalse($this->policy->delete($this->adminUser, $doc));
    }

    public function test_document_with_unknown_parent_type_is_denied_by_default(): void
    {
        $unmappedParent = Role::first();

        $doc = $this->createDoc([
            'documentable_type' => Role::class,
            'documentable_id' => $unmappedParent->id,
            'title' => 'Unmapped Type Document',
            'original_filename' => 'unmapped.pdf',
            'storage_filename' => 'unmapped-1234.pdf',
        ]);

        // Non-admin site supervisor cannot access unmapped parent
        $this->assertFalse($this->policy->canAccessParent($this->siteSupervisor, $unmappedParent));
        $this->assertFalse($this->policy->view($this->siteSupervisor, $doc));

        // scopeVisibleTo excludes unknown parent types
        $visibleSupervisor = Document::visibleTo($this->siteSupervisor)->pluck('id')->all();
        $this->assertNotContains($doc->id, $visibleSupervisor);
    }

    public function test_all_18_model_map_types_are_explicitly_supported_and_authorized(): void
    {
        $allModelClasses = array_values(DocumentController::MODEL_MAP);

        // Verify that every single class in MODEL_MAP is recognized by policy
        foreach ($allModelClasses as $modelClass) {
            $instance = new $modelClass();
            $this->assertNotNull($instance);

            // Admin user must have access to all 18 parent model types
            $this->assertTrue(
                $this->policy->canAccessParent($this->adminUser, $instance),
                "Model class {$modelClass} should be accessible to Admin in canAccessParent"
            );
        }

        // Verify getAccessibleParentTypes returns all 18 for Admin
        $accessible = $this->policy->getAccessibleParentTypes($this->adminUser);
        foreach ($allModelClasses as $modelClass) {
            $this->assertContains($modelClass, $accessible, "Accessible parent types must include {$modelClass}");
        }
    }

    public function test_cross_tenant_document_access_is_strictly_denied(): void
    {
        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project Alpha',
            'code' => 'PRJ-A',
            'status' => 'in_progress',
        ]);

        $docA = $this->createDoc([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'category' => 'contract',
            'title' => 'Project A Contract',
            'original_filename' => 'project_a.pdf',
            'storage_filename' => 'proj-a-1234.pdf',
        ]);

        // User from Company B cannot view docA
        $this->assertFalse($this->policy->view($this->otherTenantUser, $docA));
        $this->assertFalse($this->policy->download($this->otherTenantUser, $docA));
        $this->assertFalse($this->policy->replace($this->otherTenantUser, $docA));
        $this->assertFalse($this->policy->delete($this->otherTenantUser, $docA));

        // HTTP access attempts must return 404/403 due to tenant scoping
        $response = $this->actingAs($this->otherTenantUser)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->get(route('documents.download', $docA));

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }

    public function test_role_based_access_denies_unauthorized_parents_to_site_supervisor(): void
    {
        $journal = JournalEntry::create([
            'company_id' => $this->companyA->id,
            'entry_number' => 'JV-2026-001',
            'entry_date' => now()->toDateString(),
            'narration' => 'Opening balance entry',
            'total_amount' => 50000.00,
            'created_by' => $this->adminUser->id,
            'status' => 'draft',
        ]);

        $journalDoc = $this->createDoc([
            'documentable_type' => JournalEntry::class,
            'documentable_id' => $journal->id,
            'category' => 'voucher_support',
            'title' => 'Journal Voucher Supporting Doc',
            'original_filename' => 'jv_support.pdf',
            'storage_filename' => 'jv-1234.pdf',
        ]);

        // Site supervisor has no finance permission -> denied
        $this->assertFalse($this->policy->canAccessParent($this->siteSupervisor, $journal));
        $this->assertFalse($this->policy->view($this->siteSupervisor, $journalDoc));

        // Project document -> site supervisor allowed
        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Alpha',
            'code' => 'SITE-A',
            'status' => 'in_progress',
        ]);

        $projectDoc = $this->createDoc([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'category' => 'drawing',
            'title' => 'Site Blueprint',
            'original_filename' => 'blueprint.pdf',
            'storage_filename' => 'blueprint-1234.pdf',
        ]);

        $this->assertTrue($this->policy->canAccessParent($this->siteSupervisor, $project));
        $this->assertTrue($this->policy->view($this->siteSupervisor, $projectDoc));
    }

    public function test_document_centre_scope_does_not_leak_metadata_for_unauthorized_parents(): void
    {
        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project Beta',
            'code' => 'PRJ-B',
            'status' => 'in_progress',
        ]);

        $projectDoc = $this->createDoc([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'category' => 'drawing',
            'title' => 'Beta Plan',
            'original_filename' => 'beta_plan.pdf',
            'storage_filename' => 'beta-1234.pdf',
        ]);

        $journal = JournalEntry::create([
            'company_id' => $this->companyA->id,
            'entry_number' => 'JV-2026-999',
            'entry_date' => now()->toDateString(),
            'narration' => 'Secret Financial Audit',
            'total_amount' => 50000.00,
            'created_by' => $this->adminUser->id,
            'status' => 'draft',
        ]);

        $journalDoc = $this->createDoc([
            'documentable_type' => JournalEntry::class,
            'documentable_id' => $journal->id,
            'category' => 'voucher_support',
            'title' => 'Secret Financial Audit Voucher',
            'original_filename' => 'secret_audit.pdf',
            'storage_filename' => 'secret-1234.pdf',
        ]);

        $this->actingAs($this->siteSupervisor);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->get(route('documents.index'));
        $response->assertStatus(200);

        // Must see projectDoc
        $response->assertSee('Beta Plan');
        $response->assertSee('beta_plan.pdf');

        // Must NOT see journalDoc or its metadata in search/index/counts
        $response->assertDontSee('Secret Financial Audit Voucher');
        $response->assertDontSee('secret_audit.pdf');

        // Searching for secret text returns 0 results for supervisor
        $searchResponse = $this->get(route('documents.index', ['search' => 'Secret Financial']));
        $searchResponse->assertStatus(200);
        $searchResponse->assertDontSee('secret_audit.pdf');
    }

    public function test_immutable_parent_record_prohibits_deletion_and_replacement(): void
    {
        $journal = JournalEntry::create([
            'company_id' => $this->companyA->id,
            'entry_number' => 'JV-POSTED-001',
            'entry_date' => now()->toDateString(),
            'narration' => 'Posted Immutable Voucher',
            'total_amount' => 50000.00,
            'created_by' => $this->adminUser->id,
            'status' => 'posted', // Immutable status
        ]);

        $doc = $this->createDoc([
            'documentable_type' => JournalEntry::class,
            'documentable_id' => $journal->id,
            'category' => 'voucher_support',
            'title' => 'Statutory Audit Slip',
            'original_filename' => 'audit_slip.pdf',
            'storage_filename' => 'audit-slip-1234.pdf',
        ]);

        $this->assertTrue($doc->isParentImmutable());
        $this->assertFalse($this->policy->delete($this->adminUser, $doc));
        $this->assertFalse($this->policy->replace($this->adminUser, $doc));

        // Trying to delete via HTTP must return 403 Forbidden
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('documents.destroy', $doc));

        $response->assertStatus(403);
    }
}
