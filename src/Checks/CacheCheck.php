<?php

namespace Rakib\ServerLens\Checks;

use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Data\CheckResult;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CacheCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Cache';
    }

    public function icon(): string
    {
        return 'sl-icon-layers';
    }

    public function run(): CheckResult
    {
        $key   = 'server_lens_cache_probe_' . uniqid('', true);
        $value = 'ok';
        $start = microtime(true);

        try {
            Cache::put($key, $value, 10);
            $read = Cache::get($key);
            Cache::forget($key);

            $ms = round((microtime(true) - $start) * 1000, 2);

            if ($read !== $value) {
                return CheckResult::warning('Read/write mismatch detected', $ms);
            }

            return CheckResult::healthy('Round-trip in ' . $ms . ' ms', $ms);
        } catch (Throwable $e) {
            return CheckResult::critical('Cache error: ' . $e->getMessage());
        }
    }
}
