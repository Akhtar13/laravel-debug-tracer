<?php

namespace Akhtar\LaravelDebugTracer\Console\Commands;

use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Illuminate\Console\Command;

class CleanupDebugTracesCommand extends Command
{
    protected $signature = 'debug-tracer:cleanup';

    protected $description = 'Delete stopped/expired debug trace files.';

    public function handle(TraceStorage $storage): int
    {
        $deleted = $storage->cleanupExpiredAndStoppedSessions();

        $this->info("Deleted {$deleted} trace session(s).");

        return self::SUCCESS;
    }
}
