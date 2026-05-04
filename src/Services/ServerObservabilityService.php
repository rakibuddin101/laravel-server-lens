<?php

namespace Rakib\ServerLens\Services;

use Rakib\ServerLens\Checks\ApplicationResponseCheck;
use Rakib\ServerLens\Checks\CacheCheck;
use Rakib\ServerLens\Checks\DatabaseCheck;
use Rakib\ServerLens\Checks\QueueCheck;
use Rakib\ServerLens\Checks\RedisCheck;
use Rakib\ServerLens\Checks\StorageCheck;
use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Models\TrafficLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ServerObservabilityService
{
    /** @var HealthCheck[] */
    private array $customChecks = [];

    public function __construct(
        private readonly HostMetricReader $hostMetricReader,
    ) {}

    public function registerCheck(HealthCheck $check): void
    {
        $this->customChecks[] = $check;
    }

    public function snapshot(): array
    {
        $seconds = max(0, (int) config('server-lens.cache_seconds', 3));

        if ($seconds === 0) {
            return $this->build();
        }

        return Cache::remember(
            'server_lens_snapshot',
            now()->addSeconds($seconds),
            fn () => $this->build()
        );
    }

    public function healthOnly(): array
    {
        return $this->buildHealthChecks();
    }

    public function metricsOnly(): array
    {
        return $this->buildMetricCards($this->hostMetricReader->read(), $this->emptyActivity());
    }

    // ── Build ─────────────────────────────────────────────────────────────────

    private function build(): array
    {
        $generatedAt = now();
        $host        = $this->hostMetricReader->read();
        $activity    = $this->requestActivity();
        $checks      = $this->buildHealthChecks();
        $metrics     = $this->buildMetricCards($host, $activity);
        $overall     = $this->overallStatus([
            ...array_map(static fn ($m) => $m['status'] ?? 'inactive', $metrics),
            ...array_map(static fn ($c) => $c['status'] ?? 'inactive', $checks),
        ]);

        return [
            'title'                => 'Server Monitor',
            'overall_status'       => $overall,
            'overall_status_label' => $this->statusLabel($overall),
            'generated_at'         => $generatedAt->toIso8601String(),
            'generated_at_label'   => $generatedAt->format('h:i:s A'),
            'poll_seconds'         => (int) config('server-lens.poll_seconds', 5),
            'resource_chart'       => ['history_points' => 12],

            'header' => [
                'hostname'    => $host['host']['hostname'] ?? php_uname('n'),
                'os'          => $host['host']['os']       ?? php_uname('s'),
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'database'    => config('database.default'),
                'cache'       => config('cache.default'),
                'queue'       => config('queue.default'),
            ],

            'footer' => [
                'cpu_cores'          => $host['host']['cores']   ?? 1,
                'uptime_human'       => $this->formatDuration($host['uptime']['seconds'] ?? null),
                'load_average_label' => $this->formatLoad($host['load_average'] ?? []),
                'request_source'     => $this->requestSource(),
            ],

            'metrics'       => $metrics,
            'health_checks' => $checks,
            'activity'      => $activity,
        ];
    }

    // ── Metrics ───────────────────────────────────────────────────────────────

    private function buildMetricCards(array $host, array $activity): array
    {
        $cpu    = $host['cpu']    ?? [];
        $memory = $host['memory'] ?? [];
        $disk   = $host['disk']   ?? [];

        $cpuPct = $cpu['usage_percent'] ?? null;
        $memUsed  = $memory['used_bytes']  ?? null;
        $memTotal = $memory['total_bytes'] ?? null;
        $diskUsed  = $disk['used_bytes']  ?? null;
        $diskTotal = $disk['total_bytes'] ?? null;

        $memPct  = $this->pct($memUsed,  $memTotal);
        $diskPct = $this->pct($diskUsed, $diskTotal);

        $avgMs = $activity['summary']['avg_response_ms'] ?? null;

        $cpuStatus  = $this->statusFromThresholds($cpuPct,  'cpu');
        $memStatus  = $this->statusFromThresholds($memPct,  'memory');
        $diskStatus = $this->statusFromThresholds($diskPct, 'disk');
        $rtStatus   = $avgMs !== null ? $this->statusFromThresholds($avgMs, 'response_ms') : 'inactive';

        return [
            'cpu' => [
                'key'     => 'cpu',
                'label'   => 'CPU Usage',
                'icon'    => 'sl-icon-cpu',
                'value'   => $cpuPct !== null ? round($cpuPct, 1) . '%' : 'N/A',
                'percent' => $this->normPct($cpuPct ?? 0),
                'meta'    => ($host['host']['cores'] ?? 1) . ' cores · load ' . ($host['load_average'][0] ?? '—'),
                'detail'  => 'Source: ' . ($cpu['source'] ?? 'unavailable'),
                'status'  => $cpuStatus,
            ],
            'memory' => [
                'key'     => 'memory',
                'label'   => 'Memory',
                'icon'    => 'sl-icon-layers',
                'value'   => $memPct !== null ? round($memPct, 1) . '%' : 'N/A',
                'percent' => $this->normPct($memPct ?? 0),
                'meta'    => $this->bytes($memUsed) . ' / ' . $this->bytes($memTotal),
                'detail'  => 'Available: ' . $this->bytes($memory['available_bytes'] ?? null),
                'status'  => $memStatus,
            ],
            'disk' => [
                'key'     => 'disk',
                'label'   => 'Disk',
                'icon'    => 'sl-icon-hard-drive',
                'value'   => $diskPct !== null ? round($diskPct, 1) . '%' : 'N/A',
                'percent' => $this->normPct($diskPct ?? 0),
                'meta'    => $this->bytes($diskUsed) . ' / ' . $this->bytes($diskTotal),
                'detail'  => 'Free: ' . $this->bytes($disk['free_bytes'] ?? null),
                'status'  => $diskStatus,
            ],
            'response' => [
                'key'     => 'response',
                'label'   => 'Avg Response',
                'icon'    => 'sl-icon-activity',
                'value'   => $avgMs !== null ? round($avgMs) . ' ms' : 'N/A',
                'percent' => $avgMs !== null ? min(100, ($avgMs / 2000) * 100) : 0,
                'meta'    => ($activity['summary']['requests_last_hour'] ?? 0) . ' req/hr',
                'detail'  => ($activity['summary']['error_rate_label'] ?? '0% errors'),
                'status'  => $rtStatus,
            ],
        ];
    }

    // ── Health checks ─────────────────────────────────────────────────────────

    private function buildHealthChecks(): array
    {
        $defaults = [
            new ApplicationResponseCheck(),
            new DatabaseCheck(),
            new CacheCheck(),
            new QueueCheck(),
            new RedisCheck(),
            new StorageCheck(),
        ];

        $checks = array_merge($defaults, $this->customChecks);

        return array_map(function (HealthCheck $check): array {
            try {
                $result = $check->run();
            } catch (Throwable $e) {
                $result = \Rakib\ServerLens\Data\CheckResult::critical($e->getMessage());
            }

            return [
                'label'      => $check->name(),
                'icon'       => $check->icon(),
                'status'     => $result->status,
                'detail'     => $result->detail,
                'latency_ms' => $result->latencyMs,
            ];
        }, $checks);
    }

    // ── Request activity ──────────────────────────────────────────────────────

    private function requestActivity(): array
    {
        if (!Schema::hasTable('server_lens_traffic_logs')) {
            return $this->emptyActivity('Traffic logs table not found — run migrations');
        }

        try {
            $now     = Carbon::now();
            $buckets = 15;

            $rows = DB::table('server_lens_traffic_logs')
                ->select([
                    DB::raw($this->minuteBucket() . ' AS bucket'),
                    DB::raw('COUNT(*) AS total'),
                    DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) AS errors'),
                    DB::raw('AVG(response_time) AS avg_ms'),
                ])
                ->where('created_at', '>=', $now->copy()->subMinutes($buckets))
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get()
                ->keyBy('bucket');

            $labels    = [];
            $success   = [];
            $errSeries = [];

            for ($i = $buckets - 1; $i >= 0; $i--) {
                $key  = Carbon::now()->subMinutes($i)->format('Y-m-d H:i');
                $row  = $rows->get($key);
                $tot  = (int) ($row->total  ?? 0);
                $err  = (int) ($row->errors ?? 0);

                $labels[]    = Carbon::now()->subMinutes($i)->format('H:i');
                $success[]   = max(0, $tot - $err);
                $errSeries[] = $err;
            }

            $recentRows = DB::table('server_lens_traffic_logs')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $recent = $recentRows->map(function (object $row): array {
                return [
                    'method'         => $row->method         ?? 'GET',
                    'path'           => $row->path           ?? '/',
                    'status_code'    => (int) ($row->status_code ?? 200),
                    'response_ms'    => $row->response_time !== null ? round((float) $row->response_time) : null,
                    'classification' => $row->classification ?? 'human',
                    'is_ajax'        => (bool) ($row->is_ajax ?? false),
                    'time_ago'       => Carbon::parse($row->created_at)->diffForHumans(short: true),
                ];
            })->all();

            $hourStats = DB::table('server_lens_traffic_logs')
                ->selectRaw('COUNT(*) as total, AVG(response_time) as avg_ms, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
                ->where('created_at', '>=', $now->copy()->subHour())
                ->first();

            $fiveMin = DB::table('server_lens_traffic_logs')
                ->where('created_at', '>=', $now->copy()->subMinutes(5))
                ->count();

            $totalHour  = (int) ($hourStats->total  ?? 0);
            $errorHour  = (int) ($hourStats->errors ?? 0);
            $avgMs      = $hourStats->avg_ms ? round((float) $hourStats->avg_ms, 1) : null;
            $errorRate  = $totalHour > 0 ? round($errorHour / $totalHour * 100, 1) : 0.0;

            $latest = DB::table('server_lens_traffic_logs')->max('created_at');

            return [
                'chart' => [
                    'labels'            => $labels,
                    'successful_series' => $success,
                    'error_series'      => $errSeries,
                ],
                'recent'  => $recent,
                'summary' => [
                    'requests_last_hour'  => $totalHour,
                    'requests_last_5m'    => $fiveMin,
                    'avg_response_ms'     => $avgMs,
                    'error_rate'          => $errorRate,
                    'error_rate_label'    => $errorRate . '% errors',
                    'latest_seen_label'   => $latest ? Carbon::parse($latest)->diffForHumans() : 'No requests yet',
                ],
            ];
        } catch (Throwable $e) {
            return $this->emptyActivity('Query error: ' . $e->getMessage());
        }
    }

    private function emptyActivity(string $reason = ''): array
    {
        return [
            'chart'   => ['labels' => [], 'successful_series' => [], 'error_series' => []],
            'recent'  => [],
            'summary' => [
                'requests_last_hour' => 0,
                'requests_last_5m'   => 0,
                'avg_response_ms'    => null,
                'error_rate'         => 0.0,
                'error_rate_label'   => '0% errors',
                'latest_seen_label'  => $reason ?: 'No requests yet',
            ],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function minuteBucket(): string
    {
        return match (config('database.default')) {
            'mysql', 'mariadb' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')",
            'pgsql'            => "TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI')",
            default            => "strftime('%Y-%m-%d %H:%M', created_at)",
        };
    }

    private function requestSource(): string
    {
        if (Schema::hasTable('server_lens_traffic_logs')) {
            return 'server_lens_traffic_logs';
        }

        return 'table not found';
    }

    private function overallStatus(array $statuses): string
    {
        if (in_array('critical', $statuses, true)) {
            return 'critical';
        }

        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        if (count(array_filter($statuses, fn ($s) => $s === 'inactive')) === count($statuses)) {
            return 'inactive';
        }

        return 'healthy';
    }

    private function statusFromThresholds(?float $value, string $metric): string
    {
        if ($value === null) {
            return 'inactive';
        }

        $warning  = (float) config("server-lens.thresholds.{$metric}.warning",  70);
        $critical = (float) config("server-lens.thresholds.{$metric}.critical",  90);

        if ($value >= $critical) {
            return 'critical';
        }

        if ($value >= $warning) {
            return 'warning';
        }

        return 'healthy';
    }

    private function pct(float|int|null $used, float|int|null $total): ?float
    {
        if ($used === null || $total === null || $total <= 0) {
            return null;
        }

        return min(100.0, max(0.0, ($used / $total) * 100));
    }

    private function normPct(float $value): float
    {
        return min(100.0, max(0.0, $value));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'critical' => 'Critical',
            'warning'  => 'Warning',
            'inactive' => 'Inactive',
            default    => 'Healthy',
        };
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return 'unavailable';
        }

        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($d > 0) {
            return $d . 'd ' . $h . 'h';
        }

        if ($h > 0) {
            return $h . 'h ' . $m . 'm';
        }

        return $m . 'm';
    }

    private function formatLoad(array $load): string
    {
        if (empty($load)) {
            return '— / — / —';
        }

        return implode(' / ', array_map(static fn ($v) => number_format((float) $v, 2), $load));
    }

    private function bytes(float|int|null $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
