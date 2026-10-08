<?php

declare(strict_types=1);

namespace Castor\Sylius\Tasks;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Castor\Sylius\App;
use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;
use Castor\Sylius\Theme\Themes;
use Castor\Sylius\Util\Assets;

use function Castor\io;

final class ThemeTasks
{
    public function __construct(
        private readonly string $name,
        private readonly string $directory,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);

        yield [
            'task' => new AsTask('setup', 'sylius:theme', 'Setup themes', ['setup-theme']),
            'function' => static function (#[AsArgument] ?string $theme = null) use ($app): void {
                $themeInstallers = Themes::installers();
                $availableThemes = array_keys($themeInstallers);
                $availableThemes[] = 'default';
                sort($availableThemes);

                $installers = array_map(
                    static fn(callable $installer): callable => static fn() => $installer($app),
                    $themeInstallers,
                );

                $installers['default'] = static function () use ($app): void {
                    Assets::build($app);
                };

                $choices = [];
                foreach ($themeInstallers as $name => $installer) {
                    $description = $installer instanceof PluginInstallerInterface ? $installer->description() : null;
                    $choices[$name] = null === $description || '' === $description
                        ? $name
                        : \sprintf('%s - %s', $name, $description);
                }
                $choices['default'] = 'default';
                ksort($choices);

                $removers = array_map(
                    static fn(callable $remover): callable => static fn() => $remover($app),
                    Themes::removers(),
                );

                $removers['default'] = static function (): void {};

                if (null === $theme) {
                    $theme = io()->choice(
                        'Which theme would you like to use?',
                        $choices,
                    );
                }

                if (!isset($installers[$theme])) {
                    io()->error(\sprintf('Unknown theme installer "%s", skipping.', $theme));

                    return;
                }

                $themesToRemove = array_diff($availableThemes, $theme ? [$theme] : []);

                foreach ($themesToRemove as $themeToRemove) {
                    if (!isset($removers[$themeToRemove])) {
                        io()->warning(\sprintf('Unknown theme remover "%s", skipping.', $themeToRemove));

                        continue;
                    }
                    $removers[$themeToRemove]();
                }

                $installers[$theme]();

                io()->success(\sprintf('"%s" theme has been installed successfully.', $theme));
            },
        ];
    }
}
