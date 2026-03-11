<?php

namespace App\Services;

use App\Constants\GridConstants;

class GridCalculatorService
{
    /**
     * Calculate all grid levels (prices) between lower and upper bounds.
     *
     * @return array<int, float> Grid prices indexed by level
     */
    public function calculateGridLevels(float $priceLower, float $priceUpper, int $gridCount, string $mode = 'arithmetic'): array
    {
        $levels = [];

        if ($mode === 'geometric' && $priceLower > 0) {
            $ratio = pow($priceUpper / $priceLower, 1 / $gridCount);
            for ($i = 0; $i <= $gridCount; $i++) {
                $levels[$i] = round($priceLower * pow($ratio, $i), 8);
            }
        } else {
            $step = ($priceUpper - $priceLower) / $gridCount;
            for ($i = 0; $i <= $gridCount; $i++) {
                $levels[$i] = round($priceLower + ($step * $i), 8);
            }
        }

        return $levels;
    }

    /**
     * Calculate profit per grid as a percentage (after commission).
     */
    public function calculateProfitPerGrid(float $priceLower, float $priceUpper, int $gridCount): float
    {
        $step = ($priceUpper - $priceLower) / $gridCount;
        $midPrice = ($priceUpper + $priceLower) / 2;
        $grossProfit = ($step / $midPrice) * 100;
        $netProfit = $grossProfit - (GridConstants::DEFAULT_COMMISSION_RATE * 2); // Buy + sell commission

        return round($netProfit, 4);
    }

    /**
     * Calculate commission per grid as a percentage.
     */
    public function calculateCommissionPerGrid(): float
    {
        return round(GridConstants::DEFAULT_COMMISSION_RATE * 2, 4);
    }

    /**
     * Calculate real investment (with leverage) and additional margin.
     */
    public function calculateInvestmentBreakdown(float $investment, int $leverage): array
    {
        $realInvestment = round($investment / $leverage, 4);
        $additionalMargin = round($investment - $realInvestment, 4);

        return [
            'real_investment' => $realInvestment,
            'additional_margin' => $additionalMargin,
        ];
    }

    /**
     * Estimate the liquidation price for a leveraged position.
     * Simplified calculation - real Binance formula is more complex.
     */
    public function estimateLiquidationPrice(
        float $entryPrice,
        int $leverage,
        string $side,
        float $maintenanceMarginRate = 0.004,
    ): float {
        if ($side === 'long') {
            $liquidationPrice = $entryPrice * (1 - (1 / $leverage) + $maintenanceMarginRate);
        } else {
            $liquidationPrice = $entryPrice * (1 + (1 / $leverage) - $maintenanceMarginRate);
        }

        return round($liquidationPrice, 8);
    }

    /**
     * Calculate full grid configuration with all derived values.
     */
    public function calculateFullGridConfig(array $params): array
    {
        $priceLower = (float) $params['price_lower'];
        $priceUpper = (float) $params['price_upper'];
        $gridCount = (int) $params['grid_count'];
        $investment = (float) $params['investment'];
        $leverage = (int) ($params['leverage'] ?? 1);
        $side = $params['side'] ?? 'long';
        $gridMode = $params['grid_mode'] ?? 'arithmetic';

        $gridLevels = $this->calculateGridLevels($priceLower, $priceUpper, $gridCount, $gridMode);
        $profitPerGrid = $this->calculateProfitPerGrid($priceLower, $priceUpper, $gridCount);
        $commissionPerGrid = $this->calculateCommissionPerGrid();
        $investmentBreakdown = $this->calculateInvestmentBreakdown($investment, $leverage);

        // Use average entry price for liquidation estimate
        $avgEntryPrice = ($priceLower + $priceUpper) / 2;
        $estLiquidationPrice = $leverage > 1
            ? $this->estimateLiquidationPrice($avgEntryPrice, $leverage, $side)
            : 0;

        return [
            'grid_levels' => $gridLevels,
            'profit_per_grid' => $profitPerGrid,
            'commission_per_grid' => $commissionPerGrid,
            'real_investment' => $investmentBreakdown['real_investment'],
            'additional_margin' => $investmentBreakdown['additional_margin'],
            'est_liquidation_price' => $estLiquidationPrice,
            'quantity_per_grid' => round($investment / ($gridCount * $avgEntryPrice), 8),
        ];
    }
}
