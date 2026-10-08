<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.checkout.common.header' => [
                'logo' => [
                    'template' => 'shop/checkout/common/header/logo.html.twig',
                    'priority' => 100,
                ],
                'taxon_menu' => [
                    'component' => 'sylius_shop:common:taxon_menu',
                    'props' => [
                        'template' => 'shop/checkout/common/header/taxon_menu.html.twig',
                    ],
                    'priority' => 50,
                ],
                'account' => [
                    'template' => 'shop/checkout/common/header/account.html.twig',
                    'priority' => 0,
                ],
            ],
        ],
    ],
]);
