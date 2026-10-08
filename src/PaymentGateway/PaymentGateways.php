<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway;

use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;

final class PaymentGateways
{
    private static array $installers = [];

    private static array $removers = [];

    public static function names(): array
    {
        $names = array_keys(self::$installers);
        sort($names);

        return $names;
    }

    public static function installers(): array
    {
        return self::$installers;
    }

    public static function addInstaller(PluginInstallerInterface $installer): void
    {
        self::$installers[$installer->name()] = $installer;
    }

    public static function removers(): array
    {
        return self::$removers;
    }

    public static function addRemover(PluginRemoverInterface $remover): void
    {
        self::$removers[$remover->name()] = $remover;
    }
}
