<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_admin.customer.show.content.header.title_block.actions' => [
                'accept' => [
                    'template' => 'admin/customer/show/content/header/title_block/actions/accept.html.twig',
                ],
                'reject' => [
                    'template' => 'admin/customer/show/content/header/title_block/actions/reject.html.twig',
                ],
            ],
        ],
    ],
]);
