<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Util;

use SyliusStarter\Core\App;

use function Castor\fs;

final readonly class Composer
{
    public static function allowContribRecipes(App $app): void
    {
        Docker::run($app, 'composer config extra.symfony.allow-contrib true');
    }

    public static function removeDevDependency(App $app, string $package): void
    {
        if (!self::hasDevDependency($app, $package)) {
            return;
        }

        Docker::run($app, \sprintf('composer remove --dev %s', $package));
    }

    /**
     * Requires $package with "^$minimumVersion" when the locked version is older (or missing).
     */
    public static function requireMinimumVersion(App $app, string $package, string $minimumVersion): void
    {
        $installedVersion = self::lockedVersion($app, $package);

        if (null !== $installedVersion && version_compare(ltrim($installedVersion, 'v'), $minimumVersion, '>=')) {
            return;
        }

        Docker::run($app, \sprintf('composer require %s:^%s --with-all-dependencies', $package, $minimumVersion));
    }

    public static function lockedVersion(App $app, string $package): ?string
    {
        $file = $app->directory() . '/composer.lock';

        if (!file_exists($file)) {
            return null;
        }

        $content = json_decode(fs()->readFile($file), true, flags: \JSON_THROW_ON_ERROR);

        foreach ([...$content['packages'] ?? [], ...$content['packages-dev'] ?? []] as $lockedPackage) {
            if (($lockedPackage['name'] ?? null) === $package) {
                return $lockedPackage['version'] ?? null;
            }
        }

        return null;
    }

    private static function hasDevDependency(App $app, string $package): bool
    {
        $file = $app->directory() . '/composer.json';

        if (!file_exists($file)) {
            return false;
        }

        $content = json_decode(fs()->readFile($file), true, flags: \JSON_THROW_ON_ERROR);

        return isset($content['require-dev'][$package]);
    }
}
