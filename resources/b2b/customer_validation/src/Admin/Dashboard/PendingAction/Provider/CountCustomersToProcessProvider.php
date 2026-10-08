<?php

declare(strict_types=1);

namespace App\Admin\Dashboard\PendingAction\Provider;

use App\Repository\Customer\CustomerRepositoryInterface;
use Sylius\Bundle\AdminBundle\PendingAction\Provider\PendingActionCountProviderInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class CountCustomersToProcessProvider implements PendingActionCountProviderInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function count(?ChannelInterface $channel = null): int
    {
        return $this->customerRepository->countCustomerToProcess();
    }
}
