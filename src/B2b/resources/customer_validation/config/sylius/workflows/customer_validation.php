<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Entity\Customer\Customer;

return App::config([
    'framework' => [
        'workflows' => [
            'customer_validation' => [
                'type' => 'state_machine',
                'marking_store' => [
                    'type' => 'method',
                    'property' => 'state',
                ],
                'supports' => [
                    Customer::class,
                ],
                'initial_marking' => Customer::STATE_NEW,
                'places' => [
                    Customer::STATE_NEW,
                    Customer::STATE_ACCEPTED,
                    Customer::STATE_REJECTED,
                ],
                'transitions' => [
                    'accept' => [
                        'from' => Customer::STATE_NEW,
                        'to' => Customer::STATE_ACCEPTED,
                    ],
                    'reject' => [
                        'from' => Customer::STATE_NEW,
                        'to' => Customer::STATE_REJECTED,
                    ],
                ],
            ],
        ],
    ],
]);
