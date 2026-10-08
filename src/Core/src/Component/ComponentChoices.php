<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Component;

final class ComponentChoices
{
    /**
     * Builds the labels displayed by interactive choice questions:
     * "name - description", or the bare name without description.
     *
     * @param array<string, InstallerInterface|RemoverInterface> $components
     *
     * @return array<string, string>
     */
    public static function build(array $components): array
    {
        $choices = [];

        foreach ($components as $name => $component) {
            $description = $component->description();

            $choices[$name] = null === $description || '' === $description
                ? $name
                : \sprintf('%s - %s', $name, $description);
        }

        ksort($choices);

        return $choices;
    }
}
