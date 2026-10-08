<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.account.login' => [
                'content' => [
                    'template' => 'shop/account/login/content.html.twig',
                ],
            ],

            'sylius_shop.account.login.content' => [
                'register_container' => [
                    'enabled' => false,
                ],
                'img_container' => [
                    'template' => 'shop/account/login/content/img_container.html.twig',
                    'priority' => 150,
                ],
                'login_container' => [
                    'template' => 'shop/account/login/content/login_container.html.twig',
                ],
            ],

            'sylius_shop.account.login.content.login_container' => [
                'header' => [
                    'template' => 'shop/account/login/content/login_container/header.html.twig',
                ],
            ],
        ],
    ],
]);
