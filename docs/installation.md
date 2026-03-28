---
title: Installation
weight: 2
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

## Requirements

- PHP 8.4 or higher
- SSH access to your target servers (with key-based authentication)
