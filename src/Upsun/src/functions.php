<?php

declare(strict_types=1);

namespace SyliusStarter\Upsun;

use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\Upsun\Tasks\UpsunTasks;

TaskProviderRegistry::register(
    'upsun',
    static fn(SyliusService $service): iterable => (new UpsunTasks($service->getName(), $service->getDirectory(), $service->databaseEngine()))(),
);
