<?php

namespace Rakib\ServerLens\Checks;

use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Data\CheckResult;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Redis';
    }

    public function icon(): string
    {
        return 'sl-icon-server';
    }

    public function run(): CheckResult
    {
        if (config('database.redis.default.host', '') === '') {
            return CheckResult::inactive('Not configured');
        }

        $start = microtime(true);

        try {
            $response = Redis::ping();
            $ms = round((microtime(true) - $start) * 1000, 2);

            $pong = is_string($response) ? strtoupper(trim($response)) : 'PONG';

            return CheckResult::healthy($pong . ' in ' . $ms . ' ms', $ms);
        } catch (Throwable $e) {
            return CheckResult::critical('Redis unreachable: ' . $e->getMessage());
        }
    }
}
