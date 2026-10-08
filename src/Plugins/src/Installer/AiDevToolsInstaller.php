<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;

use function Castor\io;

final readonly class AiDevToolsInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'ai_dev_tools';
    }

    public function description(): string
    {
        return 'Dev-only AI tooling for Sylius';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding AI dev tools plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require --dev sylius/sylius-ai-dev-tools');

        Docker::run($app, 'vendor/bin/mate init');
        Docker::run($app, 'vendor/bin/mate discover');
    }
}
