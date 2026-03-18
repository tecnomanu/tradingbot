<?php

namespace App\Services;

use App\Constants\BinanceConstants;
use Illuminate\Support\Facades\Http;

class TechnicalAnalysisService
{
    public function fetchPriceAndStats(string $baseUrl, string $symbol): array
    {
        try {
            $statsRaw = @file_get_contents("{$baseUrl}/fapi/v1/ticker/24hr?symbol={$symbol}");
            if ($statsRaw) {
                $d = json_decode($statsRaw, true);
                $price = (float) ($d['lastPrice'] ?? 0);
                $stats = [
                    'change_pct' => round((float) ($d['priceChangePercent'] ?? 0), 2),
                    'high'       => (float) ($d['highPrice'] ?? 0),
                    'low'        => (float) ($d['lowPrice'] ?? 0),
                    'volume'     => round((float) ($d['volume'] ?? 0), 2),
                ];
                return [$price, $stats];
            }
        } catch (\Throwable) {}

        try {
            $raw = @file_get_contents("{$baseUrl}/fapi/v2/ticker/price?symbol={$symbol}");
            $price = $raw ? (float) (json_decode($raw, true)['price'] ?? 0) : 0;
        } catch (\Throwable) {
            $price = 0;
        }
        return [$price, []];
    }

    public function fetchKlines(string $baseUrl, string $symbol, string $interval, int $limit): array
    {
        try {
            $raw = @file_get_contents("{$baseUrl}/fapi/v1/klines?symbol={$symbol}&interval={$interval}&limit={$limit}");
            if (!$raw) return [];
            return array_map(fn($k) => [
                'time'   => (int) $k[0],
                'open'   => (float) $k[1],
                'high'   => (float) $k[2],
                'low'    => (float) $k[3],
                'close'  => (float) $k[4],
                'volume' => (float) $k[5],
            ], json_decode($raw, true));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetch klines via Laravel HTTP (used by AiTradingAgent).
     */
    public function fetchKlinesHttp(string $symbol, string $interval, int $limit): array
    {
        $url = BinanceConstants::FUTURES_BASE_URL . '/fapi/v1/klines';
        $response = Http::get($url, [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);

        if (!$response->successful()) {
            return [];
        }

        return array_map(fn($k) => [
            'open_time'  => $k[0],
            'open'       => (float) $k[1],
            'high'       => (float) $k[2],
            'low'        => (float) $k[3],
            'close'      => (float) $k[4],
            'volume'     => (float) $k[5],
            'close_time' => $k[6],
        ], $response->json());
    }

    public function calculateRsi(array $closes, int $period = 14): ?float
    {
        if (count($closes) < $period + 1) {
            return null;
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = max($change, 0);
            $losses[] = max(-$change, 0);
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;
        }

        if ($avgLoss == 0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;
        return round(100 - (100 / (1 + $rs)), 2);
    }

    public function calculateSma(array $data, int $period): float
    {
        $slice = array_slice($data, -$period);
        return array_sum($slice) / count($slice);
    }

    public function calculateEma(array $data, int $period): float
    {
        $multiplier = 2 / ($period + 1);
        $ema = $data[0];

        for ($i = 1; $i < count($data); $i++) {
            $ema = ($data[$i] - $ema) * $multiplier + $ema;
        }

        return $ema;
    }

    public function calculateAtr(array $highs, array $lows, array $closes, int $period): float
    {
        $trueRanges = [];

        for ($i = 1; $i < count($closes); $i++) {
            $trueRanges[] = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
        }

        return array_sum(array_slice($trueRanges, -$period)) / $period;
    }

    public function calculateBollingerBands(array $closes, int $period, float $stdDevMultiplier): array
    {
        $slice = array_slice($closes, -$period);
        $mean = array_sum($slice) / count($slice);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $slice)) / count($slice);
        $stdDev = sqrt($variance);

        return [
            'upper'  => $mean + ($stdDevMultiplier * $stdDev),
            'middle' => $mean,
            'lower'  => $mean - ($stdDevMultiplier * $stdDev),
        ];
    }

    public function detectTrend(array $klines): array
    {
        if (count($klines) < 2) {
            return ['direction' => 'unknown', 'pct' => 0];
        }

        $first = $klines[0]['close'];
        $last  = $klines[count($klines) - 1]['close'];
        $pct   = round((($last - $first) / $first) * 100, 2);

        return [
            'direction' => $pct > 1 ? 'uptrend' : ($pct < -1 ? 'downtrend' : 'sideways'),
            'pct'       => $pct,
        ];
    }

    /**
     * Full market data gathering for AI analysis.
     */
    public function gatherMarketData(string $symbol): ?array
    {
        try {
            $klines = $this->fetchKlinesHttp($symbol, '1h', 100);
            if (empty($klines)) {
                return null;
            }

            $closes = array_column($klines, 'close');
            $volumes = array_column($klines, 'volume');
            $highs = array_column($klines, 'high');
            $lows = array_column($klines, 'low');

            $currentPrice = end($closes);
            $rsi = $this->calculateRsi($closes, 14) ?? 50.0;
            $sma20 = $this->calculateSma($closes, 20);
            $sma50 = $this->calculateSma($closes, 50);
            $ema12 = $this->calculateEma($closes, 12);
            $ema26 = $this->calculateEma($closes, 26);
            $macd = $ema12 - $ema26;
            $avgVolume = array_sum(array_slice($volumes, -20)) / 20;
            $currentVolume = end($volumes);
            $atr = $this->calculateAtr($highs, $lows, $closes, 14);
            $bollingerBands = $this->calculateBollingerBands($closes, 20, 2);

            $priceChange24h = count($closes) >= 24
                ? (($currentPrice - $closes[count($closes) - 24]) / $closes[count($closes) - 24]) * 100
                : 0;

            return [
                'price'    => round($currentPrice, 2),
                'chg24h'   => round($priceChange24h, 2),
                'rsi'      => round($rsi, 2),
                'sma20'    => round($sma20, 2),
                'sma50'    => round($sma50, 2),
                'macd'     => round($macd, 2),
                'vol_ratio' => round($currentVolume / max($avgVolume, 1), 2),
                'atr'      => round($atr, 2),
                'bb_upper' => round($bollingerBands['upper'], 2),
                'bb_lower' => round($bollingerBands['lower'], 2),
                'bb_mid'   => round($bollingerBands['middle'], 2),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Grid positioning analysis for a bot.
     */
    public function analyzePositioning(float $priceLower, float $priceUpper, int $gridCount, float $currentPrice, int $openBuys = 0, int $openSells = 0): array
    {
        $range = $priceUpper - $priceLower;
        if ($range <= 0) {
            return ['in_range' => null];
        }

        $positionPct = (($currentPrice - $priceLower) / $range) * 100;
        $inRange     = $currentPrice >= $priceLower && $currentPrice <= $priceUpper;
        $gridSpacing = $range / max($gridCount - 1, 1);
        $distToLower = $currentPrice - $priceLower;
        $distToUpper = $priceUpper - $currentPrice;
        $currentLevel = $range > 0 ? (int) floor($distToLower / $gridSpacing) : 0;

        return [
            'in_range'              => $inRange,
            'position_pct'          => round($positionPct, 1),
            'current_price'         => $currentPrice,
            'grid_lower'            => $priceLower,
            'grid_upper'            => $priceUpper,
            'distance_to_lower'     => round($distToLower, 2),
            'distance_to_upper'     => round($distToUpper, 2),
            'distance_to_lower_pct' => round(($distToLower / $currentPrice) * 100, 2),
            'distance_to_upper_pct' => round(($distToUpper / $currentPrice) * 100, 2),
            'current_grid_level'    => $currentLevel,
            'grid_spacing'          => round($gridSpacing, 2),
            'open_buys'             => $openBuys,
            'open_sells'            => $openSells,
        ];
    }

    /**
     * Risk assessment for a bot.
     */
    public function assessRisk(
        float $currentPrice,
        float $priceLower,
        float $priceUpper,
        ?float $stopLoss,
        ?float $takeProfit,
        ?float $rsi,
        ?float $estLiquidationPrice,
    ): array {
        $alerts = [];

        if ($currentPrice > $priceUpper) {
            $alerts[] = ['level' => 'warning', 'msg' => "Price ($currentPrice) is ABOVE the grid upper bound ($priceUpper). No active grid trading happening."];
        } elseif ($currentPrice < $priceLower) {
            $alerts[] = ['level' => 'critical', 'msg' => "Price ($currentPrice) is BELOW the grid lower bound ($priceLower). Risk of liquidation."];
        }

        if ($stopLoss && $currentPrice > 0) {
            $slDistPct = round((($currentPrice - $stopLoss) / $currentPrice) * 100, 2);
            if ($slDistPct < 2) {
                $alerts[] = ['level' => 'critical', 'msg' => "Price is within {$slDistPct}% of stop loss ($stopLoss). Very high risk."];
            } elseif ($slDistPct < 5) {
                $alerts[] = ['level' => 'warning', 'msg' => "Price is {$slDistPct}% from stop loss ($stopLoss). Monitor closely."];
            }
        }

        if ($takeProfit && $currentPrice > $takeProfit) {
            $alerts[] = ['level' => 'info', 'msg' => "Price ($currentPrice) has passed take profit ($takeProfit). Consider stopping the bot or updating TP."];
        }

        if ($rsi !== null) {
            if ($rsi > 75) {
                $alerts[] = ['level' => 'warning', 'msg' => "RSI at $rsi — heavily overbought. High chance of pullback into grid."];
            } elseif ($rsi < 25) {
                $alerts[] = ['level' => 'warning', 'msg' => "RSI at $rsi — heavily oversold. Downside risk for the grid."];
            }
        }

        $liqDistPct = ($estLiquidationPrice && $currentPrice > 0)
            ? round((($currentPrice - $estLiquidationPrice) / $currentPrice) * 100, 1)
            : null;

        return [
            'level'                => count($alerts) ? ($alerts[0]['level'] === 'critical' ? 'critical' : 'warning') : 'ok',
            'alerts'               => $alerts,
            'stop_loss'            => $stopLoss,
            'take_profit'          => $takeProfit,
            'liquidation_price'    => $estLiquidationPrice,
            'liquidation_dist_pct' => $liqDistPct,
        ];
    }
}
