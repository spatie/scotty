---
title: Envoy compatibility
weight: 6
---

Scotty reads `Envoy.blade.php` files out of the box. If you're already using [Laravel Envoy](https://laravel.com/docs/envoy), you can switch to Scotty without changing your deploy file.

## Supported Envoy features

Scotty supports these Envoy directives:

- `@servers` for server definitions
- `@task` / `@endtask` for task definitions
- `@macro` / `@endmacro` (and `@story` / `@endstory`) for task sequences
- `@setup` / `@endsetup` for PHP setup blocks
- `@before` / `@after` / `@success` / `@error` / `@finished` lifecycle hooks
- `@if` / `@foreach` / `@for` and other control structures
- `{{ $variable }}` echo syntax
- `@import` for including other files
- `@set` for variable assignment

For the full Blade format reference, see the [Laravel Envoy documentation](https://laravel.com/docs/envoy).

## File lookup order

Scotty checks for files in this order:

1. `Scotty.sh`
2. `scotty.sh`
3. `Scotty.blade.php`
4. `scotty.blade.php`
5. `Envoy.sh`
6. `envoy.sh`
7. `Envoy.blade.php`
8. `envoy.blade.php`

You can also specify a file directly:

```bash
scotty run deploy --path=my-deploy-file.sh
scotty run deploy --conf=Envoy.blade.php
```

## Migrating to the bash format

The main differences between the Blade and bash formats:

| Blade format | Bash format |
|---|---|
| `@servers(['remote' => 'forge@1.1.1.1'])` | `# @servers remote=forge@1.1.1.1` |
| `@task('deploy', ['on' => 'remote'])` | `# @task on:remote` |
| `@endtask` | `}` (end of function) |
| `@macro('deploy')` ... `@endmacro` | `# @macro deploy task1 task2` |
| `{{ $variable }}` | `$VARIABLE` |
| `@setup` PHP block for computed values | Shell `$(command)` substitution |
| `@if($condition)` | Bash `if [ condition ]` |

The bash format has the advantage of full editor support (syntax highlighting, linting, shellcheck) and no template compilation step.
