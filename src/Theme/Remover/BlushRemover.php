<?php

declare(strict_types=1);

namespace Castor\Sylius\Theme\Remover;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;
use Castor\Sylius\Util\Javascript;

use function Castor\finder;
use function Castor\fs;

final class BlushRemover implements PluginRemoverInterface
{
    public function name(): string
    {
        return 'blush';
    }

    public function description(): ?string
    {
        return null;
    }

    public function __invoke(App $app): void
    {
        $resourcesDir = \dirname(__DIR__, 3) . '/resources/theme/blush';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        Javascript::removeImport($app, 'assets/shop/entrypoint.js', './styles/app.scss');
    }
}
