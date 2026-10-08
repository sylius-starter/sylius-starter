<?php

declare(strict_types=1);

namespace App\Repository\Customer;

use App\Entity\Customer\Customer;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface as BaseCustomerRepositoryInterface;

/**
 * @extends BaseCustomerRepositoryInterface<Customer>
 */
interface CustomerRepositoryInterface
{
    public function countCustomerToProcess(): int;
}
