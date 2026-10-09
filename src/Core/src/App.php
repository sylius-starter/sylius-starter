<?php

declare(strict_types=1);

namespace SyliusStarter\Core;

final readonly class App
{
    public function __construct(
        private string $name,
        private string $directory,
        private ?string $domain = null,
        private ?string $subdomain = null,
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

    public function subdomain(): ?string
    {
        return $this->subdomain;
    }

    /**
     * Shop hostname: {@code subdomain.domain} when a subdomain is set, otherwise the apex domain.
     */
    public function hostname(): ?string
    {
        if (null === $this->domain || '' === $this->domain) {
            return null;
        }

        if (null !== $this->subdomain && '' !== $this->subdomain) {
            return $this->subdomain . '.' . $this->domain;
        }

        return $this->domain;
    }
}
