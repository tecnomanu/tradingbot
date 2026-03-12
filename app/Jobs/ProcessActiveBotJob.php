<?php

namespace App\Jobs;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\GridTradingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\BotLog as Log;

class ProcessActiveBotJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private Bot $bot,
    ) {}

    public function uniqueId(): string
    {
        return 'process_bot_' . $this->bot->id;
    }

    public function handle(GridTradingEngine $engine): void
    {
        $this->bot->refresh();

        if ($this->bot->status !== BotStatus::Active) {
            return;
        }

        try {
            $engine->processBot($this->bot);
        } catch (\Exception $e) {
            Log::error('ProcessActiveBotJob: error', [
                'bot_id' => $this->bot->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function tags(): array
    {
        return ['bot:' . $this->bot->id, 'process'];
    }
}
