<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Installer;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Util\Assets;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Database;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class WishlistInstaller implements InstallerInterface
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
        io()->title('Adding Wishlist Plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'bin/console assets:install public');
        Docker::run($app, 'yarn install');
        Assets::build($app);
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
