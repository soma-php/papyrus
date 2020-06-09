<?php namespace Papyrus\Providers;

use Soma\Store;
use Soma\ServiceProvider;
use Psr\Container\ContainerInterface;

use Soma\Manifest;
use Soma\Repository;

use Papyrus\Content\Filesystem;
use Papyrus\Content\Page;
use Papyrus\Content\Common;
use Papyrus\ContentManager;
use Papyrus\Content\Router;
use Papyrus\Content\Image;
use Papyrus\ShortcodeManager;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\LinkStub;
use Symfony\Component\VarDumper\Caster\CutStub;

class ContentProvider extends ServiceProvider
{
    public function install(ContainerInterface $c) : void
    {
        ensure_dir_exists(get_path('cache.content'));
        ensure_dir_exists(get_path('cache.images'));
        ensure_dir_exists(get_path('cache.resources'));
    }

    public function uninstall(ContainerInterface $c) : void
    {
        runlink(get_path('cache.content'));
        runlink(get_path('cache.images'));
        runlink(get_path('cache.resources'));
    }

    public function ready(ContainerInterface $c) : void
    {
        // if (! is_cli() && config('content.router', false)) {
        //     $content = $c->get('content');
        //     $request = $content->getRequest();
        //     $router = $content->getRouter();

        //     event('content.router.init', $router);
        //     $page = $router->resolve($request);

        //     // If not bool then successfully resolved
        //     if (! is_bool($page)) {
        //         // If we ignore mtime on page files there's an option to still refresh
        //         // the cache on only the currently visited page
        //         $ignoreMTime = config('content.cache.ignore-mtime', false);
        //         $validateVisited = config('content.cache.validate-visited', false);

        //         if ($ignoreMTime && $validateVisited) {
        //             if (! $page->validateCache(false)) {
        //                 $page->compile();
        //             }
        //         }

        //         // Trigger event that PapyrusProvider handles (connecting the routing
        //         // with the theme manager)
        //         event('content.router.found', compact('page', 'request'));
        //     }
        //     // If bool and positive then it has been marked as handled by custom handler
        //     else {
        //         if ($page) {
        //             event('content.router.external', compact('request'));
        //         } else {
        //             event('content.router.unknown', compact('request'));
        //         }
        //     }

        //     event('content.router.resolved', compact('request'));
        // }
    }

    public function getFactories() : array
    {
        return [
            'content' => function(ContainerInterface $c) {
                ContentManager::$baseUrl = $c->get('urls')->get('root');

                // Create manager
                $manager = new ContentManager(
                    $c->get('content.request'),
                    $c->get('content.router'),
                    $c->get('content.filesystem'),
                    $c->get('content.shortcodes'),
                    new Repository($c->get('config')->get('content'))
                );

                return $manager;
            },
            'content.shortcodes' => function(ContainerInterface $c) {
                return new ShortcodeManager;
            },
            'content.request' => function(ContainerInterface $c) {
                return Request::createFromGlobals();
            },
            'content.filesystem' => function(ContainerInterface $c) {
                // Configure URIs
                Page::$rootDir = $c->get('paths')->get('content');
                Page::$rootUrl = $c->get('urls')->get('root');
                Page::$cacheDir = $c->get('paths')->get('cache.content');

                // Set cache mode
                Page::$ignoreMTime = config('content.cache.ignore-mtime', false);

                // Load content filters
                foreach (config('content.filters', []) as $name => $class) {
                    Page::addFilter($name, new $class());
                }

                // Load Page mixins
                foreach (array_merge([Common::class], config('content.mixins', [])) as $class) {
                    Page::mixin(new $class());
                }

                return new Filesystem($c->get('paths')->get('content'));
            },
            'content.router' => function(ContainerInterface $c) {
                $router = new Router($c->get('content.filesystem'));

                if (config('content.feed', true)) {
                    $route = '/'.ltrim(config('config.feed.route', '/feed'), '/');

                    $router->registerHandler($route, function (string $uri, Request $request) {
                        $content = app('content');
                        $themes = app('themes');
                        
                        $type = ltrim('/', $uri) ?: null;
                        $feed = $content->feed($type);

                        $compiler = $themes->getCompiler();
                        $active = $themes->getActiveTheme();
                        $body = $feed->content($compiler, $active);

                        response(200, $body, [
                            'Content-Type' => 'application/rss+xml; charset=UTF-8',
                        ])->send();

                        return true;
                    });
                }

                foreach (config('content.router.handlers', []) as $uri => $handler) {
                    if (is_callable($handler)) {
                        $router->registerHandler($uri, $handler);
                    }
                }

                return $router;
            },
        ];
    }

    public function getExtensions() : array
    {
        return [
            'config' => function(Repository $config, ContainerInterface $c) {
                // Override content config from content/config.yml
                if (file_exists($path = $c->get('paths')->get('content.config'))) {
                    $contentConf = (new Manifest($path, true, true))->all();
                    $contentConf = array_replace_recursive($config->get('content'), $contentConf);
                    $config->set('content', $contentConf);
                }

                // Set timezone if defined in content config
                if ($timezone = $config->get('content.timezone', false)) {
                    date_default_timezone_set($timezone);
                }

                // Set date format if defined in content config
                if (! defined('DATE_FORMAT') && $format = $config->get('content.date-format', false)) {
                    define('DATE_FORMAT', $format);
                }

                // Set custom casters for tinker
                if ($c->get('app')->isCommandLine()) {
                    $casters = $config->get('app.tinker.casters', []);

                    // Content\Page
                    if (! isset($casters['Papyrus\Content\Page'])) {
                        $casters['Papyrus\Content\Page'] = function ($page) {
                            $props = $page->export();

                            foreach (['html', 'raw', 'body', 'bare'] as $prop) {
                                $value = $props[$prop];
                                $props[$prop] = new CutStub($props[$prop]);
                                $props[$prop]->value = substr($value, 0, 64);
                            }

                            $props['url'] = new LinkStub($props['url']);
                            return $props;
                        };

                        $config->set('app.tinker.casters', $casters);
                    }

                    // Content\Menu
                    if (! isset($casters['Papyrus\Content\Menu'])) {
                        $casters['Papyrus\Content\Menu'] = function ($menu) {
                            return [
                                Caster::PREFIX_VIRTUAL.'items' => $menu->items(),
                            ];
                        };

                        $config->set('app.tinker.casters', $casters);
                    }
                }

                return $config;
            },
            'paths' => function(Store $paths, ContainerInterface $c) {
                $paths['content'] = env('APP_CONTENT');
                $paths['content.config'] = $paths['content'].'/config.yml';
                $paths['cache.content'] = $paths['cache'].'/content';
                $paths['cache.images'] = $paths['cache.public'].'/images';
                $paths['cache.resources'] = $paths['cache.public'].'/resources';

                return $paths;
            },
            'urls' => function(Store $urls, ContainerInterface $c) {
                $urls['cache.content'] = $urls['cache'].'/content';
                $urls['cache.images'] = $urls['cache.public'].'/images';
                $urls['cache.resources'] = $urls['cache.public'].'/resources';
                $urls['content.feed'] = $urls['root'].'/feed';

                return $urls;
            },
        ];
    }
}
