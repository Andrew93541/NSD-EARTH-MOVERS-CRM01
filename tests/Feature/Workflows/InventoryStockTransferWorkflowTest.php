<?php

namespace Tests\Feature\Workflows;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_inventory_receipt_transfer_issue_and_cumulative_return_workflow(): void
    {
        // 1. Setup Company
        $company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
            'currency' => 'INR',
            'is_active' => true,
        ]);
        session(['current_company_id' => $company->id]);

        // Roles
        $storekeeperRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Storekeeper',
            'slug' => 'storekeeper',
            'permissions' => ['inventory.*', 'materials.*'],
            'is_system' => true,
        ]);

        $pmRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.*', 'sites.*', 'inventory.*', 'materials.*'],
            'is_system' => true,
        ]);

        $storekeeper = User::factory()->create(['name' => 'Govind Storekeeper']);
        $company->users()->attach($storekeeper->id, ['role_id' => $storekeeperRole->id, 'status' => 'active', 'is_primary' => true]);

        $pm = User::factory()->create(['name' => 'Vikram PM']);
        $company->users()->attach($pm->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => false]);

        // Warehouses
        $centralWarehouse = Warehouse::create([
            'company_id' => $company->id,
            'name' => 'Central Peenya Yard',
            'type' => 'central',
            'is_active' => true,
        ]);

        $siteStore = Warehouse::create([
            'company_id' => $company->id,
            'name' => 'Airport Express Site Store',
            'type' => 'site_store',
            'is_active' => true,
        ]);

        // Material
        $material = Material::create([
            'company_id' => $company->id,
            'code' => 'MAT-REBAR-16',
            'name' => '16mm TMT Steel Rebar Fe550D',
            'unit_of_measure' => 'MT',
            'standard_cost' => 58000.00,
            'reorder_level' => 10.00,
            'is_active' => true,
        ]);

        // Project & Site
        $project = Project::create([
            'company_id' => $company->id,
            'code' => 'PRJ-2026-AIRPORT',
            'name' => 'Airport Elevated Metro Corridor',
            'manager_id' => $pm->id,
            'status' => 'on_track',
            'approved_budget' => 80000000.00,
        ]);

        $site = Site::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Pier 45 Construction Site',
            'code' => 'STE-P45',
            'supervisor_id' => $pm->id,
        ]);

        // 2. Initial stock receipt into Central Warehouse: 100 MT
        StockLedgerEntry::create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'warehouse_id' => $centralWarehouse->id,
            'entry_date' => '2026-10-01',
            'transaction_type' => 'goods_receipt',
            'reference_type' => 'goods_receipts',
            'reference_id' => 1,
            'quantity' => 100.00,
            'direction' => 'in',
            'unit_rate' => 58000.00,
            'total_amount' => 5800000.00,
            'balance_quantity' => 100.00,
            'created_by' => $storekeeper->id,
        ]);

        // 3. Transfer 40 MT from Central to Site Store
        $transferResponse = $this->actingAs($storekeeper)->post(route('inventory.transfer.store'), [
            'from_warehouse_id' => $centralWarehouse->id,
            'to_warehouse_id' => $siteStore->id,
            'transfer_date' => '2026-10-02',
            'material_id' => $material->id,
            'quantity' => 40.00,
            'vehicle_number' => 'KA-01-AB-1234',
            'driver_name' => 'Ravi Kumar',
            'notes' => 'Transfer for pier foundation casting',
        ]);

        $transferResponse->assertRedirect(route('inventory.ledger'));
        $this->assertDatabaseHas('stock_transfers', [
            'company_id' => $company->id,
            'from_warehouse_id' => $centralWarehouse->id,
            'to_warehouse_id' => $siteStore->id,
            'status' => 'received',
        ]);

        // Verify balances after transfer:
        // Central Warehouse: 100 in - 40 out = 60 MT
        // Site Store: 0 in + 40 in = 40 MT
        $centralStock = StockLedgerEntry::where('warehouse_id', $centralWarehouse->id)->where('direction', 'in')->sum('quantity')
            - StockLedgerEntry::where('warehouse_id', $centralWarehouse->id)->where('direction', 'out')->sum('quantity');
        $siteStock = StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'in')->sum('quantity')
            - StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'out')->sum('quantity');

        $this->assertEquals(60.00, (float) $centralStock);
        $this->assertEquals(40.00, (float) $siteStock);

        // 4. Over-transfer Rejection (attempt to transfer 70 MT when only 60 MT available in Central)
        $overTransferResponse = $this->actingAs($storekeeper)->post(route('inventory.transfer.store'), [
            'from_warehouse_id' => $centralWarehouse->id,
            'to_warehouse_id' => $siteStore->id,
            'transfer_date' => '2026-10-03',
            'material_id' => $material->id,
            'quantity' => 70.00,
        ]);

        $overTransferResponse->assertSessionHasErrors('quantity');

        // 5. Site Issue: Storekeeper issues 30 MT from Site Store to Pier 45 Site
        $issueResponse = $this->actingAs($storekeeper)->post(route('material-issues.store'), [
            'issue_number' => 'MIS-2026-0001',
            'project_id' => $project->id,
            'site_id' => $site->id,
            'warehouse_id' => $siteStore->id,
            'issue_date' => '2026-10-04',
            'purpose' => 'Pier 45 foundation rebars',
            'items' => [
                [
                    'material_id' => $material->id,
                    'requested_quantity' => 30.00,
                    'issued_quantity' => 30.00,
                ],
            ],
        ]);

        $issueResponse->assertRedirect();
        $issue = MaterialIssue::where('project_id', $project->id)->firstOrFail();
        $this->assertEquals('completed', $issue->status);

        $issueItem = $issue->items->first();
        $this->assertEquals(30.00, (float) $issueItem->issued_quantity);
        $this->assertEquals(0.00, (float) $issueItem->returned_quantity);

        // Site Store stock should now be: 40 - 30 = 10 MT
        $siteStockAfterIssue = StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'in')->sum('quantity')
            - StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'out')->sum('quantity');
        $this->assertEquals(10.00, (float) $siteStockAfterIssue);

        // 6. Over-Return Rejection (Site tries to return 35 MT when only 30 MT was issued)
        $overReturnResponse = $this->actingAs($storekeeper)->post(route('material-issues.return', $issue), [
            'return_date' => '2026-10-05',
            'returns' => [
                [
                    'item_id' => $issueItem->id,
                    'quantity' => 35.00,
                    'reason' => 'Excess materials',
                ],
            ],
        ]);

        $overReturnResponse->assertSessionHasErrors('returns');

        // 7. First Cumulative Return: Return 10 MT
        $return1Response = $this->actingAs($storekeeper)->post(route('material-issues.return', $issue), [
            'return_date' => '2026-10-05',
            'returns' => [
                [
                    'item_id' => $issueItem->id,
                    'quantity' => 10.00,
                    'reason' => 'First batch return from trimmed rebars',
                ],
            ],
        ]);

        $return1Response->assertRedirect();
        $freshItem1 = $issueItem->fresh();
        $this->assertEquals(10.00, (float) $freshItem1->returned_quantity);

        // Site Store stock should now be: 10 + 10 = 20 MT
        $siteStockAfterReturn1 = StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'in')->sum('quantity')
            - StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'out')->sum('quantity');
        $this->assertEquals(20.00, (float) $siteStockAfterReturn1);

        // 8. Second Cumulative Return: Return 15 MT
        $return2Response = $this->actingAs($storekeeper)->post(route('material-issues.return', $issue), [
            'return_date' => '2026-10-06',
            'returns' => [
                [
                    'item_id' => $issueItem->id,
                    'quantity' => 15.00,
                    'reason' => 'Second batch return from completed pier',
                ],
            ],
        ]);

        $return2Response->assertRedirect();
        $freshItem2 = $issueItem->fresh();
        $this->assertEquals(25.00, (float) $freshItem2->returned_quantity);

        // Site Store stock should now be: 20 + 15 = 35 MT
        $siteStockAfterReturn2 = StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'in')->sum('quantity')
            - StockLedgerEntry::where('warehouse_id', $siteStore->id)->where('direction', 'out')->sum('quantity');
        $this->assertEquals(35.00, (float) $siteStockAfterReturn2);

        // 9. Third Return Rejection (trying to return 10 MT more when only 5 MT remains returnable: 30 - 25 = 5)
        $overRemainingResponse = $this->actingAs($storekeeper)->post(route('material-issues.return', $issue), [
            'return_date' => '2026-10-07',
            'returns' => [
                [
                    'item_id' => $issueItem->id,
                    'quantity' => 10.00,
                    'reason' => 'Attempting over-return',
                ],
            ],
        ]);

        $overRemainingResponse->assertSessionHasErrors('returns');
        $this->assertEquals(25.00, (float) $issueItem->fresh()->returned_quantity);
    }
}
