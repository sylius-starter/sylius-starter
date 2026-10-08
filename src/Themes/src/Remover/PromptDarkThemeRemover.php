<?php

declare(strict_types=1);

namespace SyliusStarter\Themes\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Javascript;

use function Castor\finder;
use function Castor\fs;

final class PromptDarkThemeRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'prompt_dark';
    }

    public function description(): ?string
    {
        return null;
    }

    public function __invoke(App $app): void
    {
        $resourcesDir = \dirname(__DIR__, 2) . '/resources/prompt_dark';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }
        Javascript::removeImport($app, 'assets/shop/entrypoint.js', './styles/app.scss');
    }
}
