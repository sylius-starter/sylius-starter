<?php

declare(strict_types=1);

namespace Castor\Sylius\B2b\Feature;

use Castor\Sylius\App;
use Castor\Sylius\B2b\B2bResourceCopier;
use Castor\Sylius\Feature\FeatureInterface;

use function Castor\io;

final readonly class HideCheckoutFeature implements FeatureInterface
{
    public function name(): string
    {
        return 'hide_checkout';
    }

    public function description(): string
    {
        return 'Hide cart and checkout for guests';
    }

    public function __invoke(App $app): void
    {
        B2bResourceCopier::copy($app, $this->name());

        io()->success('Checkout has been hidden successfully.');
    }
}
