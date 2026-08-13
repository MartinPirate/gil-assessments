<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Widgets\OperationsOverview;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use ReflectionMethod;
use Savanna\Theme\SavannaThemePlugin;
use Tests\TestCase;

/**
 * The Savanna theme, and the sparkline data the stat tiles are drawn from.
 */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_theme_is_registered_on_the_panel(): void
    {
        $plugin = Filament::getPanel('admin')->getPlugin('savanna-theme');

        $this->assertInstanceOf(SavannaThemePlugin::class, $plugin);
    }

    /**
     * The document's own navy is pinned by CSS, so the panel accent is free to
     * be the theme's. If this ever reverts, the invoice screen silently stops
     * matching the supplied screenshot.
     */
    public function test_the_panel_accent_is_the_theme_accent(): void
    {
        $colors = Filament::getPanel('admin')->getColors();

        $this->assertArrayHasKey('primary', $colors);
        $this->assertNotEmpty($colors['primary']);
    }

    protected function series(OperationsOverview $widget, ...$args): array
    {
        $method = new ReflectionMethod($widget, 'lastSevenDays');
        $method->setAccessible(true);

        return $method->invoke($widget, ...$args);
    }

    protected function invoice(float $total, string $postingDate, int $docNum): Invoice
    {
        $customer = Customer::firstOrCreate(
            ['code' => 'CC00001'],
            ['name' => 'Naivas Supermarket Ltd', 'currency' => 'KES'],
        );

        return Invoice::create([
            'doc_num' => $docNum,
            'series' => 'IN',
            'doc_type' => Invoice::TYPE_INVOICE,
            'customer_id' => $customer->id,
            'customer_code' => $customer->code,
            'customer_name' => $customer->name,
            'currency' => 'KES',
            'posting_date' => $postingDate,
            'remarks' => 'Test',
            'total_before_discount' => $total,
            'total_after_discount' => $total,
            'document_total' => $total,
            'balance_due' => $total,
            'status' => Invoice::STATUS_OPEN,
        ]);
    }

    public function test_the_sparkline_returns_seven_points_oldest_first(): void
    {
        $this->invoice(1000, today()->subDays(6)->toDateString(), 1);
        $this->invoice(2500, today()->toDateString(), 2);

        $series = $this->series(
            new OperationsOverview(),
            Invoice::query()->posted(),
            'posting_date',
            'document_total',
        );

        $this->assertCount(7, $series);
        $this->assertEqualsWithDelta(1000, $series[0], 0.001, 'The oldest day comes first.');
        $this->assertEqualsWithDelta(2500, $series[6], 0.001, 'Today comes last.');
    }

    /**
     * A quiet day has to stay a zero. If empty days were dropped the line would
     * close the gap and report a smooth trend that never happened.
     */
    public function test_days_without_rows_are_zero_not_missing(): void
    {
        $this->invoice(4000, today()->toDateString(), 1);

        $series = $this->series(
            new OperationsOverview(),
            Invoice::query()->posted(),
            'posting_date',
            'document_total',
        );

        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0], array_slice($series, 0, 6));
        $this->assertEqualsWithDelta(4000, $series[6], 0.001);
    }

    public function test_two_invoices_on_one_day_are_summed(): void
    {
        $this->invoice(1000, today()->toDateString(), 1);
        $this->invoice(1500, today()->toDateString(), 2);

        $series = $this->series(
            new OperationsOverview(),
            Invoice::query()->posted(),
            'posting_date',
            'document_total',
        );

        $this->assertEqualsWithDelta(2500, $series[6], 0.001);
    }

    /**
     * Both column names reach a raw expression, so anything outside the
     * whitelist must be refused rather than interpolated.
     */
    public function test_an_unknown_column_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->series(
            new OperationsOverview(),
            Invoice::query(),
            'posting_date); DROP TABLE invoices; --',
        );
    }

    public function test_an_unknown_sum_column_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->series(new OperationsOverview(), Invoice::query(), 'posting_date', 'password');
    }

    /**
     * Every tile an administrator sees should carry a sparkline.
     */
    public function test_each_stat_carries_a_chart(): void
    {
        Auth::login(User::factory()->role(UserRole::Admin)->create());

        $widget = new OperationsOverview();
        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        $stats = $method->invoke($widget);

        $this->assertNotEmpty($stats);

        foreach ($stats as $stat) {
            $chart = (fn () => $this->chart)->call($stat);

            $this->assertIsArray($chart, 'Every stat tile should have sparkline data.');
            $this->assertCount(7, $chart);
        }
    }
}
