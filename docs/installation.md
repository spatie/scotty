---
title: Installation & setup
weight: 4
---

Install Scotty globally via Composer:

```bash
composer global require spatie/scotty
```

Make sure the Composer global bin directory is in your `$PATH`. You can find the path by running:

```bash
composer global config bin-dir --absolute
```

After installation, the `scotty` command should be available:

```bash
scotty list
```

## Creating your first Scotty file

Run `scotty init` to create a new file. You'll be prompted to choose a format (bash or Blade) and a server host:

```bash
scotty init
```

You can also create a `Scotty.sh` or `Envoy.blade.php` file manually. See the [Blade format](/docs/scotty/v1/basic-usage/blade-format) or [Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format) pages for details.
