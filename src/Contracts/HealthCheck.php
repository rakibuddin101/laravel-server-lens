<?php

namespace Rakib\ServerLens\Contracts;

use Rakib\ServerLens\Data\CheckResult;

interface HealthCheck
{
    public function name(): string;

    public function icon(): string;

    public function run(): CheckResult;
}
