<?php

declare(strict_types=1);

namespace Castor\Sylius\Installer;

use Castor\Attribute\AsListener;
use Castor\Docker\Event\RegisterServiceInstallerEvent;

#[AsListener(RegisterServiceInstallerEvent::class)]
function register_builtin_installers(RegisterServiceInstallerEvent $event): void
{
    $event->addInstaller(new SyliusInstaller());
}
