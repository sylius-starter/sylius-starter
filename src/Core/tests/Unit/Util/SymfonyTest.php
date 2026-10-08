<?php

declare(strict_types=1);

namespace Unit\Util;

use Castor\Container;
use Castor\Sylius\App;
use Castor\Sylius\Util\Symfony;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(Symfony::class)]
final class SymfonyTest extends TestCase
{
    private string $root;

    private Filesystem $filesystem;

    private App $app;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/castor-symfony-' . uniqid('', true);
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->root);

        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Container::class, 'fs'))->setValue($container, $this->filesystem);
        Container::set($container);

        $this->app = new App('app', $this->root);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);
    }


    public function testRemoveJsControllerRemovesTheControllerAndItsEmptyPackage(): void
    {
        $this->write('assets/shop/controllers.json', <<<'JSON'
            {
              "controllers": {
                "@sylius/shop-bundle": {
                  "api-login": {
                    "enabled": true,
                    "fetch": "lazy"
                  }
                }
              },
              "entrypoints": []
            }
            JSON);

        Symfony::removeJsController($this->app, '@sylius/shop-bundle', 'api-login', 'assets/shop/controllers.json');

        $data = json_decode($this->read('assets/shop/controllers.json'), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame([], $data['controllers']);
        static::assertSame([], $data['entrypoints']);
    }

    public function testRemoveJsControllerKeepsSiblingsAndPackages(): void
    {
        $this->write('assets/shop/controllers.json', <<<'JSON'
            {
              "controllers": {
                "@sylius/shop-bundle": {
                  "api-login": {
                    "enabled": true
                  },
                  "search": {
                    "enabled": true
                  }
                },
                "@sylius/admin-bundle": {
                  "api-login": {
                    "enabled": true
                  }
                }
              },
              "entrypoints": []
            }
            JSON);

        Symfony::removeJsController($this->app, '@sylius/shop-bundle', 'api-login', 'assets/shop/controllers.json');

        $data = json_decode($this->read('assets/shop/controllers.json'), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['search' => ['enabled' => true]], $data['controllers']['@sylius/shop-bundle']);
        static::assertArrayHasKey('@sylius/admin-bundle', $data['controllers']);
    }

    public function testRemoveJsControllerLeavesFileUntouchedWhenControllerIsAbsent(): void
    {
        $original = <<<'JSON'
            {
              "controllers": {
                "@sylius/shop-bundle": {
                  "search": {
                    "enabled": true
                  }
                }
              },
              "entrypoints": []
            }
            JSON;

        $this->write('assets/shop/controllers.json', $original);

        Symfony::removeJsController($this->app, '@sylius/shop-bundle', 'api-login', 'assets/shop/controllers.json');

        static::assertSame($original, $this->read('assets/shop/controllers.json'));
    }

    private function write(string $file, string $content): void
    {
        $path = $this->root . '/' . $file;

        $this->filesystem->mkdir(\dirname($path));
        static::assertNotFalse(file_put_contents($path, $content));
    }

    private function read(string $file): string
    {
        return (string) file_get_contents($this->root . '/' . $file);
    }
}
