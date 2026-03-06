<?php

namespace App\Http\Controllers\Api;

use App\Constants\BinanceConstants;
use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BinanceAccount;
use App\Services\BinanceFuturesService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BinanceFuturesService $futures,
    ) {}

    /**
     * Full market analysis for a bot: current price, 24h stats, klines, grid positioning, risk assessment.
     *
     * Query params:
     *   interval = 1h|4h|1d   (kline interval, default 4h)
     *   candles  = int         (number of candles, default 24)
     */
    public function analyze(Request $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');

        $interval = $request->query('interval', '4h');
        $candles  = min((int) $request->query('candles', 24), 100);
        $symbol   = $bot->symbol;

        // Determine base URL (testnet vs mainnet) — same as the bot uses
        $isTestnet = (bool) $bot->binanceAccount?->is_testnet;
        $baseUrl   = $isTestnet
            ? BinanceConstants::TESTNET_FUTURES_URL
            : BinanceConstants::FUTURES_BASE_URL;

        [$currentPrice, $stats24h] = $this->fetchPriceAndStats($baseUrl, $symbol);
        $klines = $this->fetchKlines($baseUrl, $symbol, $interval, $candles);
        $rsi    = $this->calculateRsi($klines);
        $trend  = $this->detectTrend($klines);

        $positioning = $this->analyzePositioning($bot, $currentPrice);
        $risk        = $this->assessRisk($bot, $currentPrice, $rsi);
        $filled      = $bot->orders()->where('status', 'filled');

        return $this->successResponse([
            'bot'        => [
                'id'     => $bot->id,
                'name'   => $bot->name,
                'symbol' => $symbol,
                'status' => $bot->status->value,
                'side'   => $bot->side->value,
                'is_testnet' => $isTestnet,
            ],
            'market'     => [
                'source'      => $isTestnet ? 'Binance Testnet Futures' : 'Binance Futures',
                'base_url'    => $baseUrl,
                'symbol'      => $symbol,
                'price'       => $currentPrice,
                'change_24h_pct' => $stats24h['change_pct'] ?? null,
                'high_24h'    => $stats24h['high'] ?? null,
                'low_24h'     => $stats24h['low'] ?? null,
                'volume_24h'  => $stats24h['volume'] ?? null,
            ],
            'technicals' => [
                'rsi_14'      => $rsi,
                'rsi_signal'  => $rsi > 70 ? 'overbought' : ($rsi < 30 ? 'oversold' : 'neutral'),
                'trend'       => $trend['direction'],
                'trend_pct'   => $trend['pct'],
                'candle_interval' => $interval,
                'candles_analyzed' => count($klines),
            ],
            'grid_positioning' => $positioning,
            'risk'       => $risk,
            'performance' => [
                'total_pnl'      => (float) $bot->total_pnl,
                'pnl_pct'        => $bot->pnl_percentage,
                'grid_profit'    => (float) $bot->grid_profit,
                'total_rounds'   => (int) $bot->total_rounds,
                'rounds_24h'     => (int) $bot->rounds_24h,
                'filled_orders'  => $filled->count(),
                'open_orders'    => $bot->orders()->where('status', 'open')->count(),
                'started_at'     => $bot->started_at?->toIso8601String(),
                'running_hours'  => $bot->started_at
                    ? round($bot->started_at->diffInMinutes(now()) / 60, 1)
                    : null,
            ],
        ]);
    }

    /**
     * Get current price and 24h stats from Binance (same endpoint as the bot).
     */
    public function price(Request $request): JsonResponse
    {
        $symbol = strtoupper($request->query('symbol', 'BTCUSDT'));
        $testnet = filter_var($request->query('testnet', 'false'), FILTER_VALIDATE_BOOLEAN);
        $baseUrl = $testnet
            ? BinanceConstants::TESTNET_FUTURES_URL
            : BinanceConstants::FUTURES_BASE_URL;

        [$price, $stats] = $this->fetchPriceAndStats($baseUrl, $symbol);

        return $this->successResponse(array_merge(
            ['symbol' => $symbol, 'price' => $price, 'source' => $testnet ? 'testnet' : 'mainnet'],
            $stats
        ));
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function fetchPriceAndStats(string $baseUrl, string $symbol): array
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

        // Fallback: price only
        try {
            $raw = @file_get_contents("{$baseUrl}/fapi/v2/ticker/price?symbol={$symbol}");
            $price = $raw ? (float) (json_decode($raw, true)['price'] ?? 0) : 0;
        } catch (\Throwable) {
            $price = 0;
        }
        return [$price, []];
    }

    private function fetchKlines(string $baseUrl, string $symbol, string $interval, int $limit): array
    {
        try {
            $raw = @file_get_contents("{$baseUrl}/fapi/v1/klines?symbol={$symbol}&interval={$interval}&limit={$limit}");
            if (!$raw) return [];
            return array_map(fn ($k) => [
                'time'  => (int) $k[0],
                'open'  => (float) $k[1],
                'high'  => (float) $k[2],
                'low'   => (float) $k[3],
                'close' => (float) $k[4],
                'volume'=> (float) $k[5],
            ], json_decode($raw, true));
        } catch (\Throwable) {
            return [];
        }
    }

    private function calculateRsi(array $klines, int $period = 14): ?float
    {
        $closes = array_column($klines, 'close');
        if (count($closes) < $period + 1) return null;

        $deltas  = [];
        for ($i = 1; $i < count($closes); $i++) {
            $deltas[] = $closes[$i] - $closes[$i - 1];
        }

        $gains  = array_map(fn ($d) => max($d, 0), array_slice($deltas, -$period));
        $losses = array_map(fn ($d) => abs(min($d, 0)), array_slice($deltas, -$period));

        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;

        if ($avgLoss == 0) return 100.0;
        return round(100 - (100 / (1 + $avgGain / $avgLoss)), 1);
    }

    private function detectTrend(array $klines): array
    {
        if (count($klines) < 2) return ['direction' => 'unknown', 'pct' => 0];

        $first = $klines[0]['close'];
        $last  = $klines[count($klines) - 1]['close'];
        $pct   = round((($last - $first) / $first) * 100, 2);

        return [
            'direction' => $pct > 1 ? 'uptrend' : ($pct < -1 ? 'downtrend' : 'sideways'),
            'pct'       => $pct,
        ];
    }

    private function analyzePositioning(Bot $bot, float $currentPrice): array
    {
        $lower = (float) $bot->price_lower;
        $upper = (float) $bot->price_upper;
        $range = $upper - $lower;

        if ($range <= 0) return ['in_range' => null];

        $positionPct = (($currentPrice - $lower) / $range) * 100;
        $inRange     = $currentPrice >= $lower && $currentPrice <= $upper;

        $gridSpacing  = $range / max($bot->grid_count - 1, 1);
        $distToLower  = $currentPrice - $lower;
        $distToUpper  = $upper - $currentPrice;

        // Which grid level is the price at?
        $currentLevel = $range > 0 ? (int) floor($distToLower / $gridSpacing) : 0;

        $openOrders  = $bot->orders()->where('status', 'open')->get(['side', 'price']);
        $openBuys    = $openOrders->where('side', 'buy')->count();
        $openSells   = $openOrders->where('side', 'sell')->count();

        return [
            'in_range'           => $inRange,
            'position_pct'       => round($positionPct, 1),
            'current_price'      => $currentPrice,
            'grid_lower'         => $lower,
            'grid_upper'         => $upper,
            'distance_to_lower'  => round($distToLower, 2),
            'distance_to_upper'  => round($distToUpper, 2),
            'distance_to_lower_pct' => round(($distToLower / $currentPrice) * 100, 2),
            'distance_to_upper_pct' => round(($distToUpper / $currentPrice) * 100, 2),
            'current_grid_level' => $currentLevel,
            'grid_spacing'       => round($gridSpacing, 2),
            'open_buys'          => $openBuys,
            'open_sells'         => $openSells,
        ];
    }

    private function assessRisk(Bot $bot, float $currentPrice, ?float $rsi): array
    {
        $alerts = [];

        $lower  = (float) $bot->price_lower;
        $upper  = (float) $bot->price_upper;
        $sl     = $bot->stop_loss_price ? (float) $bot->stop_loss_price : null;
        $tp     = $bot->take_profit_price ? (float) $bot->take_profit_price : null;

        // Price outside range
        if ($currentPrice > $upper) {
            $alerts[] = ['level' => 'warning', 'msg' => "Price ($currentPrice) is ABOVE the grid upper bound ($upper). No active grid trading happening."];
        } elseif ($currentPrice < $lower) {
            $alerts[] = ['level' => 'critical', 'msg' => "Price ($currentPrice) is BELOW the grid lower bound ($lower). Risk of liquidation."];
        }

        // Stop loss proximity
        if ($sl && $currentPrice > 0) {
            $slDistPct = (($currentPrice - $sl) / $currentPrice) * 100;
            if ($slDistPct < 2) {
                $alerts[] = ['level' => 'critical', 'msg' => "Price is within {$slDistPct}% of stop loss ($sl). Very high risk."];
            } elseif ($slDistPct < 5) {
                $alerts[] = ['level' => 'warning', 'msg' => "Price is {$slDistPct}% from stop loss ($sl). Monitor closely."];
            }
        }

        // Take profit already passed
        if ($tp && $currentPrice > $tp) {
            $alerts[] = ['level' => 'info', 'msg' => "Price ($currentPrice) has passed take profit ($tp). Consider stopping the bot or updating TP."];
        }

        // RSI signals
        if ($rsi !== null) {
            if ($rsi > 75) {
                $alerts[] = ['level' => 'warning', 'msg' => "RSI at $rsi — heavily overbought. High chance of pullback into grid."];
            } elseif ($rsi < 25) {
                $alerts[] = ['level' => 'warning', 'msg' => "RSI at $rsi — heavily oversold. Downside risk for the grid."];
            }
        }

        // Liquidation price
        $liqPrice = $bot->est_liquidation_price ? (float) $bot->est_liquidation_price : null;
        $liqDistPct = ($liqPrice && $currentPrice > 0)
            ? round((($currentPrice - $liqPrice) / $currentPrice) * 100, 1)
            : null;

        return [
            'level'               => count($alerts) ? ($alerts[0]['level'] === 'critical' ? 'critical' : 'warning') : 'ok',
            'alerts'              => $alerts,
            'stop_loss'           => $sl,
            'take_profit'         => $tp,
            'liquidation_price'   => $liqPrice,
            'liquidation_dist_pct'=> $liqDistPct,
        ];
    }
}
