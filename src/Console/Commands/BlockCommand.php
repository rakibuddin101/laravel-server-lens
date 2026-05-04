<?php

namespace Rakib\ServerLens\Console\Commands;

use Rakib\ServerLens\Models\IpBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BlockCommand extends Command
{
    protected $signature   = 'server-lens:block {ip : The IP address to block} {--hours= : Block for N hours (omit for permanent)} {--reason= : Reason for the block}';
    protected $description = 'Block an IP address from accessing the application';

    public function handle(): int
    {
        $ip     = $this->argument('ip');
        $hours  = $this->option('hours') ? (int) $this->option('hours') : null;
        $reason = $this->option('reason') ?? 'Blocked via Artisan';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->error("Invalid IP address: {$ip}");
            return self::FAILURE;
        }

        IpBlock::updateOrCreate(
            ['ip_address' => $ip],
            [
                'reason'     => $reason,
                'is_active'  => true,
                'expires_at' => $hours ? now()->addHours($hours) : null,
                'blocked_by' => 'artisan',
            ]
        );

        Cache::forget('sl_ipblock_' . md5($ip));

        $expiry = $hours ? "for {$hours} hour(s)" : 'permanently';
        $this->components->info("IP {$ip} has been blocked {$expiry}.");

        return self::SUCCESS;
    }
}
