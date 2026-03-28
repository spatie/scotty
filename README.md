# Scotty: a beautiful SSH task runner

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/scotty/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/scotty/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)

Scotty beams your deployment scripts to remote servers. It's a fresh take on [Laravel Envoy](https://laravel.com/docs/envoy), built from the ground up with better output, a new bash file format, and pause/resume support.

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)
<!-- SCREENSHOT NEEDED: Full `scotty run deploy` output showing a real deploy with multiple tasks, spinner, output, timing, and summary table -->

## What Scotty adds over Envoy

- **A pure bash file format** with full syntax highlighting and editor support (no more Blade for shell scripts)
- **Real time output** with a spinner, elapsed timer, and command tracing showing which line is executing
- **Per task timing** and a summary table when the deploy finishes
- **Pause and resume** by pressing `p` during a deploy
- **`scotty doctor`** to validate your file, SSH connectivity, and remote tools before deploying
- **Envoy compatibility** so you can drop Scotty into any project that already has an `Envoy.blade.php`

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/scotty.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/scotty)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

```bash
composer global require spatie/scotty
```

## Quick start

Create a `Scotty.sh` file in your project root:

```bash
#!/usr/bin/env scotty

# @servers remote=forge@your-server.com
# @macro deploy pullCode clearCache

# @task on:remote
pullCode() {
    cd /home/forge/my-app
    git pull origin main
}

# @task on:remote
clearCache() {
    cd /home/forge/my-app
    php artisan cache:clear
}
```

Run it:

```bash
scotty run deploy
```

Every line is real bash. No templating, no special syntax, full editor support.

Already using [Laravel Envoy](https://laravel.com/docs/envoy)? Scotty reads `Envoy.blade.php` files too. Just run `scotty run deploy` in the same directory.

## Validate your setup

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.png?raw=true)
<!-- SCREENSHOT NEEDED: `scotty doctor` output showing all checks passing on a real server -->

## Documentation

Full documentation is available at [spatie.be/docs/scotty](https://spatie.be/docs/scotty).

**Basic usage**
- [The Blade format](docs/basic-usage/blade-format.md) (servers, tasks, stories, hooks, variables, setup)
- [The Scotty.sh format](docs/basic-usage/bash-format.md) (the new bash format, migration guide)
- [Running tasks](docs/basic-usage/running-tasks.md) (pretend, continue, summary, dynamic options)

**Advanced**
- [Pause and resume](docs/advanced-usage/pause-and-resume.md)
- [Doctor](docs/advanced-usage/doctor.md) (validate servers, connectivity, remote tools)
- [File lookup order](docs/advanced-usage/file-lookup-order.md)

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
