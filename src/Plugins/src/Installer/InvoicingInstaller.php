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

final readonly class InvoicingInstaller implements InstallerInterface
{
    public function name(): string
    {
        return 'invoicing';
    }

    public function description(): string
    {
        return 'Invoicing plugin for Sylius';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Invoicing Plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require sylius/invoicing-plugin');
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
