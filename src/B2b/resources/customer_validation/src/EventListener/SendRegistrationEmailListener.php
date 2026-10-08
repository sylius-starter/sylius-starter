<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Customer\Customer;
use Sylius\Bundle\CoreBundle\Mailer\Emails as CoreBundleEmails;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

#[AsCompletedListener(workflow: 'customer_validation', transition: 'accept')]
final readonly class SendRegistrationEmailListener
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private SenderInterface $emailSender,
        private ChannelRepositoryInterface $channelRepository,
    ) {}

    public function __invoke(CompletedEvent $event): void
    {
        $subject = $event->getSubject();

        Assert::isInstanceOf($subject, Customer::class);

        /** @var ShopUserInterface $user */
        $user = $subject->getUser();

        $email = $subject->getEmail();
        $channel = $this->channelRepository->findOneByCode($subject->getRegistrationChannel() ?? throw new \LogicException('Unknown channel code'));

        /* @phpstan-ignore-next-line */
        $this->emailSender->send(
            CoreBundleEmails::USER_REGISTRATION,
            [$email],
            [
                'user' => $user,
                'channel' => $channel,
                'localeCode' => $subject->getLocaleCode() ?? 'en_US',
            ],
        );
    }
}
