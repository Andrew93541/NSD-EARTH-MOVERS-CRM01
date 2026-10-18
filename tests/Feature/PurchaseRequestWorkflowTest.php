<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurchaseRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminUser;
    protected User $projectManager;
    protected User $siteSupervisor;
    protected User $siteSupervisor2;
    protected User $procurementOfficer;
    protected User $companyBUser;
    protected Project $project;
    protected Site $siteA;
    protected Site $siteB;
    protected Warehouse $warehouse;
    protected Material $steelMaterial;
    protected Material $cementMaterial;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Company A setup
        $this->companyA = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $adminRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);

        $pmRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.view', 'projects.edit', 'sites.view', 'materials.request', 'materials.approve'],
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'materials.request'],
        ]);

        $procurementRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Procurement Officer',
            'slug' => 'procurement_officer',
            'permissions' => ['procurement.*'],
        ]);

        $this->adminUser = User::create([
            'name' => 'Rajesh Varma (Owner)',
            'email' => 'owner@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->companyA->users()->attach($this->adminUser->id, ['role_id' => $adminRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->projectManager = User::create([
            'name' => 'Amit Sharma (PM)',
            'email' => 'pm@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->companyA->users()->attach($this->projectManager->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisor = User::create([
            'name' => 'Vikram Malhotra (Supervisor 1)',
            'email' => 'supervisor1@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->companyA->users()->attach($this->siteSupervisor->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->siteSupervisor2 = User::create([
            'name' => 'Ramesh Kumar (Supervisor 2)',
            'email' => 'supervisor2@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->companyA->users()->attach($this->siteSupervisor2->id, ['role_id' => $supervisorRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->procurementOfficer = User::create([
            'name' => 'Sneha Kulkarni (Procurement)',
            'email' => 'procurement@nsd.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->companyA->users()->attach($this->procurementOfficer->id, ['role_id' => $procurementRole->id, 'status' => 'active', 'is_primary' => true]);

        // Company B setup for multi-tenant isolation testing
        $this->companyB = Company::create([
            'name' => 'Competitor Infrastructure Ltd',
            'slug' => 'competitor-infra',
        ]);
        $compRole = Role::create([
            'company_id' => $this->companyB->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
        ]);
        $this->companyBUser = User::create([
            'name' => 'External Rival',
            'email' => 'rival@othercompany.local',
            'password' => Hash::make('Password123!'),
        ]);
        $this->companyB->users()->attach($this->companyBUser->id, ['role_id' => $compRole->id, 'status' => 'active', 'is_primary' => true]);

        // Projects, Sites, Warehouses, Materials, Suppliers for Company A
        $this->project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Hebbal Flyover Extension',
            'code' => 'HEB-FLY-01',
            'manager_id' => $this->projectManager->id,
            'status' => 'on_track',
            'contract_value' => 85000000.00,
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'name' => 'Pillar 14 Casting Yard',
            'supervisor_id' => $this->siteSupervisor->id,
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project->id,
            'name' => 'Pier 22 Foundation Site',
            'supervisor_id' => $this->siteSupervisor2->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->companyA->id,
            'name' => 'Hebbal Central Batching Yard',
            'type' => 'central',
            'is_active' => true,
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Structural Metals',
            'slug' => 'structural-metals',
        ]);

        $this->steelMaterial = Material::create([
            'company_id' => $this->companyA->id,
            'material_category_id' => $category->id,
            'code' => 'STEEL-TMT-16MM',
            'name' => 'Fe 550D TMT Rebars 16mm',
            'unit_of_measure' => 'tons',
            'standard_cost' => 64000.00,
            'is_active' => true,
        ]);

        $this->cementMaterial = Material::create([
            'company_id' => $this->companyA->id,
            'material_category_id' => $category->id,
            'code' => 'CEM-OPC-53',
            'name' => 'UltraTech OPC 53 Grade Cement',
            'unit_of_measure' => 'bags',
            'standard_cost' => 380.00,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Tata Steel Infrastructure Supplies',
            'contact_person' => 'Sunil Gupta',
            'phone' => '+91 98801 11223',
            'gstin' => '29AAACT2727Q1ZB',
            'status' => 'active',
        ]);
    }

    public function test_authorized_site_supervisor_can_create_draft_purchase_request(): void
    {
        $payload = [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'warehouse_id' => $this->warehouse->id,
            'required_by_date' => Carbon::now()->addDays(5)->toDateString(),
            'priority' => 'urgent',
            'reason' => 'Critical reinforcement needed for girder casting batch next Monday.',
            'action' => 'draft',
            'items' => [
                [
                    'material_id' => $this->steelMaterial->id,
                    'quantity_requested' => 25.500,
                    'specifications' => 'IS 1786:2008 Grade Fe 550D test certificate required',
                    'estimated_rate' => 64000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.store'), $payload);

        $pr = PurchaseRequest::where('site_id', $this->siteA->id)->first();
        $this->assertNotNull($pr);
        $response->assertRedirect(route('purchase-requests.show', $pr));

        $this->assertEquals('draft', $pr->status);
        $this->assertEquals('urgent', $pr->priority);
        $this->assertEquals($this->siteSupervisor->id, $pr->requested_by);
        $this->assertNull($pr->submitted_at);
        $this->assertCount(1, $pr->items);

        $item = $pr->items->first();
        $this->assertEquals(25.500, (float) $item->quantity_requested);
        $this->assertEquals(0.000, (float) $item->quantity_ordered);
        $this->assertEquals(25.500, (float) $item->remainingQuantity());

        // Inventory is not affected by draft
        $this->assertEquals(0, StockLedgerEntry::where('material_id', $this->steelMaterial->id)->count());

        // Audit log exists
        $audit = AuditLog::where('auditable_type', PurchaseRequest::class)
            ->where('auditable_id', $pr->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals('purchase_request_drafted', $audit->action);
    }

    public function test_request_submission_changes_status_and_notifies_project_manager(): void
    {
        $payload = [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'required_by_date' => Carbon::now()->addDays(7)->toDateString(),
            'priority' => 'critical',
            'reason' => 'Emergency batch of cement required to avoid concrete work stoppage.',
            'action' => 'submit',
            'items' => [
                [
                    'material_id' => $this->cementMaterial->id,
                    'quantity_requested' => 500.000,
                    'specifications' => 'Fresh packaging, less than 30 days old',
                    'estimated_rate' => 380.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.store'), $payload);

        $pr = PurchaseRequest::where('site_id', $this->siteA->id)->first();
        $this->assertNotNull($pr);
        $response->assertRedirect(route('purchase-requests.show', $pr));

        $this->assertEquals('pending', $pr->status);
        $this->assertNotNull($pr->submitted_at);

        // Project Manager should receive an AppNotification
        $notification = AppNotification::where('user_id', $this->projectManager->id)
            ->where('type', 'danger') // critical priority triggers 'danger'
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString($pr->pr_number, $notification->message);

        // Submitting an existing draft
        $draftPr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-DRAFT',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'required_by_date' => Carbon::today()->addDays(3),
            'status' => 'draft',
            'priority' => 'normal',
            'reason' => 'Routine replenishment of cement bags',
            'requested_by' => $this->siteSupervisor->id,
        ]);

        $submitResponse = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.submit', $draftPr));

        $submitResponse->assertRedirect(route('purchase-requests.show', $draftPr));
        $draftPr->refresh();
        $this->assertEquals('pending', $draftPr->status);
        $this->assertNotNull($draftPr->submitted_at);
    }

    public function test_invalid_quantities_and_unrelated_project_site_are_rejected(): void
    {
        // Negative quantity
        $response = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.store'), [
                'project_id' => $this->project->id,
                'site_id' => $this->siteA->id,
                'required_by_date' => Carbon::today()->addDays(4)->toDateString(),
                'priority' => 'normal',
                'reason' => 'Invalid quantity test',
                'action' => 'draft',
                'items' => [
                    [
                        'material_id' => $this->steelMaterial->id,
                        'quantity_requested' => -10.0,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['items.0.quantity_requested']);

        // Site belonging to a different project
        $otherProject = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Outer Ring Road Asphalting',
            'code' => 'ORR-ASPH-02',
            'status' => 'on_track',
        ]);

        $siteMismatchResponse = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.store'), [
                'project_id' => $otherProject->id,
                'site_id' => $this->siteA->id, // siteA belongs to $this->project, NOT $otherProject
                'required_by_date' => Carbon::today()->addDays(4)->toDateString(),
                'priority' => 'normal',
                'reason' => 'Mismatch testing',
                'action' => 'draft',
                'items' => [
                    [
                        'material_id' => $this->steelMaterial->id,
                        'quantity_requested' => 10.0,
                    ],
                ],
            ]);

        $siteMismatchResponse->assertSessionHasErrors(['site_id']);
    }

    public function test_site_supervisor_cannot_create_request_for_unassigned_site(): void
    {
        // Supervisor 1 attempts to create a request for Site B (supervised by Supervisor 2)
        $response = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.store'), [
                'project_id' => $this->project->id,
                'site_id' => $this->siteB->id, // Assigned to Supervisor 2
                'required_by_date' => Carbon::today()->addDays(4)->toDateString(),
                'priority' => 'normal',
                'reason' => 'Unauthorized site requisition',
                'action' => 'draft',
                'items' => [
                    [
                        'material_id' => $this->steelMaterial->id,
                        'quantity_requested' => 5.0,
                    ],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_segregation_of_duties_prevents_self_approval(): void
    {
        // Site supervisor creates and submits a request
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-SOD01',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(3),
            'status' => 'pending',
            'priority' => 'normal',
            'reason' => 'Requisition needing segregation check',
            'requested_by' => $this->siteSupervisor->id,
        ]);

        // Site supervisor tries to approve their own request -> 403 Forbidden (not an approver)
        $response = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr));

        $response->assertForbidden();

        // What if Project Manager created the request themselves?
        $pmPr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-SOD02',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(3),
            'status' => 'pending',
            'priority' => 'normal',
            'reason' => 'PM requested material indent',
            'requested_by' => $this->projectManager->id,
        ]);

        // PM tries to approve their own request -> should be blocked by segregation of duties
        $pmSelfApproveResponse = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pmPr));

        $pmSelfApproveResponse->assertSessionHasErrors(['status']);
        $this->assertEquals('pending', $pmPr->fresh()->status);

        // However, Company Owner/Admin CAN approve it
        $ownerApproveResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pmPr));

        $ownerApproveResponse->assertRedirect(route('purchase-requests.show', $pmPr));
        $this->assertEquals('approved', $pmPr->fresh()->status);
    }

    public function test_project_manager_can_approve_and_requester_is_notified(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-APP01',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(5),
            'status' => 'pending',
            'priority' => 'urgent',
            'reason' => 'TMT bars for pier reinforcement',
            'requested_by' => $this->siteSupervisor->id,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->steelMaterial->id,
            'quantity_requested' => 15.000,
            'quantity_ordered' => 0.000,
            'estimated_rate' => 64000.00,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.approve', $pr));

        $response->assertRedirect(route('purchase-requests.show', $pr));
        $pr->refresh();

        $this->assertEquals('approved', $pr->status);
        $this->assertEquals($this->projectManager->id, $pr->approved_by);
        $this->assertNotNull($pr->approved_at);

        // Requester notified
        $notification = AppNotification::where('user_id', $this->siteSupervisor->id)
            ->where('type', 'success')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('approved', strtolower($notification->message));

        // Approving does NOT create any stock ledger movement
        $this->assertEquals(0, StockLedgerEntry::where('material_id', $this->steelMaterial->id)->count());
    }

    public function test_rejection_requires_reason_and_allows_revision(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-REJ01',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(5),
            'status' => 'pending',
            'priority' => 'normal',
            'reason' => 'Requesting extra cement bags',
            'requested_by' => $this->siteSupervisor->id,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->cementMaterial->id,
            'quantity_requested' => 300.000,
            'quantity_ordered' => 0.000,
        ]);

        // Rejection without reason fails validation
        $failResponse = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.reject', $pr), []);

        $failResponse->assertSessionHasErrors(['rejection_reason']);

        // Rejection with valid reason
        $rejectResponse = $this->actingAs($this->projectManager)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.reject', $pr), [
                'rejection_reason' => 'Existing inventory at Hebbal yard has 250 bags available. Reduce indent to 50 bags.',
            ]);

        $rejectResponse->assertRedirect(route('purchase-requests.show', $pr));
        $pr->refresh();

        $this->assertEquals('rejected', $pr->status);
        $this->assertEquals('Existing inventory at Hebbal yard has 250 bags available. Reduce indent to 50 bags.', $pr->rejection_reason);

        // Requester modifies and resubmits
        $resubmitResponse = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('purchase-requests.update', $pr), [
                'project_id' => $this->project->id,
                'site_id' => $this->siteA->id,
                'required_by_date' => Carbon::today()->addDays(5)->toDateString(),
                'priority' => 'normal',
                'reason' => 'Adjusted quantity as instructed by PM: 50 bags only.',
                'action' => 'submit',
                'items' => [
                    [
                        'material_id' => $this->cementMaterial->id,
                        'quantity_requested' => 50.000,
                        'specifications' => 'Top up bags',
                        'estimated_rate' => 380.00,
                    ],
                ],
            ]);

        $resubmitResponse->assertRedirect(route('purchase-requests.show', $pr));
        $pr->refresh();

        $this->assertEquals('pending', $pr->status);
        $this->assertNull($pr->rejection_reason); // Cleared upon resubmission
        $this->assertEquals(50.000, (float) $pr->items->first()->quantity_requested);
    }

    public function test_unapproved_requests_cannot_be_converted_to_purchase_order(): void
    {
        $draftPr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-UNAPP',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'required_by_date' => Carbon::today()->addDays(5),
            'status' => 'draft',
            'priority' => 'normal',
            'reason' => 'Draft unapproved',
            'requested_by' => $this->siteSupervisor->id,
        ]);

        $response = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('purchase-requests.convert', $draftPr));

        $response->assertRedirect(route('purchase-requests.show', $draftPr));
        $response->assertSessionHasErrors(['status']);
    }

    public function test_unauthorized_user_cannot_convert_purchase_order(): void
    {
        $approvedPr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-APPTOPO',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(5),
            'status' => 'approved',
            'priority' => 'normal',
            'reason' => 'Ready for conversion',
            'requested_by' => $this->siteSupervisor->id,
            'approved_by' => $this->projectManager->id,
            'approved_at' => Carbon::now(),
        ]);

        // Site supervisor has no procurement rights
        $response = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('purchase-requests.convert', $approvedPr));

        $response->assertForbidden();
    }

    public function test_procurement_can_convert_approved_request_with_correct_po_linkage_and_gst(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-CONV01',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(7),
            'status' => 'approved',
            'priority' => 'urgent',
            'reason' => 'TMT steel bars for pier casting',
            'requested_by' => $this->siteSupervisor->id,
            'approved_by' => $this->projectManager->id,
            'approved_at' => Carbon::now(),
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->steelMaterial->id,
            'specifications' => 'Fe 550D TMT Rebars',
            'quantity_requested' => 10.000,
            'quantity_ordered' => 0.000,
            'estimated_rate' => 64000.00,
        ]);

        $poPayload = [
            'supplier_id' => $this->supplier->id,
            'po_date' => Carbon::today()->toDateString(),
            'delivery_due_date' => Carbon::today()->addDays(5)->toDateString(),
            'terms_and_conditions' => 'Payment within 30 days of physical GRN inspection',
            'items' => [
                [
                    'pr_item_id' => $item->id,
                    'quantity_to_order' => 10.000,
                    'unit_price' => 63500.00,
                    'tax_percent' => 18.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.convert.store', $pr), $poPayload);

        $po = PurchaseOrder::where('purchase_request_id', $pr->id)->first();
        $this->assertNotNull($po);
        $response->assertRedirect(route('procurement.show', $po));

        // Total checks: Subtotal = 10 * 63500 = 6,35,000. Tax 18% = 1,14,300. Total = 7,49,300.
        $this->assertEquals(635000.00, (float) $po->subtotal);
        $this->assertEquals(114300.00, (float) $po->tax_amount);
        $this->assertEquals(749300.00, (float) $po->total_amount);
        $this->assertEquals('approved', $po->status);

        // Line item linkage
        $poItem = $po->items->first();
        $this->assertNotNull($poItem);
        $this->assertEquals($item->id, $poItem->purchase_request_item_id);
        $this->assertEquals(10.000, (float) $poItem->quantity_ordered);

        // PR line and PR status updated
        $item->refresh();
        $this->assertEquals(10.000, (float) $item->quantity_ordered);
        $this->assertEquals(0.000, (float) $item->remainingQuantity());

        $pr->refresh();
        $this->assertEquals('po_created', $pr->status);

        // Traceable relationship works both ways
        $this->assertEquals($pr->id, $po->purchaseRequest->id);
        $this->assertTrue($pr->purchaseOrders->contains($po));
    }

    public function test_partial_conversions_track_remaining_and_prevent_overordering(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-PARTIAL',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(10),
            'status' => 'approved',
            'priority' => 'normal',
            'reason' => 'Large batch of cement for foundation and columns',
            'requested_by' => $this->siteSupervisor->id,
            'approved_by' => $this->projectManager->id,
            'approved_at' => Carbon::now(),
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->cementMaterial->id,
            'quantity_requested' => 1000.000,
            'quantity_ordered' => 0.000,
            'estimated_rate' => 380.00,
        ]);

        // Attempting to order 1500 bags (more than 1000 requested)
        $overOrderResponse = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.convert.store', $pr), [
                'supplier_id' => $this->supplier->id,
                'po_date' => Carbon::today()->toDateString(),
                'items' => [
                    [
                        'pr_item_id' => $item->id,
                        'quantity_to_order' => 1500.000,
                        'unit_price' => 375.00,
                        'tax_percent' => 18.00,
                    ],
                ],
            ]);

        $overOrderResponse->assertSessionHasErrors(['items']);

        // First partial conversion: Order 400 bags
        $batch1Response = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.convert.store', $pr), [
                'supplier_id' => $this->supplier->id,
                'po_date' => Carbon::today()->toDateString(),
                'items' => [
                    [
                        'pr_item_id' => $item->id,
                        'quantity_to_order' => 400.000,
                        'unit_price' => 375.00,
                        'tax_percent' => 18.00,
                    ],
                ],
            ]);

        $batch1Response->assertRedirect();
        $pr->refresh();
        $item->refresh();

        $this->assertEquals('partially_converted', $pr->status);
        $this->assertEquals(400.000, (float) $item->quantity_ordered);
        $this->assertEquals(600.000, (float) $item->remainingQuantity());

        // Second conversion: Order remaining 600 bags
        $batch2Response = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.convert.store', $pr), [
                'supplier_id' => $this->supplier->id,
                'po_date' => Carbon::today()->toDateString(),
                'items' => [
                    [
                        'pr_item_id' => $item->id,
                        'quantity_to_order' => 600.000,
                        'unit_price' => 375.00,
                        'tax_percent' => 18.00,
                    ],
                ],
            ]);

        $batch2Response->assertRedirect();
        $pr->refresh();
        $item->refresh();

        $this->assertEquals('po_created', $pr->status);
        $this->assertEquals(1000.000, (float) $item->quantity_ordered);
        $this->assertEquals(0.000, (float) $item->remainingQuantity());
        $this->assertEquals(0, $pr->remainingItemsCount());

        // Now that it is fully converted, trying to open conversion screen again redirects
        $exhaustedResponse = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('purchase-requests.convert', $pr));

        $exhaustedResponse->assertRedirect(route('purchase-requests.show', $pr));
        $exhaustedResponse->assertSessionHasErrors(['status']);
    }

    public function test_cross_company_access_is_strictly_blocked(): void
    {
        $prCompanyA = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-ALPHA',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'required_by_date' => Carbon::today()->addDays(3),
            'status' => 'draft',
            'priority' => 'normal',
            'reason' => 'Confidential company A indent',
            'requested_by' => $this->siteSupervisor->id,
        ]);

        // Company B user trying to view PR from Company A
        $response = $this->actingAs($this->companyBUser)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->get(route('purchase-requests.show', $prCompanyA));

        // Company scope causes 404 (model not found for current company)
        $response->assertNotFound();

        // Company B user trying to edit or approve PR from Company A
        $editResponse = $this->actingAs($this->companyBUser)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->get(route('purchase-requests.edit', $prCompanyA));
        $editResponse->assertNotFound();

        $approveResponse = $this->actingAs($this->companyBUser)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->post(route('purchase-requests.approve', $prCompanyA));
        $approveResponse->assertNotFound();
    }

    public function test_converted_po_can_be_received_via_goods_receipt(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-GRNTEST',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(5),
            'status' => 'approved',
            'priority' => 'normal',
            'reason' => 'GRN Integration Verification',
            'requested_by' => $this->siteSupervisor->id,
            'approved_by' => $this->projectManager->id,
            'approved_at' => Carbon::now(),
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->cementMaterial->id,
            'quantity_requested' => 100.000,
            'quantity_ordered' => 0.000,
        ]);

        // Convert to PO
        $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.convert.store', $pr), [
                'supplier_id' => $this->supplier->id,
                'po_date' => Carbon::today()->toDateString(),
                'items' => [
                    [
                        'pr_item_id' => $item->id,
                        'quantity_to_order' => 100.000,
                        'unit_price' => 380.00,
                        'tax_percent' => 18.00,
                    ],
                ],
            ]);

        $po = PurchaseOrder::where('purchase_request_id', $pr->id)->first();
        $this->assertNotNull($po);

        // Record GRN on this PO
        $grnResponse = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('procurement.receive.store', $po), [
                'warehouse_id' => $this->warehouse->id,
                'receipt_date' => Carbon::today()->toDateString(),
                'delivery_challan_number' => 'DC-TATA-7788',
                'vehicle_number' => 'KA-01-AB-1234',
                'items' => [
                    [
                        'item_id' => $po->items->first()->id,
                        'quantity_received' => 100.000,
                        'quantity_accepted' => 100.000,
                        'quantity_rejected' => 0.000,
                    ],
                ],
            ]);

        $grnResponse->assertRedirect();

        // Check stock ledger received items
        $ledger = StockLedgerEntry::where('material_id', $this->cementMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals('in', $ledger->direction);
        $this->assertEquals(100.000, (float) $ledger->quantity);
    }

    public function test_unauthorized_user_cannot_edit_or_cancel_another_users_draft(): void
    {
        $draftPr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-SUP1-DRAFT',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'required_by_date' => Carbon::today()->addDays(5),
            'status' => 'draft',
            'priority' => 'normal',
            'reason' => 'Created by Supervisor 1',
            'requested_by' => $this->siteSupervisor->id,
        ]);

        // Supervisor 2 tries to edit Supervisor 1's draft -> 403
        $editResponse = $this->actingAs($this->siteSupervisor2)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('purchase-requests.edit', $draftPr));
        $editResponse->assertForbidden();

        // Supervisor 2 tries to cancel Supervisor 1's draft -> 403
        $cancelResponse = $this->actingAs($this->siteSupervisor2)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.cancel', $draftPr));
        $cancelResponse->assertForbidden();

        // Supervisor 1 CAN cancel their own draft
        $selfCancelResponse = $this->actingAs($this->siteSupervisor)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.cancel', $draftPr));
        $selfCancelResponse->assertRedirect(route('purchase-requests.index'));
        $this->assertEquals('cancelled', $draftPr->fresh()->status);
    }

    public function test_commercial_supplier_invoice_can_be_logged_against_converted_purchase_order(): void
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'pr_number' => 'PR-2026-INV01',
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'request_date' => Carbon::today(),
            'submitted_at' => Carbon::now(),
            'required_by_date' => Carbon::today()->addDays(5),
            'status' => 'approved',
            'priority' => 'normal',
            'reason' => 'Ready for invoice test',
            'requested_by' => $this->siteSupervisor->id,
            'approved_by' => $this->projectManager->id,
            'approved_at' => Carbon::now(),
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $this->cementMaterial->id,
            'quantity_requested' => 50.000,
            'quantity_ordered' => 0.000,
        ]);

        // Convert to PO
        $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('purchase-requests.convert.store', $pr), [
                'supplier_id' => $this->supplier->id,
                'po_date' => Carbon::today()->toDateString(),
                'items' => [
                    [
                        'pr_item_id' => $item->id,
                        'quantity_to_order' => 50.000,
                        'unit_price' => 380.00,
                        'tax_percent' => 18.00,
                    ],
                ],
            ]);

        $po = PurchaseOrder::where('purchase_request_id', $pr->id)->first();
        $this->assertNotNull($po);

        // Record Supplier Invoice against converted PO
        $invResponse = $this->actingAs($this->procurementOfficer)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('procurement.invoice.store', $po), [
                'invoice_number' => 'INV-TATA-9901',
                'invoice_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::today()->addDays(30)->toDateString(),
                'subtotal' => 19000.00,
                'tax_amount' => 3420.00,
            ]);

        $invResponse->assertRedirect(route('procurement.show', $po));

        $invoice = SupplierInvoice::where('invoice_number', 'INV-TATA-9901')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($po->id, $invoice->purchase_order_id);
        $this->assertEquals(22420.00, (float) $invoice->total_amount);
        $this->assertEquals('pending_approval', $invoice->status);
    }
}
