<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways;

use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Component\RemoverInterface;

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

    public static function addInstaller(InstallerInterface $installer): void
    {
        self::$installers[$installer->name()] = $installer;
    }

    public static function removers(): array
    {
        return self::$removers;
    }

    public static function addRemover(RemoverInterface $remover): void
    {
        self::$removers[$remover->name()] = $remover;
    }
}
