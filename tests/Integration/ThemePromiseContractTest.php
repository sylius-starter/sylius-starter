<?php

declare(strict_types=1);

namespace SyliusStarter\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Every theme renders a "promise" block on the homepage through the shared
 * "theme_promise" hookable. The "theme_" prefix belongs to the themes package,
 * so that no other package (import...) hides or overrides it by accident.
 */
#[CoversNothing]
final class ThemePromiseContractTest extends TestCase
{
    private const HOOK_CONFIG = 'config/sylius/twig_hooks/shop/homepage/index.php';
    private const TEMPLATE = 'templates/shop/homepage/promise.html.twig';

    /**
     * @return iterable<string, array{string}>
     */
    public static function themes(): iterable
    {
        foreach ((new Finder())->directories()->in(self::themesDir())->depth(0) as $dir) {
            yield $dir->getFilename() => [$dir->getFilename()];
        }
    }

    #[DataProvider('themes')]
    public function testEveryThemeShipsThePromiseTemplate(string $theme): void
    {
        static::assertFileExists(self::themesDir() . '/' . $theme . '/' . self::TEMPLATE);
    }

    #[DataProvider('themes')]
    public function testEveryThemeConfiguresThePromiseHookable(string $theme): void
    {
        $config = (string) file_get_contents(self::themesDir() . '/' . $theme . '/' . self::HOOK_CONFIG);

        static::assertMatchesRegularExpression(
            '/\'theme_promise\'\s*=>\s*\[\s*\'template\'\s*=>\s*\'shop\/homepage\/promise\.html\.twig\'/',
            $config,
            \sprintf('Theme "%s" must render its promise block through the "theme_promise" hookable.', $theme),
        );
    }

    public function testOnlyThemesDeclareThemePrefixedHookables(): void
    {
        $root = \dirname(__DIR__, 2);
        $offenders = [];

        $configs = (new Finder())
            ->files()
            ->in($root . '/src/*/resources')
            ->path('#config/sylius/twig_hooks/#')
            ->name(['*.php', '*.yaml', '*.yml']);

        foreach ($configs as $file) {
            if (str_starts_with($file->getRealPath(), self::themesDir() . '/')) {
                continue;
            }

            if (preg_match('/^\s*(?:\'theme_[a-z_]+\'\s*=>|theme_[a-z_]+:)/m', $file->getContents())) {
                $offenders[] = $file->getRealPath();
            }
        }

        static::assertSame([], $offenders, 'Hookables prefixed with "theme_" belong to the themes package.');
    }

    private static function themesDir(): string
    {
        return (string) realpath(\dirname(__DIR__, 2) . '/src/Themes/resources');
    }
}
