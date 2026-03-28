---
title: Introduction
weight: 1
---

Scotty is a CLI tool that runs your deployment scripts on remote servers over SSH. You define tasks in a simple file, and Scotty takes care of connecting to your servers, running each script, and showing you exactly what's happening along the way.

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)

Scotty was built as a modern alternative to [Laravel Envoy](https://laravel.com/docs/envoy). Where Envoy uses Blade templates for shell scripts (no syntax highlighting, no linting), Scotty uses a `Scotty.sh` format that is plain bash with annotation comments, so your editor understands it perfectly. Scotty also provides much better output during deploys: a spinner with elapsed time, per task timing, and a summary table when everything finishes. You can press `p` to pause a running deploy and resume it with `Enter`. There's a built-in `scotty doctor` command that validates your file, checks SSH connectivity, and verifies that required tools are installed on each server. And if you're already using Envoy, Scotty can read your existing `Envoy.blade.php` out of the box, so you can [try it without changing anything](envoy-compatibility).

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
