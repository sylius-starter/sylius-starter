<?php

declare(strict_types=1);

namespace Unit\B2b;

use Castor\Sylius\B2b\B2bFeatures;
use Castor\Sylius\Feature\FeatureInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(B2bFeatures::class)]
final class B2bFeaturesTest extends TestCase
{
    public function testExposesFeaturesByName(): void
    {
        $features = B2bFeatures::features();

        static::assertSame([
            'hide_checkout',
            'hide_prices',
            'customer_validation',
        ], array_keys($features));

        foreach ($features as $name => $feature) {
            static::assertInstanceOf(FeatureInterface::class, $feature);
            static::assertSame($name, $feature->name());
            static::assertNotSame('', $feature->description());
        }
    }

    public function testSortsFeatureNames(): void
    {
        static::assertSame([
            'customer_validation',
            'hide_checkout',
            'hide_prices',
        ], B2bFeatures::names());
    }

    public function testProvidesDescriptionsForChoices(): void
    {
        static::assertSame([
            'customer_validation' => 'customer_validation - Require admin approval before customers can sign in',
            'hide_checkout' => 'hide_checkout - Hide cart and checkout for guests',
            'hide_prices' => 'hide_prices - Hide product prices for guests',
        ], B2bFeatures::choices());
    }
}
