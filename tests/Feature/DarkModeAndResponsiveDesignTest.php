<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DarkModeAndResponsiveDesignTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'slug' => 'nsd-earth-movers',
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->adminUser = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $this->company->users()->attach($this->adminUser->id, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    public function test_guest_layout_contains_theme_script_and_dark_mode_elements(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('localStorage.getItem(\'theme\')', false);
        $response->assertSee('darkModeManager()', false);
        $response->assertSee('dark:bg-slate-950', false);
    }

    public function test_app_layout_renders_header_theme_toggle_and_mobile_drawer(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="header-theme-toggle"', false);
        $response->assertSee('darkModeManager()', false);
        $response->assertSee('mobileNavOpen', false);
        $response->assertSee('dark:bg-slate-950', false);
        $response->assertSee('dark:bg-slate-900', false);
    }

    public function test_quotations_and_material_issues_render_responsive_containers_and_dark_mode(): void
    {
        $quoteResponse = $this->actingAs($this->adminUser)->get('/quotations');
        $quoteResponse->assertStatus(200);
        $quoteResponse->assertSee('overflow-x-auto', false);
        $quoteResponse->assertSee('grid-cols-1', false);

        $issueResponse = $this->actingAs($this->adminUser)->get('/material-issues');
        $issueResponse->assertStatus(200);
        $issueResponse->assertSee('overflow-x-auto', false);
        $issueResponse->assertSee('grid-cols-1', false);
    }
}
