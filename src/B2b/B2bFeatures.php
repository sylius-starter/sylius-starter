<?php

declare(strict_types=1);

namespace Castor\Sylius\B2b;

use Castor\Sylius\B2b\Feature\CustomerValidationFeature;
use Castor\Sylius\B2b\Feature\HideCheckoutFeature;
use Castor\Sylius\B2b\Feature\HidePricesFeature;
use Castor\Sylius\Feature\FeatureInterface;

final class B2bFeatures
{
    /**
     * @return array<string, FeatureInterface>
     */
    public static function features(): array
    {
        return [
            'hide_checkout' => new HideCheckoutFeature(),
            'hide_prices' => new HidePricesFeature(),
            'customer_validation' => new CustomerValidationFeature(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        $names = array_keys(self::features());
        sort($names);

        return $names;
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $features = self::features();
        $choices = [];

        foreach (self::names() as $name) {
            $choices[$name] = \sprintf('%s - %s', $name, $features[$name]->description());
        }

        return $choices;
    }
}
