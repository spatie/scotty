---
title: Installation & setup
weight: 4
---

Scotty ships as a single-file phar executable. There are two ways to install it.

## Per project (recommended)

Download the phar from the latest GitHub release and drop it into your project:

```bash
curl -L https://github.com/spatie/scotty/releases/latest/download/scotty -o scotty
chmod +x scotty
./scotty list
```

Commit the phar to your repository so everyone on the team uses the same version, or fetch it during your build step.

## Globally

You can install Scotty as a global Composer package:

```bash
composer global require spatie/scotty
```

Make sure Composer's global bin directory is in your `$PATH`. If you're not sure where it is:

```bash
composer global config bin-dir --absolute
```

Once installed, you should be able to run:

```bash
scotty list
```

> Installing Scotty as a per-project Composer dev dependency (`composer require --dev spatie/scotty`) is not supported. Scotty is a Laravel Zero application and its `illuminate/*` requirements will conflict with the host application's. Use the phar download or the global install instead.

## Creating your first Scotty file

The quickest way to get started is to run `scotty init` in your project root:

```bash
scotty init
```

You'll be asked to choose a format (bash or Blade) and enter your server's SSH connection string. Scotty creates the file for you.

You can also create a `Scotty.sh` file by hand. Check the [Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format) page for details on the syntax.
