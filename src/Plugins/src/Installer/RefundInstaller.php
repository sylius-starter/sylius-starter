<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Installer;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Database;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class RefundInstaller implements InstallerInterface
{
    public function name(): string
    {
        return 'refund';
    }

    public function description(): string
    {
        return 'Basic refunds functionality for Sylius';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Refund Plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require sylius/refund-plugin');
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
