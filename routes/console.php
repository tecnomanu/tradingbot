<?php

use App\Jobs\ProcessAllActiveBotsJob;
use App\Jobs\RunAgentConsultationJob;
use App\Jobs\RunAiAnalysisJob;
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

Schedule::job(new RunAiAnalysisJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('run-ai-analysis');

Schedule::job(new RunAgentConsultationJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('run-agent-consultation');
