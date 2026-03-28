---
title: Installation & setup
weight: 4
---

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

## Creating your first Scotty file

The quickest way to get started is to run `scotty init` in your project root:

```bash
scotty init
```

You'll be asked to choose a format (bash or Blade) and enter your server's SSH connection string. Scotty creates the file for you.

You can also create a `Scotty.sh` file by hand. Check the [Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format) page for details on the syntax.
