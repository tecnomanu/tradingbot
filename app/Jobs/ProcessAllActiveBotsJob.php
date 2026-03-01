<?php

namespace App\Jobs;

use App\Enums\BotStatus;
use App\Models\Bot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches a ProcessActiveBotJob for each active bot.
 * Runs on a schedule (e.g. every 15 seconds) to keep the grid engine alive.
 */
class ProcessAllActiveBotsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $uniqueFor = 30;

    public function handle(): void
    {
        $bots = Bot::where('status', BotStatus::Active)
            ->with('binanceAccount')
            ->get();

        foreach ($bots as $bot) {
            ProcessActiveBotJob::dispatch($bot);
        }
    }

    public function tags(): array
    {
        return ['process-all-bots'];
    }
}
