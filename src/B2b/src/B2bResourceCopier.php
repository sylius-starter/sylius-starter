<?php

declare(strict_types=1);

namespace Castor\Sylius\B2b;

use Castor\Sylius\App;
use Symfony\Component\Yaml\Yaml;

use function Castor\finder;
use function Castor\fs;

final readonly class B2bResourceCopier
{
    public static function copy(App $app, string $feature): void
    {
        $sharedResourcesDir = self::resourcesDir('shared');
        $resourcesDir = self::resourcesDir($feature);

        foreach (finder()->files()->in($sharedResourcesDir) as $file) {
            self::copyResource($sharedResourcesDir, $file->getRelativePathname(), $app->directory());
        }

        foreach (finder()->files()->in($resourcesDir) as $file) {
            self::copyResource($resourcesDir, $file->getRelativePathname(), $app->directory());
        }
    }

    private static function copyResource(string $sourceDir, string $relativePath, string $destinationDir): void
    {
        $source = $sourceDir . '/' . $relativePath;
        $destination = $destinationDir . '/' . $relativePath;

        if (preg_match('/^translations\/(?:messages|flashes)\.[a-zA-Z_]+\.yaml$/', $relativePath) && fs()->exists($destination)) {
            $resourceTranslations = Yaml::parseFile($source);
            $existingTranslations = Yaml::parseFile($destination);

            fs()->dumpFile(
                $destination,
                Yaml::dump(array_replace_recursive($resourceTranslations, $existingTranslations), 8, 4),
            );

            return;
        }

        fs()->copy($source, $destination);
    }

    private static function resourcesDir(string $feature): string
    {
        return \dirname(__DIR__, 2) . '/resources/b2b/' . $feature;
    }
}
