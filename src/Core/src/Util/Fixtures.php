<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Util;

use SyliusStarter\Core\App;

use function Castor\fs;
use function SyliusStarter\Import\ensure_import_scaffold;
use function SyliusStarter\Import\is_import_scaffold_deployed;

final readonly class Fixtures
{
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
        $suites = array_values(array_filter($args, static fn (?string $arg): bool => null !== $arg && '' !== $arg));

        if (\in_array('app', $suites, true)) {
            self::ensureAppFixtureSuite($app);
        }

        Docker::run($app, \sprintf('php bin/console sylius:fixtures:load %s -n', implode(' ', $suites)));
    }

    private static function ensureAppFixtureSuite(App $app): void
    {
        if (!\function_exists('SyliusStarter\Import\ensure_import_scaffold')) {
            return;
        }

        $wasDeployed = is_import_scaffold_deployed($app);
        ensure_import_scaffold($app, $app->name());

        if (!$wasDeployed) {
            Docker::run($app, 'php bin/console cache:clear --no-warmup -n');
        }
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
        $hostname = $app->domain();
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
