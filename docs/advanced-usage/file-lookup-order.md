---
title: File lookup order
weight: 3
---

When you run a command, Scotty checks for files in this order:

1. `Scotty.sh`
2. `scotty.sh`
3. `Scotty.blade.php`
4. `scotty.blade.php`
5. `Envoy.sh`
6. `envoy.sh`
7. `Envoy.blade.php`
8. `envoy.blade.php`

The first file found is used. This means you can drop Scotty into a project that uses `Envoy.blade.php` and it will pick it up automatically.

## Specifying a file directly

You can bypass the lookup with the `--path` or `--conf` options:

```bash
scotty run deploy --path=deploy/production.sh
scotty run deploy --conf=Envoy.blade.php
```

## Parser selection

Scotty determines the parser based on the file extension:

- Files ending in `.sh` (case insensitive) are parsed as bash
- Everything else is parsed as Blade
