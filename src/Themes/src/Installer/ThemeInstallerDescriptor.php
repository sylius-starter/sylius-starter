<?php

declare(strict_types=1);

namespace SyliusStarter\Themes\Installer;

use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Themes\Attribute\AsThemeInstaller;

final readonly class ThemeInstallerDescriptor
{
    public function __construct(
        public AsThemeInstaller $attribute,
        public \ReflectionFunction|InstallerInterface $installer,
    ) {}
}
