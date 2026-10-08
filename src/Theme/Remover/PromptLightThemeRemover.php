<?php

declare(strict_types=1);

namespace Castor\Sylius\Theme\Remover;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;
use Castor\Sylius\Util\Javascript;

use function Castor\finder;
use function Castor\fs;

final class PromptLightThemeRemover implements PluginRemoverInterface
{
    public function name(): string
    {
        return 'prompt_light';
    }

    public function description(): ?string
    {
        return null;
    }

    public function __invoke(App $app): void
    {
        $resourcesDir = \dirname(__DIR__, 3) . '/resources/theme/prompt_light';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }
        Javascript::removeImport($app, 'assets/shop/entrypoint.js', './styles/app.scss');
    }
}
