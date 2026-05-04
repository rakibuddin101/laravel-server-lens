# Laravel Server Lens

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rakib/laravel-server-lens.svg?style=flat-square)](https://packagist.org/packages/rakib/laravel-server-lens)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rakibdevs/laravel-server-lens/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rakibdevs/laravel-server-lens/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rakib/laravel-server-lens.svg?style=flat-square)](https://packagist.org/packages/rakib/laravel-server-lens)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE.md)

A plug-and-play server observability and traffic monitoring dashboard for Laravel. Get live CPU, RAM, disk metrics, service health checks, a real-time request feed with bot detection, and IP blocking — all in one embeddable dashboard with no external services and no build step required.

![Dashboard Preview](https://raw.githubusercontent.com/rakibdevs/laravel-server-lens/main/art/preview.png)

## Installation

You can install the package via Composer:

```bash
composer require rakib/laravel-server-lens
```

Run the installer to publish config, migrations, and assets:

```bash
php artisan server-lens:install
```

Run the migrations:

```bash
php artisan migrate
```

Register the middleware to enable traffic monitoring.

**Laravel 11+ — `bootstrap/app.php`**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Rakib\ServerLens\Http\Middleware\TrafficMonitorMiddleware::class);
})
```

**Laravel 10 — `app/Http/Kernel.php`**

```php
protected $middleware = [
    // ...
    \Rakib\ServerLens\Http\Middleware\TrafficMonitorMiddleware::class,
];
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="server-lens-config"
```

This is the contents of the published config file:

```php
return [

    'enabled' => env('SERVER_LENS_ENABLED', true),

    'route_prefix' => 'ops',
    'middleware'   => ['web', 'auth'],

    'poll_seconds'  => 5,
    'cache_seconds' => 3,

    'prune_after_days' => 30,

    'log_mode' => env('SERVER_LENS_LOG_MODE', 'all'), // 'all' or 'security'

    'thresholds' => [
        'cpu'         => ['warning' => 70,  'critical' => 90],
        'memory'      => ['warning' => 75,  'critical' => 90],
        'disk'        => ['warning' => 80,  'critical' => 95],
        'response_ms' => ['warning' => 500, 'critical' => 1500],
    ],

    'skip_extensions' => ['css', 'js', 'png', 'jpg', 'svg', 'woff2'],

    'skip_paths' => [],

    'geo' => [
        'driver'    => env('SERVER_LENS_GEO_DRIVER', 'none'), // 'none' or 'maxmind'
        'mmdb_path' => env('SERVER_LENS_MMDB_PATH', ''),
    ],

    'api' => [
        'enabled' => env('SERVER_LENS_API_ENABLED', false),
        'token'   => env('SERVER_LENS_API_TOKEN', ''),
    ],

];
```

You can publish the views to customise the dashboard:

```bash
php artisan vendor:publish --tag="server-lens-views"
```

## Usage

Visit the dashboard at `/ops` (or whatever `route_prefix` you configured):

```
http://your-app.test/ops
```

### Programmatic access

```php
use Rakib\ServerLens\Facades\ServerLens;

$snapshot = ServerLens::snapshot();    // full metrics, health, and activity
$health   = ServerLens::healthOnly();  // health checks only
$metrics  = ServerLens::metricsOnly(); // CPU, RAM, Disk
```

### Custom health checks

Implement the `HealthCheck` contract and register it in a service provider:

```php
use Rakib\ServerLens\Contracts\HealthCheck;
use Rakib\ServerLens\Data\CheckResult;

class StripeApiCheck implements HealthCheck
{
    public function name(): string { return 'Stripe API'; }

    public function run(): CheckResult
    {
        $start = microtime(true);
        // your probe logic here
        $ms = round((microtime(true) - $start) * 1000, 2);

        return CheckResult::healthy("Reachable in {$ms} ms", $ms);
    }
}
```

```php
// In AppServiceProvider::boot()
use Rakib\ServerLens\Facades\ServerLens;

ServerLens::registerCheck(new StripeApiCheck());
```

### Artisan commands

```bash
# Run all health checks and print results
php artisan server-lens:check

# Delete traffic logs older than prune_after_days
php artisan server-lens:prune

# Block an IP address (add --hours=N for a temporary block)
php artisan server-lens:block 203.0.113.42
php artisan server-lens:block 203.0.113.42 --hours=24

# Remove an IP block
php artisan server-lens:unblock 203.0.113.42
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Rakib Uddin](https://github.com/rakibdevs)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
