<?php

namespace App\Jobs;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\PnlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\BotLog as Log;

class TakePnlSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(PnlService $pnlService): void
    {
        $bots = Bot::where('status', BotStatus::Active)->get();

        foreach ($bots as $bot) {
            try {
                $pnlService->takeSnapshot($bot);
            } catch (\Exception $e) {
                Log::error('TakePnlSnapshotJob: error', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function tags(): array
    {
        return ['pnl-snapshot'];
    }
}
