<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.product.show.content.info.summary' => [
                // Disable the add to cart button on the product details page
                'add_to_cart' => [
                    'props' => [
                        'template' => 'shop/product/show/content/info/summary/add_to_cart.html.twig',
                    ],
                ],
            ],

            'sylius_shop.base.header.content' => [
                // Disable the cart on the header
                'cart' => [
                    'props' => [
                        'template' => 'shop/shared/components/header/cart.html.twig',
                    ],
                ],
            ],

            'sylius_shop.cart.index' => [
                // Disable content of the cart summary page
                // But, it could be better to add a condition on the routing configuration
                'content' => [
                    'template' => 'shop/shared/layout/base/header/content.html.twig',
                ],
            ],
        ],
    ],
]);
