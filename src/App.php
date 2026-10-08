<?php

declare(strict_types=1);

namespace Castor\Sylius;

final readonly class App
{
    public function __construct(
        private string $name,
        private string $directory,
        private ?string $domain = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function domain(): ?string
    {
        return $this->domain;
    }
}
