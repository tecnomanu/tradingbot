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
     * Format Risk Guard trigger notification for Telegram.
     */
    public function formatRiskGuardNotification(
        string $botName,
        string $symbol,
        string $reason,
        string $level = 'hard',
        string $action = 'stop_bot_only',
        string $drawdownMode = 'peak_equity_drawdown',
    ): string {
        $emoji = $level === 'soft' ? '⚠️' : '🚨';
        $levelLabel = $level === 'soft' ? 'SOFT GUARD' : 'HARD GUARD';
        $modeLabel = $drawdownMode === 'initial_capital_loss' ? 'Pérdida s/ capital' : 'Drawdown desde pico';

        $actionLabels = [
            'stop_bot_only' => 'Bot detenido',
            'close_position_and_stop' => 'Posición cerrada + bot detenido',
            'pause_and_rebuild' => 'Pausado para re-entry automático',
            'notify_only' => 'Solo notificación (bot sigue activo)',
        ];

        $lines = [
            "{$emoji} <b>{$levelLabel} — {$botName}</b> ({$symbol})",
            '',
            $reason,
            '',
            "<b>Modo:</b> {$modeLabel}",
        ];

        if ($level === 'hard') {
            $lines[] = "<b>Acción:</b> " . ($actionLabels[$action] ?? $action);
        } else {
            $lines[] = '<b>Acción:</b> Protección activa (bot sigue operando)';
        }

        if ($level === 'soft') {
            $lines[] = '';
            $lines[] = '🛡 El bot entró en modo protección: sin rebuilds, agente conservador.';
        } elseif ($action === 'pause_and_rebuild') {
            $lines[] = '';
            $lines[] = '🔄 Se intentará re-entry automático cuando las condiciones mejoren.';
        } else {
            $lines[] = '';
            $lines[] = '⚠️ Revisá el bot y reiniciá manualmente si corresponde.';
        }

        return implode("\n", $lines);
    }

    /**
     * Format re-entry notification for Telegram.
     */
    public function formatReentryNotification(
        string $botName,
        string $symbol,
        bool $success,
        string $reason,
    ): string {
        if ($success) {
            return implode("\n", [
                "🔄 <b>Re-entry exitoso — {$botName}</b> ({$symbol})",
                '',
                $reason,
                '',
                '✅ El bot fue reactivado con grid reconstruido.',
            ]);
        }

        return implode("\n", [
            "⏸ <b>Re-entry bloqueado — {$botName}</b> ({$symbol})",
            '',
            "<b>Motivo:</b> {$reason}",
            '',
            '⏳ Se reintentará en el próximo ciclo.',
        ]);
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
                'sl_set' => '🛡 Stop-Loss configurado',
                'tp_set' => '🎯 Take-Profit configurado',
                'stop_loss_set' => '🛡 Stop-Loss configurado',
                'take_profit_set' => '🎯 Take-Profit configurado',
                'bot_stopped' => '🛑 Bot detenido',
                'position_closed' => '💰 Posición cerrada',
                'orders_cancelled' => '❌ Órdenes canceladas',
                'risk_guard_triggered' => '🚨 Risk Guard disparado',
                'price_out_of_range' => '⚠️ Precio fuera de rango',
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
