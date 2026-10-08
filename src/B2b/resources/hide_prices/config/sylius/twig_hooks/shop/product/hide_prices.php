<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.shared.product.card.prices' => [
                'price' => [
                    'props' => [
                        'template' => 'shop/product/common/price.html.twig',
                    ],
                ],
            ],

            'sylius_shop.product.show.content.info.summary.prices' => [
                'price' => [
                    'props' => [
                        'template' => 'shop/product/show/content/info/summary/prices/price.html.twig',
                    ],
                ],
            ],
        ],
    ],
]);
