<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Feature;

use SyliusStarter\Core\App;

interface FeatureInterface
{
    public function name(): string;

    public function description(): string;

    public function __invoke(App $app): void;
}
