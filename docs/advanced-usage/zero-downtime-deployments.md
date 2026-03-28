---
title: Zero-downtime deployments
weight: 5
---

A basic deploy script pulls code into a single directory and restarts services. That works, but there's a window where the application is in an inconsistent state: new code is on disk, but dependencies haven't been installed yet, or the cache hasn't been cleared. If a request comes in during that window, your users see an error.

Zero-downtime deployments solve this by preparing the new release in a separate directory. Only when everything is ready, you swap a symlink to point at it. The switch is atomic, so users never see a broken state.

This guide walks you through building a zero-downtime deploy script step by step.

## The directory structure

The idea is simple. Instead of deploying into one directory, you maintain a structure like this:

```
/var/www/my-app/
├── current -> /var/www/my-app/releases/20260328-150000
├── persistent/
│   └── storage/
├── releases/
│   ├── 20260328-140000/
│   └── 20260328-150000/
└── .env
```

`releases/` holds a directory per deploy, each containing a full copy of the application. `current` is a symlink that points to the active release. `persistent/` holds files that survive across deploys (like storage, uploads, or logs). The `.env` file lives outside the releases so all of them share the same configuration.

Your web server points its document root at `/var/www/my-app/current/public`. When you deploy, you prepare a new release directory, and when everything is ready, you update the `current` symlink. Your web server immediately starts serving the new code.

## Setting up the script

Start with your server and some variables:

```bash
#!/usr/bin/env scotty

# @servers local=127.0.0.1 remote=deployer@your-server.com

BASE_DIR="/var/www/my-app"
RELEASES_DIR="$BASE_DIR/releases"
PERSISTENT_DIR="$BASE_DIR/persistent"
CURRENT_DIR="$BASE_DIR/current"
NEW_RELEASE_NAME=$(date +%Y%m%d-%H%M%S)
NEW_RELEASE_DIR="$RELEASES_DIR/$NEW_RELEASE_NAME"
REPOSITORY="your-org/your-repo"
BRANCH="${BRANCH:-main}"
```

The release name is a timestamp, so each deploy gets a unique directory. `BRANCH` defaults to `main` but can be overridden from the command line with `--branch=develop`.

## Pulling the latest code locally

Before anything happens on the server, make sure your local checkout is up to date. This is a good place to catch issues early:

```bash
# @task on:local
startDeployment() {
    git checkout $BRANCH
    git pull origin $BRANCH
}
```

## Cloning to a fresh release directory

On the server, create the release directory and clone the repository into it. Using `--depth 1` keeps it fast by only fetching the latest commit:

```bash
# @task on:remote
cloneRepository() {
    [ -d $RELEASES_DIR ] || mkdir -p $RELEASES_DIR
    [ -d $PERSISTENT_DIR ] || mkdir -p $PERSISTENT_DIR
    [ -d $PERSISTENT_DIR/storage ] || mkdir -p $PERSISTENT_DIR/storage

    cd $RELEASES_DIR
    git clone --depth 1 --branch $BRANCH git@github.com:$REPOSITORY $NEW_RELEASE_NAME
}
```

At this point, you have a fresh copy of your code in `$NEW_RELEASE_DIR`, but the live application hasn't been touched.

## Installing dependencies

Link the shared `.env` file and install Composer dependencies:

```bash
# @task on:remote
runComposer() {
    cd $NEW_RELEASE_DIR
    ln -nfs $BASE_DIR/.env .env
    composer install --prefer-dist --no-dev -o
}
```

If your project has frontend assets, build those too:

```bash
# @task on:remote
buildAssets() {
    cd $NEW_RELEASE_DIR
    npm ci
    npm run build
    rm -rf node_modules
}
```

Removing `node_modules` after the build keeps the release directory lean. The built assets in `public/build` are all you need.

## Linking persistent files

The new release has its own `storage` directory from the repo, but you want to use the shared one that persists across deploys. Replace it with a symlink:

```bash
# @task on:remote
updateSymlinks() {
    rm -rf $NEW_RELEASE_DIR/storage
    cd $NEW_RELEASE_DIR
    ln -nfs $PERSISTENT_DIR/storage storage
}
```

If you have other persistent directories (uploads, media), add symlinks for those too.

## Running migrations

Now run migrations against the new release. Since the code is already in place, the migrations can reference any new models or configuration:

```bash
# @task on:remote
migrateDatabase() {
    cd $NEW_RELEASE_DIR
    php artisan migrate --force
}
```

## Switching to the new release

This is the key step. Updating the symlink is atomic, so the switch from old to new happens instantly:

```bash
# @task on:remote
blessNewRelease() {
    ln -nfs $NEW_RELEASE_DIR $CURRENT_DIR

    cd $NEW_RELEASE_DIR
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan cache:clear
    php artisan horizon:terminate

    sudo service php8.4-fpm restart
}
```

Restarting PHP-FPM ensures the new code is picked up immediately. Terminating Horizon restarts your queue workers with the new release.

## Cleaning up old releases

Keep the last few releases around (so you can roll back if needed) and delete the rest:

```bash
# @task on:remote
cleanOldReleases() {
    cd $RELEASES_DIR
    ls -dt $RELEASES_DIR/* | tail -n +4 | xargs rm -rf
}
```

This keeps the three most recent releases and removes everything older.

## Wiring it all together

Create a macro that runs all tasks in order:

```bash
# @macro deploy startDeployment cloneRepository runComposer buildAssets updateSymlinks migrateDatabase blessNewRelease cleanOldReleases
```

Now `scotty run deploy` does the whole thing. If any step fails, Scotty stops and the live application is untouched because the `current` symlink still points at the old release.

## The complete script

```bash
#!/usr/bin/env scotty

# @servers local=127.0.0.1 remote=deployer@your-server.com
# @macro deploy startDeployment cloneRepository runComposer buildAssets updateSymlinks migrateDatabase blessNewRelease cleanOldReleases

BASE_DIR="/var/www/my-app"
RELEASES_DIR="$BASE_DIR/releases"
PERSISTENT_DIR="$BASE_DIR/persistent"
CURRENT_DIR="$BASE_DIR/current"
NEW_RELEASE_NAME=$(date +%Y%m%d-%H%M%S)
NEW_RELEASE_DIR="$RELEASES_DIR/$NEW_RELEASE_NAME"
REPOSITORY="your-org/your-repo"
BRANCH="${BRANCH:-main}"

# @task on:local
startDeployment() {
    git checkout $BRANCH
    git pull origin $BRANCH
}

# @task on:remote
cloneRepository() {
    [ -d $RELEASES_DIR ] || mkdir -p $RELEASES_DIR
    [ -d $PERSISTENT_DIR ] || mkdir -p $PERSISTENT_DIR
    [ -d $PERSISTENT_DIR/storage ] || mkdir -p $PERSISTENT_DIR/storage

    cd $RELEASES_DIR
    git clone --depth 1 --branch $BRANCH git@github.com:$REPOSITORY $NEW_RELEASE_NAME
}

# @task on:remote
runComposer() {
    cd $NEW_RELEASE_DIR
    ln -nfs $BASE_DIR/.env .env
    composer install --prefer-dist --no-dev -o
}

# @task on:remote
buildAssets() {
    cd $NEW_RELEASE_DIR
    npm ci
    npm run build
    rm -rf node_modules
}

# @task on:remote
updateSymlinks() {
    rm -rf $NEW_RELEASE_DIR/storage
    cd $NEW_RELEASE_DIR
    ln -nfs $PERSISTENT_DIR/storage storage
}

# @task on:remote
migrateDatabase() {
    cd $NEW_RELEASE_DIR
    php artisan migrate --force
}

# @task on:remote
blessNewRelease() {
    ln -nfs $NEW_RELEASE_DIR $CURRENT_DIR

    cd $NEW_RELEASE_DIR
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan cache:clear
    php artisan horizon:terminate

    sudo service php8.4-fpm restart
}

# @task on:remote
cleanOldReleases() {
    cd $RELEASES_DIR
    ls -dt $RELEASES_DIR/* | tail -n +4 | xargs rm -rf
}
```

Deploy with:

```bash
scotty run deploy
```

Or deploy a specific branch:

```bash
scotty run deploy --branch=develop
```