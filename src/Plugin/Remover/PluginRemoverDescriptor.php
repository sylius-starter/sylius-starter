<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Remover;

use Castor\Sylius\Attribute\AsPluginRemover;

final readonly class PluginRemoverDescriptor
{
    public function __construct(
        public AsPluginRemover $attribute,
        public \ReflectionFunction|PluginRemoverInterface $remover,
    ) {}
}
