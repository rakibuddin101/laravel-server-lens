<?php

namespace Rakib\ServerLens\Http\Middleware;

use Rakib\ServerLens\Models\IpBlock;
use Rakib\ServerLens\Models\TrafficLog;
use Rakib\ServerLens\Services\GeoLocationService;
use Rakib\ServerLens\Services\TrafficClassifierService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrafficMonitorMiddleware
{
    private float $startTime = 0.0;

    public function __construct(
        private readonly TrafficClassifierService $classifier,
        private readonly GeoLocationService       $geo,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('server-lens.enabled', true)) {
            return $next($request);
        }

        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $ip = $request->ip() ?? '0.0.0.0';

        if ($this->isBlocked($ip)) {
            $this->logBlocked($request, $ip);
            abort(403, 'Your IP address has been blocked.');
        }

        $this->startTime = microtime(true);

        $response = $next($request);

        $request->attributes->set('_sl_time',   (microtime(true) - $this->startTime) * 1000);
        $request->attributes->set('_sl_status', $response->getStatusCode());

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (!config('server-lens.enabled', true)) {
            return;
        }

        if ($this->shouldSkip($request)) {
            return;
        }

        $ip           = $request->ip() ?? '0.0.0.0';
        $statusCode   = $request->attributes->get('_sl_status', $response->getStatusCode());
        $responseTime = $request->attributes->get('_sl_time', 0.0);

        try {
            $count          = $this->classifier->incrementRequestCount($ip);
            $classification = $this->classifier->classify($request, $statusCode, $count);

            $logMode = config('server-lens.log_mode', 'all');

            if (
                $logMode === 'security'
                && $classification['classification'] === 'human'
                && $statusCode < 400
                && mt_rand(1, 10) > 1
            ) {
                return;
            }

            $geo = $this->geo->lookup($ip);

            TrafficLog::create([
                'ip_address'     => $ip,
                'user_agent'     => Str::limit($request->userAgent() ?? '', 512),
                'method'         => $request->method(),
                'url'            => Str::limit($request->fullUrl(), 2048),
                'path'           => Str::limit('/' . ltrim($request->path(), '/'), 768),
                'status_code'    => $statusCode,
                'response_time'  => round($responseTime, 2),
                'referrer'       => Str::limit((string) $request->headers->get('referer', ''), 2048) ?: null,
                'user_id'        => auth()->id(),
                'session_id'     => $request->hasSession() ? $request->session()->getId() : null,
                'classification' => $classification['classification'],
                'action_taken'   => $classification['action_taken'],
                'country_code'   => $geo['country_code'],
                'country_name'   => $geo['country_name'],
                'city'           => $geo['city'],
                'bot_name'       => $classification['bot_name'],
                'is_ajax'        => $request->ajax(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('[ServerLens] TrafficMonitor error: ' . $e->getMessage());
        }
    }

    private function shouldSkip(Request $request): bool
    {
        $path      = ltrim($request->path(), '/');
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        $skipExt = config('server-lens.skip_extensions', []);

        if (in_array($extension, $skipExt, true)) {
            return true;
        }

        $prefix = rtrim(config('server-lens.route_prefix', 'ops'), '/');

        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return true;
        }

        foreach (config('server-lens.skip_paths', []) as $skip) {
            if ($path === $skip || str_starts_with($path, $skip . '/')) {
                return true;
            }
        }

        if (str_starts_with($path, '@vite') || str_starts_with($path, '__vite')) {
            return true;
        }

        return false;
    }

    private function isBlocked(string $ip): bool
    {
        return (bool) Cache::remember('sl_ipblock_' . md5($ip), 300, function () use ($ip) {
            return IpBlock::where('ip_address', $ip)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->exists();
        });
    }

    private function logBlocked(Request $request, string $ip): void
    {
        try {
            $geo = $this->geo->lookup($ip);

            TrafficLog::create([
                'ip_address'     => $ip,
                'user_agent'     => Str::limit($request->userAgent() ?? '', 512),
                'method'         => $request->method(),
                'url'            => Str::limit($request->fullUrl(), 2048),
                'path'           => Str::limit('/' . ltrim($request->path(), '/'), 768),
                'status_code'    => 403,
                'response_time'  => 0,
                'referrer'       => null,
                'user_id'        => null,
                'session_id'     => null,
                'classification' => 'blocked',
                'action_taken'   => 'blocked',
                'country_code'   => $geo['country_code'],
                'country_name'   => $geo['country_name'],
                'city'           => $geo['city'],
                'bot_name'       => null,
                'is_ajax'        => $request->ajax(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
        }
    }
}
