<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways\Installer;

use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\PaymentGateways\Attribute\AsPaymentGatewayInstaller;

final readonly class PaymentGatewayInstallerDescriptor
{
    public function __construct(
        public AsPaymentGatewayInstaller $attribute,
        public \ReflectionFunction|InstallerInterface $installer,
    ) {}
}
