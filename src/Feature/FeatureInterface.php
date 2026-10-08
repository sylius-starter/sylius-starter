<?php

declare(strict_types=1);

namespace Castor\Sylius\Feature;

use Castor\Sylius\App;

interface FeatureInterface
{
    public function name(): string;

    public function description(): string;

    public function __invoke(App $app): void;
}
