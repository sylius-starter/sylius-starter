<?php

declare(strict_types=1);

namespace SyliusStarter\Themes\Installer;

use Castor\Docker\Installer\Input;
use Castor\Docker\Installer\InputType;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Installer\SyliusInstallerExtensionInterface;
use SyliusStarter\Themes\Themes;

use function Castor\run;

/**
 * Asks which storefront theme to use when a Sylius service is added
 * with "castor docker:service:install sylius", then sets it up once Sylius is ready.
 */
final readonly class ThemesSyliusInstallerExtension implements SyliusInstallerExtensionInterface
{
    public const DEFAULT_THEME = 'default';

    public function getInputs(): array
    {
        $themes = array_keys(Themes::installers());
        $themes[] = self::DEFAULT_THEME;
        sort($themes);

        return [
            new Input('theme', 'Theme', InputType::Choice, self::DEFAULT_THEME, $themes),
        ];
    }

    public function afterScaffold(App $app, array $answers): void
    {
        $theme = (string) ($answers['theme'] ?? '');

        // The Sylius default storefront is already built by the installer.
        if ('' === $theme || self::DEFAULT_THEME === $theme) {
            return;
        }

        run('castor sylius:theme:setup --no-interaction ' . escapeshellarg($theme));
    }
}
