<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Tests\Unit\Storefront;

use Castor\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Storefront\Hero;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(Hero::class)]
final class HeroTest extends TestCase
{
    private string $root;

    private Filesystem $filesystem;

    private App $app;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/castor-hero-' . uniqid('', true);
        $this->filesystem = new Filesystem();
        $this->filesystem->dumpFile($this->root . '/config/packages/_sylius.yaml', <<<'YAML'
            imports:
                - { resource: "@SyliusCoreBundle/Resources/config/app/config.yml" }

            YAML);

        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Container::class, 'fs'))->setValue($container, $this->filesystem);
        Container::set($container);

        $this->app = new App('app', $this->root);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);
    }

    public function testInstallCopiesTheSlotIntoTheApplication(): void
    {
        static::assertFalse(Hero::isInstalled($this->app));

        Hero::install($this->app);

        static::assertTrue(Hero::isInstalled($this->app));
        static::assertFileExists($this->root . '/src/Twig/Components/StorefrontHero.php');
        static::assertFileExists($this->root . '/src/Storefront/Hero/HeroContent.php');
        static::assertFileExists($this->root . '/src/Storefront/Hero/HeroContributorInterface.php');
        static::assertFileExists($this->root . '/templates/storefront/hero/component.html.twig');
        static::assertFileExists($this->root . '/templates/storefront/hero/default.html.twig');
        static::assertFileDoesNotExist($this->root . '/' . Hero::THEME_TEMPLATE, 'Core must never ship the theme template.');
    }

    public function testInstallIsIdempotent(): void
    {
        Hero::install($this->app);
        Hero::install($this->app);

        $config = (string) file_get_contents($this->root . '/config/packages/_sylius.yaml');

        static::assertSame(1, substr_count($config, Hero::CONFIG_IMPORT));
    }

    public function testInstallRefreshesCoreOwnedFiles(): void
    {
        Hero::install($this->app);

        $component = $this->root . '/src/Twig/Components/StorefrontHero.php';
        file_put_contents($component, '<?php // stale');
        touch($component, time() + 3600);

        Hero::install($this->app);

        static::assertStringContainsString('final class StorefrontHero', (string) file_get_contents($component));
    }
}
