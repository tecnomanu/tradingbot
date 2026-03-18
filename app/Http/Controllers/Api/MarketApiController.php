<?php

namespace App\Http\Controllers\Api;

use App\Constants\BinanceConstants;
use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Services\TechnicalAnalysisService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TechnicalAnalysisService $technicals,
    ) {}

    public function analyze(Request $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');

        $interval = $request->query('interval', '4h');
        $candles  = min((int) $request->query('candles', 24), 100);
        $symbol   = $bot->symbol;

        $isTestnet = (bool) $bot->binanceAccount?->is_testnet;
        $baseUrl   = $isTestnet
            ? BinanceConstants::TESTNET_FUTURES_URL
            : BinanceConstants::FUTURES_BASE_URL;

        [$currentPrice, $stats24h] = $this->technicals->fetchPriceAndStats($baseUrl, $symbol);
        $klines = $this->technicals->fetchKlines($baseUrl, $symbol, $interval, $candles);
        $closes = array_column($klines, 'close');
        $rsi    = $this->technicals->calculateRsi($closes);
        $trend  = $this->technicals->detectTrend($klines);

        $openOrders = $bot->orders()->where('status', 'open')->get(['side', 'price']);
        $positioning = $this->technicals->analyzePositioning(
            (float) $bot->price_lower,
            (float) $bot->price_upper,
            $bot->grid_count,
            $currentPrice,
            $openOrders->where('side', 'buy')->count(),
            $openOrders->where('side', 'sell')->count(),
        );

        $risk = $this->technicals->assessRisk(
            $currentPrice,
            (float) $bot->price_lower,
            (float) $bot->price_upper,
            $bot->stop_loss_price ? (float) $bot->stop_loss_price : null,
            $bot->take_profit_price ? (float) $bot->take_profit_price : null,
            $rsi,
            $bot->est_liquidation_price ? (float) $bot->est_liquidation_price : null,
        );

        $filled = $bot->orders()->where('status', 'filled');

        return $this->successResponse([
            'bot' => [
                'id'         => $bot->id,
                'name'       => $bot->name,
                'symbol'     => $symbol,
                'status'     => $bot->status->value,
                'side'       => $bot->side->value,
                'is_testnet' => $isTestnet,
            ],
            'market' => [
                'source'         => $isTestnet ? 'Binance Testnet Futures' : 'Binance Futures',
                'base_url'       => $baseUrl,
                'symbol'         => $symbol,
                'price'          => $currentPrice,
                'change_24h_pct' => $stats24h['change_pct'] ?? null,
                'high_24h'       => $stats24h['high'] ?? null,
                'low_24h'        => $stats24h['low'] ?? null,
                'volume_24h'     => $stats24h['volume'] ?? null,
            ],
            'technicals' => [
                'rsi_14'           => $rsi,
                'rsi_signal'       => $rsi > 70 ? 'overbought' : ($rsi < 30 ? 'oversold' : 'neutral'),
                'trend'            => $trend['direction'],
                'trend_pct'        => $trend['pct'],
                'candle_interval'  => $interval,
                'candles_analyzed' => count($klines),
            ],
            'grid_positioning' => $positioning,
            'risk'       => $risk,
            'performance' => [
                'total_pnl'     => (float) $bot->total_pnl,
                'pnl_pct'       => $bot->pnl_percentage,
                'grid_profit'   => (float) $bot->grid_profit,
                'total_rounds'  => (int) $bot->total_rounds,
                'rounds_24h'    => (int) $bot->rounds_24h,
                'filled_orders' => $filled->count(),
                'open_orders'   => $bot->orders()->where('status', 'open')->count(),
                'started_at'    => $bot->started_at?->toIso8601String(),
                'running_hours' => $bot->started_at
                    ? round($bot->started_at->diffInMinutes(now()) / 60, 1)
                    : null,
            ],
        ]);
    }

    public function price(Request $request): JsonResponse
    {
        $symbol  = strtoupper($request->query('symbol', 'BTCUSDT'));
        $testnet = filter_var($request->query('testnet', 'false'), FILTER_VALIDATE_BOOLEAN);
        $baseUrl = $testnet
            ? BinanceConstants::TESTNET_FUTURES_URL
            : BinanceConstants::FUTURES_BASE_URL;

        [$price, $stats] = $this->technicals->fetchPriceAndStats($baseUrl, $symbol);

        return $this->successResponse(array_merge(
            ['symbol' => $symbol, 'price' => $price, 'source' => $testnet ? 'testnet' : 'mainnet'],
            $stats
        ));
    }
}
