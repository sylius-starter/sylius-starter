<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Storefront;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Util\Yaml;
use Symfony\Component\Finder\Finder;

use function Castor\fs;

/**
 * Shared homepage hero slot.
 *
 * Owned by core so that several packages can use it without conflicting:
 *  - themes provide the markup (THEME_TEMPLATE), never the hook config;
 *  - other packages (import...) provide data through a HeroContributorInterface
 *    service generated in the application, never markup.
 *
 * install() is idempotent: every package needing the slot calls it.
 */
final readonly class Hero
{
    /** Template a theme ships to render the hero (relative to the app directory). */
    public const THEME_TEMPLATE = 'templates/shop/homepage/hero.html.twig';

    public const CONFIG_IMPORT = '../sylius/storefront/*.yaml';

    public static function resourcesDir(): string
    {
        return \dirname(__DIR__, 2) . '/resources/storefront/hero';
    }

    public static function install(App $app): void
    {
        $resourcesDir = self::resourcesDir();

        foreach ((new Finder())->files()->in($resourcesDir) as $file) {
            // Core owns these files: always refresh them.
            fs()->copy($file->getPathname(), $app->directory() . '/' . $file->getRelativePathname(), true);
        }

        Yaml::addImport($app, 'config/packages/_sylius.yaml', self::CONFIG_IMPORT);
    }

    public static function isInstalled(App $app): bool
    {
        return is_file($app->directory() . '/config/sylius/storefront/hero.yaml');
    }
}
