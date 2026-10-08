<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.base.header' => [
                'top_bar' => [
                    'enabled' => false,
                ],
                'navbar' => [
                    'enabled' => false,
                ],
            ],
            'sylius_shop.base.header.content' => [
                'logo' => [
                    'template' => 'shop/shared/layout/header/logo.html.twig',
                ],
                'cart' => [
                    'component' => 'sylius_shop:cart:widget',
                    'props' => [
                        'template' => 'shop/shared/components/header/cart.html.twig',
                    ],
                ],
                'taxon_hamburger' => [
                    'template' => '@SyliusShop/shared/layout/base/header/content/taxon_hamburger.html.twig',
                    'enabled' => true,
                ],
                'taxon_menu' => [
                    'component' => 'sylius_shop:common:taxon_menu',
                    'props' => [
                        'template' => 'shop/shared/layout/base/header/content/taxon_menu.html.twig',
                    ],
                    'priority' => 250,
                ],
            ],
        ],
    ],
]);
