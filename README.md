# MyAdmin cPanel Webhosting Plugin

[![Tests](https://github.com/detain/myadmin-cpanel-webhosting/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-cpanel-webhosting/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-cpanel-webhosting/version)](https://packagist.org/packages/detain/myadmin-cpanel-webhosting)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-cpanel-webhosting/downloads)](https://packagist.org/packages/detain/myadmin-cpanel-webhosting)
[![License](https://poser.pugx.org/detain/myadmin-cpanel-webhosting/license)](https://packagist.org/packages/detain/myadmin-cpanel-webhosting)

A MyAdmin plugin that provides full cPanel and WHM integration for webhosting management. This package handles account provisioning, suspension, reactivation, termination, DNS zone management, reseller configuration, package management, and SSL certificate operations through the cPanel XML-API.

## Features

- **Account Management** - Create, suspend, unsuspend, and terminate cPanel hosting accounts
- **Reseller Support** - Full reseller account setup with ACL configuration and account limits
- **DNS Management** - Add, edit, and remove DNS zones and records
- **Package Management** - Create, edit, delete, and list hosting packages
- **SSL Certificate Management** - Generate, install, and list SSL certificates
- **Auto-Login** - API endpoint for automatic cPanel login via session tokens
- **XML-API Client** - Complete PHP client for the cPanel/WHM XML-API with curl and fopen support
- **Event-Driven Architecture** - Integrates with Symfony EventDispatcher for modular hook-based operation

## Installation

Install via Composer:

```sh
composer require detain/myadmin-cpanel-webhosting
```

## Usage

### Plugin Registration

The plugin registers event hooks automatically through the `getHooks()` method:

```php
use Detain\MyAdminCpanel\Plugin;

$hooks = Plugin::getHooks();
// Returns hook mappings for: settings, activate, reactivate,
// deactivate, terminate, api.register, function.requirements, ui.menu
```

### XML-API Client

The included `xmlapi` class provides direct access to the cPanel/WHM XML-API:

```php
$whm = new xmlapi('your-server-ip');
$whm->set_port('2087');
$whm->set_protocol('https');
$whm->set_output('json');
$whm->hash_auth('root', $accessHash);

// List accounts
$accounts = $whm->listaccts();

// Create an account
$whm->createacct([
    'username' => 'newuser',
    'password' => 'securepass',
    'domain'   => 'example.com',
]);
```

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

To generate a coverage report:

```sh
vendor/bin/phpunit --coverage-text
```

## License

This package is licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.en.html) license.
