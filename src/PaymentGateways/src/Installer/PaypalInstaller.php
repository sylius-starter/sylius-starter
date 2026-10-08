<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways\Installer;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Database;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;

use function Castor\io;

final readonly class PaypalInstaller implements InstallerInterface
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
        io()->title('Adding Paypal plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require sylius/paypal-plugin');
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
