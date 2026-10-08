<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Remover;

use Castor\Sylius\App;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;

use function Castor\fs;
use function Castor\io;

final readonly class AiDevToolsRemover implements PluginRemoverInterface
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
        io()->title('Removing AI dev tools plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove --dev sylius/sylius-ai-dev-tools');

        fs()->remove([
            $app->directory() . '/mate',
            $app->directory() . '/.agents',
            $app->directory() . '/.claude',
            $app->directory() . '/AGENTS.md',
            $app->directory() . '/CLAUDE.md',
        ]);
    }
}
