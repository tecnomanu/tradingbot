<?php

namespace App\Services;

use App\Constants\BinanceConstants;
use App\Models\AiAgentLog;
use App\Models\Bot;
use Illuminate\Support\Facades\Http;
use App\Support\BotLog as Log;

class AiTradingAgent
{
    private string $apiUrl;
    private string $model;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.ai.url') ?: 'https://api.groq.com/openai/v1/chat/completions';
        $this->model = config('services.ai.model') ?: 'qwen/qwen3-32b';
        $this->apiKey = config('services.ai.key') ?: '';
    }

    public function analyzeBot(Bot $bot): ?AiAgentLog
    {
        $marketData = $this->gatherMarketData($bot->symbol);

        if (!$marketData) {
            Log::warning('AiTradingAgent: Could not gather market data', ['symbol' => $bot->symbol]);
            return null;
        }

        $botContext = $this->buildBotContext($bot, $marketData);
        $prompt = $this->buildAnalysisPrompt($botContext);

        $start = microtime(true);
        $response = $this->callLlm($prompt);
        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (!$response) {
            return null;
        }

        $parsed = $this->parseResponse($response);

        return AiAgentLog::create([
            'bot_id' => $bot->id,
            'symbol' => $bot->symbol,
            'action' => 'analyze',
            'signal' => $parsed['signal'] ?? 'neutral',
            'confidence' => $parsed['confidence'] ?? 0.5,
            'market_data' => $marketData,
            'reasoning' => $parsed['reasoning'] ?? $response['content'],
            'suggestion' => $parsed['suggestion'] ?? null,
            'model' => $this->model,
            'tokens_used' => $response['tokens'] ?? null,
            'latency_ms' => $latencyMs,
        ]);
    }

    public function gatherMarketData(string $symbol): ?array
    {
        try {
            $klines = $this->fetchKlines($symbol, '1h', 100);
            if (empty($klines)) {
                return null;
            }

            $closes = array_column($klines, 'close');
            $volumes = array_column($klines, 'volume');
            $highs = array_column($klines, 'high');
            $lows = array_column($klines, 'low');

            $currentPrice = end($closes);
            $rsi = $this->calculateRsi($closes, 14);
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
                'price' => round($currentPrice, 2),
                'chg24h' => round($priceChange24h, 2),
                'rsi' => round($rsi, 2),
                'sma20' => round($sma20, 2),
                'sma50' => round($sma50, 2),
                'macd' => round($macd, 2),
                'vol_ratio' => round($currentVolume / max($avgVolume, 1), 2),
                'atr' => round($atr, 2),
                'bb_upper' => round($bollingerBands['upper'], 2),
                'bb_lower' => round($bollingerBands['lower'], 2),
                'bb_mid' => round($bollingerBands['middle'], 2),
            ];
        } catch (\Exception $e) {
            Log::error('AiTradingAgent: gatherMarketData failed', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function fetchKlines(string $symbol, string $interval, int $limit): array
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
            'open_time' => $k[0],
            'open' => (float) $k[1],
            'high' => (float) $k[2],
            'low' => (float) $k[3],
            'close' => (float) $k[4],
            'volume' => (float) $k[5],
            'close_time' => $k[6],
        ], $response->json());
    }

    private function calculateRsi(array $closes, int $period): float
    {
        if (count($closes) < $period + 1) {
            return 50.0;
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
        return 100 - (100 / (1 + $rs));
    }

    private function calculateSma(array $data, int $period): float
    {
        $slice = array_slice($data, -$period);
        return array_sum($slice) / count($slice);
    }

    private function calculateEma(array $data, int $period): float
    {
        $multiplier = 2 / ($period + 1);
        $ema = $data[0];

        for ($i = 1; $i < count($data); $i++) {
            $ema = ($data[$i] - $ema) * $multiplier + $ema;
        }

        return $ema;
    }

    private function calculateAtr(array $highs, array $lows, array $closes, int $period): float
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

    private function calculateBollingerBands(array $closes, int $period, float $stdDevMultiplier): array
    {
        $slice = array_slice($closes, -$period);
        $mean = array_sum($slice) / count($slice);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $slice)) / count($slice);
        $stdDev = sqrt($variance);

        return [
            'upper' => $mean + ($stdDevMultiplier * $stdDev),
            'middle' => $mean,
            'lower' => $mean - ($stdDevMultiplier * $stdDev),
        ];
    }

    private function buildBotContext(Bot $bot, array $marketData): array
    {
        $openOrders = $bot->orders()->where('status', 'open')->count();
        $filledOrders = $bot->orders()->where('status', 'filled')->count();

        return [
            'bot' => [
                'id' => $bot->id,
                'symbol' => $bot->symbol,
                'price_lower' => (float) $bot->price_lower,
                'price_upper' => (float) $bot->price_upper,
                'grid_count' => $bot->grid_count,
                'investment' => (float) $bot->investment,
                'total_pnl' => (float) $bot->total_pnl,
                'grid_profit' => (float) $bot->grid_profit,
                'total_rounds' => $bot->total_rounds,
                'stop_loss_price' => $bot->stop_loss_price ? (float) $bot->stop_loss_price : null,
                'take_profit_price' => $bot->take_profit_price ? (float) $bot->take_profit_price : null,
                'open_orders' => $openOrders,
                'filled_orders' => $filledOrders,
            ],
            'market' => $marketData,
        ];
    }

    private function buildAnalysisPrompt(array $context): string
    {
        $m = $context['market'];
        $b = $context['bot'];

        $gridPos = $b['price_upper'] > $b['price_lower']
            ? round(($m['current_price'] - $b['price_lower']) / ($b['price_upper'] - $b['price_lower']) * 100, 1)
            : 50;

        return <<<PROMPT
{$m['symbol']} Price:{$m['current_price']} 24h:{$m['price_change_24h']}% RSI:{$m['rsi_14']} MACD:{$m['macd']} Vol:{$m['volume_ratio']}x ATR:{$m['atr_14']} BB:{$m['bollinger_lower']}/{$m['bollinger_upper']}
Bot: grid {$b['price_lower']}-{$b['price_upper']} ({$b['grid_count']}lvl) pos:{$gridPos}% PNL:{$b['total_pnl']} gridPnl:{$b['grid_profit']} open:{$b['open_orders']} filled:{$b['filled_orders']} SL:{$this->formatNullable($b['stop_loss_price'])} TP:{$this->formatNullable($b['take_profit_price'])}

Reply ONLY valid JSON, no extra text: {"signal":"bullish|bearish|neutral","confidence":0-1,"reasoning":"max 15 words","suggestion":{"action":"hold|set_sl|set_tp|adjust_grid|widen_range|narrow_range|stop_bot","new_sl":null,"new_tp":null}}
PROMPT;
    }

    private function formatNullable(?float $value): string
    {
        return $value ? (string) round($value, 1) : 'none';
    }

    private function callLlm(string $prompt): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Crypto grid bot analyst. JSON only, no markdown, no thinking. Keep reasoning under 15 words.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 300,
            ]);

            if (!$response->successful()) {
                Log::error('AiTradingAgent: LLM API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            $tokens = $data['usage']['total_tokens'] ?? null;

            if (!$content) {
                return null;
            }

            // Strip thinking tags (closed or unclosed)
            $content = preg_replace('/<think>.*?(<\/think>|$)/s', '', $content);
            $content = trim($content);

            if (!$content) {
                return null;
            }

            return [
                'content' => $content,
                'tokens' => $tokens,
            ];
        } catch (\Exception $e) {
            Log::error('AiTradingAgent: callLlm exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function parseResponse(?array $response): array
    {
        if (!$response || !isset($response['content'])) {
            return ['signal' => 'neutral', 'confidence' => 0, 'reasoning' => 'Failed to get LLM response'];
        }

        $content = $response['content'];

        // Strip LLM thinking tags (qwen3, deepseek, etc.)
        $content = preg_replace('/<think>.*?<\/think>/s', '', $content);

        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }

        $content = trim($content);
        $parsed = json_decode($content, true);

        // Handle truncated JSON: try to extract key fields with regex
        if (!$parsed) {
            $signal = 'neutral';
            $confidence = 0.5;
            $reasoning = $content;

            if (preg_match('/"signal"\s*:\s*"(bullish|bearish|neutral)"/', $content, $m)) {
                $signal = $m[1];
            }
            if (preg_match('/"confidence"\s*:\s*([\d.]+)/', $content, $m)) {
                $confidence = (float) $m[1];
            }
            if (preg_match('/"reasoning"\s*:\s*"([^"]+)"/', $content, $m)) {
                $reasoning = $m[1];
            }

            $suggestion = null;
            if (preg_match('/"action"\s*:\s*"([^"]+)"/', $content, $m)) {
                $suggestion = ['action' => $m[1], 'details' => ''];
                if (preg_match('/"details"\s*:\s*"([^"]+)"/', $content, $m2)) {
                    $suggestion['details'] = $m2[1];
                }
            }

            return compact('signal', 'confidence', 'reasoning', 'suggestion');
        }

        return [
            'signal' => $parsed['signal'] ?? 'neutral',
            'confidence' => (float) ($parsed['confidence'] ?? 0.5),
            'reasoning' => $parsed['reasoning'] ?? 'No reasoning provided',
            'suggestion' => $parsed['suggestion'] ?? null,
        ];
    }

    public function applysuggestion(Bot $bot, AiAgentLog $log): bool
    {
        $suggestion = $log->suggestion;

        if (!$suggestion || !isset($suggestion['action'])) {
            return false;
        }

        $action = $suggestion['action'];

        Log::info('AiTradingAgent: Applying suggestion', [
            'bot_id' => $bot->id,
            'action' => $action,
            'suggestion' => $suggestion,
        ]);

        $applied = false;

        switch ($action) {
            case 'set_sl':
                if (isset($suggestion['new_sl']) && $suggestion['new_sl'] > 0) {
                    $bot->update(['stop_loss_price' => $suggestion['new_sl']]);
                    $applied = true;
                }
                break;

            case 'set_tp':
                if (isset($suggestion['new_tp']) && $suggestion['new_tp'] > 0) {
                    $bot->update(['take_profit_price' => $suggestion['new_tp']]);
                    $applied = true;
                }
                break;

            case 'stop_bot':
                if ($log->confidence >= 0.8) {
                    app(GridTradingEngine::class)->stopBot($bot);
                    $applied = true;
                }
                break;

            default:
                // hold, adjust_grid, widen_range, narrow_range: log only, don't auto-apply
                break;
        }

        if ($applied) {
            $log->update(['applied' => true]);
        }

        return $applied;
    }
}
