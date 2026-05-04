# Changelog

All notable changes to `laravel-server-lens` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-04

### Added
- Real-time dashboard at `/ops` with live polling every 5 seconds
- Server metrics — CPU usage, RAM, Disk, and average response time with colour-coded status
- Rolling resource utilisation chart (CPU · Memory · Disk) powered by ApexCharts
- Request throughput bar chart — success vs errors over the last 15 minutes
- Six built-in health checks: Application, Database, Cache, Queue, Redis, Storage
- Custom health check support via the `HealthCheck` contract and `ServerLens::registerCheck()`
- Live request feed showing the last 20 requests with method, path, status code, response time, and classification
- Bot detection — classifies traffic as `human`, `bot`, `suspicious`, or `blocked`
- `TrafficMonitorMiddleware` — logs requests in `terminate()` with zero response-time impact
- IP blocking — permanent or time-limited blocks via `server-lens:block` and `server-lens:unblock`
- IP block cache (5-minute TTL) to avoid per-request database queries
- Dark mode and light mode toggle — preference applied instantly across charts and UI
- Pause / resume live polling — state persists in `localStorage` across page reloads
- Geo-location support via MaxMind GeoLite2 (optional, driver-based)
- `security` log mode — samples 1-in-10 normal human requests to reduce writes on high-traffic sites
- Configurable skip rules for file extensions and URL path prefixes
- Optional read-only JSON API (`/ops/api/snapshot`, `/ops/api/health`, `/ops/api/metrics`)
- `server-lens:install` — publishes config, migrations, and assets in one command
- `server-lens:check` — runs all health checks and prints results in the terminal
- `server-lens:prune` — deletes traffic logs older than `prune_after_days`
- Automatic daily scheduling of the prune command via the Laravel scheduler
- `ServerLens` facade with `snapshot()`, `healthOnly()`, and `metricsOnly()` methods
- Embeddable widget support — drop the dashboard into any existing Blade view
- cPanel / shared hosting compatibility
- Support for Laravel 10, 11, and 12 with PHP 8.1+
- Support for MySQL, MariaDB, PostgreSQL, and SQLite

[Unreleased]: https://github.com/rakibdevs/laravel-server-lens/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/rakibdevs/laravel-server-lens/releases/tag/v1.0.0
