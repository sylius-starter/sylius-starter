<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways\Remover;

use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\PaymentGateways\Attribute\AsPaymentGatewayRemover;

final readonly class PaymentGatewayRemoverDescriptor
{
    public function __construct(
        public AsPaymentGatewayRemover $attribute,
        public \ReflectionFunction|RemoverInterface $remover,
    ) {}
}
