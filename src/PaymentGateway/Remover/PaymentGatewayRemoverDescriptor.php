<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway\Remover;

use Castor\Sylius\Attribute\AsPaymentGatewayRemover;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;

final readonly class PaymentGatewayRemoverDescriptor
{
    public function __construct(
        public AsPaymentGatewayRemover $attribute,
        public \ReflectionFunction|PluginRemoverInterface $remover,
    ) {}
}
