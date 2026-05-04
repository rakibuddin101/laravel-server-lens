<?php

namespace Rakib\ServerLens\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array snapshot()
 * @method static array healthOnly()
 * @method static array metricsOnly()
 * @method static void  registerCheck(\Rakib\ServerLens\Contracts\HealthCheck $check)
 */
class ServerLens extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Rakib\ServerLens\Services\ServerObservabilityService::class;
    }
}
