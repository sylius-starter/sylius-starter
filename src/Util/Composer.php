<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;

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
