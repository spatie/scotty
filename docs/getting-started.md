---
title: Getting started
weight: 3
---

This page walks you through creating a Scotty file and running your first task on a remote server.

## Prerequisites

Make sure you have [Scotty installed](/docs/scotty/v1/installation) and SSH access (with key-based authentication) to a server you want to run commands on.

## Create a Scotty file

In the root of your project, run:

```bash
scotty init
```

Choose the bash format when prompted, and enter your server's SSH connection string (for example `forge@your-server.com`). Scotty creates a `Scotty.sh` file for you.

You can also create the file by hand. Create a `Scotty.sh` in your project root:

```bash
#!/usr/bin/env scotty

# @servers remote=forge@your-server.com
```

Replace `forge@your-server.com` with the actual user and hostname of your server.

## Add your first task

A task is a bash function with a `# @task` annotation above it. The `on:` parameter tells Scotty which server to run it on.

Add a simple task that checks the server's uptime:

```bash
#!/usr/bin/env scotty

# @servers remote=forge@your-server.com

# @task on:remote
checkUptime() {
    uptime
    df -h /
}
```

## Run it

```bash
scotty run checkUptime
```

Scotty connects to your server over SSH, runs the commands inside `checkUptime`, and streams the output back to your terminal with timing information.

## Add a deploy task

Now let's add something more practical. Say you have a Laravel application at `/home/forge/my-app` on the server:

```bash
#!/usr/bin/env scotty

# @servers remote=forge@your-server.com

# @task on:remote
checkUptime() {
    uptime
    df -h /
}

# @task on:remote
pullCode() {
    cd /home/forge/my-app
    git pull origin main
}

# @task on:remote
clearCache() {
    cd /home/forge/my-app
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear
}
```

You can run each task individually:

```bash
scotty run pullCode
scotty run clearCache
```

## Group tasks into a macro

Instead of running tasks one by one, group them into a macro:

```bash
# @macro deploy pullCode clearCache
```

Add this line near the top of your file, after the `# @servers` line. Now run both tasks in sequence with:

```bash
scotty run deploy
```

If any task fails, Scotty stops and shows you the output. You'll see a summary table at the end with timing for each task.

## Verify your setup

Before running a deploy for real, use the doctor command to check that everything is in order:

```bash
scotty doctor
```

This validates your Scotty file, tests SSH connectivity, and checks that tools like PHP, Composer, and Git are available on the server.

## The complete file

Here's what your `Scotty.sh` should look like:

```bash
#!/usr/bin/env scotty

# @servers remote=forge@your-server.com
# @macro deploy pullCode clearCache

# @task on:remote
checkUptime() {
    uptime
    df -h /
}

# @task on:remote
pullCode() {
    cd /home/forge/my-app
    git pull origin main
}

# @task on:remote
clearCache() {
    cd /home/forge/my-app
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear
}
```

## Next steps

- Learn the full [Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format) with variables, parallel execution, hooks, and more
- Already using Laravel Envoy? See the [Envoy compatibility](/docs/scotty/v1/advanced-usage/envoy-compatibility) page
- See all the ways to [run tasks](/docs/scotty/v1/basic-usage/running-tasks), including pretend mode and summary mode
