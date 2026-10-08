<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Task;

use SyliusStarter\Core\Service\SyliusService;

/**
 * Packages (themes, plugins, import, ...) register their Castor tasks here,
 * usually from a file autoloaded by Composer. The SyliusService then exposes
 * every registered task without knowing the packages that provide them.
 *
 * A provider receives the SyliusService and returns an iterable of
 * array{task: \Castor\Attribute\AsTask, function: callable}.
 */
final class TaskProviderRegistry
{
    /** @var array<string, \Closure(SyliusService): iterable<array{task: \Castor\Attribute\AsTask, function: callable}>> */
    private static array $providers = [];

    /**
     * @param callable(SyliusService): iterable<array{task: \Castor\Attribute\AsTask, function: callable}> $provider
     */
    public static function register(string $name, callable $provider): void
    {
        self::$providers[$name] = $provider(...);
    }

    public static function has(string $name): bool
    {
        return isset(self::$providers[$name]);
    }

    public static function unregister(string $name): void
    {
        unset(self::$providers[$name]);
    }

    /**
     * @return array<string, \Closure(SyliusService): iterable<array{task: \Castor\Attribute\AsTask, function: callable}>>
     */
    public static function all(): array
    {
        return self::$providers;
    }

    /**
     * @return iterable<array{task: \Castor\Attribute\AsTask, function: callable}>
     */
    public static function tasksFor(SyliusService $service): iterable
    {
        foreach (self::$providers as $provider) {
            yield from $provider($service);
        }
    }
}
