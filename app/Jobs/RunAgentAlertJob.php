<?php

namespace App\Jobs;

use App\Enums\AgentTrigger;
use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\Agent\AgentOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\BotLog as Log;

/**
 * Triggers a one-off agent consultation for a specific bot when a critical
 * condition is detected (e.g. SL/TP price level reached). The agent decides
 * the appropriate response: close position, adjust grid, set tighter SL, or stop.
 *
 * Using ShouldBeUnique prevents duplicate alert consultations for the same
 * bot+trigger within the uniqueFor window.
 */
class RunAgentAlertJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;
    public int $uniqueFor = 300; // 5 min cooldown per bot+trigger to avoid storm

    public function __construct(
        private Bot $bot,
        private AgentTrigger $trigger,
        private string $alertContext,
    ) {}

    public function uniqueId(): string
    {
        return "agent_alert_{$this->bot->id}_{$this->trigger->value}";
    }

    public function handle(AgentOrchestrator $orchestrator): void
    {
        $this->bot->refresh();

        if ($this->bot->status !== BotStatus::Active) {
            Log::info('RunAgentAlertJob: bot not active, skipping', [
                'bot_id' => $this->bot->id,
            'trigger' => $this->trigger->value,
        ]);
            return;
        }

        Log::info('RunAgentAlertJob: starting alert consultation', [
            'bot_id' => $this->bot->id,
            'trigger' => $this->trigger->value,
        ]);

        $conversation = $orchestrator->consult($this->bot, $this->trigger, $this->alertContext);

        Log::info('RunAgentAlertJob: alert consultation complete', [
            'bot_id' => $this->bot->id,
            'trigger' => $this->trigger->value,
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'tools_used' => $conversation->total_tool_calls,
        ]);
    }

    public function tags(): array
    {
        return ['bot:' . $this->bot->id, 'agent-alert', $this->trigger->value];
    }
}
