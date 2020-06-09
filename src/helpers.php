<?php

if (! function_exists('response')) {
    /**
     * Simply create an HTTP response
     *
     * @param integer [$code]
     * @param string [$content]
     * @param array [$headers]
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function response(int $code = 200, string $content = '', array $headers = []) : \Symfony\Component\HttpFoundation\Response
    {
        $headers['content-type'] = $headers['content-type'] ?? 'text/plain';
        $content = (is_string($content)) ? $content : json_encode($content);

        return new \Symfony\Component\HttpFoundation\Response($content, $code, $headers);
    }
}

if (! function_exists('request')) {
    /**
     * Return the current request object
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    function request() : \Symfony\Component\HttpFoundation\Request
    {
        return app('content.request');
    }
}

if (! function_exists('current_url')) {
    /**
     * Alias for request_url
     *
     * @return string
     */
    function current_url() : string
    {
        return request_url();
    }
}

if (! function_exists('request_url')) {
    /**
     * Return the current request's base URL and request URI
     *
     * @return string
     */
    function request_url() : string
    {
        $request = app('content.request');

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getRequestUri();
    }
}

if (! function_exists('request_vars')) {
    /**
     * Return the value of an HTTP parameter sent using any method
     *
     * @param string $key
     * @param mixed [$default]
     * @return mixed
     */
    function request_vars(string $key, $default = null)
    {
        return app('content.request')->get($key, $default);
    }
}

if (! function_exists('request_body')) {
    /**
     * Return the request body content or a resource to read the body stream
     *
     * @return string|resource
     */
    function request_body()
    {
        return app('content.request')->getContent();
    }
}

if (! function_exists('request_uri')) {
    /**
     * Get the current request's URI
     *
     * @return string
     */
    function request_uri()
    {
        return app('content.request')->getRequestUri();
    }
}

if (! function_exists('request_scheme')) {
    /**
     * Get the current request's scheme
     *
     * @return string
     */
    function request_scheme()
    {
        return app('content.request')->getScheme();
    }
}

if (! function_exists('request_host')) {
    /**
     * Get the current request's host
     *
     * @return string
     */
    function request_host()
    {
        return app('content.request')->getHttpHost();
    }
}

if (! function_exists('image')) {
    /**
     * Create a \Papyrus\Content\Image instance from a filesystem resource
     *
     * Both remote and local files will be created as an Image instance
     * and local files will first be queried from within the theme and if not found
     * then from the content directory.
     * 
     * @param string|SplFileInfo $path The filesystem location of the resource
     * @param array [$attr] Optional override of image attributes
     * @return \Papyrus\Content\Image|null
     */
    function image($path, array $attr = []) : ?\Papyrus\Content\Image
    {
        if ($path instanceof \Papyrus\Content\Image) {
            return $path;
        }
        if ($path instanceof \SplFileInfo) {
            $path = $path->getPathname();
        }

        try {
            if (is_string($path)) {
                if (is_url($path)) {
                    return new \Papyrus\Content\Image($path, $attr);
                }
                if (file_exists($themePath = theme_path($path))) {
                    return new \Papyrus\Content\Image($themePath, $attr);
                }
                if (file_exists($contentPath = content_path($path))) {
                    return new \Papyrus\Content\Image($contentPath, $attr);
                }
            }
        }
        catch (\Exception $e) {}

        return null;
    }
}

if (! function_exists('content_path')) {
    /**
     * Get the full path to the content folder or a resource within
     *
     * @param string [$path] Either empty or a relative path to a resource within the content directory
     * @return string
     */
    function content_path(string $path = '')
    {
        return get_path('content').($path ? '/'.$path : $path);
    }
}

if (! function_exists('theme_path')) {
    /**
     * Get the full path to the active theme's folder or a resource within
     *
     * @param string [$path] Either empty or a relative path to a resource within the theme's directory
     * @param string|\Papyrus\Theme $theme A specific theme to look within
     * @return string
     */
    function theme_path(string $path = '', $theme = null)
    {
        return app('themes')->path($path, $theme);
    }
}

if (! function_exists('theme_url')) {
    /**
     * Get the full URL to the active theme's folder or a resource within
     *
     * @param string [$url] Either empty or a relative path to a resource within the theme's directory
     * @param string|\Papyrus\Theme $theme A specific theme to look within
     * @return string
     */
    function theme_url(string $url = '', $theme = null)
    {
        return app('themes')->url($url, $theme);
    }
}

if (! function_exists('get_page')) {
    /**
     * Get a \Papyrus\Content\Page from the content directory
     *
     * @param string $id Can be provided as a route or relative path
     * @param boolean [$drafts] Determines whether drafts should be considered
     * @param boolean [$draftsOnly]
     * @return \Papyrus\Content\Page|null
     */
    function get_page(string $id, bool $drafts = false, bool $draftsOnly = false) : ?\Papyrus\Content\Page
    {
        return app('content')->get($id, $drafts, $draftsOnly);
    }
}

if (! function_exists('query_pages')) {
    /**
     * Run a query against the filesystem
     * 
     * Rather than just querying for a single file one can specify a directory
     * and get all the files within.
     *
     * @param string $query Relative path or route
     * @param integer [$depth] 0 ignores depth but a positive value limits the depth to which the filesystem will be searched
     * @param boolean [$drafts] Determines whether drafts should be considered
     * @return \Illuminate\Support\Collection
     */
    function query_pages($query, $depth = 0, $drafts = false)
    {
        return app('content.filesystem')->query($query, $depth, $drafts);
    }
}

if (! function_exists('all_pages')) {
    /**
     * Get all pages found within the content directory
     *
     * @param boolean [$drafts] Determines whether drafts should be considered
     * @param boolean [$draftsOnly]
     * @return \Illuminate\Support\Collection
     */
    function all_pages($drafts = false, $draftsOnly = false)
    {
        return app('content')->all($drafts, $draftsOnly);
    }
}

if (! function_exists('search')) {
    /**
     * Perform a full-text search of the provided terms
     *
     * @param string $terms The terms to search for
     * @param array|\Illuminate\Support\Collection [$pages] A subset of pages to search through
     * @return \Papyrus\Content\Search
     */
    function search(string $terms, $pages = null) : \Papyrus\Content\Search
    {
        return app('content')->search($terms, $pages);
    }
}

if (! function_exists('paginate')) {
    /**
     * Paginate a set of pages
     *
     * @param array|\Illuminate\Support\Collection [$pages] An optional subset of pages to paginate
     * @param integer [$limit] Number of items per page
     * @param integer [$page] Current page number
     * @return \Papyrus\Content\Pagination
     */
    function paginate($pages = null, $limit = null, $page = null) : \Papyrus\Content\Pagination
    {
        return app('content')->paginate($pages, $limit, $page);
    }
}

if (! function_exists('filter_content')) {
    /**
     * Run string through the configured \Papyrus\Content\Page filters
     *
     * @param string $content
     * @param array|\Soma\Store [$meta]
     * @param SplFileInfo [$file]
     * @return string
     */
    function filter_content(string $content, $meta = [], $file = null) : string
    {
        return \Papyrys\Content\Page::filter($content, $meta, $file);
    }
}

if (! function_exists('get_menu')) {
    /**
     * Get a configured menu
     * 
     * If the menu doesn't exist an empty \Papyrus\Content\Menu instance will be created
     *
     * @param string $id
     * @return \Papyrus\Content\Menu
     */
    function get_menu(string $id) : \Papyrus\Content\Menu
    {
        return app('content')->menu($id);
    }
}

if (! function_exists('get_feed')) {
    /**
     * Get a \Papyrus\Content\Feed
     *
     * @param string [$type] Get specific feed type
     * @return \Papyrus\Content\Feed
     */
    function get_feed(?string $type = null) : \Papyrus\Content\Feed
    {
        return app('content')->getFeed($type);
    }
}

if (! function_exists('feed_url')) {
    /**
     * Get a feed URL
     *
     * @param string [$type] Get specific feed type
     * @return string
     */
    function feed_url(?string $type = null) : string
    {
        return app('content')->feedUrl($type);
    }
}

if (! function_exists('markdown')) {
    /**
     * Parse a markdown formatted string
     * 
     * If you want to parse a string according to the same
     * rules as \Papyrus\Content\Page then you should rather
     * use `filter_content`.
     *
     * @param string $text
     * @return string
     */
    function markdown(string $text) : string
    {
        return \ParsedownExtra::instance()->text($text);
    }
}

if (! function_exists('strip_bare')) {
    /**
     * Strip text completely bare from HTML and special characters
     *
     * @param string $html
     * @param boolean [$newLines] By default newlines are stripped but this can be turned off
     * @return string
     */
    function strip_bare(string $html, bool $newLines = true) : string
    {
        // Add a space to each tag before stripping them
        $html = str_replace('<', ' <', $html);
        $string = strip_tags($html);

        if ($newLines) {
            $string = preg_replace('/\s+/S', ' ', $string);
        }
        else {
            $string = preg_replace( '/\h+/', ' ', $string);
        }

        // Remove special characters
        $string = html_entity_decode($string);
        $string = urldecode($string);

        // Remove urls
        $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@";
        $bare = preg_replace($regex, ' ', $string);
        
        return trim($bare);
    }
}

