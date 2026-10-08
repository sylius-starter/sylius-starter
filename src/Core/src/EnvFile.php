<?php

declare(strict_types=1);

namespace Castor\Sylius;

use function Castor\fs;

final class EnvFile
{
    private string $content;

    public function __construct(
        private readonly string $path,
    ) {
        $this->content = fs()->readFile($path);
    }

    public function set(string $name, string $value): self
    {
        $value = $this->escapeValue($value);

        $pattern = \sprintf(
            '/^%s=.*$/m',
            preg_quote($name, '/'),
        );

        $line = \sprintf('%s=%s', $name, $value);

        if (1 === preg_match($pattern, $this->content)) {
            $this->content = preg_replace(
                $pattern,
                $line,
                $this->content,
                1,
            );

            return $this;
        }

        $this->content = rtrim($this->content) . "\n{$line}\n";

        return $this;
    }

    public function save(): self
    {
        fs()->dumpFile($this->path, $this->content);

        return $this;
    }

    private function escapeValue(string $value): string
    {
        if ('' === $value || preg_match('/[\s#"\']/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
