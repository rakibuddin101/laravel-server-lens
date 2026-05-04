<?php

namespace Rakib\ServerLens\Console\Commands;

use Rakib\ServerLens\Models\IpBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UnblockCommand extends Command
{
    protected $signature   = 'server-lens:unblock {ip : The IP address to unblock}';
    protected $description = 'Remove a blocked IP address';

    public function handle(): int
    {
        $ip = $this->argument('ip');

        $deleted = IpBlock::where('ip_address', $ip)->delete();

        Cache::forget('sl_ipblock_' . md5($ip));

        if ($deleted === 0) {
            $this->components->warn("No block found for IP: {$ip}");
            return self::SUCCESS;
        }

        $this->components->info("IP {$ip} has been unblocked.");

        return self::SUCCESS;
    }
}
