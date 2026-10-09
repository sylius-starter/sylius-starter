<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SyliusStarter\Core\App;

final class AppTest extends TestCase
{
    public function testHostnameIsApexWhenSubdomainIsOmitted(): void
    {
        $app = new App('app', 'app', 'sylius-starter-sk.test');

        static::assertNull($app->subdomain());
        static::assertSame('sylius-starter-sk.test', $app->hostname());
    }

    public function testHostnamePrefixedWithSubdomain(): void
    {
        $app = new App('app', 'app', 'sylius-starter-sk.test', 'shop');

        static::assertSame('shop', $app->subdomain());
        static::assertSame('shop.sylius-starter-sk.test', $app->hostname());
    }

    public function testHostnameIsNullWithoutDomain(): void
    {
        $app = new App('app', 'app', null, 'shop');

        static::assertNull($app->hostname());
    }
}
