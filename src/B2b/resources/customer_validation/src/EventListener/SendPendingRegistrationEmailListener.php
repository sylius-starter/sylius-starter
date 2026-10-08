<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Customer\Customer;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webmozart\Assert\Assert;

#[AsEventListener(event: 'sylius.customer.post_register')]
final readonly class SendPendingRegistrationEmailListener
{
    public function __construct(
        private SenderInterface $emailSender,
    ) {}

    public function __invoke(GenericEvent $event): void
    {
        $subject = $event->getSubject();

        Assert::isInstanceOf($subject, Customer::class);

        /* @phpstan-ignore-next-line */
        $this->emailSender->send(
            'sylius.customer_validation.registration',
            [$subject->getEmail()],
            [
                'customer' => $subject,
                'localeCode' => $subject->getLocaleCode() ?? 'en_US',
            ],
        );
    }
}
