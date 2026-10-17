<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InventoryTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Warehouse $centralYard;
    protected Warehouse $siteStore;
    protected Material $cement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-construction',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Store Manager',
            'slug' => 'store-manager',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Warehouse Supervisor',
            'email' => 'store@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->user->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Civil & Structural',
            'code' => 'CIV',
        ]);

        $this->cement = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'code' => 'MAT-CEM-001',
            'name' => 'Ultratech Cement 53 Grade',
            'unit_of_measure' => 'Bags',
            'reorder_level' => 100,
            'standard_cost' => 380.00,
        ]);

        $this->centralYard = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Central Yard',
            'code' => 'WH-MAIN',
            'type' => 'central',
        ]);

        $this->siteStore = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Riverside Site Store',
            'code' => 'WH-SITE-1',
            'type' => 'site_store',
        ]);

        // Seed initial opening stock of 500 bags into Central Yard
        StockLedgerEntry::create([
            'company_id' => $this->company->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->centralYard->id,
            'entry_date' => Carbon::now()->subDays(5),
            'transaction_type' => 'opening_stock',
            'quantity' => 500.000,
            'direction' => 'in',
            'unit_rate' => 380.00,
            'total_amount' => 190000.00,
            'balance_quantity' => 500.000,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_transfer_stock_between_warehouses_atomically(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/inventory/transfer', [
                'from_warehouse_id' => $this->centralYard->id,
                'to_warehouse_id' => $this->siteStore->id,
                'material_id' => $this->cement->id,
                'transfer_date' => Carbon::today()->format('Y-m-d'),
                'quantity' => 150,
                'vehicle_number' => 'MH-12-TR-9988',
                'driver_name' => 'Ramesh Kumar',
                'notes' => 'Urgent transfer for foundation slab',
            ]);

        $response->assertRedirect('/inventory/ledger');
        $response->assertSessionHas('success');

        // Verify StockTransfer record
        $this->assertDatabaseHas('stock_transfers', [
            'company_id' => $this->company->id,
            'from_warehouse_id' => $this->centralYard->id,
            'to_warehouse_id' => $this->siteStore->id,
            'vehicle_number' => 'MH-12-TR-9988',
            'status' => 'received',
        ]);

        // Verify Central Yard outflow
        $this->assertDatabaseHas('stock_ledger_entries', [
            'company_id' => $this->company->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->centralYard->id,
            'direction' => 'out',
            'quantity' => 150.000,
            'transaction_type' => 'transfer_out',
        ]);

        // Verify Riverside Site Store inflow
        $this->assertDatabaseHas('stock_ledger_entries', [
            'company_id' => $this->company->id,
            'material_id' => $this->cement->id,
            'warehouse_id' => $this->siteStore->id,
            'direction' => 'in',
            'quantity' => 150.000,
            'transaction_type' => 'transfer_in',
        ]);

        // Check balances
        $centralBalance = StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('warehouse_id', $this->centralYard->id)
            ->where('direction', 'in')->sum('quantity') -
            StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('warehouse_id', $this->centralYard->id)
            ->where('direction', 'out')->sum('quantity');

        $this->assertEquals(350, $centralBalance);

        $siteBalance = StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('warehouse_id', $this->siteStore->id)
            ->where('direction', 'in')->sum('quantity') -
            StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('warehouse_id', $this->siteStore->id)
            ->where('direction', 'out')->sum('quantity');

        $this->assertEquals(150, $siteBalance);
    }

    public function test_cannot_transfer_stock_if_insufficient_quantity(): void
    {
        // Try transferring 600 bags when only 500 available
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/inventory/transfer', [
                'from_warehouse_id' => $this->centralYard->id,
                'to_warehouse_id' => $this->siteStore->id,
                'material_id' => $this->cement->id,
                'transfer_date' => Carbon::today()->format('Y-m-d'),
                'quantity' => 600,
                'vehicle_number' => 'MH-12-TR-9988',
            ]);

        $response->assertSessionHasErrors('quantity');

        // No transfer record should exist
        $this->assertDatabaseMissing('stock_transfers', [
            'from_warehouse_id' => $this->centralYard->id,
        ]);

        // Central Yard balance remains 500
        $centralBalance = StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('warehouse_id', $this->centralYard->id)
            ->where('direction', 'in')->sum('quantity') -
            StockLedgerEntry::where('material_id', $this->cement->id)
            ->where('warehouse_id', $this->centralYard->id)
            ->where('direction', 'out')->sum('quantity');

        $this->assertEquals(500, $centralBalance);
    }
}
