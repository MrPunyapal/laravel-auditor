<?php

declare(strict_types=1);

namespace LaravelAuditor\Tests;

use LaravelAuditor\LaravelAuditorServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelAuditorServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        $router->get('/auditor-test', static fn (): string => 'ok')->name('auditor.test');
    }
}
