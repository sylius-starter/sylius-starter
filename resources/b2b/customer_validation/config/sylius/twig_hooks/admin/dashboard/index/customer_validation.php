<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'sylius_twig_hooks' => [
        'hooks' => [
            'sylius_admin.dashboard.index.content.latest_statistics.pending_actions.body' => [
                'customers_to_process' => [
                    'component' => 'sylius_admin:dashboard:pending_action:count_customers_to_process',
                    'props' => [
                        'channelCode' => '@=_context.channel_code',
                    ],
                    'priority' => 500,
                ],
            ],
        ],
    ],
]);
