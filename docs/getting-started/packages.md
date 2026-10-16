---
title: Packages
description: The sylius-starter/* packages, their namespace and the tasks they provide.
sidebar:
  order: 2
---

| Package                                                                              | Namespace               | Tasks                                                                 |
|--------------------------------------------------------------------------------------|-------------------------|-----------------------------------------------------------------------|
| [`sylius-starter/core`](https://github.com/sylius-starter/sylius-starter-core)       | `SyliusStarter\Core`    | Castor plugin: `SyliusService`, installer, `sylius:fixtures`, helpers |
| [`sylius-starter/plugins`](https://github.com/sylius-starter/sylius-starter-plugins) | `SyliusStarter\Plugins` | [`sylius:plugin:add`, `sylius:plugin:remove`](../commands/plugins.md) |
| [`sylius-starter/themes`](https://github.com/sylius-starter/sylius-starter-themes)   | `SyliusStarter\Themes`  | [`sylius:theme:setup`](../commands/themes.md)                         |
| [`sylius-starter/menu`](https://github.com/sylius-starter/sylius-starter-menu)       | `SyliusStarter\Menu`    | [`sylius:menu:remove`](../commands/admin-menu.md)                     |
| [`sylius-starter/b2b`](https://github.com/sylius-starter/sylius-starter-b2b)         | `SyliusStarter\B2b`     | [`sylius:b2b:enable`](../commands/b2b.md)                             |
| [`sylius-starter/import`](https://github.com/sylius-starter/sylius-starter-import)   | `SyliusStarter\Import`  | [`sylius:import:*`](../import/index.md)                               |
| [`sylius-starter/upsun`](https://github.com/sylius-starter/sylius-starter-upsun)     | `SyliusStarter\Upsun`   | [`sylius:upsun:*`](../commands/upsun.md)                              |
| `sylius-starter/sylius-starter`                                                      | —                       | All of the above                                                      |

The core is the Castor plugin itself. Every other package only contributes tasks: it registers them in the core
from a file autoloaded by Composer, so installing a package is enough to get its tasks on your `SyliusService`.
