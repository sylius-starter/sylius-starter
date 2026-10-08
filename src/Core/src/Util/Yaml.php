<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;

use function Castor\fs;

final readonly class Yaml
{
    public static function import(App $app, string $file, string $resource): void
    {
        $realFile = $app->directory() . '/' . $file;

        $content = fs()->readFile($realFile);

        $import = \sprintf(
            "    - { resource: '%s' }",
            $resource,
        );

        if (str_contains($content, $import)) {
            return;
        }

        if (preg_match('/^imports:\R/m', $content)) {
            $replaced = preg_replace(
                '/(^imports:\R(?:^[ \t]+.*\R?)*)/m',
                "$1{$import}\n",
                $content,
                1,
            );

            if (null !== $replaced) {
                $content = $replaced;
            }
        } else {
            $content
                = "imports:\n"
                . $import
                . "\n\n"
                . ltrim($content);
        }

        fs()->dumpFile($realFile, $content);
    }

    public static function uncommentBlock(App $app, string $file, string $block): void
    {
        $realFile = $app->directory() . '/' . $file;

        $content = fs()->readFile($realFile);

        $lines = explode("\n", trim($block));

        $pattern = implode(
            '\R',
            array_map(
                static fn(string $line): string => \sprintf(
                    '[ \t]*#%s',
                    preg_quote($line, '/'),
                ),
                $lines,
            ),
        );

        $replaced = preg_replace_callback(
            \sprintf('/%s/m', $pattern),
            static fn(array $matches): string => preg_replace(
                '/^([ \t]*)#/m',
                '$1',
                $matches[0],
            ) ?? $matches[0],
            $content,
            1,
        );

        if (null !== $replaced) {
            $content = $replaced;
        }

        fs()->dumpFile($realFile, $content);
    }

    public static function appendToSection(
        App $app,
        string $file,
        string $section,
        string $block,
    ): void {
        $realFile = $app->directory() . '/' . $file;

        $content = fs()->readFile($realFile);

        $trimmedBlock = preg_replace('/^\R+|\R+$/', '', $block);

        if (null === $trimmedBlock) {
            return;
        }

        $block = $trimmedBlock;

        if (str_contains($content, $block)) {
            return;
        }

        $pattern = \sprintf(
            '/^%s:\R(.*?)(?=^[^\s]|\z)/ms',
            preg_quote($section, '/'),
        );

        $replaced = preg_replace_callback(
            $pattern,
            static fn(array $matches): string => \sprintf(
                "%s:\n%s\n\n%s\n",
                $section,
                rtrim($matches[1]),
                $block,
            ),
            $content,
            1,
        );

        if (null !== $replaced) {
            $content = $replaced;
        }

        fs()->dumpFile($realFile, $content);
    }

    public static function addImport(App $app, string $file, string $resource): void
    {
        $realFile = $app->directory() . '/' . $file;

        $content = fs()->readFile($realFile);

        $import = \sprintf(
            "    - { resource: '%s' }",
            $resource,
        );

        if (str_contains($content, $import)) {
            return;
        }

        if (preg_match('/^imports:\R/m', $content)) {
            $replaced = preg_replace(
                '/(^imports:\R(?:^[ \t]+.*\R?)*)/m',
                "$1{$import}\n",
                $content,
                1,
            );

            if (null !== $replaced) {
                $content = $replaced;
            }
        } else {
            $content
                = "imports:\n"
                . $import
                . "\n\n"
                . ltrim($content);
        }

        fs()->dumpFile($realFile, $content);
    }

    public static function addImportWithOptions(
        App $app,
        string $file,
        string $resource,
        bool $ignoreErrors = false,
    ): void {
        $realFile = $app->directory() . '/' . $file;

        $content = fs()->readFile($realFile);

        $import = $ignoreErrors
            ? \sprintf("    - { resource: '%s', ignore_errors: not_found }", $resource)
            : \sprintf("    - { resource: '%s' }", $resource);

        if (str_contains($content, $import)) {
            return;
        }

        if (preg_match('/^imports:\R/m', $content)) {
            $replaced = preg_replace(
                '/(^imports:\R(?:^[ \t]+.*\R?)*)/m',
                "$1{$import}\n",
                $content,
                1,
            );

            if (null !== $replaced) {
                $content = $replaced;
            }
        } else {
            $content
                = "imports:\n"
                . $import
                . "\n\n"
                . ltrim($content);
        }

        fs()->dumpFile($realFile, $content);
    }
}
