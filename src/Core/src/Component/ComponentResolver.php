<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Component;

use Castor\Exception\FunctionConfigurationException;

/**
 * Shared discovery of user-land installers/removers declared with a PHP
 * attribute (#[AsPluginInstaller], #[AsThemeRemover], ...) on a function or
 * on an invokable class.
 */
final class ComponentResolver
{
    /**
     * @template T of object
     *
     * @param class-string<T> $attributeClass
     *
     * @return array{0: T, 1: \ReflectionFunction|callable}|null
     */
    public static function resolve(\ReflectionFunction|\ReflectionClass $reflection, string $attributeClass): ?array
    {
        $attributes = $reflection->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);

        if ([] === $attributes) {
            return null;
        }

        try {
            /** @var T $attribute */
            $attribute = $attributes[0]->newInstance();
        } catch (\Throwable $e) {
            throw new FunctionConfigurationException(\sprintf('Could not instantiate the attribute "%s".', $attributeClass), $reflection, $e);
        }

        if ($reflection instanceof \ReflectionFunction) {
            return [$attribute, $reflection];
        }

        try {
            $instance = $reflection->newInstance();
        } catch (\Throwable $e) {
            throw new FunctionConfigurationException(\sprintf('Could not instantiate the class "%s".', $reflection->name), $reflection, $e);
        }

        if (!\is_callable($instance)) {
            throw new FunctionConfigurationException(\sprintf('"%s" is not callable.', $reflection->name), $reflection, null);
        }

        return [$attribute, $instance];
    }

    /**
     * Snapshot of the user-defined functions and declared classes that may
     * carry a component attribute.
     *
     * @return list<\ReflectionFunction|\ReflectionClass<object>>
     */
    public static function candidates(): array
    {
        $candidates = [];

        foreach (get_defined_functions()['user'] as $function) {
            $candidates[] = new \ReflectionFunction($function);
        }

        foreach (get_declared_classes() as $class) {
            $candidates[] = new \ReflectionClass($class);
        }

        return $candidates;
    }
}
