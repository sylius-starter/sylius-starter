<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Docker;

use function Castor\fs;
use function Castor\io;

final readonly class AiDevToolsRemover implements RemoverInterface
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
