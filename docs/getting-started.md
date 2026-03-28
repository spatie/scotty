---
title: Getting started
weight: 3
---

Let's get Scotty up and running. By the end of this page, you'll have created a Scotty file and run your first task on a remote server.

## Prerequisites

You'll need [Scotty installed](/docs/scotty/v1/installation) and SSH access (with key-based authentication) to a server you want to run commands on.

## Create a Scotty file

In your project root, run:

```bash
scotty init
```

Choose the bash format when prompted and enter your server's SSH connection string (for example `deployer@your-server.com`). Scotty creates a `Scotty.sh` file for you.

You can also create the file by hand. Just add a `Scotty.sh` in your project root:

```bash
#!/usr/bin/env scotty

# @servers remote=deployer@your-server.com
```

Replace `deployer@your-server.com` with your actual server.

## Add your first task

A task is a bash function with a `# @task` annotation above it. The `on:` parameter tells Scotty which server to run it on.

Let's add a simple task that checks the server's uptime:

```bash
#!/usr/bin/env scotty

# @servers remote=deployer@your-server.com

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

Scotty connects to your server over SSH, runs the commands, and streams the output back. That's your first manual SSH step, automated.

## Add a deploy task

Now let's add something more useful. Say you have a Laravel app at `/var/www/my-app` on the server:

```bash
#!/usr/bin/env scotty

# @servers remote=deployer@your-server.com

# @task on:remote
checkUptime() {
    uptime
    df -h /
}

# @task on:remote
pullCode() {
    cd /var/www/my-app
    git pull origin main
}

# @task on:remote
clearCache() {
    cd /var/www/my-app
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear
}
```

You can run each one individually with `scotty run pullCode` or `scotty run clearCache`.

## Group tasks into a macro

Running tasks one by one isn't much better than doing it by hand. A macro lets you run them in sequence with a single command. Add this near the top of your file, right after the `# @servers` line:

```bash
# @macro deploy pullCode clearCache
```

Now:

```bash
scotty run deploy
```

If any task fails, Scotty stops right there so you can investigate. You'll see a summary table at the end with timing for each step.

## Verify your setup

Before running a deploy for real, use the doctor command to check that everything looks good:

```bash
scotty doctor
```

This validates your Scotty file, tests SSH connectivity, and checks that tools like PHP, Composer, and Git are available on the server.

## The complete file

Here's what your `Scotty.sh` looks like now:

```bash
#!/usr/bin/env scotty

# @servers remote=deployer@your-server.com
# @macro deploy pullCode clearCache

# @task on:remote
checkUptime() {
    uptime
    df -h /
}

# @task on:remote
pullCode() {
    cd /var/www/my-app
    git pull origin main
}

# @task on:remote
clearCache() {
    cd /var/www/my-app
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear
}
```

## Next steps

- Learn the full [Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format) with variables, parallel execution, hooks, and more
- Already using Envoy? See the [Envoy compatibility](/docs/scotty/v1/advanced-usage/envoy-compatibility) page
- Check out how to [run tasks](/docs/scotty/v1/basic-usage/running-tasks) with pretend mode, summary mode, and more
