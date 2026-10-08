<?php

declare(strict_types=1);

namespace App\Admin\Dashboard\Twig\Component;

use App\Admin\Dashboard\PendingAction\Provider\CountCustomersToProcessProvider;
use Sylius\Bundle\AdminBundle\Twig\Component\Dashboard\PendingActionCountComponent;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent(
    name: 'sylius_admin:dashboard:pending_action:count_customers_to_process',
    template: 'admin/dashboard/index/component/pending_actions/body/customers_to_process.html.twig',
    route: 'sylius_admin_live_component',
)]
final class CountCustomersToProcessComponent extends PendingActionCountComponent
{
    public function __construct(
        ChannelRepositoryInterface $channelRepository,
        CountCustomersToProcessProvider $pendingActionProvider,
    ) {
        parent::__construct($channelRepository, $pendingActionProvider);
    }
}
