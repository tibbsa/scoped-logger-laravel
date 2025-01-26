# Fine-grained logging level management for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tibbsa/scoped-logger-laravel.svg?style=flat-square)](https://packagist.org/packages/tibbsa/scoped-logger-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/tibbsa/scoped-logger-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/tibbsa/scoped-logger-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/tibbsa/scoped-logger-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/tibbsa/scoped-logger-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/tibbsa/scoped-logger-laravel.svg?style=flat-square)](https://packagist.org/packages/tibbsa/scoped-logger-laravel)

When troubleshooting specific issues in a Laravel application, it is often helpful to have increased logging 
visibility regarding a specific portion of your application. However, if you increase your log level, then 
you will also increase the log traffic from all _other_ parts of the application at the same time. While 
logging channels off some flexibility in separating different types of log entries, they are cumbersome and
must all be pre-configured before they can be used.

This package adds the ability to define different logging 'levels' (and other log/no log rules) based on the
"scope" of a particular log entry, which can either be developer-defined at the time of the log entry, or 
optionally auto-determined based on the class from which the log entry is being made. 
 
## Installation

You can install the package via composer:

```bash
composer require tibbsa/scoped-logger-laravel
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="scoped-logger-config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$scopedLogger = new TibbsA\ScopedLogger();
echo $scopedLogger->echoPhrase('Hello, TibbsA!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities


## Credits

- [Anthony Tibbs](https://github.com/tibbsa)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
