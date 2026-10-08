<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Installer;

use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Plugins\Attribute\AsPluginInstaller;

final readonly class PluginInstallerDescriptor
{
    public function __construct(
        public AsPluginInstaller $attribute,
        public \ReflectionFunction|InstallerInterface $installer,
    ) {}
}
