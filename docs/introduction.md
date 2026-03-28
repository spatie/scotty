---
title: Introduction
weight: 1
---

Scotty is a CLI tool that beams your deployment scripts to remote servers. Think of it as a task runner for SSH.

You define tasks in a simple file, and Scotty handles connecting to your servers, running the scripts, and showing you what's happening.

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)
<!-- SCREENSHOT NEEDED: Full `scotty run deploy` output showing a real deploy with multiple tasks, spinner, output, timing, and summary table -->

## What Scotty adds over Envoy

Scotty was built as a modern alternative to [Laravel Envoy](https://laravel.com/docs/envoy). Here's what you get:

### A pure bash file format

Envoy uses Blade templates for shell scripts. That means no syntax highlighting, no linting, and a templating language designed for HTML being used for bash. Scotty introduces a `Scotty.sh` format that is plain bash with annotation comments. Your editor understands it perfectly.

![Scotty.sh in an editor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-file-editor.png?raw=true)
<!-- SCREENSHOT NEEDED: Scotty.sh file open in VS Code or PhpStorm showing syntax highlighting -->

### Better output

Envoy streams raw output with minimal formatting. Scotty shows a spinner with elapsed time, the command currently being executed, and per task timing. When the deploy finishes, you get a summary table.

![Deploy output comparison](https://github.com/spatie/scotty/blob/main/docs/images/scotty-output.png?raw=true)
<!-- SCREENSHOT NEEDED: scotty run deploy output during a real deploy, showing the spinner line, task output, and the summary table at the end -->

### Pause and resume

Press `p` during a deploy to pause after the current task. Scotty immediately acknowledges the request and waits between tasks. Press `Enter` to continue.

![Pause and resume](https://github.com/spatie/scotty/blob/main/docs/images/scotty-pause.png?raw=true)
<!-- SCREENSHOT NEEDED: Deploy paused between tasks, showing the "⏸ Paused" message -->

### Doctor command

Run `scotty doctor` to validate your file, check SSH connectivity to all servers, and verify that required tools are installed on each remote machine.

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.png?raw=true)
<!-- SCREENSHOT NEEDED: `scotty doctor` output with all checks passing -->

### Envoy compatibility

Scotty reads existing `Envoy.blade.php` files out of the box. You can try Scotty without changing any files in your project. See the [Envoy compatibility](envoy-compatibility) page for details and a migration guide.

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
