<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Customer\Customer;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webmozart\Assert\Assert;

#[AsEventListener(event: 'sylius.customer.pre_register')]
final readonly class LocaleCodeSetterListener
{
    public function __construct(
        private DisabledUserRegistrationListener $decorated,
        private LocaleContextInterface $localeContext,
    ) {}

    public function __invoke(GenericEvent $event): void
    {
        $this->decorated->handleUserVerification($event);

        $subject = $event->getSubject();
        Assert::isInstanceOf($subject, Customer::class);

        $subject->setLocaleCode($this->localeContext->getLocaleCode());
    }
}
