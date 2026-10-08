<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways\Installer;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Util\Assets;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;

use function Castor\io;

final readonly class StripeInstaller implements InstallerInterface
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
