<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Installer;

use Castor\Docker\Installer\Input;
use SyliusStarter\Core\App;

/**
 * Lets a package hook into the Sylius service installer (castor docker:service:install sylius):
 * ask extra questions and run extra steps once Sylius has been scaffolded.
 */
interface SyliusInstallerExtensionInterface
{
    /**
     * @return list<Input>
     */
    public function getInputs(): array;

    /**
     * @param array<string, mixed> $answers
     */
    public function afterScaffold(App $app, array $answers): void;
}
