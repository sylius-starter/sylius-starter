<?php

declare(strict_types=1);

namespace SyliusStarter\Themes\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_FUNCTION)]
class AsThemeInstaller
{
    public function __construct(
        public string $name,
    ) {}
}
