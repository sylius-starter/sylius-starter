<?php

declare(strict_types=1);

namespace Castor\Sylius\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_FUNCTION)]
class AsPluginRemover
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}
}
