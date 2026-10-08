<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Installer;

use Castor\Attribute\AsListener;
use Castor\Docker\Event\RegisterServiceInstallerEvent;

#[AsListener(RegisterServiceInstallerEvent::class)]
function register_builtin_installers(RegisterServiceInstallerEvent $event): void
{
    $event->addInstaller(new SyliusInstaller());
}
