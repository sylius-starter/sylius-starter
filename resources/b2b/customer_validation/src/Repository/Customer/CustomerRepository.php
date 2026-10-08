<?php

declare(strict_types=1);

namespace App\Repository\Customer;

use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\CustomerRepository as BaseCustomerRepository;

/**
 * @extends BaseCustomerRepository<Customer>
 */
class CustomerRepository extends BaseCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        parent::__construct($entityManager, new ClassMetadata(Customer::class));
    }

    public function countCustomerToProcess(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.state = :state')
            ->setParameter('state', Customer::STATE_NEW)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
