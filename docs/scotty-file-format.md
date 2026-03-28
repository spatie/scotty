---
title: The Scotty.sh format
weight: 3
---

Scotty introduces a new bash file format. Every line is real bash, with no template syntax. Your editor highlights it correctly and you get full shell support.

## Servers

Define your servers at the top of the file:

```bash
# @servers local=127.0.0.1 remote=forge@your-server.com
```

Multiple servers:

```bash
# @servers local=127.0.0.1 web-1=forge@1.1.1.1 web-2=forge@2.2.2.2
```

## Tasks

A task is a bash function preceded by a `# @task` annotation:

```bash
# @task on:remote
deploy() {
    cd /home/forge/my-app
    git pull origin main
    php artisan migrate --force
}
```

The `on:` parameter specifies which server to run on.

### Multiple servers

Target multiple servers by separating names with commas:

```bash
# @task on:web-1,web-2
deploy() {
    cd /home/forge/my-app
    git pull origin main
}
```

By default, the task completes on each server sequentially (finishing on `web-1` before starting on `web-2`).

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
# @task on:remote confirm="Are you sure you want to deploy?"
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

## Lifecycle hooks

Run scripts before or after tasks, and on success or failure:

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
    composer install --prefer-dist --no-dev -q -o
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
