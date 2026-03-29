<?php

namespace App\Jobs;

use App\Enums\AgentTrigger;
use App\Enums\BotStatus;
use App\Models\AiConversation;
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
    public int $timeout = 300;
    public int $uniqueFor = 240;

    public function uniqueId(): string
    {
        return 'agent_consultation_all';
    }

    public function handle(AgentOrchestrator $orchestrator): void
    {
        $bots = Bot::where('status', BotStatus::Active)->get();

        foreach ($bots as $bot) {
            try {
                if (!$this->shouldConsult($bot)) {
                    Log::debug('RunAgentConsultationJob: skipped (interval not reached)', ['bot_id' => $bot->id]);
                    continue;
                }

                Log::info('RunAgentConsultationJob: consulting agent for bot', ['bot_id' => $bot->id]);

                $conversation = $orchestrator->consult($bot, AgentTrigger::Scheduled);

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

    private function shouldConsult(Bot $bot): bool
    {
        if (!$bot->ai_agent_enabled) {
            return false;
        }

        $intervalMinutes = (int) $bot->ai_consultation_interval;

        if ($intervalMinutes <= 0 || empty($bot->ai_system_prompt)) {
            return false;
        }

        // Dynamic frequency: use ai_next_consultation_at if the agent set it
        if ($bot->ai_next_consultation_at !== null) {
            return now()->gte($bot->ai_next_consultation_at);
        }

        // Fallback: static interval from bot config
        $lastSuccess = AiConversation::where('bot_id', $bot->id)
            ->where('status', 'completed')
            ->where('total_tokens', '>', 0)
            ->latest()
            ->first();

        return !$lastSuccess || $lastSuccess->ended_at->lt(now()->subMinutes($intervalMinutes));
    }

    public function tags(): array
    {
        return ['agent-consultation'];
    }
}
