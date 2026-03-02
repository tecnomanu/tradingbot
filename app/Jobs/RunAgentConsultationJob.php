<?php

namespace App\Jobs;

use App\Enums\BotStatus;
use App\Models\AiAgentLog;
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
    public int $uniqueFor = 900;

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
                    Log::debug('RunAgentConsultationJob: skipped (no changes)', ['bot_id' => $bot->id]);
                    continue;
                }

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

    private function shouldConsult(Bot $bot): bool
    {
        // Always consult if no successful consultation in the last hour
        $lastSuccess = AiConversation::where('bot_id', $bot->id)
            ->where('status', 'completed')
            ->where('total_tokens', '>', 0)
            ->latest()
            ->first();

        if (!$lastSuccess || $lastSuccess->ended_at->lt(now()->subHour())) {
            return true;
        }

        // Consult if recent analyses show non-neutral signal or actionable suggestion
        $recentLogs = AiAgentLog::where('bot_id', $bot->id)
            ->where('created_at', '>=', now()->subMinutes(20))
            ->latest()
            ->limit(3)
            ->get();

        if ($recentLogs->isEmpty()) {
            return true;
        }

        foreach ($recentLogs as $log) {
            if ($log->signal !== 'neutral') {
                return true;
            }
            $action = $log->suggestion['action'] ?? 'hold';
            if (!in_array($action, ['hold', 'adjust_grid'])) {
                return true;
            }
        }

        return false;
    }

    public function tags(): array
    {
        return ['agent-consultation'];
    }
}
