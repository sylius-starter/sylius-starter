<?php

declare(strict_types=1);

namespace App\Admin\Grid\Mutator;

use App\Entity\Customer\Customer;
use Sylius\Bundle\GridBundle\Builder\Action\Action;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Component\Grid\Attribute\AsGridMutator;
use Sylius\Component\Grid\Builder\GridBuilderInterface;
use Sylius\Component\Grid\Mutator\GridMutatorInterface;

#[AsGridMutator(grid: 'sylius_admin_customer')]
final class CustomerValidationGridMutator implements GridMutatorInterface
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->withFilters(
                SelectFilter::create('state', [
                    'sylius.ui.new' => Customer::STATE_NEW,
                    'sylius.ui.accepted' => Customer::STATE_ACCEPTED,
                    'sylius.ui.rejected' => Customer::STATE_REJECTED,
                ])
                    ->setLabel('sylius.ui.state'),
            )
            ->withFields(
                TwigField::create('state', 'admin/customer/grid/field/state.html.twig')
                    ->setLabel('sylius.ui.state'),
            )
            ->withItemActions(
                Action::create('reject', 'custom')
                    ->setTemplate('admin/customer/grid/action/reject.html.twig')
                    ->setLabel('sylius.ui.reject'),
                Action::create('validate', 'custom')
                    ->setTemplate('admin/customer/grid/action/accept.html.twig')
                    ->setLabel('sylius.ui.accept'),
            )
        ;
    }
}
