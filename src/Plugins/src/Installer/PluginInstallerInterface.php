<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\App;

interface PluginInstallerInterface
{
    public function name(): string;

    public function description(): ?string;

    public function __invoke(App $app): void;
}
