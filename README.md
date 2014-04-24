Config
==========

> Config - configuration parser - somewhere between ZF1 and ZF2, sections with include ...

## Install

> Using Composer and Packagist

in composer.json file add:
"require": {
    "g4/config": "*"
}

## Usage

```php
$config = new G4\Config\Config();

$data = $config
    ->setCachePath(__DIR__)
    ->setSection('local')
    ->setPath('config.ini')
    ->getData();
```

## Development

### Install dependencies

    $ make install

### Run tests

    $ make test

## License

(The MIT License)
see LICENSE file for details...