<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_shop.account.register.content.form' => [
                'captcha' => [
                    'template' => 'shop/account/register/content/form/captcha.html.twig',
                ],
            ],
        ],
    ],
]);
