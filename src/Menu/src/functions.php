<?php

declare(strict_types=1);

namespace SyliusStarter\Menu;

use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\Menu\Tasks\MenuTasks;

TaskProviderRegistry::register(
    'menu',
    static fn(SyliusService $service): iterable => (new MenuTasks($service->getName(), $service->getDirectory()))(),
);
