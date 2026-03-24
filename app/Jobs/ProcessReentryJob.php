<?php

namespace App\Jobs;

use App\Services\ReentryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReentryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReentryService $reentryService): void
    {
        $processed = $reentryService->processAutomaticReentries();

        if ($processed > 0) {
            Log::info("ProcessReentryJob: {$processed} bot(s) re-entered successfully");
        }
    }
}
