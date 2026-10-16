---
title: Deploy to Upsun
description: Set up the Upsun configuration of Sylius Standard, validate it against your Castor and Doctrine setup, then deploy it.
sidebar:
  order: 5
---

Provided by `sylius-starter/upsun`.

## Set up a project where the application is in a subdirectory

Upsun reads `.upsun/config.yaml` at the root of the Git repository. Sylius Standard ships it in the application
directory, which is the repository root when the application has its own repository. In a project created from the
skeleton, the application lives in a subdirectory (`app/`) of the repository, so the file must move:

```bash
castor sylius:upsun:setup
```

The task moves `app/.upsun/config.yaml` to `.upsun/config.yaml` at the root of the repository and sets
`source.root: "app"` on the Sylius application, so that Upsun builds it from its directory. The paths of the
configuration (mounts, `web.locations`, hooks) are relative to `source.root` and stay unchanged. Only that line is
edited: the comments and the layout of Sylius Standard's file are kept. Commit the moved file afterwards.

It does nothing when the application is the root of its repository, nor when `.upsun/config.yaml` is already set up at
the root, and it never overwrites another configuration found there. When Sylius Standard updates its
`.upsun/config.yaml`, report the changes to the one at the root of the repository by hand.

## Check the configuration

Sylius Standard already ships an official `.upsun/config.yaml`. This read-only check validates that the file is present
at the root of the Git repository and defines Upsun applications, one of them serving the Sylius application (the one
whose `source.root` points at it when it is in a subdirectory). It then compares the locally linked Castor database, the
application's Doctrine DBAL driver, and the declared Upsun database service. It checks explicit Doctrine drivers first and supports the standard
`DATABASE_URL` configuration as a fallback. If an engine cannot be determined, it reports that instead of guessing.

```bash
castor sylius:upsun:check
```

The check uses the configured Symfony environment, does not generate or modify Upsun or Doctrine configuration, and does
not run Upsun or Symfony CLI commands.

It exits with a non-zero code when `.upsun/config.yaml` is missing, invalid or still in the application subdirectory,
when no Upsun application serves the Sylius application, when the database relationship is broken, or when the
application and Upsun use different database engines. A local Castor database that differs from the
application, or an engine that cannot be determined, is only a warning.

## Deploy

```bash
castor sylius:upsun:deploy
castor sylius:upsun:deploy --environment=staging --activate
castor sylius:upsun:deploy --project=<project-id>
```

The task runs the check first and stops if it fails. It then deploys with the [Symfony CLI](https://symfony.com/download)
(`symfony cloud:deploy`), or with the [Upsun CLI](https://docs.upsun.com/administration/cli.html) (`upsun push`) when
the Symfony CLI is not installed. It pushes the whole Git repository, from its root. Before pushing, it makes sure that:

- you are logged in (`symfony cloud:login`);
- an Upsun project is linked to the repository (`symfony cloud:set-remote <project-id>`, or `symfony cloud:create` for
  a new one), unless you pass `--project`.

Upsun deploys Git commits: the task warns about uncommitted changes, which are not deployed.

| Option          | Description                                                        |
|-----------------|--------------------------------------------------------------------|
| `--environment` | Upsun environment to deploy (defaults to the current Git branch)   |
| `--project`     | Upsun project ID (defaults to the project linked to the repository) |
| `--activate`    | Activate the environment if it is not active yet                   |

The CLI runs on the host, where your Upsun login lives, not in the Docker containers.
