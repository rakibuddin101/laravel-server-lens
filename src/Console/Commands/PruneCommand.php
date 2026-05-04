<?php

namespace Rakib\ServerLens\Console\Commands;

use Rakib\ServerLens\Models\TrafficLog;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature   = 'server-lens:prune {--days= : Override the configured retention days}';
    protected $description = 'Delete traffic logs older than the configured retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('server-lens.prune_after_days', 30));

        if ($days < 1) {
            $this->error('Days must be at least 1.');
            return self::FAILURE;
        }

        $cutoff  = now()->subDays($days);
        $deleted = TrafficLog::where('created_at', '<', $cutoff)->delete();

        $this->components->info(
            "Pruned {$deleted} traffic log " . ($deleted === 1 ? 'entry' : 'entries') .
            " older than {$days} days (before {$cutoff->toDateString()})."
        );

        return self::SUCCESS;
    }
}
