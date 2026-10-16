<?php

declare(strict_types=1);

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsContext;
use Castor\Attribute\AsListener;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Context;
use Castor\Docker\Event\RegisterServiceEvent;
use Castor\Docker\Service\PhpMode;
use Castor\Docker\Service\PostgresService;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Plugins\Attribute\AsPluginInstaller;
use SyliusStarter\Plugins\Attribute\AsPluginRemover;
use SyliusStarter\Themes\Themes;
use Symfony\Component\Console\Question\ChoiceQuestion;

use function Castor\io;
use function Castor\context;
use function Castor\import;
use function Castor\PHPQa\phpstan;
use function Castor\PHPQa\php_cs_fixer;
use function Castor\run;
use function worktree\domain;
use function worktree\project_name;
use function worktree\storefront_url;

import(__DIR__ . '/.castor/app');
import(__DIR__ . '/.castor/monorepo');
import(__DIR__ . '/.castor/worktree');

#[AsContext(default: true)]
function default_context(): Context
{
    // A git worktree gets its own compose project, hence its own containers and volumes (see .castor/worktree).
    $projectName = project_name();

    return new Context(null === $projectName ? [] : ['project_name' => $projectName]);
}

#[AsTask(description: 'Fix CS', namespace: 'qa', name: 'cs', aliases: ['cs'])]
function qa_phpcsfixer(bool $dryRun = false): int
{
    $args = null;

    if ($dryRun) {
        $args = ['fix', '--dry-run'];
    }

    return php_cs_fixer(arguments: $args, version: '3.92.4')->getExitCode();
}

#[AsTask(description: 'Run PHPStan', namespace: 'qa', name: 'phpstan', aliases: ['phpstan'])]
function qa_phpstan(bool $generateBaseline = false): int
{
    $args = ['analyze', '--configuration', context()->workingDirectory . '/phpstan.dist.neon', '--memory-limit=1G'];

    if ($generateBaseline) {
        $args[] = '-b';
    }

    return phpstan(arguments: $args, version: '2.1.32')->getExitCode();
}

/**
 * @param list<string> $themes
 */
#[AsTask(description: 'Refresh the theme screenshots in docs/images', namespace: 'docs', name: 'screenshots')]
function docs_screenshots(
    #[AsArgument(description: 'Themes to capture (all of them when omitted and not interactive)')]
    array $themes = [],
    #[AsOption(description: 'Do not capture the cart page')]
    bool $skipCart = false,
    #[AsOption(description: 'Theme to activate once the screenshots are taken')]
    ?string $restore = null,
    #[AsOption(description: 'Comma-separated screen sizes: normal, wide, mobile (default: all of them)')]
    ?string $viewports = null,
    #[AsOption(description: 'Capture a single desktop viewport of this width instead of the screen sizes')]
    ?int $width = null,
    #[AsOption(description: 'Height of that viewport (default: 900)')]
    ?int $height = null,
): int {
    $available = array_keys(Themes::installers());
    sort($available);

    if ([] === $themes) {
        // The default of a multiselect question over a list is an index, not a value: "0" is "all".
        // With 'all', the prompt hits an undefined array key on every attempt and asks again forever.
        $question = new ChoiceQuestion('Which themes do you want to capture? (comma-separated)', ['all', ...$available], '0');
        $question->setMultiselect(true);
        $themes = io()->askQuestion($question);
    }

    $themes = in_array('all', $themes, true) ? $available : array_values(array_unique($themes));

    if ([] !== $unknown = array_diff($themes, $available)) {
        io()->error(sprintf('Unknown theme(s): %s. Available: %s.', implode(', ', $unknown), implode(', ', $available)));

        return 1;
    }

    if (null !== $restore && 'default' !== $restore && !in_array($restore, $available, true)) {
        io()->error(sprintf('Unknown theme "%s" to restore. Available: default, %s.', $restore, implode(', ', $available)));

        return 1;
    }

    $context = context()->withWorkingDirectory(__DIR__);

    foreach ($themes as $theme) {
        io()->section(sprintf('Theme "%s"', $theme));

        // Each theme must be active on the storefront before its screenshots are taken.
        run(['castor', 'sylius:theme:setup', $theme], context: $context);

        $command = ['node', 'scripts/capture-theme-screenshots.mjs', $theme, '--base-url', storefront_url()];

        if (!$skipCart) {
            $command[] = '--include-cart';
        }

        if (null !== $viewports) {
            array_push($command, '--viewports', $viewports);
        }

        if (null !== $width) {
            array_push($command, '--width', (string) $width);
        }

        if (null !== $height) {
            array_push($command, '--height', (string) $height);
        }

        run($command, context: $context);
    }

    if (null !== $restore) {
        run(['castor', 'sylius:theme:setup', $restore], context: $context);
    } else {
        io()->note(sprintf('The "%s" theme is still active. Run "castor sylius:theme:setup <theme>" to switch back.', end($themes)));
    }

    io()->success(sprintf('Screenshots refreshed in docs/images for: %s.', implode(', ', $themes)));

    return 0;
}

#[AsPluginInstaller(name: 'test_installer_with_function')]
function test_installer(): void
{
    io()->success('New installer using a custom function is ok');
}

#[AsPluginRemover(name: 'test_remover_with_function')]
function test_remover(): void
{
    io()->success('New remover using a custom function is ok');
}

#[AsListener(RegisterServiceEvent::class)]
function register_service(RegisterServiceEvent $event): void
{
    $postgres = (new PostgresService())->withVersion('16');
    $event->addService($postgres);
    $event->addService((new SyliusService('app'))->withDirectory(__DIR__ . '/app')->withVersion('8.5')->withMode(PhpMode::FrankenPhp)->withPhpIni(['memory_limit' => '1G', 'opcache.memory_consumption' => '1024', 'opcache.interned_strings_buffer' => '64', 'opcache.max_accelerated_files' => '100000'])->withHttpAccess()->withDomain(domain())->withDatabaseService($postgres));
}
