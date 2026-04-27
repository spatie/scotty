---
title: The Scotty.sh format
weight: 3
---

The `Scotty.sh` format is plain bash with annotation comments. Every line is real bash, so your editor highlights it correctly and all your existing shell tooling works.

## Servers

At the top of your file, define which servers you want to connect to:

```bash
# @servers local=127.0.0.1 remote=deployer@your-server.com
```

You can define as many as you need:

```bash
# @servers local=127.0.0.1 web-1=deployer@1.1.1.1 web-2=deployer@2.2.2.2
```

If your server listens on a non-default SSH port, append it to the host with a colon:

```bash
# @servers remote=deployer@your-server.com:2222
```

For more complex SSH options (identity files, jump hosts, ProxyCommand, etc.), put them in `~/.ssh/config` under a `Host` block and reference that host name from `@servers`.

## Tasks

A task is just a bash function with a `# @task` annotation above it. The `on:` parameter tells Scotty which server to run it on:

```bash
# @task on:remote
deploy() {
    cd /var/www/my-app
    git pull origin main
    php artisan migrate --force
}
```

That's the core concept. Everything else builds on this.

### Running on multiple servers

You can target multiple servers by separating their names with commas:

```bash
# @task on:web-1,web-2
deploy() {
    cd /var/www/my-app
    git pull origin main
}
```

By default, the task runs on each server one after the other.

### Parallel execution

If you want to run on all servers at the same time, add `parallel`:

```bash
# @task on:web-1,web-2 parallel
restartWorkers() {
    sudo supervisorctl restart all
}
```

This is handy for things like restarting workers across a cluster, where you don't need to wait for one to finish before starting the next.

### Confirmation

For dangerous tasks (like deploying to production), you can require confirmation:

```bash
# @task on:remote confirm="Are you sure you want to deploy to production?"
deploy() {
    cd /var/www/my-app
    git pull origin main
}
```

Scotty will ask before running the task. If you say no, it stops.

## Macros

A macro groups multiple tasks together so you can run them with a single command:

```bash
# @macro deploy pullCode runComposer clearCache restartWorkers
```

If the list gets long, you can use the multi-line format:

```bash
# @macro deploy
#   pullCode
#   runComposer
#   generateAssets
#   updateSymlinks
#   clearCache
#   restartWorkers
# @endmacro
```

Run it with `scotty run deploy`. The tasks execute in the order you listed them. If any task fails, execution stops immediately.

## Variables

You can define variables at the top of your file, right after the server and macro lines:

```bash
BRANCH="main"
REPOSITORY="your/repo"
APP_DIR="/var/www/my-app"
RELEASES_DIR="$APP_DIR/releases"
NEW_RELEASE_NAME=$(date +%Y%m%d-%H%M%S)
```

These are plain bash variables, so computed values like `$(date)` work naturally. All variables are available in all tasks.

You can also pass variables from the command line:

```bash
scotty run deploy --branch=develop
```

The key gets uppercased and dashes become underscores, so `--branch=develop` sets `$BRANCH` to `develop`.

## Helper functions

Any function without a `# @task` annotation is treated as a helper. Helpers are available in all tasks:

```bash
log() {
    echo -e "\033[32m$1\033[0m"
}

# @task on:remote
deploy() {
    log "Deploying..."
    cd /var/www/my-app
    git pull origin main
}
```

## Hooks

You can run code at different points during execution. This is useful for things like sending Slack notifications:

```bash
# @before
beforeEachTask() {
    echo "Starting task..."
}

# @after
afterEachTask() {
    echo "Task done."
}

# @success
onSuccess() {
    curl -X POST https://hooks.slack.com/... \
        -d '{"text": "Deploy succeeded!"}'
}

# @error
onError() {
    curl -X POST https://hooks.slack.com/... \
        -d '{"text": "Deploy failed!"}'
}

# @finished
onFinished() {
    echo "Deploy process complete."
}
```

`@before` and `@after` run around each task. `@success` and `@error` run once at the end depending on whether everything passed. `@finished` always runs, regardless of the outcome.

## Complete example

Here's a full deploy script using all the concepts above:

```bash
#!/usr/bin/env scotty

# @servers local=127.0.0.1 remote=deployer@your-server.com
# @macro deploy
#   startDeployment
#   cloneRepository
#   runComposer
#   blessNewRelease
#   cleanOldReleases
# @endmacro

BRANCH="main"
REPOSITORY="your/repo"
APP_DIR="/var/www/my-app"
RELEASES_DIR="$APP_DIR/releases"
CURRENT_DIR="$APP_DIR/current"
NEW_RELEASE_NAME=$(date +%Y%m%d-%H%M%S)
NEW_RELEASE_DIR="$RELEASES_DIR/$NEW_RELEASE_NAME"

# @task on:local
startDeployment() {
    git checkout $BRANCH
    git pull origin $BRANCH
}

# @task on:remote
cloneRepository() {
    cd $RELEASES_DIR
    git clone --depth 1 git@github.com:$REPOSITORY --branch $BRANCH $NEW_RELEASE_NAME
}

# @task on:remote
runComposer() {
    cd $NEW_RELEASE_DIR
    ln -nfs $APP_DIR/.env .env
    composer install --prefer-dist --no-dev -o
}

# @task on:remote
blessNewRelease() {
    ln -nfs $NEW_RELEASE_DIR $CURRENT_DIR
    sudo service php8.4-fpm restart
}

# @task on:remote
cleanOldReleases() {
    cd $RELEASES_DIR
    ls -dt $RELEASES_DIR/* | tail -n +4 | xargs rm -rf
}
```

## Migrating from Envoy

If you're coming from Laravel Envoy, here's a quick reference. For the full Blade format documentation, see the [Envoy compatibility](/docs/scotty/v1/advanced-usage/envoy-compatibility) page.

| Blade format | Scotty.sh format |
|---|---|
| `@servers(['remote' => '1.1.1.1'])` | `# @servers remote=1.1.1.1` |
| `@task('deploy', ['on' => 'remote'])` | `# @task on:remote` |
| `@endtask` | `}` (end of function) |
| `@story('deploy')` ... `@endstory` | `# @macro deploy task1 task2` |
| `{{ $variable }}` | `$VARIABLE` |
| `@setup` PHP block | Shell `$(command)` substitution |
| `@if($condition)` | Bash `if [ condition ]` |
