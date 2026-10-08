<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Util\Assets;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Database;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\io;

final readonly class CmsInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'cms';
    }

    public function description(): string
    {
        return 'CMS plugin for Sylius applications';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding CMS plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require sylius/cms-plugin');
        Docker::run($app, 'yarn add trix@^2.0.0 swiper@^11.2.6 @sylius-cms-plugin/admin@file:vendor/sylius/cms-plugin/assets/admin');

        Symfony::addJsController(
            $app,
            '@sylius-cms-plugin/admin',
            'preview',
            [
                'enabled' => true,
                'fetch' => 'lazy',
            ],
        );

        Assets::install($app);
        Assets::build($app);
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
