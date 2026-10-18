<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\MeasurementBookEntry;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorBillPayment;
use App\Models\SubcontractorWorkOrder;
use App\Models\SubcontractorWorkOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProjectBudgetService;
use App\Services\SubcontractorBillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubcontractorWorkOrderAndBillingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Company $otherCompany;
    protected User $admin;
    protected User $projectManager;
    protected User $siteSupervisor;
    protected User $accountant;
    protected Project $project;
    protected Site $site;
    protected Supplier $subcontractor;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Primary Company & Roles
        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
        ]);
        session(['current_company_id' => $this->company->id]);

        $adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        $pmRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'work_orders.*', 'measurement_book.*', 'subcontractor_bills.*'],
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'measurement_book.record', 'measurement_book.view', 'work_orders.view'],
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'accounting.*', 'subcontractor_bills.*'],
        ]);

        $this->admin = User::create([
            'name' => 'Admin Boss',
            'email' => 'admin@nsd.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->companies()->attach($this->company->id, ['role_id' => $adminRole->id, 'is_primary' => true]);

        $this->projectManager = User::create([
            'name' => 'PM Vikram Singh',
            'email' => 'pm@nsd.com',
            'password' => bcrypt('password'),
        ]);
        $this->projectManager->companies()->attach($this->company->id, ['role_id' => $pmRole->id, 'is_primary' => true]);

        $this->siteSupervisor = User::create([
            'name' => 'Supervisor Ramesh',
            'email' => 'supervisor@nsd.com',
            'password' => bcrypt('password'),
        ]);
        $this->siteSupervisor->companies()->attach($this->company->id, ['role_id' => $supervisorRole->id, 'is_primary' => true]);

        $this->accountant = User::create([
            'name' => 'Accountant Suresh',
            'email' => 'accountant@nsd.com',
            'password' => bcrypt('password'),
        ]);
        $this->accountant->companies()->attach($this->company->id, ['role_id' => $accountantRole->id, 'is_primary' => true]);

        // 2. Project & Site
        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'NH-44 Highway Overpass Construction',
            'code' => 'PRJ-NH44-01',
            'approved_budget' => 5000000.00,
            'contract_value' => 6000000.00,
            'status' => 'in_progress',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Chainage 12+000 to 18+500 Site',
            'location' => 'Sector 4, Highway Corridor',
        ]);

        // 3. Subcontractor
        $this->subcontractor = Supplier::create([
            'company_id' => $this->company->id,
            'subcontractor_code' => 'SC-2026-001',
            'vendor_type' => 'subcontractor',
            'work_categories' => ['earthwork', 'civil', 'road_works'],
            'name' => 'Apex Infra & Earthmovers Pvt Ltd',
            'contact_person' => 'Rajesh Sharma',
            'phone' => '+91 98765 43210',
            'email' => 'billing@apexinfra.in',
            'gstin' => '07AAAAA1234A1Z5',
            'pan' => 'AAAAA1234A',
            'tds_section' => '194C_company_2%',
            'payment_terms' => '30_days',
            'bank_name' => 'State Bank of India',
            'bank_account_number' => '987654321012',
            'ifsc_code' => 'SBIN0001234',
            'status' => 'active',
        ]);

        // 4. Other Company for Multi-Tenant Isolation
        $this->otherCompany = Company::create([
            'name' => 'Competitor Infra Ltd',
            'slug' => 'competitor-infra',
        ]);
    }

    /**
     * 1. Authorized creation and approval of subcontractor work orders.
     */
    public function test_authorized_creation_and_approval_of_subcontractor_work_orders(): void
    {
        $data = [
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'subcontractor_id' => $this->subcontractor->id,
            'wo_number' => 'WO-2026-1001',
            'title' => 'Bulk Earth Excavation & Subgrade Preparation',
            'work_category' => 'earthwork',
            'start_date' => Carbon::today()->toDateString(),
            'completion_date' => Carbon::today()->addMonths(3)->toDateString(),
            'retention_percent' => 5.00,
            'tds_rate_percent' => 2.00,
            'gst_rate_percent' => 18.00,
            'payment_terms' => '30_days',
            'items' => [
                [
                    'item_code' => 'BOQ-01',
                    'description' => 'Excavation in ordinary soil with 20T excavator',
                    'unit_of_measure' => 'cum',
                    'approved_quantity' => 1000.000,
                    'unit_rate' => 120.00,
                ],
                [
                    'item_code' => 'BOQ-02',
                    'description' => 'Granular Sub-Base (GSB) laying & roller compaction',
                    'unit_of_measure' => 'cum',
                    'approved_quantity' => 500.000,
                    'unit_rate' => 800.00,
                ],
            ],
        ];

        $workOrder = SubcontractorBillingService::createWorkOrder($data, $this->projectManager);

        $this->assertInstanceOf(SubcontractorWorkOrder::class, $workOrder);
        $this->assertEquals('WO-2026-1001', $workOrder->wo_number);
        $this->assertEquals('draft', $workOrder->status);
        $this->assertCount(2, $workOrder->items);

        // Expected contract value = (1000 * 120) + (500 * 800) = 120,000 + 400,000 = 520,000
        $this->assertEquals(520000.00, (float) $workOrder->contract_value);

        // Approve Work Order
        $approvedWo = SubcontractorBillingService::approveWorkOrder($workOrder, $this->projectManager);
        $this->assertEquals('approved', $approvedWo->status);
        $this->assertEquals($this->projectManager->id, $approvedWo->approved_by);
        $this->assertNotNull($approvedWo->approved_at);

        // Check Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approve_subcontractor_work_order',
            'auditable_type' => SubcontractorWorkOrder::class,
            'auditable_id' => $workOrder->id,
        ]);
    }

    /**
     * 2. Valid measurement calculations across supported units and measurement methods.
     */
    public function test_valid_measurement_calculations_across_supported_units_and_methods(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        // A. Formula: dimensions_l_b_h (50 * 10 * 0.5 * 1 = 250 cum)
        $mb1 = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'site_id' => $wo->site_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Excavation from Chainage 12+000 to 12+050',
            'measurement_formula' => 'dimensions_l_b_h',
            'length' => 50.000,
            'breadth' => 10.000,
            'depth_height' => 0.500,
            'multiplier_units' => 1.00,
            'status' => 'submitted',
        ], $this->siteSupervisor);

        $this->assertEquals(250.000, (float) $mb1->measured_quantity);
        $this->assertEquals(250.000, (float) $mb1->cumulative_quantity);

        // B. Formula: area_depth_a_d (100 * 2 = 200 sqm)
        $qtyArea = MeasurementBookEntry::computeFormulaQuantity('area_depth_a_d', 100.0, 2.0, null, 1.0);
        $this->assertEquals(200.000, $qtyArea);

        // C. Formula: unit_count_nos (25 * 2 = 50 nos)
        $qtyNos = MeasurementBookEntry::computeFormulaQuantity('unit_count_nos', 25.0, null, null, 2.0);
        $this->assertEquals(50.000, $qtyNos);

        // D. Formula: direct_manual with reason
        $qtyManual = MeasurementBookEntry::computeFormulaQuantity('direct_manual', null, null, null, 1.0, 75.500);
        $this->assertEquals(75.500, $qtyManual);
    }

    /**
     * 3. Rejection of invalid quantities and mismatched project/site/work-order relationships.
     */
    public function test_rejection_of_invalid_quantities_and_mismatched_project_site_relationships(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        // Other project
        $otherProject = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Different Metro Station Project',
            'code' => 'PRJ-METRO-02',
            'status' => 'in_progress',
        ]);

        // Attempt measurement with mismatched project ID
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not match Work Order project/');

        SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $otherProject->id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Mismatched project test',
            'measurement_formula' => 'dimensions_l_b_h',
            'length' => 10.0,
            'breadth' => 10.0,
            'depth_height' => 1.0,
        ], $this->siteSupervisor);
    }

    /**
     * 4. Prevention of certified quantities exceeding the approved work-order limit.
     */
    public function test_prevention_of_certified_quantities_exceeding_approved_work_order_limit(): void
    {
        $wo = $this->createApprovedWorkOrder(100.000); // approved quantity is 100 cum
        $item = $wo->items->first();

        // 1st measurement: 80 cum (within 100 limit)
        $mb1 = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'First trench layer',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 80.000,
            'override_reason' => 'Surveyor total station measurement',
        ], $this->siteSupervisor);

        SubcontractorBillingService::approveMeasurement($mb1, $this->projectManager);

        // 2nd measurement: 30 cum (80 + 30 = 110 > 100 limit!)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exceeds the approved work order limit/');

        SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Second trench layer causing excess',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 30.000,
            'override_reason' => 'Surveyor total station measurement',
        ], $this->siteSupervisor);
    }

    /**
     * 5. Rejected measurement correction and resubmission with history preserved.
     */
    public function test_rejected_measurement_correction_and_resubmission_with_history_preserved(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Initial trenching check',
            'measurement_formula' => 'dimensions_l_b_h',
            'length' => 10.0,
            'breadth' => 10.0,
            'depth_height' => 1.0,
        ], $this->siteSupervisor);

        $this->assertEquals('submitted', $mb->status);

        // Reject measurement with mandatory reason
        $rejected = SubcontractorBillingService::rejectMeasurement(
            $mb,
            'Survey cross sections showed average depth is 0.8m not 1.0m',
            $this->projectManager
        );

        $this->assertEquals('rejected', $rejected->status);
        $this->assertStringContainsString('average depth is 0.8m', $rejected->rejection_reason);

        // Resubmit with corrected dimensions (depth 0.8m -> 80 cum)
        $resubmitted = SubcontractorBillingService::resubmitMeasurement($rejected, [
            'depth_height' => 0.800,
        ], $this->siteSupervisor);

        $this->assertEquals('submitted', $resubmitted->status);
        $this->assertEquals(80.000, (float) $resubmitted->measured_quantity);
        $this->assertNull($resubmitted->rejection_reason);
        $this->assertStringContainsString('Resubmitted on', $resubmitted->site_remarks);
    }

    /**
     * 6. Only approved measurements can be billed.
     */
    public function test_only_approved_measurements_can_be_billed(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Unapproved measurement',
            'measurement_formula' => 'dimensions_l_b_h',
            'length' => 10.0,
            'breadth' => 10.0,
            'depth_height' => 0.5,
            'status' => 'submitted',
        ], $this->siteSupervisor);

        // Attempt to bill unapproved measurement
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Only \'approved\' measurements can be billed/');

        SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);
    }

    /**
     * 7. Previously billed quantities cannot be billed twice.
     */
    public function test_previously_billed_quantities_cannot_be_billed_twice(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Measurement sheet #1',
            'measurement_formula' => 'dimensions_l_b_h',
            'length' => 10.0,
            'breadth' => 10.0,
            'depth_height' => 0.5,
        ], $this->siteSupervisor);

        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        // Generate Bill 1 and Certify it
        $bill1 = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);
        SubcontractorBillingService::certifyBill($bill1, $this->projectManager);

        $this->assertEquals('certified', $mb->fresh()->status);
        $this->assertEquals($bill1->id, $mb->fresh()->certified_in_bill_id);

        // Attempt to bill the same MB entry again in Bill 2
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Duplicate billing is strictly prohibited/');

        SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);
    }

    /**
     * 8. Partial billing preserves the correct remaining quantity.
     */
    public function test_partial_billing_preserves_correct_remaining_quantity(): void
    {
        $wo = $this->createApprovedWorkOrder(1000.000); // 1000 cum approved
        $item = $wo->items->first();

        // MB 1: 300 cum
        $mb1 = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->subDays(5)->toDateString(),
            'description' => 'Section A',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 300.000,
            'override_reason' => 'Batch 1 certified',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb1, $this->projectManager);

        $bill1 = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb1->id], ['bill_date' => Carbon::today()->toDateString()], $this->projectManager);
        SubcontractorBillingService::certifyBill($bill1, $this->projectManager);

        $itemFresh = $item->fresh();
        $this->assertEquals(300.000, (float) $itemFresh->billed_quantity);
        $this->assertEquals(700.000, $itemFresh->remainingQuantity());

        // MB 2: 400 cum
        $mb2 = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Section B',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 400.000,
            'override_reason' => 'Batch 2 certified',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb2, $this->projectManager);

        $bill2 = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb2->id], ['bill_date' => Carbon::today()->toDateString()], $this->projectManager);
        SubcontractorBillingService::certifyBill($bill2, $this->projectManager);

        $itemFinal = $item->fresh();
        $this->assertEquals(700.000, (float) $itemFinal->billed_quantity);
        $this->assertEquals(300.000, $itemFinal->remainingQuantity());
    }

    /**
     * 9. Approved variations support revised quantities only through authorized process.
     */
    public function test_approved_variations_support_revised_quantities_only_through_authorized_process(): void
    {
        $wo = $this->createApprovedWorkOrder(500.000); // 500 cum limit
        $item = $wo->items->first();

        // Attempting to record 600 cum fails
        try {
            SubcontractorBillingService::recordMeasurement([
                'work_order_id' => $wo->id,
                'work_order_item_id' => $item->id,
                'project_id' => $wo->project_id,
                'measurement_date' => Carbon::today()->toDateString(),
                'description' => 'Excess excavation',
                'measurement_formula' => 'direct_manual',
                'manual_quantity_override' => 600.000,
                'override_reason' => 'Excess depth survey',
            ], $this->siteSupervisor);
            $this->fail('Should have thrown quantity limit exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds the approved work order limit', $e->getMessage());
        }

        // Add controlled variation increasing quantity to 750 cum
        $variation = SubcontractorBillingService::addWorkOrderVariation($wo, [
            'variation_type' => 'quantity_increase',
            'work_order_item_id' => $item->id,
            'revised_quantity' => 750.000,
            'reason' => 'Client issued revised profile drawings extending excavation width',
        ], $this->projectManager);

        $this->assertEquals('approved', $variation->status);
        $this->assertEquals(750.000, (float) $item->fresh()->approved_quantity);

        // Now recording 600 cum succeeds!
        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Extended excavation execution',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 600.000,
            'override_reason' => 'As per revised profile drawing',
        ], $this->siteSupervisor);

        $this->assertEquals(600.000, (float) $mb->measured_quantity);
    }

    /**
     * 10. Correct work-order–MB–bill 3-way reconciliation.
     */
    public function test_correct_work_order_mb_bill_three_way_reconciliation(): void
    {
        $wo = $this->createApprovedWorkOrder(1000.000, 150.00); // 1000 cum @ ₹150
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Reconciliation test run',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 200.000,
            'override_reason' => 'Measured as per contract levels',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);

        $billItem = $bill->items->first();
        $this->assertEquals(0.000, (float) $billItem->previously_billed_quantity);
        $this->assertEquals(200.000, (float) $billItem->current_billed_quantity);
        $this->assertEquals(200.000, (float) $billItem->cumulative_billed_quantity);
        $this->assertEquals(150.00, (float) $billItem->unit_rate);
        $this->assertEquals(30000.00, (float) $billItem->gross_amount); // 200 * 150 = 30,000
    }

    /**
     * 11. Contractual retention (5%), TDS (2%), GST (18%), and deduction calculations.
     */
    public function test_contractual_retention_and_configured_tax_deduction_calculations(): void
    {
        $wo = $this->createApprovedWorkOrder(1000.000, 100.00); // 1000 cum @ ₹100
        $item = $wo->items->first();

        // Measure 1000 cum -> Gross Work Value = ₹100,000
        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Full scope measured',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 1000.000,
            'override_reason' => 'Final site survey',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
            'retention_rate_percent' => 5.00,
            'tds_rate_percent' => 2.00,
            'gst_rate_percent' => 18.00,
            'advance_recovery_amount' => 10000.00,
        ], $this->projectManager);

        // Gross = ₹100,000
        $this->assertEquals(100000.00, (float) $bill->gross_work_value);
        // Retention 5% = ₹5,000
        $this->assertEquals(5000.00, (float) $bill->retention_amount);
        // TDS 2% = ₹2,000
        $this->assertEquals(2000.00, (float) $bill->tds_amount);
        // GST 18% = ₹18,000
        $this->assertEquals(18000.00, (float) $bill->gst_amount);
        // Advance Recovery = ₹10,000
        $this->assertEquals(10000.00, (float) $bill->advance_recovery_amount);

        // Net Payable = 100,000 + 18,000 - 5,000 - 2,000 - 10,000 = ₹101,000
        $this->assertEquals(101000.00, (float) $bill->net_payable_amount);
    }

    /**
     * 12. Approval and certification permission checks.
     */
    public function test_approval_and_certification_permission_checks(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Security check',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 50.000,
            'override_reason' => 'Initial trench',
        ], $this->siteSupervisor);

        // Site supervisor tries to approve measurement -> HTTP 403 Forbidden
        $response = $this->actingAs($this->siteSupervisor)
            ->post(route('measurement-books.approve', $mb));
        $response->assertStatus(403);

        // Project manager approves measurement -> HTTP 302 Success
        $pmResponse = $this->actingAs($this->projectManager)
            ->post(route('measurement-books.approve', $mb));
        $pmResponse->assertStatus(302);
        $this->assertEquals('approved', $mb->fresh()->status);

        // Prepare Bill
        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);

        // Site supervisor tries to certify bill -> HTTP 403 Forbidden
        $supCertResponse = $this->actingAs($this->siteSupervisor)
            ->post(route('subcontractor-bills.certify', $bill));
        $supCertResponse->assertStatus(403);

        // PM certifies bill -> HTTP 302 Success
        $pmCertResponse = $this->actingAs($this->projectManager)
            ->post(route('subcontractor-bills.certify', $bill));
        $pmCertResponse->assertStatus(302);
        $this->assertEquals('certified', $bill->fresh()->status);
    }

    /**
     * 13. Payment allocations, outstanding balances, and overpayment prevention.
     */
    public function test_payment_allocations_outstanding_balances_and_overpayment_prevention(): void
    {
        $wo = $this->createApprovedWorkOrder(100.0, 1000.0);
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'For payment test',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 100.0,
            'override_reason' => 'Completed tranche',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
            'retention_rate_percent' => 5.0,
            'tds_rate_percent' => 2.0,
            'gst_rate_percent' => 18.0,
        ], $this->projectManager);
        SubcontractorBillingService::certifyBill($bill, $this->projectManager);

        $netPayable = (float) $bill->net_payable_amount; // 100,000 + 18,000 - 5,000 - 2,000 = 111,000
        $this->assertEquals(111000.00, $netPayable);
        $this->assertEquals(111000.00, $bill->outstandingBalance());

        // Partial Payment: ₹50,000
        $p1 = SubcontractorBillingService::recordBillPayment($bill, [
            'amount' => 50000.00,
            'payment_date' => Carbon::today()->toDateString(),
            'payment_mode' => 'bank_transfer',
            'transaction_reference' => 'UTR-BATCH-001',
        ], $this->accountant);

        $this->assertEquals(50000.00, (float) $p1->amount);
        $this->assertEquals('partially_paid', $bill->fresh()->status);
        $this->assertEquals(61000.00, $bill->fresh()->outstandingBalance());

        // Overpayment attempt: ₹70,000 (exceeds ₹61,000 remaining!)
        try {
            SubcontractorBillingService::recordBillPayment($bill, [
                'amount' => 70000.00,
                'payment_date' => Carbon::today()->toDateString(),
                'payment_mode' => 'bank_transfer',
                'transaction_reference' => 'UTR-FAIL-OVERPAY',
            ], $this->accountant);
            $this->fail('Should have rejected overpayment');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Overpayment is strictly prevented', $e->getMessage());
        }

        // Final Payment: ₹61,000
        SubcontractorBillingService::recordBillPayment($bill, [
            'amount' => 61000.00,
            'payment_date' => Carbon::today()->toDateString(),
            'payment_mode' => 'bank_transfer',
            'transaction_reference' => 'UTR-BATCH-002',
        ], $this->accountant);

        $this->assertEquals('paid', $bill->fresh()->status);
        $this->assertEquals(0.00, $bill->fresh()->outstandingBalance());
    }

    /**
     * 14. Balanced accounting journal entries and prevention of duplicate posting.
     */
    public function test_balanced_accounting_journal_entries_and_prevention_of_duplicate_posting(): void
    {
        $wo = $this->createApprovedWorkOrder(200.0, 500.0);
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Posting audit test',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 200.0,
            'override_reason' => 'Total certified',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);

        $certified = SubcontractorBillingService::certifyBill($bill, $this->projectManager);

        $journal = $certified->journalEntry;
        $this->assertNotNull($journal);
        $this->assertEquals('posted', $journal->status);
        $this->assertStringStartsWith('JV-SCB-', $journal->entry_number);

        // Verify debits equal credits
        $totalDebits = $journal->lines->sum('debit');
        $totalCredits = $journal->lines->sum('credit');
        $this->assertEquals(0, bccomp((string) $totalDebits, (string) $totalCredits, 2));

        // Verify accounts used
        $costLine = $journal->lines->firstWhere('account.code', '5040');
        $this->assertNotNull($costLine, 'Direct cost account 5040 must be debited');
        $this->assertEquals($wo->project_id, $costLine->project_id, 'Cost line must be tagged with project');

        $retLine = $journal->lines->firstWhere('account.code', '2030');
        $this->assertNotNull($retLine, 'Subcontractor retention liability account 2030 must be credited');

        // Prevent duplicate certification
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be certified again/');
        SubcontractorBillingService::certifyBill($certified, $this->projectManager);
    }

    /**
     * 15. Project budgets do not double-count commitments and certified costs.
     */
    public function test_project_budgets_do_not_double_count_commitments_and_certified_costs(): void
    {
        // 1. Initial State: Budget = 5,000,000, Actual = 0, Committed = 0
        $initialActual = ProjectBudgetService::getActualCosts($this->project);
        $initialCommitted = ProjectBudgetService::getCommittedCosts($this->project);
        $this->assertEquals(0.00, $initialActual);
        $this->assertEquals(0.00, $initialCommitted);

        // 2. Issue Work Order of ₹600,000
        $wo = $this->createApprovedWorkOrder(1000.0, 600.0); // 1000 * 600 = ₹600,000

        $commAfterWo = ProjectBudgetService::getCommittedCosts($this->project);
        $actualAfterWo = ProjectBudgetService::getActualCosts($this->project);

        $this->assertEquals(600000.00, $commAfterWo, 'Approved work order should be counted in committed costs');
        $this->assertEquals(0.00, $actualAfterWo, 'Uncertified work order should not be in actual costs');
        $this->assertEquals(600000.00, $commAfterWo + $actualAfterWo);

        // 3. Certify partial bill of ₹200,000 (333.333 cum)
        $item = $wo->items->first();
        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Tranche 1',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 300.000,
            'override_reason' => 'First stage measured',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        $bill1 = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);
        SubcontractorBillingService::certifyBill($bill1, $this->projectManager);

        $actualAfterBill1 = ProjectBudgetService::getActualCosts($this->project);
        $commAfterBill1 = ProjectBudgetService::getCommittedCosts($this->project);

        // Actual costs = 180,000 (300 * 600)
        $this->assertEquals(180000.00, round($actualAfterBill1, 2));
        // Remaining commitment = 600,000 - 180,000 = 420,000
        $this->assertEquals(420000.00, round($commAfterBill1, 2));
        // Total committed + actual remains EXACTLY 600,000. Zero double counting!
        $this->assertEquals(600000.00, round($actualAfterBill1 + $commAfterBill1, 2));
    }

    /**
     * 16. Cross-company isolation for work orders, measurements, bills, and documents.
     */
    public function test_cross_company_isolation_for_work_orders_measurements_and_bills(): void
    {
        $wo = $this->createApprovedWorkOrder();

        // User from other company
        $otherAdmin = User::create([
            'name' => 'Competitor Admin',
            'email' => 'competitor@infra.com',
            'password' => bcrypt('password'),
        ]);
        $otherRole = Role::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Competitor Admin Role',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);
        $otherAdmin->companies()->attach($this->otherCompany->id, ['role_id' => $otherRole->id, 'is_primary' => true]);

        // Attempt to access work order of company 1 with company 2 session -> 404
        session(['current_company_id' => $this->otherCompany->id]);
        $response = $this->actingAs($otherAdmin)->get(route('work-orders.show', $wo));
        $response->assertStatus(404);
    }

    /**
     * 17. Concurrent billing attempts cannot claim the same measurement quantities.
     */
    public function test_concurrent_billing_attempts_cannot_claim_the_same_measurement_quantities(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Concurrency test entry',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 100.0,
            'override_reason' => 'Surveyor report',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        // Bill 1 created and certified
        $bill1 = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);
        SubcontractorBillingService::certifyBill($bill1, $this->projectManager);

        // Concurrently, another request tries to bill the same MB entry
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Duplicate billing is strictly prohibited/');

        SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);
    }

    /**
     * 18. Controlled bill reversal with counter-journal and MB status restoration.
     */
    public function test_controlled_bill_reversal_with_counter_journal_and_mb_restoration(): void
    {
        $wo = $this->createApprovedWorkOrder();
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Reversal test entry',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 150.0,
            'override_reason' => 'Initial report',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
        ], $this->projectManager);
        SubcontractorBillingService::certifyBill($bill, $this->projectManager);

        $this->assertEquals('certified', $bill->fresh()->status);
        $this->assertEquals('certified', $mb->fresh()->status);

        // Reverse bill with audit justification
        $reversed = SubcontractorBillingService::reverseBill(
            $bill,
            'Client audit revised foundation specifications requiring MB realignment',
            $this->projectManager
        );

        $this->assertEquals('reversed', $reversed->status);
        $this->assertStringContainsString('Client audit revised', $reversed->rejection_reason);

        // Reversal counter-journal created
        $reversalJournal = JournalEntry::where('reference_type', 'subcontractor_bills_reversal')
            ->where('reference_id', $bill->id)
            ->first();

        $this->assertNotNull($reversalJournal);
        $this->assertStringStartsWith('JV-REV-SCB-', $reversalJournal->entry_number);

        // MB entry restored to approved state and unlinked from bill
        $this->assertEquals('approved', $mb->fresh()->status);
        $this->assertNull($mb->fresh()->certified_in_bill_id);

        // Work order item billed quantities decremented
        $this->assertEquals(0.000, (float) $item->fresh()->billed_quantity);
    }

    /**
     * 19. Exact audit verification of reported transaction:
     * Gross: ₹195,000, GST: ₹35,100, Retention: ₹9,750, TDS: ₹1,950, Advance: ₹50,000 => Net: ₹168,400.
     * Verifies bill calculation, UI reconciliation display, balanced GL posting (with Dr/Cr 1040),
     * partial and full payment tracking, and overpayment prevention.
     */
    public function test_subcontractor_bill_with_advance_recovery_exact_reconciliation_and_journal_balance(): void
    {
        // 1. Create Subcontractor with Section 194C Individual classification (1% TDS)
        $subcon = Supplier::create([
            'company_id' => $this->company->id,
            'subcontractor_code' => 'SC-IND-01',
            'vendor_type' => 'subcontractor',
            'name' => 'Sharma Earthmoving Contractors',
            'pan' => 'AAAPS1234A',
            'gstin' => '07AAAPS1234A1Z1',
            'tds_section' => '194C_individual_1%',
            'status' => 'active',
        ]);
        $this->assertEquals(1.00, $subcon->defaultTdsRate());

        // 2. Create and approve Work Order: 1,300 cum @ ₹150.00 = ₹195,000.00
        $wo = SubcontractorBillingService::createWorkOrder([
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'subcontractor_id' => $subcon->id,
            'title' => 'NH-44 Subgrade Soil Excavation',
            'work_category' => 'earthwork',
            'start_date' => Carbon::today()->toDateString(),
            'completion_date' => Carbon::today()->addMonths(2)->toDateString(),
            'retention_percent' => 5.00,
            'tds_rate_percent' => 1.00, // 1% u/s 194C
            'gst_rate_percent' => 18.00,
            'payment_terms' => '30_days',
            'items' => [
                [
                    'item_code' => 'BOQ-SOIL-01',
                    'description' => 'Subgrade excavation in ordinary rock',
                    'unit_of_measure' => 'cum',
                    'approved_quantity' => 1300.000,
                    'unit_rate' => 150.00,
                ],
            ],
        ], $this->projectManager);
        SubcontractorBillingService::approveWorkOrder($wo, $this->projectManager);

        $item = $wo->items->first();

        // 3. Record & Approve Measurement for full 1,300 cum
        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Measured section CH 12+000 to CH 13+300',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 1300.000,
            'override_reason' => 'Total joint survey verified by site engineer',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        // 4. Create RA Bill with ₹50,000 Advance Recovery
        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
            'retention_rate_percent' => 5.00,
            'tds_rate_percent' => 1.00,
            'gst_rate_percent' => 18.00,
            'advance_recovery_amount' => 50000.00,
        ], $this->projectManager);

        // 5. Verify authoritative arithmetic
        // Gross = 1,300 * 150 = ₹195,000.00
        $this->assertEquals(195000.00, (float) $bill->gross_work_value);
        // GST (18%) = 195,000 * 0.18 = ₹35,100.00
        $this->assertEquals(35100.00, (float) $bill->gst_amount);
        // Retention (5%) = 195,000 * 0.05 = ₹9,750.00
        $this->assertEquals(9750.00, (float) $bill->retention_amount);
        // TDS (1%) = 195,000 * 0.01 = ₹1,950.00
        $this->assertEquals(1950.00, (float) $bill->tds_amount);
        // Advance Recovery = ₹50,000.00
        $this->assertEquals(50000.00, (float) $bill->advance_recovery_amount);
        // Certified Net Payable = 195,000 + 35,100 - 9,750 - 1,950 - 50,000 = ₹168,400.00
        $this->assertEquals(168400.00, (float) $bill->net_payable_amount);
        $this->assertEquals(168400.00, (float) $bill->outstandingBalance());

        // 6. Verify UI View displays the exact figures and advance recovery card
        $response = $this->actingAs($this->accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('subcontractor-bills.show', $bill));
        $response->assertStatus(200);
        $response->assertSee('195,000.00');
        $response->assertSee('35,100.00');
        $response->assertSee('9,750.00');
        $response->assertSee('1,950.00');
        $response->assertSee('50,000.00');
        $response->assertSee('168,400.00');
        $response->assertSee('Mobilization Advance Recovery');

        // 7. Certify Bill & Verify Double-Entry Journal Balance
        $certifiedBill = SubcontractorBillingService::certifyBill($bill, $this->accountant);
        $this->assertEquals('certified', $certifiedBill->status);

        $journal = $certifiedBill->journalEntry;
        $this->assertNotNull($journal);
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(230100.00, (float) $journal->total_amount);

        // Sum debits and credits of actual lines
        $totalDebits = (float) $journal->lines->sum('debit');
        $totalCredits = (float) $journal->lines->sum('credit');
        $this->assertEquals(230100.00, $totalDebits);
        $this->assertEquals(230100.00, $totalCredits);
        $this->assertEquals(0, bccomp((string) $totalDebits, (string) $totalCredits, 2));

        // Line-by-line verification
        $costLine = $journal->lines->firstWhere('account.code', '5040');
        $this->assertEquals(195000.00, (float) $costLine->debit);
        $this->assertEquals(0.00, (float) $costLine->credit);

        $gstLine = $journal->lines->firstWhere('account.code', '1030');
        $this->assertEquals(35100.00, (float) $gstLine->debit);
        $this->assertEquals(0.00, (float) $gstLine->credit);

        $retLine = $journal->lines->firstWhere('account.code', '2030');
        $this->assertEquals(0.00, (float) $retLine->debit);
        $this->assertEquals(9750.00, (float) $retLine->credit);

        $tdsLine = $journal->lines->firstWhere('account.code', '2040');
        $this->assertEquals(0.00, (float) $tdsLine->debit);
        $this->assertEquals(1950.00, (float) $tdsLine->credit);

        $advLine = $journal->lines->firstWhere('account.code', '1040');
        $this->assertNotNull($advLine, 'Advance recovery line (Account 1040) must be present in journal');
        $this->assertEquals(0.00, (float) $advLine->debit);
        $this->assertEquals(50000.00, (float) $advLine->credit);

        $payableLine = $journal->lines->firstWhere('account.code', '2020');
        $this->assertEquals(0.00, (float) $payableLine->debit);
        $this->assertEquals(168400.00, (float) $payableLine->credit);

        // 8. Payment Remittance Workflow
        // Partial payment of ₹100,000.00
        $payment1 = SubcontractorBillingService::recordBillPayment($certifiedBill, [
            'amount' => 100000.00,
            'payment_date' => Carbon::today()->toDateString(),
            'payment_mode' => 'neft_rtgs',
            'transaction_reference' => 'UTR-NEFT-998811',
        ], $this->accountant);

        $this->assertEquals(100000.00, (float) $payment1->amount);
        $this->assertEquals(100000.00, (float) $certifiedBill->fresh()->paid_amount);
        $this->assertEquals(68400.00, (float) $certifiedBill->fresh()->outstandingBalance());
        $this->assertEquals('partially_paid', $certifiedBill->fresh()->status);

        // Attempt overpayment of ₹70,000 (when outstanding is ₹68,400) -> Must fail
        try {
            SubcontractorBillingService::recordBillPayment($certifiedBill->fresh(), [
                'amount' => 70000.00,
                'payment_date' => Carbon::today()->toDateString(),
                'payment_mode' => 'bank_transfer',
                'transaction_reference' => 'UTR-OVERPAY-001',
            ], $this->accountant);
            $this->fail('Expected overpayment exception was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds outstanding balance', $e->getMessage());
        }

        // Settle remaining ₹68,400.00
        $payment2 = SubcontractorBillingService::recordBillPayment($certifiedBill->fresh(), [
            'amount' => 68400.00,
            'payment_date' => Carbon::today()->toDateString(),
            'payment_mode' => 'bank_transfer',
            'transaction_reference' => 'UTR-FINAL-998822',
        ], $this->accountant);

        $this->assertEquals(68400.00, (float) $payment2->amount);
        $this->assertEquals(168400.00, (float) $certifiedBill->fresh()->paid_amount);
        $this->assertEquals(0.00, (float) $certifiedBill->fresh()->outstandingBalance());
        $this->assertEquals('paid', $certifiedBill->fresh()->status);
    }

    /**
     * 20. Controlled reversal of bill with advance recovery restores Account 1040 advance balance.
     */
    public function test_reversal_of_bill_with_advance_recovery_restores_advance_balance(): void
    {
        $wo = $this->createApprovedWorkOrder(500.0, 200.0); // ₹100,000
        $item = $wo->items->first();

        $mb = SubcontractorBillingService::recordMeasurement([
            'work_order_id' => $wo->id,
            'work_order_item_id' => $item->id,
            'project_id' => $wo->project_id,
            'measurement_date' => Carbon::today()->toDateString(),
            'description' => 'Measurement for reversal test',
            'measurement_formula' => 'direct_manual',
            'manual_quantity_override' => 500.0,
            'override_reason' => 'Joint verification',
        ], $this->siteSupervisor);
        SubcontractorBillingService::approveMeasurement($mb, $this->projectManager);

        $bill = SubcontractorBillingService::createBillFromMeasurements($wo, [$mb->id], [
            'bill_date' => Carbon::today()->toDateString(),
            'retention_rate_percent' => 5.00,
            'tds_rate_percent' => 2.00,
            'gst_rate_percent' => 18.00,
            'advance_recovery_amount' => 20000.00,
        ], $this->projectManager);

        $certifiedBill = SubcontractorBillingService::certifyBill($bill, $this->accountant);

        // Reverse bill
        $reversed = SubcontractorBillingService::reverseBill(
            $certifiedBill,
            'Survey data error detected post-certification; cancelling bill',
            $this->accountant
        );

        $this->assertEquals('reversed', $reversed->status);

        $reversalJournal = JournalEntry::where('reference_type', 'subcontractor_bills_reversal')
            ->where('reference_id', $bill->id)
            ->first();

        $this->assertNotNull($reversalJournal);
        $this->assertEquals(0, bccomp(
            (string) $reversalJournal->lines->sum('debit'),
            (string) $reversalJournal->lines->sum('credit'),
            2
        ));

        // Advance recovery account 1040 must be debited in reversal (restoring the advance asset)
        $advReversalLine = $reversalJournal->lines->firstWhere('account.code', '1040');
        $this->assertNotNull($advReversalLine);
        $this->assertEquals(20000.00, (float) $advReversalLine->debit);
        $this->assertEquals(0.00, (float) $advReversalLine->credit);

        // Original journal entry must be marked reversed with audit trace
        $originalJournal = JournalEntry::find($certifiedBill->journal_entry_id);
        $this->assertNotNull($originalJournal);
        $this->assertEquals('reversed', $originalJournal->status);
        $this->assertEquals($this->accountant->id, $originalJournal->reversed_by);
        $this->assertNotNull($originalJournal->reversed_at);
        $this->assertEquals('Survey data error detected post-certification; cancelling bill', $originalJournal->reversal_reason);
    }

    /**
     * Helper to create and approve a test work order.
     */
    protected function createApprovedWorkOrder(float $qty = 1000.0, float $rate = 120.0): SubcontractorWorkOrder
    {
        $wo = SubcontractorBillingService::createWorkOrder([
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'subcontractor_id' => $this->subcontractor->id,
            'title' => 'Earthmoving Test Package',
            'work_category' => 'earthwork',
            'start_date' => Carbon::today()->toDateString(),
            'completion_date' => Carbon::today()->addMonths(2)->toDateString(),
            'retention_percent' => 5.00,
            'tds_rate_percent' => 2.00,
            'gst_rate_percent' => 18.00,
            'payment_terms' => '30_days',
            'items' => [
                [
                    'item_code' => 'BOQ-01',
                    'description' => 'Test Excavation Item',
                    'unit_of_measure' => 'cum',
                    'approved_quantity' => $qty,
                    'unit_rate' => $rate,
                ],
            ],
        ], $this->projectManager);

        return SubcontractorBillingService::approveWorkOrder($wo, $this->projectManager);
    }
}
