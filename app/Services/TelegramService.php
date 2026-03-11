<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $baseUrl;
    private ?string $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    public function sendMessage(string $chatId, string $text, ?string $parseMode = 'HTML'): bool
    {
        if (!$this->isConfigured() || empty($chatId)) {
            return false;
        }

        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::warning('TelegramService: failed to send message', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('TelegramService: exception sending message', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function setWebhook(string $url): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $response = Http::post("{$this->baseUrl}/setWebhook", [
            'url' => $url,
            'allowed_updates' => ['message'],
        ]);

        return $response->json() ?? ['ok' => false];
    }

    public function getWebhookInfo(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        return Http::get("{$this->baseUrl}/getWebhookInfo")->json() ?? [];
    }

    /**
     * Format agent consultation result for Telegram.
     */
    public function formatAgentNotification(
        string $botName,
        string $symbol,
        array $actions,
        ?string $summary,
        ?string $analysis
    ): string {
        $emoji = empty($actions) ? '📊' : '⚡';
        $header = "{$emoji} <b>{$botName}</b> ({$symbol})";

        $lines = [$header, ''];

        if (!empty($actions)) {
            $actionLabels = [
                'grid_adjusted' => '🔄 Grid ajustado',
                'stop_loss_set' => '🛡 Stop Loss configurado',
                'take_profit_set' => '🎯 Take Profit configurado',
                'bot_stopped' => '🛑 Bot detenido',
                'position_closed' => '💰 Posición cerrada',
                'orders_cancelled' => '❌ Órdenes canceladas',
            ];

            $lines[] = '<b>Acciones:</b>';
            foreach ($actions as $action) {
                $label = $actionLabels[$action] ?? "• {$action}";
                $lines[] = $label;
            }
            $lines[] = '';
        }

        if ($summary) {
            $lines[] = "<b>Resumen:</b> {$summary}";
        }

        if ($analysis) {
            $truncated = mb_strlen($analysis) > 500
                ? mb_substr($analysis, 0, 500) . '…'
                : $analysis;
            $lines[] = '';
            $lines[] = "<i>{$truncated}</i>";
        }

        return implode("\n", $lines);
    }
}
