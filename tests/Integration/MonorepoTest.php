<?php

declare(strict_types=1);

namespace SyliusStarter\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SyliusStarter\Monorepo\Monorepo;

final class MonorepoTest extends TestCase
{
    private Monorepo $monorepo;

    protected function setUp(): void
    {
        $this->monorepo = new Monorepo(\dirname(__DIR__, 2));
    }

    public function testTheMonorepoIsValid(): void
    {
        static::assertSame([], $this->monorepo->validate());
    }

    public function testCoreIsTheFirstPackage(): void
    {
        static::assertSame('sylius-starter/core', $this->monorepo->packages()[0]['name']);
    }

    public function testRootComposerReplacesEveryPackage(): void
    {
        $replace = $this->monorepo->rootComposer()['replace'] ?? [];

        foreach ($this->monorepo->packages() as $package) {
            static::assertSame('self.version', $replace[$package['name']] ?? null, $package['name']);
        }
    }

    public function testItResolvesPackagesByShortNameOrDirectory(): void
    {
        static::assertSame('src/PaymentGateways', $this->monorepo->package('payment-gateways')['path']);
        static::assertSame('src/PaymentGateways', $this->monorepo->package('PaymentGateways')['path']);
        static::assertSame('src/PaymentGateways', $this->monorepo->package('sylius-starter/payment-gateways')['path']);
    }

    public function testItBuildsRemoteUrls(): void
    {
        static::assertSame('git@github.com:sylius-starter/core.git', Monorepo::remoteUrl('git@github.com:{name}.git', 'sylius-starter/core'));
        static::assertSame('https://github.com/acme/sylius-core.git', Monorepo::remoteUrl('https://github.com/acme/sylius-{short}.git', 'sylius-starter/core'));
    }

    public function testItDetectsAnUnsyncedRootComposer(): void
    {
        $root = $this->monorepo->rootComposer();
        unset($root['replace']['sylius-starter/themes']);

        static::assertNotSame($root, $this->monorepo->mergedRootComposer($root));
        static::assertSame($this->monorepo->rootComposer(), $this->monorepo->mergedRootComposer($root));
    }
}
