<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Database;
use SyliusStarter\Core\Util\Docker;

use function Castor\io;

final readonly class InvoicingRemover implements RemoverInterface
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
        io()->title('Removing Invoicing plugin');

        Composer::allowContribRecipes($app);
        Database::rollbackPluginMigrations($app, 'Sylius\InvoicingPlugin\Migrations');
        Docker::run($app, 'composer remove sylius/invoicing-plugin');
    }
}
