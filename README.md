# Scotty: a beautiful SSH task runner

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/scotty/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/scotty/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)

Scotty is a tool that runs tasks on your remote servers over SSH. You write your tasks in a `Scotty.sh` file (plain bash with annotation comments), and Scotty takes care of connecting, running each script, and showing you exactly what's happening. It's fully compatible with [Laravel Envoy](https://laravel.com/docs/envoy), so you can use it as a drop-in replacement.

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.jpg?raw=true)

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/scotty.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/scotty)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Documentation

You'll find full documentation on [https://spatie.be/docs/scotty](https://spatie.be/docs/scotty).

## Quick start

Install Scotty globally via Composer:

```bash
composer global require spatie/scotty
```

Create a `Scotty.sh` file in your project root:

```bash
#!/usr/bin/env scotty

# @servers remote=deployer@your-server.com
# @macro deploy pullCode clearCache

# @task on:remote
pullCode() {
    cd /var/www/my-app
    git pull origin main
}

# @task on:remote
clearCache() {
    cd /var/www/my-app
    php artisan cache:clear
}
```

Run it:

```bash
scotty run deploy
```

Already using [Laravel Envoy](https://laravel.com/docs/envoy)? Scotty reads `Envoy.blade.php` files too. Just run `scotty run deploy` in the same directory.

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
