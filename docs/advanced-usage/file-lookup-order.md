---
title: File lookup order
weight: 3
---

When you run a command, Scotty looks for a file in your current directory in this order:

1. `Scotty.sh`
2. `scotty.sh`
3. `Scotty.blade.php`
4. `scotty.blade.php`
5. `Envoy.sh`
6. `envoy.sh`
7. `Envoy.blade.php`
8. `envoy.blade.php`

It uses the first one it finds. This means you can drop Scotty into a project that uses `Envoy.blade.php` and it'll pick it up automatically, no config needed.

## Specifying a file directly

If your file lives somewhere else, or you want to be explicit about which one to use:

```bash
scotty run deploy --path=deploy/production.sh
scotty run deploy --conf=Envoy.blade.php
```

## How Scotty picks the parser

Scotty looks at the file extension to decide how to parse it:

- Files ending in `.sh` are parsed as bash (the [Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format))
- Everything else is parsed as Blade (the [Envoy format](/docs/scotty/v1/advanced-usage/envoy-compatibility))
