<?php

declare(strict_types=1);

namespace SyliusStarter\Monorepo;

/**
 * Monorepo helper: every directory in src/ holding a composer.json is a
 * package that gets split into its own read-only repository.
 *
 * The root composer.json is generated from the packages (require, autoload,
 * replace...), so the package composer.json files are the single source of
 * truth. This class has no Castor dependency so it can be used from CI
 * scripts as well.
 */
final class Monorepo
{
    public const string VENDOR = 'sylius-starter';
    public const string NAMESPACE = 'SyliusStarter';
    public const string CORE_PACKAGE = 'sylius-starter/core';

    public function __construct(
        private readonly string $rootDir,
    ) {}

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    /**
     * Core first, then alphabetical order.
     *
     * @return list<array{name: string, path: string, directory: string, composer: array<string, mixed>}>
     */
    public function packages(): array
    {
        $packages = [];

        foreach (glob($this->rootDir . '/src/*/composer.json') ?: [] as $composerFile) {
            $composer = self::readJson($composerFile);
            $directory = basename(\dirname($composerFile));

            $packages[] = [
                'name' => (string) ($composer['name'] ?? ''),
                'path' => 'src/' . $directory,
                'directory' => $directory,
                'composer' => $composer,
            ];
        }

        usort($packages, static function (array $a, array $b): int {
            if (self::CORE_PACKAGE === $a['name']) {
                return -1;
            }

            if (self::CORE_PACKAGE === $b['name']) {
                return 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $packages;
    }

    /**
     * Packages description used by the CI matrix and "castor monorepo:packages".
     *
     * @return list<array{name: string, short: string, path: string}>
     */
    public function matrix(): array
    {
        return array_map(
            static fn(array $package): array => [
                'name' => $package['name'],
                'short' => self::shortName($package['name']),
                'path' => $package['path'],
            ],
            $this->packages(),
        );
    }

    /**
     * @return array{name: string, path: string, directory: string, composer: array<string, mixed>}
     */
    public function package(string $nameOrDirectory): array
    {
        foreach ($this->packages() as $package) {
            if (\in_array($nameOrDirectory, [$package['name'], $package['directory'], $package['path'], self::shortName($package['name'])], true)) {
                return $package;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Unknown package "%s".', $nameOrDirectory));
    }

    /**
     * @return array<string, mixed>
     */
    public function rootComposer(): array
    {
        return self::readJson($this->rootDir . '/composer.json');
    }

    /**
     * Builds the root composer.json from the packages, keeping every root key
     * that is not derived from them (name, require-dev, config, scripts...).
     *
     * @param array<string, mixed>|null $root
     *
     * @return array<string, mixed>
     */
    public function mergedRootComposer(?array $root = null): array
    {
        $root ??= $this->rootComposer();
        $packages = $this->packages();
        $internal = array_column($packages, 'name');

        $require = [];
        $suggest = [];
        $psr4 = [];
        $files = [];
        $devPsr4 = [];
        $replace = [];

        foreach ($packages as $package) {
            $composer = $package['composer'];
            $replace[$package['name']] = 'self.version';

            foreach ($composer['require'] ?? [] as $dependency => $constraint) {
                if (\in_array($dependency, $internal, true)) {
                    continue;
                }

                $require[$dependency] ??= $constraint;
            }

            foreach ($composer['suggest'] ?? [] as $dependency => $reason) {
                $suggest[$dependency] ??= $reason;
            }

            foreach ($composer['autoload']['psr-4'] ?? [] as $namespace => $path) {
                $psr4[$namespace] = self::prefixPath($package['path'], $path);
            }

            foreach ($composer['autoload']['files'] ?? [] as $file) {
                $files[] = self::prefixPath($package['path'], $file);
            }

            foreach ($composer['autoload-dev']['psr-4'] ?? [] as $namespace => $path) {
                $devPsr4[$namespace] = self::prefixPath($package['path'], $path);
            }
        }

        $require = self::sortDependencies($require);
        ksort($suggest);

        $root['require'] = $require;
        $root['replace'] = $replace;

        if ([] !== $suggest) {
            $root['suggest'] = $suggest;
        } else {
            unset($root['suggest']);
        }

        $root['autoload'] = ['psr-4' => $psr4, 'files' => $files];
        // Keep root-only dev namespaces (tests/, tooling), drop the ones generated from the packages.
        $rootDevPsr4 = array_filter(
            $root['autoload-dev']['psr-4'] ?? [],
            static fn(string $path): bool => !str_starts_with($path, 'src/'),
        );
        $root['autoload-dev'] = ['psr-4' => $devPsr4 + $rootDevPsr4];

        return self::orderKeys($root);
    }

    public function writeRootComposer(): bool
    {
        $current = $this->rootComposer();
        $merged = $this->mergedRootComposer($current);

        if ($current === $merged) {
            return false;
        }

        file_put_contents($this->rootDir . '/composer.json', self::encodeJson($merged));

        return true;
    }

    /**
     * @return list<string> human readable errors, empty when the monorepo is consistent
     */
    public function validate(): array
    {
        $errors = [];
        $packages = $this->packages();
        $names = array_column($packages, 'name');
        $namespaces = [];

        if ([] === $packages) {
            return ['No package found in src/*/composer.json.'];
        }

        if (!\in_array(self::CORE_PACKAGE, $names, true)) {
            $errors[] = \sprintf('The "%s" package is missing.', self::CORE_PACKAGE);
        }

        foreach ($packages as $package) {
            $composer = $package['composer'];
            $label = $package['path'];
            $expectedNamespace = self::NAMESPACE . '\\' . $package['directory'] . '\\';
            $namespaces[$expectedNamespace] = $package['name'];

            if (!str_starts_with($package['name'], self::VENDOR . '/')) {
                $errors[] = \sprintf('%s: package name "%s" must start with "%s/".', $label, $package['name'], self::VENDOR);
            }

            if (\count(array_keys($names, $package['name'], true)) > 1) {
                $errors[] = \sprintf('%s: package name "%s" is used by several packages.', $label, $package['name']);
            }

            foreach (['description', 'license'] as $key) {
                if ('' === (string) ($composer[$key] ?? '')) {
                    $errors[] = \sprintf('%s: "%s" is missing in composer.json.', $label, $key);
                }
            }

            if (($composer['autoload']['psr-4'] ?? []) !== [$expectedNamespace => 'src/']) {
                $errors[] = \sprintf('%s: autoload.psr-4 must be {"%s": "src/"}.', $label, addslashes($expectedNamespace));
            }

            foreach ($composer['autoload']['files'] ?? [] as $file) {
                if (!is_file($this->rootDir . '/' . $package['path'] . '/' . $file)) {
                    $errors[] = \sprintf('%s: autoloaded file "%s" does not exist.', $label, $file);
                }
            }

            foreach ($composer['require'] ?? [] as $dependency => $constraint) {
                if (\in_array($dependency, $names, true) && 'self.version' !== $constraint) {
                    $errors[] = \sprintf('%s: internal dependency "%s" must use "self.version" (got "%s").', $label, $dependency, $constraint);
                }
            }

            if (self::CORE_PACKAGE !== $package['name'] && !isset($composer['require'][self::CORE_PACKAGE])) {
                $errors[] = \sprintf('%s: must require "%s".', $label, self::CORE_PACKAGE);
            }

            if (!is_file($this->rootDir . '/' . $package['path'] . '/LICENSE')) {
                $errors[] = \sprintf('%s: LICENSE file is missing.', $label);
            }

            if (!is_file($this->rootDir . '/' . $package['path'] . '/README.md')) {
                $errors[] = \sprintf('%s: README.md file is missing.', $label);
            }
        }

        // Every package must declare the internal packages it uses.
        foreach ($packages as $package) {
            $used = $this->usedNamespaces($package['path'] . '/src');

            foreach ($used as $namespace) {
                $dependency = $namespaces[$namespace] ?? null;

                if (null === $dependency || $dependency === $package['name']) {
                    continue;
                }

                if (!isset($package['composer']['require'][$dependency])) {
                    $errors[] = \sprintf('%s: uses "%s" but does not require "%s".', $package['path'], rtrim($namespace, '\\'), $dependency);
                }
            }
        }

        if ($this->rootComposer() !== $this->mergedRootComposer()) {
            $errors[] = 'Root composer.json is not in sync with the packages, run "castor monorepo:merge".';
        }

        return $errors;
    }

    /**
     * Splitsh-lite / git remote URL of a package split repository.
     * "{name}" is replaced by the package name (sylius-starter/core), "{short}" by its short name (core).
     */
    public static function remoteUrl(string $pattern, string $packageName): string
    {
        return strtr($pattern, [
            '{name}' => $packageName,
            '{short}' => self::shortName($packageName),
        ]);
    }

    public static function shortName(string $packageName): string
    {
        return substr($packageName, (int) strrpos($packageName, '/') + 1);
    }

    /**
     * @return array<string, mixed>
     */
    public static function readJson(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('"%s" does not contain a JSON object.', $file));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encodeJson(array $data): string
    {
        return json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @return list<string>
     */
    private function usedNamespaces(string $relativeDir): array
    {
        $dir = $this->rootDir . '/' . $relativeDir;

        if (!is_dir($dir)) {
            return [];
        }

        $namespaces = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }

            preg_match_all('/' . self::NAMESPACE . '\\\\([A-Za-z0-9]+)\\\\/', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $namespace) {
                $namespaces[self::NAMESPACE . '\\' . $namespace . '\\'] = true;
            }
        }

        return array_keys($namespaces);
    }

    private static function prefixPath(string $packagePath, string $path): string
    {
        return $packagePath . '/' . ltrim($path, './');
    }

    /**
     * php and extensions first, then alphabetical order (Composer's sort-packages behaviour).
     *
     * @param array<string, string> $dependencies
     *
     * @return array<string, string>
     */
    private static function sortDependencies(array $dependencies): array
    {
        uksort($dependencies, static function (string $a, string $b): int {
            $weight = static fn(string $name): int => match (true) {
                'php' === $name => 0,
                str_starts_with($name, 'ext-') => 1,
                default => 2,
            };

            return [$weight($a), $a] <=> [$weight($b), $b];
        });

        return $dependencies;
    }

    /**
     * @param array<string, mixed> $composer
     *
     * @return array<string, mixed>
     */
    private static function orderKeys(array $composer): array
    {
        $order = ['name', 'description', 'type', 'license', 'keywords', 'homepage', 'authors', 'require', 'require-dev', 'replace', 'conflict', 'suggest', 'autoload', 'autoload-dev', 'extra', 'config', 'scripts', 'minimum-stability', 'prefer-stable'];
        $ordered = [];

        foreach ($order as $key) {
            if (\array_key_exists($key, $composer)) {
                $ordered[$key] = $composer[$key];
            }
        }

        return $ordered + $composer;
    }
}
