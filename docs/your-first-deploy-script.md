---
title: Your first deploy script
weight: 4
---

Let's say you have a Laravel application running on a server at `deployer@your-server.com`. The app lives at `/var/www/my-app`. Right now you deploy by SSH'ing in, running `git pull`, some artisan commands, and restarting the queue. Let's automate that with Scotty.

## Start with the basics

Create a `Scotty.sh` file in your project root:

```bash
#!/usr/bin/env scotty

# @servers remote=deployer@your-server.com
```

That's enough to connect. You can already test your SSH connection:

```bash
scotty doctor
```

If the connection check passes, you're good to go.

## Pull the latest code

Add your first task. This is what you'd normally type after SSH'ing in:

```bash
# @task on:remote
pullCode() {
    cd /var/www/my-app
    git pull origin main
}
```

Run it:

```bash
scotty run pullCode
```

Scotty connects, runs the commands, and shows you the output. You've just replaced your first manual SSH step.

## Add the rest of your deploy

Think about what else you do after pulling code. Probably install dependencies, run migrations, clear caches, and restart workers. Each of those becomes a task:

```bash
# @task on:remote
runComposer() {
    cd /var/www/my-app
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
}

# @task on:remote
runMigrations() {
    cd /var/www/my-app
    php artisan migrate --force
}

# @task on:remote
clearCaches() {
    cd /var/www/my-app
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
}

# @task on:remote
restartWorkers() {
    cd /var/www/my-app
    php artisan horizon:terminate
}
```

You could run each task individually, but that's not much better than doing it by hand.

## Wire them together with a macro

A macro runs tasks in sequence. Add this near the top of your file, right after the `# @servers` line:

```bash
# @macro deploy pullCode runComposer runMigrations clearCaches restartWorkers
```

Now one command does everything:

```bash
scotty run deploy
```

Scotty runs each task in order. If something fails (say `composer install` hits an error), it stops right there so you can investigate. At the end, you get a summary table showing how long each step took.

## Clean up the repetition

Every task starts with `cd /var/www/my-app`. You can use a variable to avoid repeating the path:

```bash
APP_DIR="/var/www/my-app"
```

Put it after the servers and macro lines. Variables are plain bash, available in all tasks:

```bash
# @task on:remote
pullCode() {
    cd $APP_DIR
    git pull origin main
}
```

## Make the branch configurable

Sometimes you want to deploy a different branch. Instead of editing the file each time, pass it from the command line:

```bash
scotty run deploy --branch=develop
```

Command line options are available as uppercased variables. Use a default so it works without the flag too:

```bash
BRANCH="${BRANCH:-main}"

# @task on:remote
pullCode() {
    cd $APP_DIR
    git pull origin $BRANCH
}
```

## Add a safety net

Before deploying to production for the first time, add a confirmation prompt:

```bash
# @task on:remote confirm="Deploy to production?"
pullCode() {
    cd $APP_DIR
    git pull origin $BRANCH
}
```

Only the first task needs the confirmation. Once you confirm, the rest of the macro runs normally.

## The complete script

Here's everything together:

```bash
#!/usr/bin/env scotty

# @servers remote=deployer@your-server.com
# @macro deploy pullCode runComposer runMigrations clearCaches restartWorkers

APP_DIR="/var/www/my-app"
BRANCH="${BRANCH:-main}"

# @task on:remote confirm="Deploy to production?"
pullCode() {
    cd $APP_DIR
    git pull origin $BRANCH
}

# @task on:remote
runComposer() {
    cd $APP_DIR
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
}

# @task on:remote
runMigrations() {
    cd $APP_DIR
    php artisan migrate --force
}

# @task on:remote
clearCaches() {
    cd $APP_DIR
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
}

# @task on:remote
restartWorkers() {
    cd $APP_DIR
    php artisan horizon:terminate
}
```

Deploy with:

```bash
scotty run deploy
```

Or deploy a specific branch:

```bash
scotty run deploy --branch=feature/new-checkout
```

That's it. You went from manually SSH'ing in and running commands to a single `scotty run deploy`. The script lives in your repo, so your whole team can use it.
