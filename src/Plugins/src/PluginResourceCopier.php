<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin;

use Castor\Sylius\App;

use function Castor\finder;
use function Castor\fs;

final readonly class PluginResourceCopier
{
    public static function copy(App $app, string $plugin): void
    {
        $resourcesDir = self::resourcesDir($plugin);

        foreach (finder()->files()->in($resourcesDir) as $file) {
            self::copyResource($resourcesDir, $file->getRelativePathname(), $app->directory());
        }
    }

    private static function copyResource(string $sourceDir, string $relativePath, string $destinationDir): void
    {
        $source = $sourceDir . '/' . $relativePath;
        $destination = $destinationDir . '/' . $relativePath;

        fs()->copy($source, $destination);
    }

    private static function resourcesDir(string $plugin): string
    {
        return \dirname(__DIR__, 2) . '/resources/plugin/' . $plugin;
    }
}
