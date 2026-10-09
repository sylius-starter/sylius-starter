<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.shared.product.card.prices' => [
                'price' => [
                    'condition' => '@=is_granted("CAN_ACCESS_B2B_SHOP")',
                ],
            ],

            'sylius_shop.product.show.content.info.summary.prices' => [
                'price' => [
                    'condition' => '@=is_granted("CAN_ACCESS_B2B_SHOP")',
                ],
                'lowest_price_before_discount' => [
                    'condition' => '@=is_granted("CAN_ACCESS_B2B_SHOP")',
                ],
            ],
        ],
    ],
]);
