<?php

declare(strict_types=1);

/*
 * Prints the monorepo packages as a JSON list, for GitHub Actions matrices:
 *   [{"name":"sylius-starter/core","short":"core","path":"src/Core"}, ...]
 *
 * Standalone on purpose (no Castor, no Composer install needed).
 */

require_once dirname(__DIR__, 2) . '/.castor/monorepo/Monorepo.php';

echo json_encode((new SyliusStarter\Monorepo\Monorepo(dirname(__DIR__, 2)))->matrix(), \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), "\n";
