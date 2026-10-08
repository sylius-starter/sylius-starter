<?php

declare(strict_types=1);

namespace Castor\Sylius\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_FUNCTION)]
class AsPaymentGatewayRemover
{
    public function __construct(
        public string $name,
    ) {}
}
