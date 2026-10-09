<?php

declare(strict_types=1);

namespace SyliusStarter\B2b\Feature;

use SyliusStarter\B2b\B2bResourceCopier;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Feature\FeatureInterface;
use SyliusStarter\Core\Util\Composer;

use function Castor\io;

final readonly class HidePricesFeature implements FeatureInterface
{
    public function name(): string
    {
        return 'hide_prices';
    }

    public function description(): string
    {
        return 'Hide product prices for guests';
    }

    public function __invoke(App $app): void
    {
        // Hookable "condition" is only supported since sylius/twig-hooks 0.14
        Composer::requireMinimumVersion($app, 'sylius/twig-hooks', '0.14');
        B2bResourceCopier::copy($app, $this->name());

        io()->success('Prices have been hidden successfully.');
    }
}
