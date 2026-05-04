<?php

namespace Rakib\ServerLens\Services;

use Illuminate\Support\Facades\Process;

class HostMetricReader
{
    private const CMD = [
        'vm_stat'    => '/usr/bin/vm_stat',
        'sysctl'     => '/usr/sbin/sysctl',
        'top_darwin' => '/usr/bin/top',
        'top_linux'  => '/usr/bin/top',
        'free'       => '/usr/bin/free',
        'nproc'      => '/usr/bin/nproc',
        'grep'       => '/usr/bin/grep',
    ];

    public function read(): array
    {
        $cpuCores    = $this->cpuCores();
        $loadAverage = $this->loadAverage();

        return [
            'host' => [
                'hostname' => gethostname() ?: php_uname('n'),
                'os'       => php_uname('s') . ' ' . php_uname('r'),
                'cores'    => $cpuCores,
            ],
            'load_average' => $loadAverage,
            'cpu'          => $this->cpuUsage($cpuCores, $loadAverage),
            'memory'       => $this->memoryUsage(),
            'disk'         => $this->diskUsage(),
            'uptime'       => $this->uptime(),
        ];
    }

    // ── CPU ───────────────────────────────────────────────────────────────────

    private function cpuUsage(int $cpuCores, array $loadAverage): array
    {
        $usage = match (PHP_OS_FAMILY) {
            'Darwin' => $this->darwinCpuUsage(),
            'Linux'  => $this->linuxCpuUsage(),
            default  => null,
        };

        $source = 'top';

        if ($usage === null) {
            $usage  = $this->approximateCpuUsage($cpuCores, $loadAverage);
            $source = 'load_average';
        }

        return [
            'usage_percent' => $usage !== null ? round($usage, 1) : null,
            'source'        => $source,
        ];
    }

    private function darwinCpuUsage(): ?float
    {
        $cmd    = self::CMD['top_darwin'] . ' -l 1 -n 0 | ' . self::CMD['grep'] . ' "CPU usage"';
        $output = $this->run($cmd);

        if (!$output || !preg_match('/CPU usage:\s+([\d.]+)%\s+user,\s+([\d.]+)%\s+sys,\s+([\d.]+)%\s+idle/i', $output, $m)) {
            return null;
        }

        return max(0.0, min(100.0, 100.0 - (float) $m[3]));
    }

    private function linuxCpuUsage(): ?float
    {
        $output = $this->run('LANG=C ' . self::CMD['top_linux'] . ' -bn1 | ' . self::CMD['grep'] . " -E '%Cpu|Cpu\\(s\\)'");

        if (!$output || !preg_match('/([\d.]+)\s*id/i', str_replace(',', '.', $output), $m)) {
            return null;
        }

        return max(0.0, min(100.0, 100.0 - (float) $m[1]));
    }

    private function approximateCpuUsage(int $cpuCores, array $loadAverage): ?float
    {
        if ($cpuCores < 1 || empty($loadAverage)) {
            return null;
        }

        return max(0.0, min(100.0, ($loadAverage[0] / $cpuCores) * 100.0));
    }

    // ── Memory ────────────────────────────────────────────────────────────────

    private function memoryUsage(): array
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $this->darwinMemory(),
            'Linux'  => $this->linuxMemory(),
            default  => ['total_bytes' => null, 'used_bytes' => null, 'available_bytes' => null],
        };
    }

    private function darwinMemory(): array
    {
        $null    = ['total_bytes' => null, 'used_bytes' => null, 'available_bytes' => null];
        $vmStat  = $this->run(self::CMD['vm_stat']);
        $memSize = $this->run(self::CMD['sysctl'] . ' -n hw.memsize');

        if (!$vmStat || !$memSize) {
            return $null;
        }

        if (!preg_match('/page size of (\d+) bytes/i', $vmStat, $pageSizeMatch)) {
            return $null;
        }

        $pageSize   = (int) $pageSizeMatch[1];
        $totalBytes = (int) trim($memSize);

        if ($pageSize <= 0 || $totalBytes <= 0) {
            return $null;
        }

        $wired      = $this->vmStatPages($vmStat, 'Pages wired down');
        $anonymous  = $this->vmStatPages($vmStat, 'Anonymous pages');
        $compressor = $this->vmStatPages($vmStat, 'Pages occupied by compressor');

        $usedBytes      = ($wired + $anonymous + $compressor) * $pageSize;
        $availableBytes = max(0, $totalBytes - $usedBytes);

        return [
            'total_bytes'     => $totalBytes,
            'used_bytes'      => max(0, $usedBytes),
            'available_bytes' => $availableBytes,
        ];
    }

    private function linuxMemory(): array
    {
        $null = ['total_bytes' => null, 'used_bytes' => null, 'available_bytes' => null];

        $meminfo = @file_get_contents('/proc/meminfo');

        if ($meminfo) {
            return $this->parseMeminfo($meminfo) ?? $null;
        }

        $output = $this->run(self::CMD['free'] . ' -b');

        if (!$output) {
            return $null;
        }

        foreach (preg_split("/\r\n|\n|\r/", trim($output)) as $line) {
            if (!str_starts_with(trim($line), 'Mem:')) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));

            if (count($parts) < 7) {
                break;
            }

            $totalBytes     = (int) ($parts[1] ?? 0);
            $availableBytes = (int) ($parts[6] ?? 0);
            $usedBytes      = max(0, $totalBytes - $availableBytes);

            if ($totalBytes > 0) {
                return [
                    'total_bytes'     => $totalBytes,
                    'used_bytes'      => $usedBytes,
                    'available_bytes' => $availableBytes,
                ];
            }
        }

        return $null;
    }

    private function parseMeminfo(string $meminfo): ?array
    {
        $values = [];

        foreach (explode("\n", $meminfo) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s*kB/i', $line, $m)) {
                $values[$m[1]] = (int) $m[2] * 1024;
            }
        }

        $total     = $values['MemTotal']     ?? null;
        $available = $values['MemAvailable'] ?? null;

        if (!$total || !$available) {
            return null;
        }

        return [
            'total_bytes'     => $total,
            'used_bytes'      => max(0, $total - $available),
            'available_bytes' => $available,
        ];
    }

    // ── Disk ──────────────────────────────────────────────────────────────────

    private function diskUsage(): array
    {
        $path       = DIRECTORY_SEPARATOR === '\\' ? base_path() : '/';
        $totalBytes = @disk_total_space($path);
        $freeBytes  = @disk_free_space($path);

        if ($totalBytes === false || $freeBytes === false || $totalBytes <= 0) {
            return ['path' => $path, 'total_bytes' => null, 'used_bytes' => null, 'free_bytes' => null];
        }

        return [
            'path'        => $path,
            'total_bytes' => (float) $totalBytes,
            'used_bytes'  => max(0.0, (float) $totalBytes - (float) $freeBytes),
            'free_bytes'  => max(0.0, (float) $freeBytes),
        ];
    }

    // ── Uptime ────────────────────────────────────────────────────────────────

    private function uptime(): array
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $this->darwinUptime(),
            'Linux'  => $this->linuxUptime(),
            default  => ['seconds' => null],
        };
    }

    private function darwinUptime(): array
    {
        $output = $this->run(self::CMD['sysctl'] . ' -n kern.boottime');

        if (!$output || !preg_match('/sec\s*=\s*(\d+)/', $output, $m)) {
            return ['seconds' => null];
        }

        return ['seconds' => max(0, time() - (int) $m[1])];
    }

    private function linuxUptime(): array
    {
        $contents = @file_get_contents('/proc/uptime');

        if ($contents) {
            return ['seconds' => (int) round((float) strtok(trim($contents), ' '))];
        }

        return ['seconds' => null];
    }

    // ── Load average & cores ──────────────────────────────────────────────────

    private function loadAverage(): array
    {
        $load = sys_getloadavg();

        if (!is_array($load) || count($load) < 3) {
            return [0.0, 0.0, 0.0];
        }

        return array_map(static fn ($v) => round((float) $v, 2), array_slice($load, 0, 3));
    }

    private function cpuCores(): int
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => self::CMD['sysctl'] . ' -n hw.ncpu',
            'Linux'  => self::CMD['nproc'],
            default  => null,
        };

        if (!$command) {
            return 1;
        }

        $output = $this->run($command);
        $cores  = $output !== null ? (int) trim($output) : 0;

        return max(1, $cores);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function vmStatPages(string $output, string $label): int
    {
        if (!preg_match('/' . preg_quote($label, '/') . ':\s+(\d+)\./i', $output, $m)) {
            return 0;
        }

        return (int) $m[1];
    }

    private function run(string $command): ?string
    {
        $timeout = (float) config('server-lens.command_timeout_seconds', 1.5);

        $result = Process::timeout((int) ceil($timeout))->run($command);

        if (!$result->successful()) {
            return null;
        }

        $output = trim($result->output());

        return $output !== '' ? $output : null;
    }
}
