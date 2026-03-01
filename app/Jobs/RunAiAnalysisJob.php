<?php

namespace App\Jobs;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\AiTradingAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\BotLog as Log;

class RunAiAnalysisJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;
    public int $uniqueFor = 300; // 5 minutes uniqueness

    public function uniqueId(): string
    {
        return 'ai_analysis_all';
    }

    public function handle(AiTradingAgent $agent): void
    {
        $bots = Bot::where('status', BotStatus::Active)->get();

        foreach ($bots as $bot) {
            try {
                $log = $agent->analyzeBot($bot);

                if ($log) {
                    Log::info('AiTradingAgent: Analysis complete', [
                        'bot_id' => $bot->id,
                        'signal' => $log->signal,
                        'confidence' => $log->confidence,
                        'action' => $log->suggestion['action'] ?? 'none',
                    ]);

                    if (
                        $log->suggestion &&
                        in_array($log->suggestion['action'] ?? '', ['set_sl', 'set_tp']) &&
                        $log->confidence >= 0.7
                    ) {
                        $agent->applySuggestion($bot, $log);
                    }
                }
            } catch (\Exception $e) {
                Log::error('AiTradingAgent: Bot analysis failed', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function tags(): array
    {
        return ['ai-analysis'];
    }
}
