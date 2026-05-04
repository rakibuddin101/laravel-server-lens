<?php

namespace Rakib\ServerLens\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrafficClassifierService
{
    private const KNOWN_BOTS = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver',
        'semrushbot', 'ahrefsbot', 'mj12bot', 'dotbot', 'rogerbot',
        'petalbot', 'applebot', 'twitterbot', 'linkedinbot', 'whatsapp',
        'facebookexternalhit', 'telegrambot', 'discordbot', 'slackbot',
        'bot', 'crawler', 'spider', 'scraper', 'wget', 'curl', 'python-requests',
        'go-http-client', 'java/', 'ruby/', 'libwww-perl', 'okhttp',
        'postmanruntime', 'axios/', 'node-fetch',
    ];

    private const SUSPICIOUS_PATHS = [
        '.env', 'wp-login', 'wp-admin', 'phpMyAdmin', 'admin.php',
        'xmlrpc.php', 'eval-stdin.php', 'shell.php', 'cmd.php',
        '..%2F', '..%5C', '/etc/passwd', '/bin/bash', 'UNION SELECT',
        '<script>', '<?php', 'base64_decode',
    ];

    public function classify(Request $request, int $statusCode, int $requestCount): array
    {
        $ua = strtolower($request->userAgent() ?? '');

        $botName = $this->detectBot($ua);

        if ($botName !== null) {
            return ['classification' => 'bot', 'action_taken' => 'logged', 'bot_name' => $botName];
        }

        if ($this->isSuspicious($request, $statusCode, $requestCount)) {
            return ['classification' => 'suspicious', 'action_taken' => 'flagged', 'bot_name' => null];
        }

        return ['classification' => 'human', 'action_taken' => 'logged', 'bot_name' => null];
    }

    public function incrementRequestCount(string $ip): int
    {
        $key   = 'sl_rq_' . md5($ip);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes(10));

        return $count;
    }

    private function detectBot(string $ua): ?string
    {
        if ($ua === '') {
            return 'empty-ua';
        }

        foreach (self::KNOWN_BOTS as $bot) {
            if (str_contains($ua, $bot)) {
                return $bot;
            }
        }

        return null;
    }

    private function isSuspicious(Request $request, int $statusCode, int $requestCount): bool
    {
        $path = $request->path();

        foreach (self::SUSPICIOUS_PATHS as $pattern) {
            if (str_contains(strtolower($path), strtolower($pattern))) {
                return true;
            }
        }

        if ($statusCode === 404 && $requestCount > 20) {
            return true;
        }

        if ($requestCount > 200) {
            return true;
        }

        return false;
    }
}
