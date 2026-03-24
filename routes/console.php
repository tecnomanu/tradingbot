<?php

use App\Jobs\ProcessAllActiveBotsJob;
use App\Jobs\ProcessReentryJob;
use App\Jobs\RunAgentConsultationJob;
use App\Jobs\TakePnlSnapshotJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ProcessAllActiveBotsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->name('process-all-active-bots');

Schedule::job(new TakePnlSnapshotJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('take-pnl-snapshots');

Schedule::job(new RunAgentConsultationJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('run-agent-consultation');

Schedule::job(new ProcessReentryJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('process-reentry');
