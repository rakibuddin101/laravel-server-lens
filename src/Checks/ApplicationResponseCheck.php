<?php

namespace Rakib\ServerLens\Checks;

use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Data\CheckResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApplicationResponseCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Application';
    }

    public function icon(): string
    {
        return 'sl-icon-activity';
    }

    public function run(): CheckResult
    {
        $url   = rtrim(config('app.url', 'http://localhost'), '/') . '/up';
        $start = microtime(true);

        try {
            $response = Http::timeout(5)->get($url);
            $ms = round((microtime(true) - $start) * 1000, 2);
            $code = $response->status();

            if ($code >= 500) {
                return CheckResult::critical('HTTP ' . $code . ' in ' . $ms . ' ms', $ms);
            }

            if ($ms > (float) config('server-lens.thresholds.response_ms.critical', 1500)) {
                return CheckResult::critical('Very slow: ' . $ms . ' ms (HTTP ' . $code . ')', $ms);
            }

            if ($ms > (float) config('server-lens.thresholds.response_ms.warning', 500)) {
                return CheckResult::warning('Slow: ' . $ms . ' ms (HTTP ' . $code . ')', $ms);
            }

            return CheckResult::healthy('HTTP ' . $code . ' in ' . $ms . ' ms', $ms);
        } catch (Throwable $e) {
            return CheckResult::critical('Unreachable: ' . $e->getMessage());
        }
    }
}
