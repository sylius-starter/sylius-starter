<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Assets;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Database;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;

use function Castor\io;

final readonly class CmsRemover implements RemoverInterface
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
        io()->title('Removing CMS plugin');

        Composer::allowContribRecipes($app);
        Database::rollbackPluginMigrations($app, 'Sylius\CmsPlugin\Migrations');
        Docker::run($app, 'composer remove sylius/cms-plugin');
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
