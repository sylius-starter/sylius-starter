<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Component;

use SyliusStarter\Core\App;

interface RemoverInterface
{
    public function name(): string;

    public function description(): ?string;

    public function __invoke(App $app): void;
}
