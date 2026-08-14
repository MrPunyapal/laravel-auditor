<?php

declare(strict_types=1);

namespace LaravelAuditor\Facades;

use Illuminate\Support\Facades\Facade;
use LaravelAuditor\Audit\Rules\RuleRegistry;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Context\ProjectContext;
use LaravelAuditor\LaravelAuditor as LaravelAuditorManager;

/**
 * @method static RuleRegistry rules()
 * @method static ContextRegistry context()
 * @method static ProjectContext project()
 * @method static array<string, mixed> collect(string $name)
 *
 * @see LaravelAuditorManager
 */
final class LaravelAuditor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelAuditorManager::class;
    }
}
