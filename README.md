# Scotty: a beautiful SSH task runner

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/scotty/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/scotty/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)

Scotty beams your deployment scripts to remote servers. It's a fresh take on [Laravel Envoy](https://github.com/laravel/envoy), built from the ground up with better output, a new bash file format, and pause/resume support.

Scotty can read existing `Envoy.blade.php` files, so you can switch over without changing anything. When you're ready, you can migrate to the new `Scotty.sh` format which is pure bash with no templating layer.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/scotty.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/scotty)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

```bash
composer global require spatie/scotty
```

## Usage

Scotty reads a `Scotty.sh` (preferred) or `Envoy.blade.php` file from your project root.

### The Scotty.sh format

Define your servers, macros, and tasks in plain bash:

```bash
#!/usr/bin/env scotty

# @servers local=127.0.0.1 remote=forge@your-server.com
# @macro deploy startDeployment cloneRepository runComposer blessNewRelease

BRANCH="main"
REPOSITORY="your/repo"
BASE_DIR="/home/forge/your-app"
RELEASES_DIR="$BASE_DIR/releases"
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
    composer install --prefer-dist --no-dev -q -o
}

# @task on:remote
blessNewRelease() {
    ln -nfs $NEW_RELEASE_DIR $BASE_DIR/current
    sudo service php8.4-fpm restart
}
```

Every line is real bash. No templating, no special syntax, full editor support.

### Running tasks

```bash
scotty run deploy
scotty run deploy-code
scotty run cloneRepository
```

### Listing available tasks

```bash
scotty tasks
```

### SSH into a server

```bash
scotty ssh
scotty ssh remote
```

### Pretend mode

See the SSH commands that would be executed without actually running them:

```bash
scotty run deploy --pretend
```

### Dynamic options

Pass custom variables via the command line:

```bash
scotty run deploy --branch=develop
```

In the bash format, this becomes available as `$BRANCH`. In Blade format, as `$branch`.

### Multi-server deployments

Tasks can target multiple servers, sequentially or in parallel:

```bash
# @servers web-1=forge@1.1.1.1 web-2=forge@2.2.2.2

# Sequential (completes on web-1, then web-2)
# @task on:web-1,web-2
deploy() {
    cd /home/forge/app && git pull
}

# Parallel (runs on both at the same time)
# @task on:web-1,web-2 parallel
restartWorkers() {
    sudo supervisorctl restart all
}
```

### Lifecycle hooks

```bash
# @before
beforeEachTask() {
    echo "Starting..."
}

# @after
afterEachTask() {
    echo "Done."
}

# @error
onError() {
    curl -X POST https://hooks.slack.com/services/xxx \
        -d '{"text": "Deploy failed!"}'
}
```

### Pause and resume

Press `p` during execution to pause after the current task completes. Press `Enter` to resume. Press `Ctrl+C` to cancel.

### Envoy compatibility

Scotty reads `Envoy.blade.php` files out of the box. If both `Scotty.sh` and `Envoy.blade.php` exist, Scotty prefers the `.sh` file.

## Acknowledgements

Scotty is built on the ideas and architecture of [Laravel Envoy](https://github.com/laravel/envoy) by Taylor Otwell. The Blade file parser includes code ported from Envoy's compiler. We're grateful for the foundation Envoy provided.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
