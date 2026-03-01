<?php

namespace App\Jobs;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\GridTradingEngine;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\BotLog as Log;

class InitializeBotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private Bot $bot,
    ) {}

    public function handle(GridTradingEngine $engine): void
    {
        $this->bot->refresh();

        if ($this->bot->status !== BotStatus::Pending && $this->bot->status !== BotStatus::Stopped) {
            Log::info('InitializeBotJob: bot not in expected state, skipping', [
                'bot_id' => $this->bot->id,
                'status' => $this->bot->status->value,
            ]);
            return;
        }

        try {
            $engine->initializeBot($this->bot);
        } catch (Exception $e) {
            Log::error('InitializeBotJob: failed to initialize', [
                'bot_id' => $this->bot->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->bot->update(['status' => BotStatus::Error]);
            }

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['bot:' . $this->bot->id, 'initialize'];
    }
}
