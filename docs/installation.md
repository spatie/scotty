---
title: Installation & setup
weight: 4
---

Scotty ships as a single-file phar executable. The phar download is the preferred installation method. Install it once globally, or commit it per project.

The phar bundles its own PHP requirements check. If you run it on a PHP version older than 8.4 (or with a missing required extension), Scotty fails fast with a clear error before any task runs.

## Per project

Download the phar from the latest GitHub release and drop it into your project:

```bash
curl -L https://github.com/spatie/scotty/releases/latest/download/scotty -o scotty
chmod +x scotty
./scotty list
```

Commit the phar to your repository so everyone on the team uses the same version, or fetch it during your build step.

## Globally

The simplest way to install Scotty globally is to drop the phar somewhere on your `$PATH`:

```bash
curl -L https://github.com/spatie/scotty/releases/latest/download/scotty -o /usr/local/bin/scotty
chmod +x /usr/local/bin/scotty
scotty list
```

Any directory on your `$PATH` works. Common alternatives are `~/.local/bin` (no `sudo` needed) or `~/bin`.

### Via Composer

You can also install Scotty as a global Composer package:

```bash
composer global require spatie/scotty
```

Make sure Composer's global bin directory is in your `$PATH`. If you're not sure where it is:

```bash
composer global config bin-dir --absolute
```

> Installing Scotty as a per-project Composer dev dependency (`composer require --dev spatie/scotty`) is not supported. Scotty is a Laravel Zero application and its `illuminate/*` requirements will conflict with the host application's. Use the phar download or the global install instead.

## Updating

Phar installs of Scotty offer to update themselves automatically. After a successful `scotty run`, Scotty checks GitHub for a newer release (once a day, cached locally). If a newer version is available, Scotty asks at the end of the run whether you want to update. The deploy always finishes first, so the prompt never blocks your release.

To update on demand, run:

```bash
scotty self-update
```

This downloads the latest phar and replaces the running binary in place. Pass `--force` to re-download even if you're already on the latest version.

To skip the post-run update check, pass `--no-update-check` to `scotty run` or set `SCOTTY_NO_UPDATE_CHECK=1` (recognized values: `1`, `true`, `yes`, `on`). The check is also skipped automatically when Scotty is running non-interactively (for example in CI).

Composer global installs upgrade with `composer global update spatie/scotty`.

## Creating your first Scotty file

The quickest way to get started is to run `scotty init` in your project root:

```bash
scotty init
```

You'll be asked to choose a format (bash or Blade) and enter your server's SSH connection string. Scotty creates the file for you.

You can also create a `Scotty.sh` file by hand. Check the [Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format) page for details on the syntax.
