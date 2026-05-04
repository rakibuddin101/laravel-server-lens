<?php

namespace Rakib\ServerLens\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'server-lens:install {--force : Overwrite existing published files}';
    protected $description = 'Install Server Lens — publish config, migrations, and assets';

    public function handle(): int
    {
        $this->info('Installing Laravel Server Lens...');
        $this->newLine();

        $force = $this->option('force') ? ['--force' => true] : [];

        $this->call('vendor:publish', array_merge([
            '--tag'      => 'server-lens-config',
            '--provider' => 'Rakib\\ServerLens\\ServerLensServiceProvider',
        ], $force));

        $this->call('vendor:publish', array_merge([
            '--tag'      => 'server-lens-migrations',
            '--provider' => 'Rakib\\ServerLens\\ServerLensServiceProvider',
        ], $force));

        $this->call('vendor:publish', array_merge([
            '--tag'      => 'server-lens-assets',
            '--provider' => 'Rakib\\ServerLens\\ServerLensServiceProvider',
        ], $force));

        $this->newLine();
        $this->components->info('Server Lens installed successfully.');
        $this->newLine();

        $this->line('  <fg=gray>Next steps:</>');
        $this->line('  1. Run <fg=cyan>php artisan migrate</>');
        $this->line('  2. Add <fg=cyan>\\Rakib\\ServerLens\\Http\\Middleware\\TrafficMonitorMiddleware::class</> to your middleware stack');
        $this->line('  3. Visit <fg=cyan>/' . config('server-lens.route_prefix', 'ops') . '</> in your browser');

        $this->newLine();

        return self::SUCCESS;
    }
}
