---
title: Introduction
weight: 1
---

Scotty is a CLI tool that beams your deployment scripts to remote servers. Think of it as a task runner for SSH.

You define tasks in a simple file, and Scotty handles connecting to your servers, running the scripts, and showing you what's happening with beautiful output.

## Why Scotty?

If you've used [Laravel Envoy](https://laravel.com/docs/envoy), you'll feel right at home. Scotty was built as a modern alternative with a few key improvements:

- **A bash file format** that gives you full syntax highlighting and editor support
- **Better output** with a spinner, per-task timing, and a summary table
- **Pause and resume** during deploys
- **Command tracing** that shows you which line of your script is currently executing

Scotty can also read existing `Envoy.blade.php` files, so you can try it without changing anything.

## Quick example

Here's a minimal `Scotty.sh` file:

```bash
#!/usr/bin/env scotty

# @servers remote=forge@your-server.com
# @macro deploy pullCode clearCache

# @task on:remote
pullCode() {
    cd /home/forge/my-app
    git pull origin main
}

# @task on:remote
clearCache() {
    cd /home/forge/my-app
    php artisan cache:clear
}
```

Run it with:

```bash
scotty run deploy
```

## Using Envoy files

If you already have an `Envoy.blade.php` file, Scotty reads it out of the box. Just run `scotty run deploy` in the same directory. Check out the [Laravel Envoy documentation](https://laravel.com/docs/envoy) for the Blade file format.

When both `Scotty.sh` and `Envoy.blade.php` exist, Scotty prefers the `.sh` file.
