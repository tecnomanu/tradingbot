<?php

namespace App\Console\Commands;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Services\Agent\AgentOrchestrator;
use Illuminate\Console\Command;

/**
 * Manual localhost test for the SL/TP → agent alert flow.
 *
 * Usage:
 *   php artisan bot:test-alert              # uses first active bot
 *   php artisan bot:test-alert 1            # uses bot #1 (any status)
 *   php artisan bot:test-alert 1 --type=tp  # simulate Take-Profit alert
 */
class TestSlTpAlertCommand extends Command
{
    protected $signature = 'bot:test-alert
                            {botId? : Bot ID to test (default: first active bot)}
                            {--type=sl : Alert type: sl (Stop-Loss) or tp (Take-Profit)}';

    protected $description = 'Simulate an SL/TP price alert and verify the agent consultation fires instead of a hard stop';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $botId = $this->argument('botId');
        $type  = $this->option('type') === 'tp' ? 'take_profit' : 'stop_loss';

        $bot = $botId
            ? Bot::find($botId)
            : Bot::where('status', BotStatus::Active)->first();

        if (!$bot) {
            $this->error('No bot found. Pass a bot ID or start a bot first.');
            return self::FAILURE;
        }

        $this->line('');
        $this->line("  <fg=cyan>Bot #{$bot->id}</> — {$bot->name} ({$bot->symbol}) — <fg=yellow>{$bot->status->value}</>");
        $this->line("  SL: " . ($bot->stop_loss_price ?? '—') . "   TP: " . ($bot->take_profit_price ?? '—'));
        $this->line('');

        $fakePrice = $type === 'stop_loss'
            ? ($bot->price_lower - 1000)
            : ($bot->price_upper + 1000);

        $slTpValue = $type === 'stop_loss' ? $bot->stop_loss_price : $bot->take_profit_price;

        $alertContext = $type === 'stop_loss'
            ? "CRITICAL: Stop-Loss price reached. Current price: {$fakePrice}, SL: {$slTpValue}. Immediate action required: check position, consider close_position + adjust_grid or stop_bot."
            : "CRITICAL: Take-Profit price reached. Current price: {$fakePrice}, TP: {$slTpValue}. Immediate action required: close_position to realize profits, then adjust_grid or stop_bot.";

        $this->info('Dispatching agent consultation (trigger: sl_tp_alert)...');
        $this->line("  Context: <fg=yellow>{$alertContext}</>");
        $this->line('');

        $start = microtime(true);

        $conversation = $orchestrator->consult($bot, 'sl_tp_alert', $alertContext);

        $elapsed = round((microtime(true) - $start) * 1000);

        $this->line('');
        $this->line('─────────────────────────────────────────');
        $statusColor = $conversation->status === 'completed' ? 'green' : 'red';
        $this->line("  Status    : <fg={$statusColor}>{$conversation->status}</>");
        $this->line("  Duration  : {$elapsed}ms");
        $this->line("  Tool calls: {$conversation->total_tool_calls}");
        $this->line("  Tokens    : {$conversation->total_tokens}");
        $this->line('');
        $this->line('  <fg=cyan>Summary:</> ' . ($conversation->summary ?? '—'));
        $this->line('');

        if (!empty($conversation->actions_taken)) {
            $this->line('  <fg=yellow>Actions taken:</>');
            foreach ($conversation->actions_taken as $action) {
                $this->line("    • {$action}");
            }
            $this->line('');
        } else {
            $this->line('  <fg=green>No actions taken</> (agent chose to observe)');
            $this->line('');
        }

        // Check bot was NOT silently stopped
        $bot->refresh();
        $isActive = $bot->status === BotStatus::Active;
        $statusLabel = $isActive
            ? '<fg=green>✓ Still ACTIVE</> (agent managed the situation)'
            : '<fg=red>✗ Bot is ' . $bot->status->value . '</> (agent decided to stop)';
        $this->line("  Bot status: {$statusLabel}");

        // Check action log
        $alertLog = BotActionLog::where('bot_id', $bot->id)
            ->where('action', 'bot_sl_tp_alert')
            ->latest()
            ->first();
        if ($alertLog) {
            $this->line("  Alert log : <fg=green>✓ bot_sl_tp_alert</> logged (id #{$alertLog->id})");
        } else {
            $this->line("  Alert log : <fg=yellow>⚠ No bot_sl_tp_alert log found</>");
        }

        $this->line('');
        $this->line("  View conversation at: /ai-agent/conversations/{$conversation->id}");
        $this->line('─────────────────────────────────────────');
        $this->line('');

        return self::SUCCESS;
    }
}
