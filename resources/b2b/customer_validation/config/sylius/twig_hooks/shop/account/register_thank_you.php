<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.account.register_thank_you.content' => [
                'title' => [
                    'template' => 'shop/account/register/thank_you/title.html.twig',
                    'priority' => 100,
                ],
                'subtitle' => [
                    'template' => 'shop/account/register/thank_you/subtitle.html.twig',
                    'priority' => 0,
                ],
            ],
        ],
    ],
]);
