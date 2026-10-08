<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;
use Symfony\Component\Process\Process;

use function Castor\io;
use function Castor\run;

final readonly class Database
{
    public static function migrate(App $app): void
    {
        run(['castor', $app->name() . ':db:migrate']);
    }

    public static function diff(App $app, ?string $namespace = 'DoctrineMigrations'): Process
    {
        $command = 'bin/console doctrine:migrations:diff';

        if (null !== $namespace) {
            $command .= ' --namespace=' . $namespace;
        }

        return Docker::run($app, $command);
    }

    public static function rollbackPluginMigrations(App $app, string $namespace): void
    {
        $output = Docker::run(
            $app,
            'bin/console doctrine:migrations:list --no-interaction --no-ansi',
        )->getOutput();

        preg_match_all(
            \sprintf(
                '/^\|\s*(%s\\\[^\s|]+)\s+\|\s+migrated\s+\|/m',
                preg_quote($namespace, '/'),
            ),
            $output,
            $matches,
        );

        $migrations = array_reverse($matches[1]);

        if ([] === $migrations) {
            io()->warning(\sprintf(
                'No executed migrations found for "%s".',
                $namespace,
            ));

            return;
        }

        foreach ($migrations as $migration) {
            io()->info(\sprintf(
                'Executing down for %s',
                $migration,
            ));

            Docker::run($app, \sprintf(
                'bin/console doctrine:migrations:execute --down --no-interaction %s',
                escapeshellarg($migration),
            ));
        }

        io()->success('Plugin migrations rolled back.');
    }
}
