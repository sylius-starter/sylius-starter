<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.homepage.index' => [
                'banner' => [
                    'template' => 'shop/homepage/banner.html.twig',
                ],
                'latest_deals' => [
                    'enabled' => false,
                ],
                'new_collection' => [
                    'enabled' => false,
                ],
                'latest_products' => [
                    'props' => [
                        'limit' => 4,
                    ],
                ],
            ],
        ],
    ],
]);
