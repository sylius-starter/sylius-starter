<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class ApiRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'api';
    }

    public function description(): string
    {
        return 'Sylius API and its test tooling';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Sylius API');

        Composer::allowContribRecipes($app);

        Composer::removeDevDependency($app, 'lchrusciel/api-test-case');

        fs()->remove([
            $app->directory() . '/config/packages/fidry_alice_data_fixtures.yaml',
        ]);

        fs()->dumpFile(
            $app->directory() . '/config/packages/sylius_api.yaml',
            "sylius_api:\n    enabled: false\n",
        );

        Symfony::removeJsController($app, '@sylius/shop-bundle', 'api-login', 'assets/shop/controllers.json');

        Symfony::cacheClear($app);
    }
}
