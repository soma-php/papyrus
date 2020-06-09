<?php

namespace Papyrus;

use Soma\Repository;
use Papyrus\ThemeManager;
use Papyrus\ContentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Papyrus
{
    protected $content;
    protected $request;
    protected $router;
    protected $themes;
    protected $cache;

    protected $_cacheDir;
    public static $cacheDir;

    public function __construct(ContentManager $content, ThemeManager $themes, Repository $cache)
    {
        $this->content = $content;
        $this->request = $content->getRequest();
        $this->router = $content->getRouter();
        $this->themes = $themes;
        $this->cache = $cache;
        $this->_cacheDir = $this->cache->get('path', self::$cacheDir);
    }

    public function processRequest(?Request $request = null) : ?Response
    {
        if (is_null($request)) {
            $request = $this->request;
        }

        event('papyrus.router.init', $this->router);
            
        $uri = $request->getRequestUri();
        $method = $request->getMethod();
        $cacheTime = $this->cache->get('time', false);
        $cachePath = $this->_cacheDir.'/'.md5($uri).'.php';
        $ignoreMTime = $this->cache->get('ignore-mtime', false);
        $validateVisited = $this->cache->get('validate-visited', false);
        
        if (! should_optimize()) {
            $cacheGateway = false;
            $caching = false;
            $cacheValid = false;
        }
        else {
            $cacheGateway = $this->cache->get('gateway', false);
            $caching = (! $cacheGateway && $method == 'GET') ? $this->shouldCache($uri) : false;
            $cacheValid = ($caching) ? $this->validateCache($cachePath, $cacheTime) : false;
        }
        
        if ($caching && $cacheValid) {
            // First line is the HTTP code
            $content = file_get_contents($cachePath);
            $code = intval(strtok($content, "\n"));
            $content = substr($content, strpos($content, "\n") + 1);

            $response = response($code, $content, ['content-type' => 'text/html']);
        }
        else {
            $result = $this->router->resolve($request);

            // If $result is bool then it was either not found or externally handled
            if (is_bool($result)) {
                if ($result) {
                    event('papyrus.router.external', compact('request'));
                    $response = null;
                } else {
                    event('papyrus.router.unknown', compact('request'));
                    $response = $this->handle404();
                }
            }
            else {
                $page = $result;

                // If we ignore mtime on page files there's an option to still refresh
                // the cache on only the currently visited page
                if (should_optimize()) {
                    $ignoreMTime = $this->cache->get('ignore-mtime', false);
                    $validateVisited = $this->cache->get('validate-visited', false);

                    if ($ignoreMTime && $validateVisited) {
                        if (! $page->validateCache(false)) {
                            $page->compile();
                        }
                    }
                }

                $template = $page->template ?? 'page';
                $data = compact('page', 'request');

                event('papyrus.router.found', $data);
                $response = $this->respondWithTemplate(200, $template, $data);
            }
        }

        if (! is_null($response)) {
            if (in_array($response->getStatusCode(), [200, 203, 300, 301, 302, 404, 410])) {
                if ($cacheGateway) {
                    $response->setSharedMaxAge($cacheTime);
                }
                elseif ($caching && ! $cacheValid) {
                    $content = $response->getStatusCode().PHP_EOL.$response->getContent();

                    if (! touch($cachePath)) {
                        throw new ErrorException('Failed to create file: '.$cachePath);
                    }
                    if (! file_put_contents($cachePath, $content)) {
                        throw new ErrorException('Failed to write to file: '.$cachePath);
                    }

                    event('papyrus.router.cached', compact('request', 'response'));
                }                    
            }
        }

        event('papyrus.router.resolved', compact('request', 'response'));

        return $response;
    }


    protected function shouldCache($uri)
    {
        $uri = ltrim($uri, '/');
        $excludes = Arr::wrap($this->cache->get('exclude', []));
        $includes = Arr::wrap($this->cache->get('include', []));

        foreach ($includes as $include) {
            if (! Str::startsWith($uri, ltrim($include, '/'))) {
                return false;
            }
        }

        foreach ($excludes as $exclude) {
            if (Str::startsWith($uri, ltrim($exclude, '/'))) {
                return false;
            }
        }

        return $this->cache->get('full-page', false);
    }

    protected function handle404($request)
    {
        // Check if 404.md file exists
        if ($page = app('content')->get(config('content.404', '/404.md'))) {
            return $this->respondWithTemplate(404, $page->template ?? 'page', compact('page', 'request'));
        }
        else {
            // Check if a 404 template exists in the theme
            if (app('themes')->locate('404')) {
                return $this->respondWithTemplate(404, '404', compact('request'));
            }
            else {
                return response(404, 'Not Found', ['content-type' => 'text/plain']);
            }
        }
    }

    protected function validateCache($path, $ttl)
    {
        if (file_exists($path)) {
            $mtime = filemtime($path);

            if ($ttl === false || time() < ($mtime + $ttl)) {
                return true;
            }
        }

        return false;
    }

    protected function respondWithTemplate($code, $template, $data)
    {
        // Verify template exists
        if (! $this->themes->locate($template)) {
            return response(501, 'Not Implemented', ['content-type' => 'text/plain']);
        }

        // Render template
        if ($content = $this->themes->render($template, $data)) {
            return response($code, $content, ['content-type' => 'text/html']);
        } else {
            return response(500, 'Internal Server Error', ['content-type' => 'text/plain']);
        }
    }
}