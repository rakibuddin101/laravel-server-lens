<?php

namespace Rakib\ServerLens\Checks;

use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Data\CheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Database';
    }

    public function icon(): string
    {
        return 'sl-icon-database';
    }

    public function run(): CheckResult
    {
        $start = microtime(true);

        try {
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000, 2);

            if ($ms > 300) {
                return CheckResult::warning('Connected but slow — ' . $ms . ' ms', $ms);
            }

            return CheckResult::healthy('Connected in ' . $ms . ' ms', $ms);
        } catch (Throwable $e) {
            return CheckResult::critical('Connection failed: ' . $e->getMessage());
        }
    }
}
