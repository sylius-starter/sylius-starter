<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway\Installer;

use Castor\Sylius\Attribute\AsPaymentGatewayInstaller;
use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;

final readonly class PaymentGatewayInstallerDescriptor
{
    public function __construct(
        public AsPaymentGatewayInstaller $attribute,
        public \ReflectionFunction|PluginInstallerInterface $installer,
    ) {}
}
