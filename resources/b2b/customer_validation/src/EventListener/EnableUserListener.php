<?php

declare(strict_types=1);

namespace App\EventListener;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Webmozart\Assert\Assert;

#[AsTransitionListener(workflow: 'customer_validation', transition: 'accept')]
final readonly class EnableUserListener
{
    public function __invoke(TransitionEvent $event): void
    {
        $subject = $event->getSubject();

        Assert::isInstanceOf($subject, CustomerInterface::class);

        /** @var ShopUserInterface $user */
        $user = $subject->getUser();

        $user->setEnabled(true);
    }
}
