<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsDecorator(decorates: 'sylius_shop.listener.user_registration')]
final readonly class DisabledUserRegistrationListener
{
    public function handleUserVerification(GenericEvent $event): void
    {
        // Nothing to do;
    }
}
