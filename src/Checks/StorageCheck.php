<?php

namespace Rakib\ServerLens\Checks;

use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Data\CheckResult;
use Throwable;

class StorageCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Storage';
    }

    public function icon(): string
    {
        return 'sl-icon-hard-drive';
    }

    public function run(): CheckResult
    {
        $path  = storage_path('app/.server_lens_probe');
        $start = microtime(true);

        try {
            file_put_contents($path, 'ok');
            $read = file_get_contents($path);
            @unlink($path);

            $ms = round((microtime(true) - $start) * 1000, 2);

            if ($read !== 'ok') {
                return CheckResult::warning('Write/read mismatch on storage disk', $ms);
            }

            return CheckResult::healthy('Read/write OK in ' . $ms . ' ms', $ms);
        } catch (Throwable $e) {
            return CheckResult::critical('Storage not writable: ' . $e->getMessage());
        }
    }
}
