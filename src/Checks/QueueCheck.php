<?php

namespace Rakib\ServerLens\Checks;

use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Data\CheckResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class QueueCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Queue';
    }

    public function icon(): string
    {
        return 'sl-icon-list';
    }

    public function run(): CheckResult
    {
        $driver = config('queue.default', 'sync');

        if ($driver === 'sync') {
            return CheckResult::inactive('Driver is sync — no worker needed');
        }

        if ($driver !== 'database') {
            return CheckResult::healthy('Driver: ' . $driver . ' (not probed)');
        }

        try {
            $pending = Schema::hasTable('jobs')
                ? (int) DB::table('jobs')->count()
                : 0;

            $failed = Schema::hasTable('failed_jobs')
                ? (int) DB::table('failed_jobs')->count()
                : 0;

            if ($failed > 0) {
                return CheckResult::warning($pending . ' pending, ' . $failed . ' failed');
            }

            if ($pending > 100) {
                return CheckResult::warning($pending . ' jobs pending — backlog detected');
            }

            return CheckResult::healthy($pending . ' pending, ' . $failed . ' failed');
        } catch (Throwable $e) {
            return CheckResult::critical('Queue check failed: ' . $e->getMessage());
        }
    }
}
