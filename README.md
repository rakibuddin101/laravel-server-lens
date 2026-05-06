# Laravel Server Lens

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rakibuddin101/laravel-server-lens.svg?style=flat-square)](https://packagist.org/packages/rakibuddin101/laravel-server-lens)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rakibdevs/laravel-server-lens/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rakibdevs/laravel-server-lens/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rakibuddin101/laravel-server-lens.svg?style=flat-square)](https://packagist.org/packages/rakibuddin101/laravel-server-lens)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE.md)

A plug-and-play server observability and traffic monitoring dashboard for Laravel. Get live CPU, RAM, disk metrics, service health checks, a real-time request feed with bot detection, and IP blocking — all in one embeddable dashboard with no external services and no build step required.

![Dashboard Preview](https://github.com/rakibuddin101/laravel-server-lens/blob/main/art/preview.png)

## Installation

You can install the package via Composer:

```bash
composer require rakibuddin101/laravel-server-lens
```

Run the installer to publish config, migrations, and assets:

```bash
php artisan server-lens:install
```

Run the migrations:

```bash
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="server-lens-config"
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

### Accessing the dashboard

The `/ops` route is protected by the `web` and `auth` middleware out of the box. If you visit it without being logged in, Laravel will redirect you to the login page — this is intentional to keep server metrics private.

**You must be authenticated** (logged in to your app) before you can view the dashboard.

To restrict access further — for example, to admins only — update the `middleware` key in `config/server-lens.php`:

```php
// config/server-lens.php
'middleware' => ['web', 'auth', 'can:admin'],   // Laravel Gate
'middleware' => ['web', 'auth', 'role:admin'],   // Spatie Permissions / your own middleware
```

To allow any authenticated user (the default):

```php
'middleware' => ['web', 'auth'],
```

To open the dashboard without any login requirement (**not recommended in production**):

```php
'middleware' => ['web'],
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

- [Rakib Uddin](https://github.com/rakibuddin101)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
