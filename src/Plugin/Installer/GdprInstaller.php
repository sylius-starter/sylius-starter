<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class GdprInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'gdpr';
    }

    public function description(): string
    {
        return 'Synolia sylius GDPR plugin';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding GDPR plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require synolia/sylius-gdpr-plugin --no-scripts');

        fs()->dumpFile(
            $app->directory() . '/config/packages/gdpr.yaml',
            <<<'YAML'
                imports:
                    - { resource: "@SynoliaSyliusGDPRPlugin/Resources/config/app/config.yaml" }

                YAML
        );
        fs()->dumpFile(
            $app->directory() . '/config/routes/gdpr.yaml',
            <<<'YAML'
                synolia_gdpr:
                    resource: "@SynoliaSyliusGDPRPlugin/Resources/config/routes.yaml"
                    prefix: '/%sylius_admin.path_name%'

                YAML
        );

        Docker::run($app, 'bin/console translation:extract en SynoliaSyliusGDPRPlugin --dump-messages');
        Docker::run($app, 'bin/console translation:extract fr SynoliaSyliusGDPRPlugin --dump-messages');
        Symfony::cacheClear($app);
    }
}
