<?php

declare(strict_types=1);

namespace SyliusStarter\B2b;

use SyliusStarter\B2b\Tasks\B2bTasks;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;

TaskProviderRegistry::register(
    'b2b',
    static fn(SyliusService $service): iterable => (new B2bTasks($service->getName(), $service->getDirectory()))(),
);
