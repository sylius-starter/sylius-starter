<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Database;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\io;

final readonly class PaypalInstaller implements PluginInstallerInterface
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
