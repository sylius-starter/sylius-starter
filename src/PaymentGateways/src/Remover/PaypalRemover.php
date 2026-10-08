<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Database;
use SyliusStarter\Core\Util\Docker;

use function Castor\io;

final readonly class PaypalRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'paypal';
    }

    public function description(): ?string
    {
        return null;
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Paypal plugin');

        Composer::allowContribRecipes($app);
        Database::rollbackPluginMigrations($app, 'Sylius\PayPalPlugin\Migrations');
        Docker::run($app, 'composer remove sylius/paypal-plugin');
    }
}
