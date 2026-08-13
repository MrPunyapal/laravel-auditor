<?php

declare(strict_types=1);

namespace LaravelAuditor\Tests;

use LaravelAuditor\LaravelAuditorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAuditorServiceProvider::class,
        ];
    }
}
