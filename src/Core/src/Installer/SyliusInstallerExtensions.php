<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Installer;

final class SyliusInstallerExtensions
{
    /** @var array<string, SyliusInstallerExtensionInterface> */
    private static array $extensions = [];

    public static function register(string $name, SyliusInstallerExtensionInterface $extension): void
    {
        self::$extensions[$name] = $extension;
    }

    public static function unregister(string $name): void
    {
        unset(self::$extensions[$name]);
    }

    /**
     * @return array<string, SyliusInstallerExtensionInterface>
     */
    public static function all(): array
    {
        return self::$extensions;
    }
}
