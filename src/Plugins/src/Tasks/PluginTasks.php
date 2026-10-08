<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Tasks;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\ComponentChoices;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Component\RemoverInterface;

use function Castor\io;

final class PluginTasks
{
    private static array $installers = [];

    private static array $removers = [];

    public function __construct(
        private readonly string $name,
        private readonly string $directory,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);

        yield [
            'task' => new AsTask('add', 'sylius:plugin', 'Adds plugins', ['sylius:add']),
            'function' => static function (#[AsRawTokens] array $plugins = []) use ($app): void {
                $installers = array_map(
                    static fn(callable $installer): callable => static fn() => $installer($app),
                    self::$installers,
                );

                if ([] === $plugins) {
                    $plugins = io()->choice(
                        'Which plugins would you like to install?',
                        self::choices(self::$installers),
                        multiSelect: true,
                    );
                }

                foreach ($plugins as $plugin) {
                    if (!isset($installers[$plugin])) {
                        io()->warning(\sprintf('Unknown plugin "%s", skipping.', $plugin));

                        continue;
                    }
                    $installers[$plugin]();
                }
            },
        ];

        yield [
            'task' => new AsTask('remove', 'sylius:plugin', 'Removes plugins', ['sylius:remove']),
            'function' => static function (#[AsRawTokens] array $plugins = []) use ($app): void {
                $removers = array_map(
                    static fn(callable $remover): callable => static fn() => $remover($app),
                    self::$removers,
                );

                if ([] === $plugins) {
                    $plugins = io()->choice(
                        'Which plugins would you like to remove?',
                        self::choices(self::$removers),
                        multiSelect: true,
                    );
                }

                foreach ($plugins as $plugin) {
                    if (!isset($removers[$plugin])) {
                        io()->warning(\sprintf('Unknown plugin "%s", skipping.', $plugin));

                        continue;
                    }
                    $removers[$plugin]();
                }
            },
        ];
    }

    /**
     * @param array<string, InstallerInterface|RemoverInterface> $components
     *
     * @return array<string, string>
     */
    public static function choices(array $components): array
    {
        return ComponentChoices::build($components);
    }

    public static function addInstaller(InstallerInterface $installer): void
    {
        self::$installers[$installer->name()] = $installer;
    }

    public static function addRemover(RemoverInterface $remover): void
    {
        self::$removers[$remover->name()] = $remover;
    }
}
