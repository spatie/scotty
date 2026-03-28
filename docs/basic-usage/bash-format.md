---
title: The Scotty.sh format
weight: 2
---

Scotty introduces a bash file format as an alternative to Blade. Every line is real bash. Your editor highlights it correctly and you get full shell support.

## Servers

Define your servers at the top of the file with a `# @servers` annotation:

```bash
# @servers local=127.0.0.1 remote=forge@your-server.com
```

Multiple servers:

```bash
# @servers local=127.0.0.1 web-1=forge@1.1.1.1 web-2=forge@2.2.2.2
```

## Tasks

A task is a bash function preceded by a `# @task` annotation. The `on:` parameter specifies which server to run on:

```bash
# @task on:remote
deploy() {
    cd /home/forge/my-app
    git pull origin main
    php artisan migrate --force
}
```

### Multiple servers

Target multiple servers by separating names with commas:

```bash
# @task on:web-1,web-2
deploy() {
    cd /home/forge/my-app
    git pull origin main
}
```

By default, the task completes on each server sequentially.

### Parallel execution

Add `parallel` to run on all servers simultaneously:

```bash
# @task on:web-1,web-2 parallel
restartWorkers() {
    sudo supervisorctl restart all
}
```

### Confirmation

Require confirmation before running a task:

```bash
# @task on:remote confirm="Are you sure you want to deploy to production?"
deploy() {
    cd /home/forge/my-app
    git pull origin main
}
```

## Macros

A macro runs multiple tasks in sequence:

```bash
# @macro deploy pullCode runComposer clearCache restartWorkers
```

Run it with `scotty run deploy`. Each task runs in order. If any task fails, execution stops.

## Variables

Define variables at the top of the file, before any functions:

```bash
BRANCH="main"
REPOSITORY="your/repo"
BASE_DIR="/home/forge/my-app"
RELEASES_DIR="$BASE_DIR/releases"
NEW_RELEASE_NAME=$(date +%Y%m%d-%H%M%S)
```

These are plain bash variables, available in all tasks. Computed values like `$(date)` work naturally.

You can also pass variables from the command line:

```bash
scotty run deploy --branch=develop
```

The key is uppercased and dashes become underscores, so `--branch=develop` becomes `$BRANCH`.

## Helper functions

Functions without a `# @task` annotation are treated as helpers. They are available in all tasks:

```bash
log() {
    echo -e "\033[32m$1\033[0m"
}

# @task on:remote
deploy() {
    log "Deploying..."
    cd /home/forge/my-app
    git pull origin main
}
```

## Hooks

Run scripts at different points in the execution lifecycle:

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

## Complete example

```bash
#!/usr/bin/env scotty

# @servers local=127.0.0.1 remote=forge@your-server.com
# @macro deploy startDeployment cloneRepository runComposer blessNewRelease cleanOldReleases

BRANCH="main"
REPOSITORY="your/repo"
BASE_DIR="/home/forge/my-app"
RELEASES_DIR="$BASE_DIR/releases"
CURRENT_DIR="$BASE_DIR/current"
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
    ln -nfs $BASE_DIR/.env .env
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

The main differences between the Blade and Scotty.sh formats:

| Blade format | Bash format |
|---|---|
| `@servers(['remote' => 'forge@1.1.1.1'])` | `# @servers remote=forge@1.1.1.1` |
| `@task('deploy', ['on' => 'remote'])` | `# @task on:remote` |
| `@endtask` | `}` (end of function) |
| `@story('deploy')` ... `@endstory` | `# @macro deploy task1 task2` |
| `{{ $variable }}` | `$VARIABLE` |
| `@setup` PHP block | Shell `$(command)` substitution |
| `@if($condition)` | Bash `if [ condition ]` |
