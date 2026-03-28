#!/usr/bin/env scotty

# @servers local=127.0.0.1 production=forge@production.example.com staging=forge@staging.example.com
# @macro deploy pull migrate
# @macro fullDeploy pull migrate clearCache

BRANCH="main"
APP_DIR="/home/forge/myapp"

# @task on:local
pull() {
    git checkout $BRANCH
    git pull origin $BRANCH
}

# @task on:production
migrate() {
    cd $APP_DIR
    php artisan migrate --force
}

# @task on:production
clearCache() {
    cd $APP_DIR
    php artisan cache:clear
    php artisan config:clear
}

# @task on:staging parallel
deployStagingParallel() {
    cd $APP_DIR
    git pull origin $BRANCH
}

# @before
beforeHook() {
    echo "Starting deployment..."
}

# @after
afterHook() {
    echo "Deployment complete!"
}

# @error
errorHook() {
    echo "Something went wrong!"
}
