---
title: Zero-downtime deployments
weight: 5
---

If you deploy by pulling code directly into your application directory, there's always a moment where things are in a weird state. The new code is on disk, but Composer hasn't run yet. Or the cache still has the old config. If a request hits the server during that window, your users get an error page.

There's a simple way to avoid this: instead of updating files in place, you prepare the new release in a separate directory. When everything is ready, you swap a symlink. The switch is instant, and nobody notices.

This is how we deploy all Spatie applications. Let's build this script together, step by step.

## How it works

On your server, you'll have a directory structure like this:

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

Let's go through each part:

- `releases/` contains a directory for every deploy. Each one is a full copy of your application.
- `current` is a symlink. It points to whichever release is currently live.
- `persistent/` holds files that should survive across deploys. Think of your `storage` directory: logs, cached files, uploaded images. You don't want to lose those every time you deploy.
- `.env` lives outside the releases, so every release automatically shares the same environment config.

Your web server's document root points to `/var/www/my-app/current/public`. When you deploy, you build everything in a new release directory. Once it's ready, you update the `current` symlink to point to it. Done. Your web server immediately serves the new code.

The clever bit: if anything goes wrong during the deploy (Composer fails, migrations break), the `current` symlink still points to the old, working release. Your users never notice.

## Let's build the script

Create a `Scotty.sh` file in your project root. We'll start with some variables:

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

Replace `deployer@your-server.com` with your actual server, and `your-org/your-repo` with your GitHub repository. The release name is a timestamp, so every deploy gets its own unique directory. `BRANCH` defaults to `main`, but you can override it later with `scotty run deploy --branch=develop`.

## Step 1: Pull locally first

Before touching the server, let's make sure our local checkout is up to date:

```bash
# @task on:local
startDeployment() {
    git checkout $BRANCH
    git pull origin $BRANCH
}
```

This runs on your own machine. If there's a merge conflict or network issue, you'll know right away before anything happens on the server.

## Step 2: Clone a fresh copy on the server

Now we clone the repository into a new release directory on the server:

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

The `[ -d ... ] || mkdir` lines ensure the directories exist on the very first deploy. After that, they're no-ops. We use `--depth 1` so git only fetches the latest commit, which is much faster than cloning the full history.

At this point, you have a fresh copy of your code on the server, but the live application hasn't been touched at all. That's the key idea.

## Step 3: Install dependencies

Link the shared `.env` file and run Composer:

```bash
# @task on:remote
runComposer() {
    cd $NEW_RELEASE_DIR
    ln -nfs $BASE_DIR/.env .env
    composer install --prefer-dist --no-dev -o
}
```

The `ln -nfs` creates a symlink from the release's `.env` to the shared one in the base directory. That way, every release uses the same database credentials and app key without you having to copy the file each time.

If your project has frontend assets, build those in a separate task:

```bash
# @task on:remote
buildAssets() {
    cd $NEW_RELEASE_DIR
    npm ci
    npm run build
    rm -rf node_modules
}
```

We remove `node_modules` after building because you don't need it at runtime. It just takes up space.

## Step 4: Link persistent files

Your new release has its own `storage` directory from the repo, but you want to use the shared one. Otherwise you'd lose your logs, cache, and uploaded files on every deploy. Replace it with a symlink:

```bash
# @task on:remote
updateSymlinks() {
    rm -rf $NEW_RELEASE_DIR/storage
    cd $NEW_RELEASE_DIR
    ln -nfs $PERSISTENT_DIR/storage storage
}
```

If your app has other directories that should persist across deploys (like a `public/uploads` folder), add more symlinks here.

## Step 5: Run migrations

```bash
# @task on:remote
migrateDatabase() {
    cd $NEW_RELEASE_DIR
    php artisan migrate --force
}
```

The `--force` flag is needed because Laravel won't run migrations in production without it. This runs against the new release directory, so if a migration fails, the live application is still untouched.

## Step 6: Go live

This is the moment. We update the `current` symlink to point to the new release:

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

The `ln -nfs` updates the symlink atomically. From this exact moment, all new requests are served by the new release. There's no in-between state.

We then rebuild the caches so they match the new code, and restart PHP-FPM so it picks up the changes. If you're running Horizon for queues, terminating it lets the supervisor restart it with the new code.

## Step 7: Clean up

Old releases pile up over time. Let's keep the three most recent ones (in case you ever need to inspect them) and delete the rest:

```bash
# @task on:remote
cleanOldReleases() {
    cd $RELEASES_DIR
    ls -dt $RELEASES_DIR/* | tail -n +4 | xargs rm -rf
}
```

## Wire it all together

Add a macro at the top of your file (right after the `# @servers` line) that runs every task in sequence:

```bash
# @macro deploy startDeployment cloneRepository runComposer buildAssets updateSymlinks migrateDatabase blessNewRelease cleanOldReleases
```

That's it. Now you can deploy with:

```bash
scotty run deploy
```

If any step fails, Scotty stops immediately. Since the `current` symlink hasn't been updated yet (that only happens in `blessNewRelease`), your users keep seeing the old, working version.

To deploy a different branch:

```bash
scotty run deploy --branch=develop
```

## The complete script

Here's everything in one file, ready to copy into your project:

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
