<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Remover;

use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Plugins\Attribute\AsPluginRemover;

final readonly class PluginRemoverDescriptor
{
    public function __construct(
        public AsPluginRemover $attribute,
        public \ReflectionFunction|RemoverInterface $remover,
    ) {}
}
