<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Util;

use SyliusStarter\Core\App;

use function Castor\fs;

final readonly class Javascript
{
    public static function addImport(App $app, string $file, string $resource): void
    {
        $realFile = $app->directory() . '/' . $file;

        $content = fs()->readFile($realFile);

        $import = \sprintf("import '%s';", $resource);

        if (str_contains($content, $import)) {
            return;
        }

        if (preg_match('/^import\b/m', $content)) {
            $replaced = preg_replace(
                '/((?:^import\b.*?;\R?)+)/ms',
                "$1{$import}\n",
                $content,
                1,
            );

            if (null !== $replaced) {
                $content = $replaced;
            }
        } else {
            $content = $import . "\n" . ltrim($content);
        }

        fs()->dumpFile($realFile, $content);
    }

    public static function removeImport(App $app, string $file, string $resource): void
    {
        $realFile = $app->directory() . '/' . $file;

        $content = fs()->readFile($realFile);

        $import = \sprintf("import '%s';", $resource);

        if (!str_contains($content, $import)) {
            return;
        }

        $replaced = preg_replace(
            '/^' . preg_quote($import, '/') . '\R?/m',
            '',
            $content,
            1,
        );

        if (null !== $replaced) {
            $content = $replaced;
        }

        fs()->dumpFile($realFile, $content);
    }
}
