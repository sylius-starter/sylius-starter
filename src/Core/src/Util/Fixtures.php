<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;

use function Castor\fs;

final readonly class Fixtures
{
    public static function load(App $app, ?string ...$args): void
    {
        Docker::run($app, \sprintf('php bin/console sylius:fixtures:load %s -n', implode(' ', $args)));
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
                                                    'locales' => ['%locale%'],
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
