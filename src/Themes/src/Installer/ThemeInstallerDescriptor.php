<?php

declare(strict_types=1);

namespace Castor\Sylius\Theme\Installer;

use Castor\Sylius\Attribute\AsThemeInstaller;
use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;

final readonly class ThemeInstallerDescriptor
{
    public function __construct(
        public AsThemeInstaller $attribute,
        public \ReflectionFunction|PluginInstallerInterface $installer,
    ) {}
}
