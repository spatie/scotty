<div align="left">
    <a href="https://spatie.be/open-source?utm_source=github&utm_medium=banner&utm_campaign=scotty">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://spatie.be/packages/header/scotty/html/dark.webp?1776851353">
        <img alt="Logo for scotty" src="https://spatie.be/packages/header/scotty/html/light.webp?1776851353">
      </picture>
    </a>

<h1>Scotty: a beautiful SSH task runner</h1>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/scotty/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/scotty/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/scotty.svg?style=flat-square)](https://packagist.org/packages/spatie/scotty)

</div>

Scotty is a tool that runs tasks on your remote servers over SSH. You write your tasks in a `Scotty.sh` file (plain bash with annotation comments), and Scotty takes care of connecting, running each script, and showing you exactly what's happening. It's fully compatible with [Laravel Envoy](https://laravel.com/docs/envoy), so you can use it as a drop-in replacement.

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.jpg?raw=true)

Here's what a `Scotty.sh` file looks like:

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

Run it with `scotty run deploy`.

## Installation

Scotty ships as a single-file phar. The recommended way to install it per project is:

```bash
curl -L https://github.com/spatie/scotty/releases/latest/download/scotty -o scotty
chmod +x scotty
./scotty list
```

To install it globally, drop the phar somewhere on your `$PATH`:

```bash
curl -L https://github.com/spatie/scotty/releases/latest/download/scotty -o /usr/local/bin/scotty
chmod +x /usr/local/bin/scotty
```

You can also install it globally with Composer:

```bash
composer global require spatie/scotty
```

See the [installation docs](https://spatie.be/docs/scotty/v1/installation-setup) for details and other options.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/scotty.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/scotty)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Documentation

All documentation is available [on our documentation site](https://spatie.be/docs/scotty).

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
