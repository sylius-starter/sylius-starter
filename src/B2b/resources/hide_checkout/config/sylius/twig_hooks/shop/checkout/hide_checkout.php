<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

// Cart and checkout routes themselves are protected by App\EventListener\RequireB2bShopAccessListener.
return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.product.show.content.info.summary' => [
                'add_to_cart' => [
                    'condition' => '@=is_granted("CAN_ACCESS_B2B_SHOP")',
                ],
            ],

            'sylius_shop.base.header.content' => [
                'cart' => [
                    'condition' => '@=is_granted("CAN_ACCESS_B2B_SHOP")',
                ],
            ],
        ],
    ],
]);
