<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionWorkCategory;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkOrderRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Company $otherCompany;
    protected User $admin;
    protected User $otherAdmin;
    protected Client $client;
    protected Project $project;
    protected Site $site;
    protected ConstructionWorkCategory $category;
    protected Client $otherClient;
    protected Project $otherProject;
    protected Site $otherSite;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // 1. Primary Company & Roles
        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
        ]);

        $ownerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh@nsdconstruction.com',
        ]);
        $this->admin->companies()->attach($this->company->id, [
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // 2. Second Company (for isolation tests)
        $this->otherCompany = Company::create([
            'name' => 'Apex Infra Corp',
            'slug' => 'apex-infra',
        ]);

        $otherOwnerRole = Role::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Apex Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $this->otherAdmin = User::factory()->create([
            'name' => 'Vikram Patel',
            'email' => 'vikram@apexinfra.com',
        ]);
        $this->otherAdmin->companies()->attach($this->otherCompany->id, [
            'role_id' => $otherOwnerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        session(['current_company_id' => $this->company->id]);

        // 3. Operational Master Data
        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Larsen & Toubro Ltd',
            'company_name' => 'L&T Heavy Civil Infra',
            'email' => 'procurement@lntecc.com',
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Mumbai-Nagpur Expressway Package 4',
            'code' => 'MNE-PKG4',
            'client_id' => $this->client->id,
            'status' => 'in_progress',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Section KM 120-145 Camp',
            'code' => 'SITE-KM120',
            'status' => 'active',
        ]);

        $this->category = ConstructionWorkCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Earthwork Excavation',
            'code' => 'EARTH-001',
            'unit_of_measure' => 'CuM',
            'is_active' => true,
        ]);

        // 4. Other Company Master Data (for strict cross-tenant foreign key testing)
        $this->otherClient = Client::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Apex Client Ltd',
            'company_name' => 'Apex Projects',
            'status' => 'active',
        ]);

        $this->otherProject = Project::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Apex Highway Package',
            'code' => 'APX-HWY',
            'client_id' => $this->otherClient->id,
            'status' => 'in_progress',
        ]);

        $this->otherSite = Site::create([
            'company_id' => $this->otherCompany->id,
            'project_id' => $this->otherProject->id,
            'name' => 'Apex Site 1',
            'code' => 'APX-S1',
            'status' => 'active',
        ]);
    }

    /**
     * Helper to create a valid digital PDF byte sequence with FlateDecode compressed stream.
     */
    protected function createMockDigitalPdf(string $textContent): string
    {
        $streamContent = "BT /F1 12 Tf 72 712 Td (" . addcslashes($textContent, "()\\") . ") Tj ET";
        $compressed = gzcompress($streamContent);
        $len = strlen($compressed);

        return "%PDF-1.4\n" .
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n" .
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n" .
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >> endobj\n" .
            "4 0 obj << /Length {$len} /Filter /FlateDecode >>\n" .
            "stream\n" .
            $compressed . "\n" .
            "endstream\n" .
            "endobj\n" .
            "trailer << /Size 5 /Root 1 0 R >>\n" .
            "%%EOF\n";
    }

    /**
     * Helper to create a scanned image-only PDF without text operators.
     */
    protected function createMockScannedPdf(): string
    {
        return "%PDF-1.4\n" .
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n" .
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n" .
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /XObject << /Im1 4 0 R >> >> >> endobj\n" .
            "4 0 obj << /Type /XObject /Subtype /Image /Width 10 /Height 10 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 30 >>\n" .
            "stream\n" .
            str_repeat("\xFF\x00\x00", 10) . "\n" .
            "endstream\n" .
            "endobj\n" .
            "trailer << /Size 5 /Root 1 0 R >>\n" .
            "%%EOF\n";
    }

    /**
     * Test A: Work Order register lists both client and internal orders, with search and filters.
     */
    public function test_work_order_register_lists_and_filters_both_client_and_internal_orders(): void
    {
        // 1. Create client-issued work order
        $clientWo = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0001',
            'order_type' => 'client_issued',
            'external_reference_number' => 'LT-EXP-8891',
            'title' => 'Expressway Embankment Earthwork',
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'total_amount' => 500000.00,
            'status' => 'draft',
            'extraction_status' => 'verified',
            'created_by' => $this->admin->id,
        ]);

        // 2. Create internal work order
        $internalWo = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'INT-202610-0001',
            'order_type' => 'internal',
            'external_reference_number' => 'INT-CAMP-01',
            'title' => 'Camp Levelling and Perimeter Ditching',
            'client_id' => null, // No fake client!
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'total_amount' => 120000.00,
            'status' => 'in_progress',
            'extraction_status' => 'none',
            'created_by' => $this->admin->id,
        ]);

        // 3. Visit register page
        $response = $this->actingAs($this->admin)->get(route('work-orders-register.index'));
        $response->assertOk();
        $response->assertSee('WO-202610-0001');
        $response->assertSee('Expressway Embankment Earthwork');
        $response->assertSee('INT-202610-0001');
        $response->assertSee('Camp Levelling and Perimeter Ditching');
        $response->assertSee('Client Issued');
        $response->assertSee('Internal Job');

        // 4. Test filter by order_type=client_issued
        $filterClientResponse = $this->actingAs($this->admin)
            ->get(route('work-orders-register.index', ['order_type' => 'client_issued']));
        $filterClientResponse->assertOk();
        $filterClientResponse->assertSee('WO-202610-0001');
        $filterClientResponse->assertDontSee('INT-202610-0001');

        // 5. Test filter by order_type=internal
        $filterInternalResponse = $this->actingAs($this->admin)
            ->get(route('work-orders-register.index', ['order_type' => 'internal']));
        $filterInternalResponse->assertOk();
        $filterInternalResponse->assertSee('INT-202610-0001');
        $filterInternalResponse->assertDontSee('WO-202610-0001');

        // 6. Test search by external reference number
        $searchResponse = $this->actingAs($this->admin)
            ->get(route('work-orders-register.index', ['search' => 'LT-EXP-8891']));
        $searchResponse->assertOk();
        $searchResponse->assertSee('WO-202610-0001');
        $searchResponse->assertDontSee('INT-202610-0001');
    }

    /**
     * Test B: Client-issued work order upload stores original document and extracts digital PDF text.
     */
    public function test_client_work_order_upload_stores_document_and_extracts_digital_pdf_text(): void
    {
        $textContent = "Work Order No: LNT-2026-9044 Issue Date: 2026-10-18 Target Date: 2026-12-31 Total Value: 750000.00 Name of Work: Site Grading and Borrow Area Excavation Larsen & Toubro Ltd";
        $pdfBytes = $this->createMockDigitalPdf($textContent);

        $uploadedFile = UploadedFile::fake()->createWithContent('Client_Work_Order_9044.pdf', $pdfBytes);

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders-register.upload-client'), [
                'file' => $uploadedFile,
            ]);

        $workOrder = WorkOrder::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($workOrder);

        $response->assertRedirect(route('work-orders-register.verify', $workOrder));

        // Verify Internal CRM ID and separate client reference
        $this->assertStringStartsWith('WO-', $workOrder->order_number);
        $this->assertEquals('client_issued', $workOrder->order_type);
        $this->assertEquals('LNT-2026-9044', $workOrder->external_reference_number);
        $this->assertEquals(750000.00, (float) $workOrder->total_amount);
        $this->assertEquals('2026-10-18', $workOrder->issue_date->format('Y-m-d'));
        $this->assertEquals('2026-12-31', $workOrder->target_completion_date->format('Y-m-d'));
        $this->assertEquals($this->client->id, $workOrder->client_id); // Auto-matched by name!
        $this->assertEquals('extracted', $workOrder->extraction_status);
        $this->assertEquals('draft', $workOrder->status);

        // Verify Document stored securely
        $document = $workOrder->primaryDocument();
        $this->assertNotNull($document);
        $this->assertEquals('application/pdf', $document->mime_type);
        $this->assertEquals(hash('sha256', $pdfBytes), $document->checksum);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
    }

    /**
     * Test C: Scanned PDF intake detects non-selectable text and marks scanned_manual_entry_required.
     */
    public function test_scanned_pdf_intake_detects_non_selectable_text_and_requires_manual_entry(): void
    {
        $scannedPdfBytes = $this->createMockScannedPdf();
        $uploadedFile = UploadedFile::fake()->createWithContent('Scanned_Signed_WO.pdf', $scannedPdfBytes);

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders-register.upload-client'), [
                'file' => $uploadedFile,
            ]);

        $workOrder = WorkOrder::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($workOrder);
        $response->assertRedirect(route('work-orders-register.verify', $workOrder));

        // Must be marked for manual entry
        $this->assertEquals('scanned_manual_entry_required', $workOrder->extraction_status);

        // Verify verification screen displays manual entry advisory
        $verifyViewResponse = $this->actingAs($this->admin)->get(route('work-orders-register.verify', $workOrder));
        $verifyViewResponse->assertOk();
        $verifyViewResponse->assertSee('Scanned Document / Non-Selectable Text');
    }

    /**
     * Test D: Staff verification confirms fields and separates verification from management approval.
     */
    public function test_staff_verification_confirms_document_and_separates_verification_from_management_approval(): void
    {
        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0010',
            'order_type' => 'client_issued',
            'external_reference_number' => 'RAW-REF-001',
            'title' => 'Initial Extracted Title',
            'client_id' => null,
            'total_amount' => 100000.00,
            'status' => 'draft',
            'extraction_status' => 'extracted',
            'created_by' => $this->admin->id,
        ]);

        $confirmPayload = [
            'title' => 'Verified Expressway Embankment Package A',
            'external_reference_number' => 'LT-EXP-FINAL-99',
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'assigned_supervisor_id' => $this->admin->id,
            'issue_date' => '2026-10-20',
            'start_date' => '2026-10-25',
            'target_completion_date' => '2026-12-15',
            'total_amount' => 450000.00,
            'description' => 'Staff verified against client letter dated 20 Oct.',
            'verification_notes' => 'Checked against physical signed copy page 1-4.',
            'submit_for_approval' => 0, // Stays in draft
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders-register.confirm-verification', $workOrder), $confirmPayload);

        $response->assertRedirect(route('work-orders-register.show', $workOrder));

        $workOrder->refresh();
        $this->assertEquals('verified', $workOrder->extraction_status);
        $this->assertEquals($this->admin->id, $workOrder->verified_by);
        $this->assertNotNull($workOrder->verified_at);
        $this->assertEquals('LT-EXP-FINAL-99', $workOrder->external_reference_number);
        $this->assertEquals(450000.00, (float) $workOrder->total_amount);

        // Verification does NOT auto-approve management status
        $this->assertEquals('draft', $workOrder->status);
        $this->assertNull($workOrder->approved_by);
        $this->assertNull($workOrder->approved_at);

        // Verification does NOT post any financial journal entries
        $this->assertEquals(0, JournalEntry::count());
    }

    /**
     * Test E: Non-destructive duplicate detection flags matching client reference without auto-merging.
     */
    public function test_duplicate_detection_flags_matching_client_reference_without_auto_merging(): void
    {
        // 1. Existing confirmed work order
        $existingOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0020',
            'order_type' => 'client_issued',
            'external_reference_number' => 'PO-DUP-888',
            'title' => 'Original Contract PO-DUP-888',
            'client_id' => $this->client->id,
            'total_amount' => 300000.00,
            'status' => 'approved',
            'extraction_status' => 'verified',
            'created_by' => $this->admin->id,
        ]);

        // 2. Upload second order matching same client and external reference number
        $textContent = "Purchase Order: PO-DUP-888 Larsen & Toubro Ltd Total Amount: 300000.00 Date: 2026-10-22";
        $pdfBytes = $this->createMockDigitalPdf($textContent);
        $uploadedFile = UploadedFile::fake()->createWithContent('PO_DUP_888_copy.pdf', $pdfBytes);

        $this->actingAs($this->admin)->post(route('work-orders-register.upload-client'), [
            'file' => $uploadedFile,
        ]);

        $newOrder = WorkOrder::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotEquals($existingOrder->id, $newOrder->id); // NOT merged!

        // Must be flagged as duplicate
        $this->assertTrue($newOrder->is_duplicate_flagged);
        $this->assertEquals($existingOrder->id, $newOrder->duplicate_matched_order_id);
        $this->assertStringContainsString('PO-DUP-888', $newOrder->duplicate_reason);

        // 3. Staff review: Authorized user can dismiss duplicate warning with a justification reason
        $dismissResponse = $this->actingAs($this->admin)
            ->post(route('work-orders-register.dismiss-duplicate', $newOrder), [
                'reason' => 'Amendment #2 issued by L&T with extra scope under the same PO head.',
            ]);

        $dismissResponse->assertRedirect(route('work-orders-register.show', $newOrder));

        $newOrder->refresh();
        $this->assertFalse($newOrder->is_duplicate_flagged);
        $this->assertEquals($this->admin->id, $newOrder->duplicate_resolved_by);
        $this->assertNotNull($newOrder->duplicate_resolved_at);
        $this->assertStringContainsString('Amendment #2', $newOrder->notes);
    }

    /**
     * Test F: Duplicate detection flags identical file SHA-256 checksums.
     */
    public function test_duplicate_detection_flags_identical_file_checksum(): void
    {
        $textContent = "Work Order: WO-UNIQUE-101 Date: 2026-10-24 Value: 200000.00";
        $pdfBytes = $this->createMockDigitalPdf($textContent);

        // Upload first time
        $file1 = UploadedFile::fake()->createWithContent('Order_One.pdf', $pdfBytes);
        $this->actingAs($this->admin)->post(route('work-orders-register.upload-client'), ['file' => $file1]);
        $order1 = WorkOrder::where('company_id', $this->company->id)->latest()->first();
        $this->assertFalse($order1->is_duplicate_flagged);

        // Upload exact same file second time
        $file2 = UploadedFile::fake()->createWithContent('Order_One_Reupload.pdf', $pdfBytes);
        $resp2 = $this->actingAs($this->admin)->post(route('work-orders-register.upload-client'), ['file' => $file2]);
        $resp2->assertSessionHasNoErrors();
        $this->assertEquals(2, WorkOrder::where('company_id', $this->company->id)->count());

        $order2 = WorkOrder::where('company_id', $this->company->id)->orderBy('id', 'desc')->first();

        $this->assertNotEquals($order1->id, $order2->id);
        $this->assertTrue($order2->is_duplicate_flagged);
        $this->assertEquals($order1->id, $order2->duplicate_matched_order_id);
        $this->assertStringContainsString('SHA-256', $order2->duplicate_reason);
    }

    /**
     * Test G: Internal work order creation supports multiple BOQ lines without forced client.
     */
    public function test_internal_work_order_creation_supports_multiple_boq_items_without_forced_client(): void
    {
        $internalPayload = [
            'title' => 'Camp Infrastructure & Stormwater Drainage',
            'external_reference_number' => 'INT-DIR-009',
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'assigned_supervisor_id' => $this->admin->id,
            'issue_date' => '2026-10-25',
            'start_date' => '2026-10-26',
            'target_completion_date' => '2026-11-30',
            'description' => 'Self-directed earthmoving and precast pipe laying.',
            'submit_for_approval' => 1,
            'items' => [
                [
                    'description' => 'Trench excavation for RCC pipe',
                    'work_category_id' => $this->category->id,
                    'unit_of_measure' => 'CuM',
                    'quantity' => 200,
                    'unit_rate' => 350.00, // 70,000
                ],
                [
                    'description' => 'Murrum gravel backfilling & compaction',
                    'work_category_id' => $this->category->id,
                    'unit_of_measure' => 'CuM',
                    'quantity' => 150,
                    'unit_rate' => 400.00, // 60,000
                ],
                [
                    'description' => 'Heavy JCB Excavator operator hire',
                    'work_category_id' => null,
                    'unit_of_measure' => 'Hours',
                    'quantity' => 40,
                    'unit_rate' => 1200.00, // 48,000
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders-register.store-internal'), $internalPayload);

        $workOrder = WorkOrder::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($workOrder);

        $response->assertRedirect(route('work-orders-register.show', $workOrder));

        $this->assertEquals('internal', $workOrder->order_type);
        $this->assertNull($workOrder->client_id); // No fake client!
        $this->assertEquals(178000.00, (float) $workOrder->total_amount); // 70k + 60k + 48k
        $this->assertEquals('pending_approval', $workOrder->status);
        $this->assertEquals('none', $workOrder->extraction_status);

        // Verify items created
        $this->assertCount(3, $workOrder->items);
        $this->assertEquals('Trench excavation for RCC pipe', $workOrder->items[0]->description);
        $this->assertEquals(70000.00, (float) $workOrder->items[0]->total_price);
    }

    /**
     * Test H: Management approval authorizes work order without posting financial entries.
     */
    public function test_management_approval_authorizes_work_order_without_posting_financial_entries(): void
    {
        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0030',
            'order_type' => 'client_issued',
            'title' => 'Approved Package Road Base',
            'total_amount' => 500000.00,
            'status' => 'pending_approval',
            'extraction_status' => 'verified',
            'created_by' => $this->admin->id,
        ]);

        $this->assertEquals(0, JournalEntry::count());

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders-register.approve', $workOrder), [
                'notes' => 'Authorized by Project Director for site mobilization.',
            ]);

        $response->assertRedirect(route('work-orders-register.show', $workOrder));

        $workOrder->refresh();
        $this->assertEquals('approved', $workOrder->status);
        $this->assertEquals($this->admin->id, $workOrder->approved_by);
        $this->assertNotNull($workOrder->approved_at);
        $this->assertEquals('Authorized by Project Director for site mobilization.', $workOrder->approval_notes);

        // Financial safeguard: Zero journal entries created on work order approval!
        $this->assertEquals(0, JournalEntry::count());
    }

    /**
     * Test I: Multi-tenant company isolation on work orders.
     */
    public function test_multi_tenant_company_isolation_on_work_orders(): void
    {
        $companyAOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-COMP-A-01',
            'order_type' => 'client_issued',
            'title' => 'Confidential Earthwork Company A',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        // User from Company B cannot access Company A's work order
        session(['current_company_id' => $this->otherCompany->id]);
        $response = $this->actingAs($this->otherAdmin)
            ->get(route('work-orders-register.show', $companyAOrder));

        $response->assertNotFound();
    }

    /**
     * Test J: Document download and preview authorization.
     */
    public function test_document_download_and_preview_enforces_authorization(): void
    {
        $pdfBytes = $this->createMockDigitalPdf("Confidential Contract Terms");
        $file = UploadedFile::fake()->createWithContent('Protected_Doc.pdf', $pdfBytes);

        $this->actingAs($this->admin)->post(route('work-orders-register.upload-client'), ['file' => $file]);
        $workOrder = WorkOrder::where('company_id', $this->company->id)->latest()->first();
        $document = $workOrder->primaryDocument();

        // 1. Authorized user can preview and download
        $previewResp = $this->actingAs($this->admin)
            ->get(route('work-orders-register.preview-document', [$workOrder, $document]));
        $previewResp->assertOk();
        $this->assertEquals('application/pdf', $previewResp->headers->get('Content-Type'));

        $downloadResp = $this->actingAs($this->admin)
            ->get(route('work-orders-register.download-document', [$workOrder, $document]));
        $downloadResp->assertOk();

        // 2. User from Company B cannot access Company A's document
        session(['current_company_id' => $this->otherCompany->id]);
        $unauthorizedResp = $this->actingAs($this->otherAdmin)
            ->get(route('work-orders-register.preview-document', [$workOrder, $document]));
        $unauthorizedResp->assertNotFound();
    }

    /**
     * Test K: Unauthorized role (e.g. basic employee) cannot approve work order.
     */
    public function test_unauthorized_user_cannot_approve_work_order(): void
    {
        $employeeRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'General Worker',
            'slug' => 'employee',
            'permissions' => ['attendance.view'],
        ]);

        $employee = User::factory()->create(['name' => 'Suresh Worker']);
        $employee->companies()->attach($this->company->id, [
            'role_id' => $employeeRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0099',
            'order_type' => 'client_issued',
            'title' => 'Bridge Foundation Package',
            'status' => 'pending_approval',
            'created_by' => $this->admin->id,
        ]);

        session(['current_company_id' => $this->company->id]);
        $response = $this->actingAs($employee)
            ->post(route('work-orders-register.approve', $workOrder));

        $response->assertForbidden();
        $this->assertEquals('pending_approval', $workOrder->fresh()->status);
    }

    /**
     * Test L: Non-PDF files are strictly rejected on client work order upload.
     */
    public function test_non_pdf_file_rejected_on_client_upload(): void
    {
        $fakeExe = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders-register.upload-client'), ['file' => $fakeExe]);

        $response->assertSessionHasErrors('file');
    }

    /**
     * Test M: Unauthorized roles cannot perform restricted Work Order Register actions.
     */
    public function test_unauthorized_roles_cannot_perform_restricted_register_actions(): void
    {
        $unauthorizedRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'HR Assistant',
            'slug' => 'hr_officer',
            'permissions' => ['employees.view'],
        ]);

        $unauthorizedUser = User::factory()->create(['name' => 'Anita HR']);
        $unauthorizedUser->companies()->attach($this->company->id, [
            'role_id' => $unauthorizedRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0088',
            'order_type' => 'client_issued',
            'title' => 'Trench Package',
            'status' => 'pending_approval',
            'extraction_status' => 'extracted',
            'created_by' => $this->admin->id,
        ]);

        $pdfBytes = $this->createMockDigitalPdf("Restricted Content");
        $file = UploadedFile::fake()->createWithContent('sample.pdf', $pdfBytes);
        $document = app(\App\Services\DocumentService::class)->storeDocument(
            $workOrder,
            $file,
            ['category' => 'work_order_original', 'title' => 'Test Doc'],
            $this->admin
        );

        session(['current_company_id' => $this->company->id]);

        // 1. Viewing register
        $this->actingAs($unauthorizedUser)->get(route('work-orders-register.index'))->assertForbidden();

        // 2. Creation hub
        $this->actingAs($unauthorizedUser)->get(route('work-orders-register.create'))->assertForbidden();

        // 3. Upload client PDF
        $this->actingAs($unauthorizedUser)->post(route('work-orders-register.upload-client'), ['file' => $file])->assertForbidden();

        // 4. Store internal work order
        $this->actingAs($unauthorizedUser)->post(route('work-orders-register.store-internal'), ['title' => 'Unauthorized Order'])->assertForbidden();

        // 5. Show order
        $this->actingAs($unauthorizedUser)->get(route('work-orders-register.show', $workOrder))->assertForbidden();

        // 6. Verify order
        $this->actingAs($unauthorizedUser)->get(route('work-orders-register.verify', $workOrder))->assertForbidden();
        $this->actingAs($unauthorizedUser)->post(route('work-orders-register.confirm-verification', $workOrder), ['title' => 'Hack'])->assertForbidden();

        // 7. Approve order
        $this->actingAs($unauthorizedUser)->post(route('work-orders-register.approve', $workOrder))->assertForbidden();

        // 8. Dismiss duplicate
        $this->actingAs($unauthorizedUser)->post(route('work-orders-register.dismiss-duplicate', $workOrder), ['reason' => 'Test'])->assertForbidden();

        // 9. Document preview & download
        $this->actingAs($unauthorizedUser)->get(route('work-orders-register.preview-document', [$workOrder, $document]))->assertForbidden();
        $this->actingAs($unauthorizedUser)->get(route('work-orders-register.download-document', [$workOrder, $document]))->assertForbidden();
    }

    /**
     * Test N: Site supervisor can view register and orders but cannot create, verify, approve, or dismiss duplicates.
     */
    public function test_site_supervisor_can_view_register_and_orders_but_cannot_create_verify_approve_or_dismiss_duplicates(): void
    {
        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'attendance.record'],
        ]);

        $supervisor = User::factory()->create(['name' => 'Ramesh Supervisor']);
        $supervisor->companies()->attach($this->company->id, [
            'role_id' => $supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0077',
            'order_type' => 'client_issued',
            'title' => 'Supervisor Viewable Package',
            'status' => 'pending_approval',
            'extraction_status' => 'extracted',
            'created_by' => $this->admin->id,
        ]);

        session(['current_company_id' => $this->company->id]);

        // Supervisor CAN view register and record
        $this->actingAs($supervisor)->get(route('work-orders-register.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('work-orders-register.show', $workOrder))->assertOk();

        // Supervisor CANNOT create, verify, approve, or dismiss duplicates
        $this->actingAs($supervisor)->get(route('work-orders-register.create'))->assertForbidden();
        $this->actingAs($supervisor)->post(route('work-orders-register.store-internal'), ['title' => 'Supervisor WO'])->assertForbidden();
        $this->actingAs($supervisor)->get(route('work-orders-register.verify', $workOrder))->assertForbidden();
        $this->actingAs($supervisor)->post(route('work-orders-register.confirm-verification', $workOrder), ['title' => 'Supervisor Verify'])->assertForbidden();
        $this->actingAs($supervisor)->post(route('work-orders-register.approve', $workOrder))->assertForbidden();
        $this->actingAs($supervisor)->post(route('work-orders-register.dismiss-duplicate', $workOrder), ['reason' => 'Supervisor Dismiss'])->assertForbidden();
    }

    /**
     * Test O: Oversized file uploads (> 15MB) are strictly rejected.
     */
    public function test_oversized_file_upload_is_strictly_rejected(): void
    {
        // 16 MB fake file (16384 KB)
        $oversizedFile = UploadedFile::fake()->create('huge_work_order.pdf', 16384, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->post(route('work-orders-register.upload-client'), ['file' => $oversizedFile]);

        $response->assertSessionHasErrors('file');
    }

    /**
     * Test P: Missing company context denies access and never exposes data.
     */
    public function test_missing_company_context_denies_access_and_never_exposes_data(): void
    {
        $unattachedUser = User::factory()->create(['name' => 'Orphan User']);
        // User has no companies attached at all

        session()->forget('current_company_id');

        // 1. Through web stack: ResolveTenantWorkspace middleware intercepts and redirects to workspace selection
        $response = $this->actingAs($unattachedUser)->get(route('work-orders-register.index'));
        $response->assertRedirect(route('workspace.select'));

        // 2. Controller-level defense: If JSON requested or middleware bypassed, getActiveCompanyId() aborts 403
        $apiResponse = $this->actingAs($unattachedUser)
            ->getJson(route('work-orders-register.index'));
        $apiResponse->assertForbidden();

        $controllerBypassResponse = $this->actingAs($unattachedUser)
            ->withoutMiddleware(\App\Http\Middleware\ResolveTenantWorkspace::class)
            ->get(route('work-orders-register.index'));
        $controllerBypassResponse->assertForbidden();

        $createResponse = $this->actingAs($unattachedUser)
            ->withoutMiddleware(\App\Http\Middleware\ResolveTenantWorkspace::class)
            ->post(route('work-orders-register.store-internal'), ['title' => 'Test']);
        $createResponse->assertForbidden();
    }

    /**
     * Test Q: Tampered session company ID cannot bypass tenant isolation.
     */
    public function test_tampered_session_company_id_cannot_bypass_tenant_isolation(): void
    {
        // Create an order in other company
        $otherOrder = WorkOrder::create([
            'company_id' => $this->otherCompany->id,
            'order_number' => 'WO-APEX-9999',
            'order_type' => 'client_issued',
            'title' => 'Apex Secret Work Order',
            'status' => 'draft',
            'created_by' => $this->otherAdmin->id,
        ]);

        // Admin from Company A sets session to otherCompany->id (tampering session)
        session(['current_company_id' => $this->otherCompany->id]);

        $response = $this->actingAs($this->admin)->get(route('work-orders-register.index'));
        $response->assertOk();

        // The response must NOT contain the other company's work order!
        $response->assertDontSee('Apex Secret Work Order');
        $response->assertDontSee('WO-APEX-9999');
    }

    /**
     * Test R: Cross-tenant access is rejected on every Work Order action.
     */
    public function test_cross_tenant_access_is_rejected_on_every_work_order_action(): void
    {
        $companyAOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-COMP-A-99',
            'order_type' => 'client_issued',
            'title' => 'Confidential Package Company A',
            'status' => 'pending_approval',
            'extraction_status' => 'extracted',
            'created_by' => $this->admin->id,
        ]);

        $pdfBytes = $this->createMockDigitalPdf("Secret Content");
        $file = UploadedFile::fake()->createWithContent('secret.pdf', $pdfBytes);
        $document = app(\App\Services\DocumentService::class)->storeDocument(
            $companyAOrder,
            $file,
            ['category' => 'work_order_original', 'title' => 'Company A Doc'],
            $this->admin
        );

        // User from Company B attempts actions on Company A's work order
        session(['current_company_id' => $this->otherCompany->id]);

        $this->actingAs($this->otherAdmin)->get(route('work-orders-register.show', $companyAOrder))->assertNotFound();
        $this->actingAs($this->otherAdmin)->get(route('work-orders-register.verify', $companyAOrder))->assertNotFound();
        $this->actingAs($this->otherAdmin)->post(route('work-orders-register.confirm-verification', $companyAOrder), ['title' => 'Hack'])->assertNotFound();
        $this->actingAs($this->otherAdmin)->post(route('work-orders-register.approve', $companyAOrder))->assertNotFound();
        $this->actingAs($this->otherAdmin)->post(route('work-orders-register.dismiss-duplicate', $companyAOrder), ['reason' => 'Hack'])->assertNotFound();
        $this->actingAs($this->otherAdmin)->get(route('work-orders-register.preview-document', [$companyAOrder, $document]))->assertNotFound();
        $this->actingAs($this->otherAdmin)->get(route('work-orders-register.download-document', [$companyAOrder, $document]))->assertNotFound();
    }

    /**
     * Test S: Cross-tenant foreign key tampering in internal creation and verification is rejected.
     */
    public function test_cross_tenant_foreign_key_tampering_is_rejected(): void
    {
        // 1. Attempting to create internal work order in Company A using Company B's project_id
        session(['current_company_id' => $this->company->id]);

        $response = $this->actingAs($this->admin)->post(route('work-orders-register.store-internal'), [
            'title' => 'Tampered Project WO',
            'project_id' => $this->otherProject->id, // Belongs to Company B!
        ]);
        $response->assertSessionHasErrors('project_id');

        // 2. Attempting to create internal work order in Company A using Company B's site_id
        $response2 = $this->actingAs($this->admin)->post(route('work-orders-register.store-internal'), [
            'title' => 'Tampered Site WO',
            'site_id' => $this->otherSite->id, // Belongs to Company B!
        ]);
        $response2->assertSessionHasErrors('site_id');

        // 3. Attempting to verify work order in Company A using Company B's client_id
        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0066',
            'order_type' => 'client_issued',
            'title' => 'Verification Tamper Test',
            'status' => 'draft',
            'extraction_status' => 'extracted',
            'created_by' => $this->admin->id,
        ]);

        $response3 = $this->actingAs($this->admin)->post(route('work-orders-register.confirm-verification', $workOrder), [
            'title' => 'Updated Title',
            'client_id' => $this->otherClient->id, // Belongs to Company B!
        ]);
        $response3->assertSessionHasErrors('client_id');
    }

    /**
     * Test T: Document mismatch cross-order tampering is rejected.
     */
    public function test_document_mismatch_cross_order_tampering_is_rejected(): void
    {
        $order1 = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0051',
            'order_type' => 'client_issued',
            'title' => 'Order 1',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $order2 = WorkOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'WO-202610-0052',
            'order_type' => 'client_issued',
            'title' => 'Order 2',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $pdfBytes = $this->createMockDigitalPdf("Doc for Order 2");
        $file = UploadedFile::fake()->createWithContent('order2_doc.pdf', $pdfBytes);
        $document2 = app(\App\Services\DocumentService::class)->storeDocument(
            $order2,
            $file,
            ['category' => 'work_order_original', 'title' => 'Order 2 Document'],
            $this->admin
        );

        session(['current_company_id' => $this->company->id]);

        // Attempting to access Document 2 via Order 1's URL
        $this->actingAs($this->admin)
            ->get(route('work-orders-register.preview-document', [$order1, $document2]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('work-orders-register.download-document', [$order1, $document2]))
            ->assertNotFound();
    }
}
