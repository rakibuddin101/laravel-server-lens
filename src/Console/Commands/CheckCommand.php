<?php

namespace Rakib\ServerLens\Console\Commands;

use Rakib\ServerLens\Checks\ApplicationResponseCheck;
use Rakib\ServerLens\Checks\CacheCheck;
use Rakib\ServerLens\Checks\DatabaseCheck;
use Rakib\ServerLens\Checks\QueueCheck;
use Rakib\ServerLens\Checks\RedisCheck;
use Rakib\ServerLens\Checks\StorageCheck;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature   = 'server-lens:check';
    protected $description = 'Run all health checks and display results in the terminal';

    public function handle(): int
    {
        $this->info('Running Server Lens health checks...');
        $this->newLine();

        $checks = [
            new ApplicationResponseCheck(),
            new DatabaseCheck(),
            new CacheCheck(),
            new QueueCheck(),
            new RedisCheck(),
            new StorageCheck(),
        ];

        $rows    = [];
        $hasWarn = false;
        $hasCrit = false;

        foreach ($checks as $check) {
            $result = $check->run();

            $status = match ($result->status) {
                'healthy'  => '<fg=green>✓ Healthy</>',
                'warning'  => '<fg=yellow>⚠ Warning</>',
                'critical' => '<fg=red>✗ Critical</>',
                default    => '<fg=gray>– Inactive</>',
            };

            $latency = $result->latencyMs !== null ? round($result->latencyMs) . ' ms' : '—';

            $rows[] = [$check->name(), $status, $result->detail, $latency];

            if ($result->status === 'warning')  $hasWarn = true;
            if ($result->status === 'critical') $hasCrit = true;
        }

        $this->table(['Check', 'Status', 'Detail', 'Latency'], $rows);

        $this->newLine();

        if ($hasCrit) {
            $this->error('One or more checks are in a CRITICAL state.');
            return self::FAILURE;
        }

        if ($hasWarn) {
            $this->warn('One or more checks have warnings.');
            return self::SUCCESS;
        }

        $this->components->info('All checks passed.');

        return self::SUCCESS;
    }
}
