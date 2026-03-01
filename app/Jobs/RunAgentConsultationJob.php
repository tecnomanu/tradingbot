<?php

namespace App\Jobs;

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

class RunAgentConsultationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 min max
    public int $uniqueFor = 900; // 15 min uniqueness

    public function uniqueId(): string
    {
        return 'agent_consultation_all';
    }

    public function handle(AgentOrchestrator $orchestrator): void
    {
        $bots = Bot::where('status', BotStatus::Active)->get();

        foreach ($bots as $bot) {
            try {
                Log::info('RunAgentConsultationJob: consulting agent for bot', ['bot_id' => $bot->id]);

                $conversation = $orchestrator->consult($bot, 'scheduled');

                Log::info('RunAgentConsultationJob: consultation complete', [
                    'bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'status' => $conversation->status,
                    'tools_used' => $conversation->total_tool_calls,
                ]);
            } catch (\Exception $e) {
                Log::error('RunAgentConsultationJob: failed', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function tags(): array
    {
        return ['agent-consultation'];
    }
}
