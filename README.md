# CodeIgniter MythToShield

MythToShield is tool to migrate from MythAuth to CodeIgniter Shield. The main purpose of this library is to migrate
the database to the Shield structure and keep the existing user data. t's up to developer to ensure that project code
works properly with the Shield library.

## Getting Started

## Prerequisites

Usage of MythToShield requires the following:

- A [CodeIgniter 4.3.5+](https://github.com/codeigniter4/CodeIgniter4/) based project
- [Shield 1.0.1+](https://github.com/codeigniter4/shield/) library
- Converting tested from [MythAuth 1.2.1](https://github.com/lonnieezell/myth-auth/) but should work on older versions as well
- [Composer](https://getcomposer.org/) for package management
- PHP 7.4.3+

## Installation

### Backup you database
Always create a backup of your database first in case anything goes wrong during the migration process.

### Remove MythAuth Library
```console
composer remove myth/auth
```
Remove the Myth auth from Autoload config. Also make sure to remove the MythAuth Config files if you have any
in the APP_NAMESPACE or anywhere else in the project since it will conflict with the Shield library

### Install Shield library
```console
composer require codeigniter4/shield
```
Add the Shield library to $psr4 namespaces in Autoload Config
```php
public $psr4 = [
    APP_NAMESPACE => APPPATH,
    //...
    'CodeIgniter\Shield' => ROOTPATH . 'vendor/codeigniter4/shield/src'
]
```

### Install Settings library
Shield should automatically pull settings library but this might change in future to be sure install the
settings library directly into the project
```console
composer require codeigniter4/settings
```
Add the Settings library to $psr4 namespaces in Autoload Config
```php
public $psr4 = [
    APP_NAMESPACE => APPPATH,
    //...
    'CodeIgniter\Settings' => ROOTPATH . 'vendor/codeigniter4/settings/src'
]
```

### Install MythToShield library
```console
composer require deployed/myth-to-shield
```
Add the MythToShield library to $psr4 namespaces in Autoload Config
```php
public $psr4 = [
    APP_NAMESPACE => APPPATH,
    //...
    'Deployed\MythToShield' => ROOTPATH . 'vendor/deployed/myth-to-shield/src'
]
```

### Configure Shield
Manually create shield config file or registrar if you want to override default table names from shield.
`php spark shield:setup` and shield migrations should not be executed. The MythToShield migrator will create
the tables. The settings library migrations can be executed, but it is not required since the MythToShield
migrator will take care of those as well.

### Additional Commands - This step is optional
MythToShield library provides MythToShield config class which can be used to execute
additional commands. See the [config file](src/Config/MythToShield.php) for more information

### 6. Run the migration command.
```
php spark mythToShield:migrate
```
It should return `MythToShield migration completed successfully` message

## Compatibility Mode
Myth Auth uses different method to store and verify the password hashes. In order for Shield to work with
existing password additional column named `myth_hash` is added to the identities table. The initial value
in this column is set to `1` for every user account.

To process the Myth Auth hashes [MythCompatSession](src/Authenticators/MythCompatSession.php) authenticator
class is loaded to replace the shield session authenticator via registrar. When user tries to logg in for the
first time via shield myth_hash value is checked if it contains `1` the user password is verified using the
Myth Auth method. On first successful login new password hash is generated and `0` is set as the myth_hash value.
When myth_hash is 0 MythCompatSession authenticator uses the more secure Shield hashing method and does not
allow users to log in with Myth Auth password hashes.

## Uninstalling the library
MythToShield library should be kept installed as long as Myth Auth password hash compatibility is needed.
When the compatibility is not needed anymore the myth_hash column should be removed from the identities table first.

There is a command for removing the database column
```console
php spark   mythToShield:cleanup
```
Once the database is cleaned the MythToShield library can also be deleted.

Remove the library from the Autoload Config
```
'Deployed\MythToShield' => ROOTPATH . 'vendor/deployed/myth-to-shield/src'
```
Remove the composer package
```console
composer remove deployed/myth-to-shield
```

## Contributing

MythToShield does accept and encourage contributions from the community in any shape. It doesn't matter
whether you can code, write documentation, or help find bugs, all contributions are welcome.
See the [CONTRIBUTING.md](CONTRIBUTING.md) file for details.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
