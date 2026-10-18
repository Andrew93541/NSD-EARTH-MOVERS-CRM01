<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Loan;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorWorkOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company1;
    protected Company $company2;
    protected User $ownerUser;
    protected User $pmUser;
    protected User $siteSupervisorUser;
    protected User $accountantUser;
    protected User $crossCompanyUser;
    protected Role $ownerRole;
    protected Role $pmRole;
    protected Role $supervisorRole;
    protected Role $accountantRole;
    protected Project $project;
    protected PurchaseRequest $purchaseRequest;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company1 = Company::create(['name' => 'NSD Earth Movers Ltd', 'slug' => 'nsd-earth-movers']);
        $this->company2 = Company::create(['name' => 'Competitor Infrastructure', 'slug' => 'competitor-infra']);

        $this->ownerRole = Role::create([
            'company_id' => $this->company1->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $this->pmRole = Role::create([
            'company_id' => $this->company1->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'procurement.*'],
            'is_system' => false,
        ]);

        $this->supervisorRole = Role::create([
            'company_id' => $this->company1->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'procurement.requests.view'],
            'is_system' => false,
        ]);

        $this->accountantRole = Role::create([
            'company_id' => $this->company1->id,
            'name' => 'Senior Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'accounting.*'],
            'is_system' => false,
        ]);

        $this->ownerUser = User::create([
            'name' => 'Rajesh Sharma',
            'email' => 'owner@nsdearthmovers.test',
            'password' => bcrypt('Secret123!'),
        ]);
        $this->company1->users()->attach($this->ownerUser->id, ['role_id' => $this->ownerRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->pmUser = User::create([
            'name' => 'Vikram Patel',
            'email' => 'pm@nsdearthmovers.test',
            'password' => bcrypt('Secret123!'),
        ]);
        $this->company1->users()->attach($this->pmUser->id, ['role_id' => $this->pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisorUser = User::create([
            'name' => 'Sunil Kumar',
            'email' => 'supervisor@nsdearthmovers.test',
            'password' => bcrypt('Secret123!'),
        ]);
        $this->company1->users()->attach($this->siteSupervisorUser->id, ['role_id' => $this->supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->accountantUser = User::create([
            'name' => 'Anil Mehta',
            'email' => 'accountant@nsdearthmovers.test',
            'password' => bcrypt('Secret123!'),
        ]);
        $this->company1->users()->attach($this->accountantUser->id, ['role_id' => $this->accountantRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->crossCompanyUser = User::create([
            'name' => 'Other Tenant User',
            'email' => 'cross@competitor.test',
            'password' => bcrypt('Secret123!'),
        ]);
        $crossRole = Role::create([
            'company_id' => $this->company2->id,
            'name' => 'Competitor Admin',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $this->company2->users()->attach($this->crossCompanyUser->id, ['role_id' => $crossRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->project = Project::create([
            'company_id' => $this->company1->id,
            'name' => 'Bengaluru Ring Road Highway',
            'code' => 'PRJ-BLR-01',
            'contract_value' => 50000000.00,
            'approved_budget' => 45000000.00,
            'status' => 'in_progress',
        ]);

        $this->purchaseRequest = PurchaseRequest::create([
            'company_id' => $this->company1->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->pmUser->id,
            'pr_number' => 'PR-2026-001',
            'request_date' => now(),
            'title' => 'Structural Steel & Crushed Aggregate Indent',
            'status' => 'pending',
            'current_approval_level' => 1,
            'estimated_total' => 250000.00,
        ]);
    }

    /**
     * Test 1: Authorized user uploads a valid file to an authorized parent record.
     */
    public function test_authorized_user_can_upload_valid_document_to_record(): void
    {
        $fakePdf = UploadedFile::fake()->create('contract_agreement.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->post(route('documents.store'), [
                'documentable_type' => 'project',
                'documentable_id' => $this->project->id,
                'category' => 'contract',
                'title' => 'Executed EPC Contract Agreement',
                'file' => $fakePdf,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('documents', [
            'company_id' => $this->company1->id,
            'documentable_type' => Project::class,
            'documentable_id' => $this->project->id,
            'category' => 'contract',
            'title' => 'Executed EPC Contract Agreement',
            'original_filename' => 'contract_agreement.pdf',
            'mime_type' => 'application/pdf',
            'version' => 1,
            'status' => 'active',
        ]);

        $document = Document::where('documentable_id', $this->project->id)->first();
        $this->assertNotNull($document);
        $this->assertNotEmpty($document->checksum);
        $this->assertStringStartsWith('companies/' . $this->company1->id . '/project/' . $this->project->id . '/', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company1->id,
            'action' => 'document.upload',
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
        ]);
    }

    /**
     * Test 2: Invalid MIME type, disallowed extension, and oversized files are rejected.
     */
    public function test_disallowed_extension_and_invalid_mime_are_rejected(): void
    {
        // 1. Disallowed extension: .exe
        $fakeExe = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');
        $response = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->post(route('documents.store'), [
                'documentable_type' => 'project',
                'documentable_id' => $this->project->id,
                'file' => $fakeExe,
            ]);
        $response->assertSessionHasErrors('file');

        // 2. Oversized file (>15MB)
        $fakeLarge = UploadedFile::fake()->create('large_drawing.pdf', 16000, 'application/pdf'); // 16 MB
        $response = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->post(route('documents.store'), [
                'documentable_type' => 'project',
                'documentable_id' => $this->project->id,
                'file' => $fakeLarge,
            ]);
        $response->assertSessionHasErrors('file');

        // 3. Empty file (0 bytes)
        $fakeEmpty = UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf');
        $response = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->post(route('documents.store'), [
                'documentable_type' => 'project',
                'documentable_id' => $this->project->id,
                'file' => $fakeEmpty,
            ]);
        $response->assertSessionHasErrors('file');
    }

    /**
     * Test 3: Generated storage names do not trust user-supplied paths or filenames.
     */
    public function test_filename_sanitization_and_uuid_storage_path_safety(): void
    {
        $service = new DocumentService();
        $dangerousFilename = '../../../../etc/passwd....exploit.pdf';
        $sanitized = $service->sanitizeFilename($dangerousFilename);

        $this->assertStringNotContainsString('/', $sanitized);
        $this->assertStringNotContainsString('..', $sanitized);
        $this->assertEquals('passwd.exploit.pdf', $sanitized);

        $fakePdf = UploadedFile::fake()->create('../../../exploit.pdf', 100, 'application/pdf');
        $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->post(route('documents.store'), [
                'documentable_type' => 'purchase_request',
                'documentable_id' => $this->purchaseRequest->id,
                'file' => $fakePdf,
            ]);

        $document = Document::where('documentable_id', $this->purchaseRequest->id)->first();
        $this->assertNotNull($document);
        $this->assertEquals('exploit.pdf', $document->original_filename);
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]+\.pdf$/i', $document->storage_filename);
    }

    /**
     * Test 4: Unauthenticated users cannot upload or retrieve documents.
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $fakePdf = UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf');

        $this->post(route('documents.store'), [
            'documentable_type' => 'purchase_request',
            'documentable_id' => $this->purchaseRequest->id,
            'file' => $fakePdf,
        ])->assertRedirect(route('login'));

        $this->get(route('documents.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * Test 5: Cross-company upload, preview, download, and deletion are denied.
     */
    public function test_cross_company_access_is_strictly_prevented(): void
    {
        $fakePdf = UploadedFile::fake()->create('internal_plan.pdf', 200, 'application/pdf');

        // Store document in Company 1
        $document = (new DocumentService())->storeDocument(
            $this->project,
            $fakePdf,
            ['category' => 'drawing'],
            $this->pmUser
        );

        // User from Company 2 tries to preview
        $this->actingAs($this->crossCompanyUser)
            ->withSession(['current_company_id' => $this->company2->id])
            ->get(route('documents.preview', $document))
            ->assertNotFound();

        // User from Company 2 tries to download
        $this->actingAs($this->crossCompanyUser)
            ->withSession(['current_company_id' => $this->company2->id])
            ->get(route('documents.download', $document))
            ->assertNotFound();

        // User from Company 2 tries to delete
        $this->actingAs($this->crossCompanyUser)
            ->withSession(['current_company_id' => $this->company2->id])
            ->delete(route('documents.destroy', $document))
            ->assertNotFound();
    }

    /**
     * Test 6 & 7: Confidential HR/payroll documents remain restricted to privileged roles.
     */
    public function test_confidential_hr_and_payroll_documents_are_restricted(): void
    {
        $employee = Employee::create([
            'company_id' => $this->company1->id,
            'first_name' => 'Ramesh',
            'last_name' => 'Yadav',
            'code' => 'EMP-001',
            'employment_type' => 'daily_waged',
            'daily_wage_rate' => 850.00,
            'monthly_salary' => 0.00,
        ]);

        $fakeAadhaar = UploadedFile::fake()->create('aadhaar_card.pdf', 300, 'application/pdf');

        // Owner attaches confidential ID proof
        $doc = (new DocumentService())->storeDocument(
            $employee,
            $fakeAadhaar,
            ['category' => 'identity_proof', 'is_confidential' => true],
            $this->ownerUser
        );

        $this->assertTrue($doc->is_confidential);

        // Site supervisor (non-privileged) attempts to view
        $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.preview', $doc))
            ->assertForbidden();

        // Owner (privileged) attempts to view
        $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.preview', $doc))
            ->assertOk();

        // Confidential Payroll Batch
        $payroll = PayrollPeriod::create([
            'company_id' => $this->company1->id,
            'title' => 'October 2026 Site Wages',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'approved',
        ]);
        $fakeSalarySlip = UploadedFile::fake()->create('salary_neft_advice.pdf', 300, 'application/pdf');
        $payrollDoc = (new DocumentService())->storeDocument(
            $payroll,
            $fakeSalarySlip,
            ['category' => 'bank_mandate'],
            $this->ownerUser
        );

        // Site supervisor is blocked from payroll file
        $this->actingAs($this->siteSupervisorUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.preview', $payrollDoc))
            ->assertForbidden();

        // Accountant can access confidential payroll documents
        $this->actingAs($this->accountantUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.preview', $payrollDoc))
            ->assertOk();
    }

    /**
     * Test 8: Document replacement creates superseding version while preserving audit trail.
     */
    public function test_document_replacement_increments_version_and_preserves_history(): void
    {
        $fakeV1 = UploadedFile::fake()->create('tender_boq_v1.pdf', 300, 'application/pdf');
        $docV1 = (new DocumentService())->storeDocument(
            $this->project,
            $fakeV1,
            ['category' => 'specification', 'title' => 'Tender BOQ Initial Draft'],
            $this->pmUser
        );

        $this->assertEquals(1, $docV1->version);
        $this->assertEquals('active', $docV1->status);

        $fakeV2 = UploadedFile::fake()->create('tender_boq_v2.pdf', 350, 'application/pdf');

        $response = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->post(route('documents.replace', $docV1), [
                'file' => $fakeV2,
                'title' => 'Tender BOQ Revised Final',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $docV1->refresh();
        $this->assertEquals('superseded', $docV1->status);
        $this->assertNotNull($docV1->superseded_by_id);

        $docV2 = Document::find($docV1->superseded_by_id);
        $this->assertNotNull($docV2);
        $this->assertEquals(2, $docV2->version);
        $this->assertEquals('active', $docV2->status);
        $this->assertEquals('Tender BOQ Revised Final', $docV2->title);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company1->id,
            'action' => 'document.replace',
            'auditable_type' => Document::class,
            'auditable_id' => $docV2->id,
        ]);
    }

    /**
     * Test 9: Attaching a document does NOT alter financial ledger balances or duplicate entries.
     */
    public function test_uploading_document_preserves_financial_immutability(): void
    {
        $je = JournalEntry::create([
            'company_id' => $this->company1->id,
            'entry_number' => 'JV-2026-999',
            'entry_date' => now(),
            'narration' => 'Initial Site Mobilization Advance',
            'status' => 'posted',
            'total_amount' => 100000.00,
            'created_by' => $this->accountantUser->id,
        ]);

        $initialTotal = $je->total_amount;
        $initialStatus = $je->status;

        $fakeVoucher = UploadedFile::fake()->create('bank_debit_advice.pdf', 200, 'application/pdf');

        $this->actingAs($this->accountantUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->post(route('documents.store'), [
                'documentable_type' => 'journal_entry',
                'documentable_id' => $je->id,
                'category' => 'voucher_support',
                'file' => $fakeVoucher,
            ]);

        $je->refresh();
        $this->assertEquals($initialTotal, $je->total_amount);
        $this->assertEquals($initialStatus, $je->status);
        $this->assertCount(1, $je->documents);
    }

    /**
     * Test 10: Posted and reversed journal supporting evidence cannot be deleted (Statutory Immutability).
     */
    public function test_statutory_immutability_blocks_deletion_of_posted_journal_evidence(): void
    {
        $je = JournalEntry::create([
            'company_id' => $this->company1->id,
            'entry_number' => 'JV-2026-888',
            'entry_date' => now(),
            'narration' => 'Statutory Audit Sample',
            'status' => 'posted',
            'total_amount' => 50000.00,
            'created_by' => $this->accountantUser->id,
        ]);

        $fakeDoc = UploadedFile::fake()->create('signed_voucher.pdf', 200, 'application/pdf');
        $doc = (new DocumentService())->storeDocument(
            $je,
            $fakeDoc,
            ['category' => 'voucher_support'],
            $this->accountantUser
        );

        $this->assertTrue($doc->isParentImmutable());

        // Attempt deletion by accountant
        $response = $this->actingAs($this->accountantUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->delete(route('documents.destroy', $doc));

        $response->assertForbidden();

        // Attempt deletion by owner
        $responseOwner = $this->actingAs($this->ownerUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->delete(route('documents.destroy', $doc));

        $responseOwner->assertForbidden();
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'deleted_at' => null]);
    }

    /**
     * Test 11: Central Document Centre search and filtering cannot leak unauthorized document metadata.
     */
    public function test_central_document_centre_filters_and_tenant_isolation(): void
    {
        // Document 1 in Company 1
        $pdf1 = UploadedFile::fake()->create('nsd_civil_drawing.pdf', 300, 'application/pdf');
        (new DocumentService())->storeDocument(
            $this->project,
            $pdf1,
            ['category' => 'drawing', 'title' => 'Structural Overpass Drawing'],
            $this->pmUser
        );

        // Document 2 in Company 2
        $compProject = Project::create([
            'company_id' => $this->company2->id,
            'name' => 'Competitor Secret Airport Project',
            'code' => 'COMP-AIR-01',
            'contract_value' => 10000000.00,
            'approved_budget' => 9000000.00,
            'status' => 'in_progress',
        ]);
        $pdf2 = UploadedFile::fake()->create('classified_tender.pdf', 300, 'application/pdf');
        (new DocumentService())->storeDocument(
            $compProject,
            $pdf2,
            ['category' => 'contract', 'title' => 'Confidential Competitor Bid'],
            $this->crossCompanyUser
        );

        // PM user from Company 1 queries Document Centre
        $response = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.index'));

        $response->assertOk();
        $response->assertSee('nsd_civil_drawing.pdf');
        $response->assertDontSee('classified_tender.pdf');
        $response->assertDontSee('Competitor Secret Airport Project');

        // Search filtering test
        $searchResponse = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.index', ['search' => 'Overpass']));

        $searchResponse->assertOk();
        $searchResponse->assertSee('Structural Overpass Drawing');
    }

    /**
     * Test 12: Secure preview and download stream with nosniff security headers.
     */
    public function test_secure_headers_on_preview_and_download(): void
    {
        $fakePdf = UploadedFile::fake()->create('approved_drawing.pdf', 250, 'application/pdf');
        $document = (new DocumentService())->storeDocument(
            $this->project,
            $fakePdf,
            ['category' => 'drawing'],
            $this->pmUser
        );

        // Preview
        $previewResp = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.preview', $document));

        $previewResp->assertOk();
        $previewResp->assertHeader('X-Content-Type-Options', 'nosniff');
        $previewResp->assertHeader('Content-Type', 'application/pdf');

        // Download
        $downloadResp = $this->actingAs($this->pmUser)
            ->withSession(['current_company_id' => $this->company1->id])
            ->get(route('documents.download', $document));

        $downloadResp->assertOk();
        $downloadResp->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
