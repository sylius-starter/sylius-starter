<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Remover;

use Castor\Sylius\App;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class ApiRemover implements PluginRemoverInterface
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
