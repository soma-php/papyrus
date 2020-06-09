<?php namespace Papyrus;

use DateTime;
use Papyrus\Content\Menu;
use Papyrus\Content\Feed;
use Papyrus\Content\Page;
use Papyrus\Content\Search;
use Papyrus\Content\Router;
use Papyrus\Content\Filesystem;
use Papyrus\Content\Pagination;
use Papyrus\Content\Filter;

use Soma\Manifest;
use Soma\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;
use Maiorano\Shortcodes\Library\SimpleShortcode;
use Maiorano\Shortcodes\Manager\ShortcodeManager;

class ContentManager
{
    protected $menus;

    protected $request;
    protected $router;
    protected $files;
    protected $shortcodes;
    protected $config;

    protected $_baseUrl;
    public static $baseUrl;

    public function __construct(Request $request, Router $router, Filesystem $files, ShortcodeManager $shortcodes, Repository $config)
    {
        $this->request = $request;
        $this->router = $router;
        $this->files = $files;
        $this->shortcodes = $shortcodes;
        $this->config = $config;

        $this->_baseUrl = $config->get('url', self::$baseUrl);
    }

    public function getRequest() : Request
    {
        return $this->request;
    }

    public function getRouter() : Router
    {
        return $this->router;
    }

    public function getFilesystem() : Filesystem
    {
        return $this->files;
    }

    public function getFilters() : array
    {
        return Page::$filters;
    }

    public function addFilter($name, Filter $filter) : ContentManager
    {
        Page::$filters[$name] = $filter;

        return $this;
    }

    public function removeFilter($name) : ContentManager
    {
        if (isset(Page::$filters[$name])) {
            unset(Page::$filters[$name]);
        }

        return $this;
    }

    public function getShortcodeManager() : ShortcodeManager
    {
        return $this->shortcodes;
    }

    public function getShortcodes(): array
    {
        return $this->shortcodes->getRegistered();
    }

    public function addShortcode(SimpleShortcode $shortcode): ContentManager
    {
        $this->shortcodes->register($shortcode);

        return $this;
    }

    public function removeShortcode(string $name): ContentManager
    {
        $this->shortcodes->deregister($name);

        return $this;
    }

    public function addShortcodeAlias(string $name, string $alias): ContentManager
    {
        $this->shortcodes->alias($name, $alias);

        return $this;
    }

    public function get(string $id, bool $drafts = false, bool $draftsOnly = false): ?Page
    {
        return $this->files->get($id, $drafts, $draftsOnly);
    }

    public function search(string $terms, $pages = null): Search
    {
        if (is_null($pages)) {
            $pages = $this->files->pages();
        }
        elseif (! $pages instanceof Collecton) {
            $pages = new Collection($pages);
        }

        $excludes = $this->config->get('search.exclude', []);
        $low_value = $this->config->get('search.low_value', []);

        return new Search($pages, $terms, $excludes, $low_value);
    }

    public function paginate($pages = null, $limit = null, $page = null): Pagination
    {
        if (is_null($pages)) {
            $pages = $this->files->pages();
        }
        elseif (! $pages instanceof Collecton) {
            $pages = new Collection($pages);
        }

        $limit = $limit ?? $this->config->get('pagination', 10);
        $page = $page ?? $this->request->query->get('page', 1);

        return new Pagination($pages, $limit, $page);
    }

    public function all(bool $drafts = false, $draftsOnly = false) : Collection
    {
        return $this->files->pages($drafts, $draftsOnly);
    }

    public function menu(string $name): Menu
    {
        // First we load the combined menus file if there is one
        if (is_null($this->menus)) {
            if ($path = $this->files->getPath('menus.yml')) {
                $this->menus = new Manifest($path, true, true, null, function($data) {
                    return array_map(function ($definition) {
                        return new Menu($definition, $this->files);
                    }, $data);
                });
            }
        }

        // If it didn't exist or if the menu we're looking is still not
        // defined then we check for a menu definition named accordingly
        if (is_null($this->menus) || ! isset($this->menus[$name])) {
            $uri = '/'.ltrim(str_replace('.', '/', $name), '/');
            
            if ($path = $this->files->getPath($uri.'.yml')) {
                $this->menus[$name] = new Manifest($path, true, true, null, function($definition) {
                    return new Menu($definition, $this->files);
                });
            }
        }

        return $this->menus[$name] ?? $this->menus[$name] = new Menu([], $this->files);
    }

    public function feed(?string $type = null): Feed
    {
        $pages = $this->files->pages();

        // Filter on feed type (URI sub-resource)
        if ($type) {
            $pages->filter(function ($page, $key) use (&$type) {
                if (strtolower($page->feed) == strtolower($type)) {
                    // Get correct capitalization since we treat the URL as case-insensitive
                    $type = $page->feed; 
                    return true;
                }
                else {
                    return false;
                }
            });
        }

        // Filter on includes
        if ($include = Arr::wrap($this->config->get('feed.include', []))) {
            $pages->filter(function ($page, $key) use ($include) {
                foreach ($include as $uri) {
                    return (! Str::startsWith($page->relativePath, $uri)) ? false : true;
                }
            });
        }

        // Filter on includes
        if ($exclude = Arr::wrap($this->config->get('feed.exclude', []))) {
            $pages->filter(function ($page, $key) use ($exclude) {
                foreach ($exclude as $uri) {
                    return (Str::startsWith($page->relativePath, $uri)) ? false : true;
                }
            });
        }

        // Filter on custom
        if (is_callable($filter = $this->config->get('feed.filter', null))) {
            $pages = $pages->filter($filter);
        }

        // Sort pages
        if ($sort = $this->config->get('feed.sort', 'published')) {
            $pages = $pages->sortBy($sort, $this->config->get('feed.order', 'asc'));
        }

        // Create feed from result
        $name = ($this->config->get('title') ?? 'Feed').(($type) ? ' - '.$type : '');
        $description = $this->config->get('description');
        $url = $this->feedUrl($type);

        $feed = new Feed($pages, $name, $description, $url);
        $feed->setLanguage($this->config->get('language', null));
        $feed->setCategory($this->config->get('category', null));
        $feed->setCopyright($this->config->get('copyright', null));

        if ($image = image($this->config->get('feed.image', null))) {
            $feed->setImage($image->src, $name, $this->_baseUrl);
        }
        
        return $feed;
    }

    public function feedUrl(?string $type = null): string
    {
        return $this->_baseUrl.'/'.ltrim($this->config->get('feed.route', '/feed'), '/').(($type) ? '/'.strtolower($type) : '');
    }
}