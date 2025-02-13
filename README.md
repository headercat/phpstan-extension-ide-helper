# phpstan-extension-ide-helper

PHPStan extension IDE helper, provides dummy PHPStan namespace classes and functions.

PHPStan is distributed via PHAR file rather than pure PHP files.
While this is perfectly adequate for using PHPStan, it makes writing PHPStan's extensions a pain.
Most IDEs and autocompletion tools cannot reference the code inside PHAR packages.

## Installation
```php
composer require --dev headercat/phpstan-extension-ide-helper
```

Or you can manually add into `composer.json`.

```json
{
  "require-dev": {
    "phpstan/phpstan": "^2.0.0-or-any-version-you-want",
    "headercat/phpstan-extension-ide-helper": "*"
  }
}
```

## Contributing
If PHPStan release a new version, please write a comment to [Issue #1](https://github.com/headercat/phpstan-extension-ide-helper/issues/1).

## How it works?
1. Clone `phpstan/phpstan-src` repository to `/phpstan`.
2. Scan all PHP files from `/phpstan`.
3. Add `return;` after namespace declaration to all scanned files from step 2.
4. Write them to a new directory `/main`.
5. Find composer dependencies that starts with `phpstan/` from `/phpstan/composer.json`.
6. Add them to `/main/composer.json`.
7. Done!

## License
Licensed under the MIT license.
