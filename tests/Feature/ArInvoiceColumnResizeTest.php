<?php

namespace Tests\Feature;

use App\Filament\Pages\ArInvoice;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The grid's column widths are draggable, as they are in the client.
 */
class ArInvoiceColumnResizeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The behaviour is injected through a panel render hook, because the panel
     * does not load the application's own JS bundle. If the hook stops firing
     * the grid silently loses the handles, so this checks the script reaches
     * the page.
     */
    public function test_the_resize_script_is_served_with_the_panel(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $response = $this->actingAs(User::factory()->create())
            ->get(ArInvoice::getUrl())
            ->assertSuccessful();

        $response->assertSee('sap-col-resizer', escape: false);
        $response->assertSee('sap-grid-widths', escape: false);
    }

    /**
     * Fixed layout is what makes a width on the header cell govern the whole
     * column; without it dragging one edge would do nothing.
     */
    public function test_the_grid_uses_a_fixed_table_layout(): void
    {
        $css = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString('table-layout: fixed', $css);
        $this->assertStringContainsString('.sap-col-resizer', $css);
    }
}
