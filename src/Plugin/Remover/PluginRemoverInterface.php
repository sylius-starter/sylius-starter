<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Remover;

use Castor\Sylius\App;

interface PluginRemoverInterface
{
    public function name(): string;

    public function description(): ?string;

    public function __invoke(App $app): void;
}
