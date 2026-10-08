<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;
use Symfony\Component\Finder\Finder;

use function Castor\fs;

final readonly class Filesystem
{
    public static function latestFile(App $app, string $directory): ?string
    {
        $directory = $app->directory() . '/' . $directory;

        if (!is_dir($directory)) {
            return null;
        }


        $finder = new Finder();
        $finder->files()
            ->in($directory)
            ->sortByModifiedTime()
            ->reverseSorting()
        ;

        foreach ($finder as $file) {
            return $file->getRelativePathname();
        }

        return null;
    }

    public static function createFile(App $app, string $file, string $body, bool $override = false): void
    {
        $realFile = $app->directory() . '/' . $file;

        if (file_exists($realFile) && !$override) {
            throw new \RuntimeException(\sprintf('File "%s" already exists.', $file));
        }

        fs()->dumpFile($realFile, $body);
    }

    public static function hasFile(App $app, string $file): bool
    {
        return file_exists($app->directory() . '/' . $file);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function exportArray(array $data): string
    {
        $export = var_export($data, true);

        $export = str_replace(
            ['array (', ')'],
            ['[', ']'],
            $export,
        );

        $replaced = preg_replace(
            '/^\s+\d+\s=>\s/m',
            '',
            $export,
        );

        if (null !== $replaced) {
            $export = $replaced;
        }

        $replaced = preg_replace(
            "/ => \n\\s+\\[/",
            ' => [',
            $export,
        );

        if (null !== $replaced) {
            $export = $replaced;
        }

        $replaced = preg_replace(
            "/\n{2,}/",
            "\n",
            $export,
        );

        return $replaced ?? $export;
    }
}
