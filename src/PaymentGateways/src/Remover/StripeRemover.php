<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway\Remover;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;
use Castor\Sylius\Util\Assets;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\io;

final readonly class StripeRemover implements PluginRemoverInterface
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
        io()->title('Removing Stripe plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove flux-se/sylius-stripe-plugin');
        Docker::run($app, 'yarn install');
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
