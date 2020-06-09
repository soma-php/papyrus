# Getting started

## Server requirements

Papyrus has a few system requirements and you will need to make sure your server meets the following:

- PHP >= 7.2.5
- JSON PHP Extension
- Mbstring PHP Extension
- GD PHP Extension (or alternatively Imagick)
- XML PHP Extension

## Installation

*Papyrus requires composer for dependency management*

Create a new project by executing the following command in a terminal:

```sh
composer create-project soma/papyrus-project [project-directory]
```

The required paths need to be created by the framework before you can run Papyrus. You can do so by running the `app:install` command after you've configured your `.env`.

```sh
php appctrl app:install
```

## Integrate into an existing project

One can also add the library to an already existing SOMA project and simply register the "meta" provider that registers all services Papyrus is composed of:

```sh
composer require soma/papyrus
```

```php
return [
    // ...
    'providers' => [
        \Papyrus\Providers\PapyrusProvider::class,
    ],
    'aliases' => [
        'Content' => \Papyrus\Facades\Content::class,
        'Themes' => \Papyrus\Facades\Themes::class,
    ],
    'commands' => [
        \Soma\Commands\AppInstall::class,
        \Soma\Commands\AppUninstall::class,
        \Soma\Commands\AppRefresh::class,
        \Soma\Commands\AppTinker::class,
        \Soma\Commands\AppServe::class,
        \Soma\Commands\AppClearCache::class,
        \Papyrus\Commands\ContentRoutes::class,
        \Papyrus\Commands\ContentCompile::class,
    ],
    // ...
];
```

## Helpers

The file [helpers.php](https://github.com/soma-php/papyrus/blob/master/src/helpers.php) contain a couple of functions that are meant to simplify either calling app services or work with certain types of data. Please also refer to the [helpers provided by Soma](https://soma-php.github.io/).