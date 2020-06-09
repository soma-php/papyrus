<?php namespace Papyrus\Providers;

use Soma\ServiceProvider;
use Psr\Container\ContainerInterface;

use Soma\Store;
use Soma\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use Papyrus\Papyrus;
use Papyrus\ThemeManager;
use Papyrus\Content\Image;
use Intervention\Image\ImageManagerStatic as ImageManager;

class PapyrusProvider extends ServiceProvider
{
    public function getProviders() : array
    {
        return [
            \Soma\Providers\EventsProvider::class,
            \Papyrus\Providers\ContentProvider::class,
            \Papyrus\Providers\ThemesProvider::class,
        ];
    }

    public function install(ContainerInterface $c) : void
    {
        ensure_dir_exists(get_path('cache.pages'));
    }

    public function uninstall(ContainerInterface $c) : void
    {
        runlink(get_path('cache.pages'));
    }

    public function ready(ContainerInterface $c) : void
    {
        if (! is_cli() && config('content.router', false)) {
            $response = $c->get('papyrus')->processRequest();

            if (! is_null($response)) {
                $response->send();
            }
        }
    }

    public function getFactories() : array
    {
        return [
            'papyrus' => function(ContainerInterface $c) {
                $content = $c->get('content');
                $themes = $c->get('themes');
                $config = new Repository($c->get('config')->get('content.cache', []));

                Papyrus::$cacheDir = $c->get('paths')->get('cache.pages');
                
                return new Papyrus($content, $themes, $config); 
            },
        ];
    }

    public function getExtensions() : array
    {
        return [
            'themes' => function(ThemeManager $themes, ContainerInterface $c) {
                // Set image sizes
                $sizes = $themes->getActiveTheme()->getMeta('images.sizes') ?: null;
                $sizes = $sizes ?? config('themes.images.sizes') ?: [];

                Image::$defaultSizes = $sizes;

                // Set image rules
                $rules = $themes->getActiveTheme()->getMeta('images.rules') ?: null;
                $rules = $rules ?? config('themes.images.rules') ?: [];

                Image::$defaultRules = $rules;

                // Set image filters
                $filters = $themes->getActiveTheme()->getMeta('images.filters') ?: [];
                $filters = array_merge($filters, config('themes.images.filters') ?: []);

                foreach ($filters as $key => $filter) {
                    if (is_string($key)) {
                        Image::addFilter($key, $filter);
                    }
                    else {
                        Image::addFilter($filter);
                    }
                }

                // Set image processing
                Image::$process = config('content.images', true);
                Image::$quality = config('content.images.quality', 90);
                Image::$cacheDir = $c->get('paths')->get('cache.images');
                Image::$cacheUrl = $c->get('urls')->get('cache.images');

                ImageManager::configure(['driver' => config('content.images.driver', 'gd')]);

                return $themes;
            },
            'paths' => function(Store $paths, ContainerInterface $c) {
                $paths['cache.pages'] = $paths['cache'].'/pages';

                return $paths;
            },
        ];
    }
}
