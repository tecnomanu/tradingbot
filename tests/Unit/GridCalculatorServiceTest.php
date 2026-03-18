<?php

namespace Tests\Unit;

use App\Constants\GridConstants;
use App\Services\GridCalculatorService;
use PHPUnit\Framework\TestCase;

class GridCalculatorServiceTest extends TestCase
{
    private GridCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GridCalculatorService();
    }

    // -------------------------------------------------------------------------
    // calculateGridLevels
    // -------------------------------------------------------------------------

    public function test_arithmetic_grid_levels_count_equals_grid_count_plus_one(): void
    {
        $levels = $this->service->calculateGridLevels(80000, 100000, 10, 'arithmetic');

        $this->assertCount(11, $levels);
    }

    public function test_arithmetic_grid_levels_first_and_last_match_bounds(): void
    {
        $levels = $this->service->calculateGridLevels(80000, 100000, 10, 'arithmetic');

        $this->assertEquals(80000.0, $levels[0]);
        $this->assertEquals(100000.0, $levels[10]);
    }

    public function test_arithmetic_grid_levels_are_evenly_spaced(): void
    {
        $levels = $this->service->calculateGridLevels(80000, 100000, 4, 'arithmetic');

        $this->assertEqualsWithDelta(85000.0, $levels[1], 0.001);
        $this->assertEqualsWithDelta(90000.0, $levels[2], 0.001);
        $this->assertEqualsWithDelta(95000.0, $levels[3], 0.001);
    }

    public function test_geometric_grid_levels_count_equals_grid_count_plus_one(): void
    {
        $levels = $this->service->calculateGridLevels(80000, 100000, 10, 'geometric');

        $this->assertCount(11, $levels);
    }

    public function test_geometric_grid_levels_first_and_last_match_bounds(): void
    {
        $levels = $this->service->calculateGridLevels(80000, 100000, 10, 'geometric');

        $this->assertEqualsWithDelta(80000.0, $levels[0], 0.01);
        $this->assertEqualsWithDelta(100000.0, $levels[10], 0.01);
    }

    public function test_geometric_grid_levels_have_constant_ratio(): void
    {
        $levels = $this->service->calculateGridLevels(80000, 100000, 4, 'geometric');

        $ratio1 = $levels[1] / $levels[0];
        $ratio2 = $levels[2] / $levels[1];
        $ratio3 = $levels[3] / $levels[2];

        $this->assertEqualsWithDelta($ratio1, $ratio2, 0.0001);
        $this->assertEqualsWithDelta($ratio2, $ratio3, 0.0001);
    }

    public function test_defaults_to_arithmetic_when_mode_is_unknown(): void
    {
        $arithmetic = $this->service->calculateGridLevels(80000, 100000, 4, 'arithmetic');
        $unknown    = $this->service->calculateGridLevels(80000, 100000, 4, 'unknown');

        $this->assertEquals($arithmetic, $unknown);
    }

    // -------------------------------------------------------------------------
    // calculateProfitPerGrid
    // -------------------------------------------------------------------------

    public function test_profit_per_grid_is_positive_for_reasonable_range(): void
    {
        $profit = $this->service->calculateProfitPerGrid(80000, 100000, 10);

        $this->assertGreaterThan(0, $profit);
    }

    public function test_profit_per_grid_deducts_double_commission(): void
    {
        $priceLower = 80000.0;
        $priceUpper = 100000.0;
        $gridCount  = 10;

        $step        = ($priceUpper - $priceLower) / $gridCount;
        $midPrice    = ($priceUpper + $priceLower) / 2;
        $grossProfit = ($step / $midPrice) * 100;
        $expected    = round($grossProfit - (GridConstants::DEFAULT_COMMISSION_RATE * 2), 4);

        $this->assertEquals($expected, $this->service->calculateProfitPerGrid($priceLower, $priceUpper, $gridCount));
    }

    public function test_profit_per_grid_increases_with_wider_range(): void
    {
        $narrow = $this->service->calculateProfitPerGrid(90000, 100000, 10);
        $wide   = $this->service->calculateProfitPerGrid(80000, 100000, 10);

        $this->assertGreaterThan($narrow, $wide);
    }

    // -------------------------------------------------------------------------
    // calculateCommissionPerGrid
    // -------------------------------------------------------------------------

    public function test_commission_per_grid_equals_double_commission_rate(): void
    {
        $expected = round(GridConstants::DEFAULT_COMMISSION_RATE * 2, 4);

        $this->assertEquals($expected, $this->service->calculateCommissionPerGrid());
    }

    // -------------------------------------------------------------------------
    // calculateInvestmentBreakdown
    // -------------------------------------------------------------------------

    public function test_investment_breakdown_with_leverage_one(): void
    {
        $result = $this->service->calculateInvestmentBreakdown(100.0, 1);

        $this->assertEquals(100.0, $result['real_investment']);
        $this->assertEquals(0.0, $result['additional_margin']);
    }

    public function test_investment_breakdown_divides_by_leverage(): void
    {
        $result = $this->service->calculateInvestmentBreakdown(100.0, 10);

        $this->assertEquals(10.0, $result['real_investment']);
        $this->assertEquals(90.0, $result['additional_margin']);
    }

    public function test_investment_breakdown_real_plus_margin_equals_investment(): void
    {
        $investment = 250.0;
        $leverage   = 7;
        $result     = $this->service->calculateInvestmentBreakdown($investment, $leverage);

        $this->assertEqualsWithDelta(
            $investment,
            $result['real_investment'] + $result['additional_margin'],
            0.001
        );
    }

    // -------------------------------------------------------------------------
    // estimateLiquidationPrice
    // -------------------------------------------------------------------------

    public function test_liquidation_price_long_is_below_entry(): void
    {
        $entry = 90000.0;
        $price = $this->service->estimateLiquidationPrice($entry, 10, 'long');

        $this->assertLessThan($entry, $price);
    }

    public function test_liquidation_price_short_is_above_entry(): void
    {
        $entry = 90000.0;
        $price = $this->service->estimateLiquidationPrice($entry, 10, 'short');

        $this->assertGreaterThan($entry, $price);
    }

    public function test_liquidation_price_closer_to_entry_with_higher_leverage(): void
    {
        $entry   = 90000.0;
        $lowLev  = $this->service->estimateLiquidationPrice($entry, 5, 'long');
        $highLev = $this->service->estimateLiquidationPrice($entry, 50, 'long');

        // Higher leverage → liq price numerically higher (closer to entry from below) for long
        $this->assertGreaterThan($lowLev, $highLev);
    }

    // -------------------------------------------------------------------------
    // calculateFullGridConfig
    // -------------------------------------------------------------------------

    public function test_full_grid_config_returns_expected_keys(): void
    {
        $config = $this->service->calculateFullGridConfig([
            'price_lower' => 80000,
            'price_upper' => 100000,
            'grid_count'  => 10,
            'investment'  => 100,
            'leverage'    => 1,
            'side'        => 'long',
        ]);

        $this->assertArrayHasKey('grid_levels', $config);
        $this->assertArrayHasKey('profit_per_grid', $config);
        $this->assertArrayHasKey('commission_per_grid', $config);
        $this->assertArrayHasKey('real_investment', $config);
        $this->assertArrayHasKey('additional_margin', $config);
        $this->assertArrayHasKey('est_liquidation_price', $config);
        $this->assertArrayHasKey('quantity_per_grid', $config);
        $this->assertArrayHasKey('step_size', $config);
    }

    public function test_full_grid_config_liquidation_price_is_zero_without_leverage(): void
    {
        $config = $this->service->calculateFullGridConfig([
            'price_lower' => 80000,
            'price_upper' => 100000,
            'grid_count'  => 10,
            'investment'  => 100,
            'leverage'    => 1,
            'side'        => 'long',
        ]);

        $this->assertEquals(0, $config['est_liquidation_price']);
    }

    public function test_full_grid_config_liquidation_price_set_with_leverage(): void
    {
        $config = $this->service->calculateFullGridConfig([
            'price_lower' => 80000,
            'price_upper' => 100000,
            'grid_count'  => 10,
            'investment'  => 100,
            'leverage'    => 10,
            'side'        => 'long',
        ]);

        $this->assertGreaterThan(0, $config['est_liquidation_price']);
    }

    public function test_full_grid_config_step_size_equals_range_divided_by_count(): void
    {
        $config = $this->service->calculateFullGridConfig([
            'price_lower' => 80000,
            'price_upper' => 100000,
            'grid_count'  => 10,
            'investment'  => 100,
            'leverage'    => 1,
            'side'        => 'long',
        ]);

        $this->assertEqualsWithDelta(2000.0, $config['step_size'], 0.001);
    }
}
