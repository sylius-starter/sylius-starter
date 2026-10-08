<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.product.show' => [
                'content' => [
                    'template' => 'shop/product/show/content.html.twig',
                ],
            ],
            'sylius_shop.product.show.content' => [
                'info' => [
                    'template' => 'shop/product/show/content/info.html.twig',
                ],
            ],
            'sylius_shop.product.show.content.info.summary' => [
                'prices' => [
                    'priority' => 450,
                ],
            ],
            'sylius_shop.product.show.content.product_listing' => [
                'associations' => [
                    'template' => 'shop/product/show/content/product_listing/associations.html.twig',
                ],
            ],
        ],
    ],
]);
