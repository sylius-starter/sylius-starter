<?php

declare(strict_types=1);

namespace Castor\Sylius\Theme\Remover;

use Castor\Sylius\Attribute\AsPaymentGatewayRemover;
use Castor\Sylius\Attribute\AsThemeInstaller;
use Castor\Sylius\Attribute\AsThemeRemover;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;

final readonly class ThemeRemoverDescriptor
{
    public function __construct(
        public AsThemeRemover $attribute,
        public \ReflectionFunction|PluginRemoverInterface $remover,
    ) {}
}
