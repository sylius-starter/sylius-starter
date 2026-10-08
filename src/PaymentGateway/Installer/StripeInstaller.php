<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;
use Castor\Sylius\Util\Assets;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\io;

final readonly class StripeInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'stripe';
    }

    public function description(): ?string
    {
        return null;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Stripe Plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require flux-se/sylius-stripe-plugin');
        Docker::run($app, 'yarn install');
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
