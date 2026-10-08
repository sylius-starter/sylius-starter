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

final readonly class WishlistRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'wishlist';
    }

    public function description(): string
    {
        return 'Wishlist plugin for Sylius';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Wishlist plugin');

        Composer::allowContribRecipes($app);
        Database::rollbackPluginMigrations($app, 'Sylius\WishlistPlugin\Migrations');
        Docker::run($app, 'composer remove sylius/wishlist-plugin');
        Docker::run($app, 'bin/console assets:install public');
        Docker::run($app, 'yarn install');
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
