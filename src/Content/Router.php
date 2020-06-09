<?php namespace Papyrus\Content;

use Papyrus\Content\Filesystem;

use InvalidArgumentException;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;

class Router
{
    protected $handlers = [];
    protected $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function normalizeHandle($uri)
    {
        $uri = (! Str::startsWith($uri, '/')) ? '/'.$uri : $uri;
        $uri = urldecode(remove_double_slashes($uri));
        $uri = rtrim($uri, '/');

        return $uri;
    }

    public function registerHandler(string $uri, callable $handler)
    {
        $this->handlers[$this->normalizeHandle($uri)] = $handler;
    }

    protected function parseRequest($request)
    {
        // Parse request
        if ($request instanceof Request) {
            $uri = $request->getPathInfo();
            $preview = make_bool($request->query->get('preview', false));
        }
        elseif (is_string($request)) {
            $info = parse_url($request);
            $uri = $info['path'] ?: '/';
            parse_str($info['query'] ?? '', $query);

            $preview = make_bool($query['preview'] ?? false);
        }
        else {
            throw new InvalidArgumentException();
        }

        return compact('uri', 'preview');
    }

    public function resolve($request, $drafts = null)
    {
        // Parse request (sets $uri and $preview)
        extract($this->parseRequest($request));

        if (is_null($drafts)) {
            $drafts = (config('content.drafts', false)) ? $preview : false;
        }

        // Check custom route handlers
        $handlerUri = $this->normalizeHandle($uri);
        
        foreach ($this->handlers as $handle => $handler) {
            if (Str::startsWith($handlerUri, $handle)) {
                $handlerUri = '/'.rel_path($handlerUri, $handle);

                return call_user_func_array($handler, [$handlerUri, $request]);
            }
        }

        // Search routes
        if ($page = $this->files->get($uri)) {
            return $page;
        } else {
            return false;
        }
    }

    public function drafts()
    {
        return $this->files->drafts()
            // We only care about the routes themselves
            ->keys()
            // Sort the list
            ->sort()->values()->all();
    }

    public function all(bool $drafts = false)
    {
        return $this->files->pages($drafts)
            // We only care about the routes themselves
            ->keys()
            // Append the handler routes and ellipsize them to show that they may have "more" routes
            ->concat(array_map(function ($key) {
                return $key.'/...';
            }, array_keys($this->handlers)))
            // Sort the list
            ->sort()->values()->all();
    }
}