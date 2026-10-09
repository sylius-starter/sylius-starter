<?php

declare(strict_types=1);

namespace SyliusStarter\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The homepage hero is a slot owned by sylius-starter/core. These checks keep
 * packages from fighting over it again (same template path, same hookable).
 */
#[CoversNothing]
final class StorefrontHeroContractTest extends TestCase
{
    private const CORE_SLOT_DIR = 'src/Core/resources/storefront/hero';

    public function testNoPackageShipsTheLegacyBannerTemplate(): void
    {
        $offenders = [];

        foreach ($this->packageResources()->path('#templates/shop/homepage/banner\.html\.twig$#') as $file) {
            $offenders[] = $file->getRelativePathname();
        }

        static::assertSame([], $offenders, 'Render the hero through templates/shop/homepage/hero.html.twig (themes) or a HeroContributorInterface (data).');
    }

    public function testOnlyCoreConfiguresTheHeroAndBannerHookables(): void
    {
        $offenders = [];

        foreach ($this->packageResources()->path('#config/sylius/twig_hooks/#')->name(['*.php', '*.yaml', '*.yml']) as $file) {
            $content = $file->getContents();

            if (!str_contains($content, 'sylius_shop.homepage.index')) {
                continue;
            }

            if (preg_match('/^\s*(?:\'(?:banner|hero)\'\s*=>|(?:banner|hero):)/m', $content)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        static::assertSame([], $offenders, 'The "banner" and "hero" hookables of sylius_shop.homepage.index belong to core (config/sylius/storefront/hero.yaml).');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function themes(): iterable
    {
        $themesDir = \dirname(__DIR__, 2) . '/src/Themes/resources';

        foreach ((new Finder())->directories()->in($themesDir)->depth(0) as $dir) {
            yield $dir->getFilename() => [$dir->getFilename()];
        }
    }

    #[DataProvider('themes')]
    public function testEveryThemeRendersTheHeroSlot(string $theme): void
    {
        $root = \dirname(__DIR__, 2) . '/src/Themes';

        static::assertFileExists($root . '/resources/' . $theme . '/templates/shop/homepage/hero.html.twig');
    }

    public function testEveryThemeInstallerInstallsTheHeroSlot(): void
    {
        $installers = (new Finder())->files()->in(\dirname(__DIR__, 2) . '/src/Themes/src/Installer')->name('*Installer.php');

        foreach ($installers as $installer) {
            static::assertStringContainsString('Hero::install($app)', $installer->getContents(), $installer->getFilename());
        }
    }

    private function packageResources(): Finder
    {
        $root = \dirname(__DIR__, 2);

        return (new Finder())
            ->files()
            ->in($root . '/src/*/resources')
            ->notPath('#^' . preg_quote(substr(self::CORE_SLOT_DIR, \strlen('src/Core/resources/')), '#') . '#');
    }
}
