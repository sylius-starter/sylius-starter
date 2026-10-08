<?php

declare(strict_types=1);

namespace monorepo;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use SyliusStarter\Monorepo\Monorepo;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\run;

function monorepo(): Monorepo
{
    return new Monorepo(context()->workingDirectory);
}

#[AsTask(description: 'List the packages of the monorepo (use --json for a CI matrix)')]
function packages(
    #[AsOption(description: 'Output the packages as JSON')]
    bool $json = false,
): void {
    $packages = monorepo()->matrix();

    if ($json) {
        io()->writeln(json_encode($packages, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));

        return;
    }

    io()->table(['Package', 'Short name', 'Path'], $packages);
}

#[AsTask(description: 'Generate the root composer.json (require, replace, autoload) from the packages')]
function merge(): void
{
    if (monorepo()->writeRootComposer()) {
        io()->success('Root composer.json has been updated. Run "composer update" to refresh the lock file / autoloader.');

        return;
    }

    io()->success('Root composer.json is already up to date.');
}

#[AsTask(description: 'Check that every package is valid and that the root composer.json is in sync')]
function validate(): int
{
    $errors = monorepo()->validate();

    if ([] === $errors) {
        io()->success(\sprintf('The monorepo is valid (%d packages).', \count(monorepo()->packages())));

        return 0;
    }

    io()->error('The monorepo is not valid.');
    io()->listing($errors);

    return 1;
}

/**
 * Splits each package (src/<Package>) into its own repository with splitsh-lite
 * (https://github.com/splitsh/lite), then pushes it when --push is given.
 *
 * Examples:
 *   castor monorepo:split                         # dry-run: compute the split SHA of every package
 *   castor monorepo:split core --push             # push main of sylius-starter/core
 *   castor monorepo:split --ref=refs/tags/v0.1.0 --push
 *
 * @param list<string> $packages
 */
#[AsTask(description: 'Split packages into their read-only repositories with splitsh-lite')]
function split(
    #[AsArgument(description: 'Packages to split (name, short name or directory), all by default')]
    array $packages = [],
    #[AsOption(description: 'Git reference to split (refs/heads/<branch> or refs/tags/<tag>), defaults to the current branch')]
    ?string $ref = null,
    #[AsOption(description: 'Push the split to the package repositories')]
    bool $push = false,
    #[AsOption(description: 'Force push (only needed when the history of a package has been rewritten)')]
    bool $force = false,
    #[AsOption(description: 'Remote URL pattern, {name} and {short} are replaced (env: SPLIT_REMOTE_PATTERN)')]
    ?string $remotePattern = null,
    #[AsOption(description: 'Path to the splitsh-lite binary (env: SPLITSH_BIN)')]
    ?string $splitshBin = null,
): int {
    $monorepo = monorepo();
    $remotePattern ??= getenv('SPLIT_REMOTE_PATTERN') ?: 'git@github.com:{name}.git';
    $splitshBin ??= getenv('SPLITSH_BIN') ?: 'splitsh-lite';
    $ref ??= 'refs/heads/' . trim(capture(['git', 'rev-parse', '--abbrev-ref', 'HEAD']));

    if (!preg_match('#^refs/(heads|tags)/.+$#', $ref)) {
        io()->error(\sprintf('Invalid reference "%s", expected refs/heads/<branch> or refs/tags/<tag>.', $ref));

        return 1;
    }

    $selected = [] === $packages
        ? $monorepo->packages()
        : array_map(static fn(string $package): array => $monorepo->package($package), $packages);

    io()->title(\sprintf('Splitting %d package(s) from %s', \count($selected), $ref));

    foreach ($selected as $package) {
        io()->section($package['name']);

        $sha = trim(capture([$splitshBin, '--prefix=' . $package['path'], '--origin=' . $ref]));

        if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
            io()->error(\sprintf('splitsh-lite did not return a commit SHA for "%s": %s', $package['path'], $sha));

            return 1;
        }

        io()->writeln(\sprintf('Split commit: <info>%s</info>', $sha));

        if (!$push) {
            continue;
        }

        $remote = Monorepo::remoteUrl($remotePattern, $package['name']);
        $command = ['git', 'push', $remote, \sprintf('%s:%s', $sha, $ref)];

        if ($force) {
            array_splice($command, 2, 0, ['--force']);
        }

        // Never print the remote: in CI it contains a token.
        io()->writeln(\sprintf('Pushing to <comment>%s</comment> (%s)', $package['name'], $ref));
        run($command, context: context()->withQuiet());
    }

    io()->success($push ? 'Packages have been split and pushed.' : 'Dry-run done, use --push to publish the splits.');

    return 0;
}
