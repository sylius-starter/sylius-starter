<?php

declare(strict_types=1);

namespace SyliusStarter\Themes\Remover;

use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Themes\Attribute\AsThemeRemover;

final readonly class ThemeRemoverDescriptor
{
    public function __construct(
        public AsThemeRemover $attribute,
        public \ReflectionFunction|RemoverInterface $remover,
    ) {}
}
