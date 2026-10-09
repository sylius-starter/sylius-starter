<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Util;

use SyliusStarter\Core\App;

use function Castor\fs;

final class Fixtures
{
    /** @var list<\Closure(App, list<string>): void> */
    private static array $beforeLoadCallbacks = [];

    /**
     * Packages may register hooks run before a fixture suite is loaded (e.g. ensure scaffold).
     *
     * @param callable(App, list<string>): void $callback
     */
    public static function beforeLoad(callable $callback): void
    {
        self::$beforeLoadCallbacks[] = $callback(...);
    }

    /**
     * Locales that must be loaded (fixture suite) before channel fixtures run.
     * PHP fixture files cannot use the Symfony {@code %locale%} parameter placeholder.
     *
     * @return list<string>
     */
    public static function defaultChannelLocaleCodes(): array
    {
        return ['en_US', 'fr_FR'];
    }

    public static function load(App $app, ?string ...$args): void
    {
        $suites = array_values(array_filter($args, static fn(?string $arg): bool => null !== $arg && '' !== $arg));

        foreach (self::$beforeLoadCallbacks as $callback) {
            $callback($app, $suites);
        }

        Docker::run($app, \sprintf('php bin/console sylius:fixtures:load %s -n', implode(' ', $suites)));
    }

    public static function createSuite(App $app, ?string $name = null): void
    {
        $name ??= 'default';

        Yaml::import($app, 'config/packages/_sylius.yaml', \sprintf('../sylius/fixtures/%s.php', $name));

        $file = \sprintf('%s/config/sylius/fixtures/%s.php', $app->directory(), $name);

        if ('default' === $name) {
            $content = <<<PHP
                <?php

                declare(strict_types=1);

                namespace Symfony\\Component\\DependencyInjection\\Loader\\Configurator;

                return App::config([
                    'imports' => [
                        ['resource' => '{$name}/channels.php'],
                    ],
                    'sylius_fixtures' => [
                        'suites' => [
                            '{$name}' => [
                                'listeners' => [
                                    'orm_purger' => null,
                                    'images_purger' => null,
                                    'logger' => null,
                                ],
                            ],
                        ],
                    ],
                ]);
                PHP;
        } else {
            $content = <<<PHP
                <?php

                declare(strict_types=1);

                namespace Symfony\\Component\\DependencyInjection\\Loader\\Configurator;

                return App::config([
                    'imports' => [
                        ['resource' => '{$name}/currencies.php'],
                        ['resource' => '{$name}/locales.php'],
                        ['resource' => '{$name}/channels.php'],
                        ['resource' => '{$name}/admin_users.php'],
                        ['resource' => '{$name}/shop_users.php'],
                    ],
                    'sylius_fixtures' => [
                        'suites' => [
                            '{$name}' => [
                                'listeners' => [
                                    'orm_purger' => null,
                                    'images_purger' => null,
                                    'logger' => null,
                                ],
                            ],
                        ],
                    ],
                ]);
                PHP;
        }

        fs()->dumpFile($file, $content);
    }

    public static function createDefaultChannel(App $app, ?string $suite = null, ?string $currency = null): void
    {
        $suite ??= 'default';
        $currency ??= 'EUR';
        $hostname = $app->hostname();
        $file = \sprintf('%s/config/sylius/fixtures/%s/channels.php', $app->directory(), $suite);
        $locales = var_export(self::defaultChannelLocaleCodes(), true);

        fs()->dumpFile(
            $file,
            <<<PHP
                <?php

                declare(strict_types=1);

                namespace Symfony\\Component\\DependencyInjection\\Loader\\Configurator;

                return App::config([
                    'sylius_fixtures' => [
                        'suites' => [
                            '{$suite}' => [
                                'fixtures' => [
                                    'channel' => [
                                        'options' => [
                                            'custom' => [
                                                'web_store' => [
                                                    'name' => 'Web store',
                                                    'code' => 'WEB_STORE',
                                                    'locales' => {$locales},
                                                    'currencies' => ['{$currency}'],
                                                    'hostname' => '{$hostname}',
                                                    'enabled' => true,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

                PHP,
        );
    }
}
