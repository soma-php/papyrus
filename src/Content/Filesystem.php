<?php namespace Papyrus\Content;

use ErrorException;
use InvalidArgumentException;
use DirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator; 

use Papyrus\Content\Page;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

class Filesystem
{
    protected $rootPath;
    protected $combined;
    protected $published;
    protected $drafts;
    protected $processed = false;

    public function __construct($path)
    {
        $this->rootPath = rtrim($path, '/');
        $this->combined = new Collection();
        $this->published = new Collection();
        $this->drafts = new Collection();
    }

    public function scanPages()
    {
        $pages = [];
        $files = (new Finder())->in($this->rootPath)
            ->name('*.md')
            ->ignoreUnreadableDirs()
            ->ignoreVCS(true)
            ->notName('default.md')
            ->followLinks()
            ->files();

        foreach ($files as $file) {
            // Exclude all files that are in a hidden directory
            if (Str::contains('/'.rel_path($file->getPath(), $this->rootPath), '/_')) {
                continue;
            }

            $pages[] = $file;
        }

        return $pages;
    }

    protected function processPages()
    {
        foreach ($this->scanPages() as $file) {
            // As we check if the page is a draft it will also be compiled if it hasn't been previously
            // since it also checks whether if the meta key "published" has been set
            $page = new Page($file);

            if ($page->isDraft()) {
                // Route is the common identifier between both drafts and published pages
                $this->drafts->put($page->route, $page);
                $this->combined->put($page->route, $page);
            } else {
                $this->published->put($page->route, $page);

                // Only map route if not already populated from a draft
                if (! isset($this->combined[$page->route])) {
                    $this->combined->put($page->route, $page);
                }
            }
        }

        $this->processed = true;
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function drafts(): Collection
    {
        if (! $this->processed) {
            $this->processPages();
        }

        return $this->drafts;
    }

    public function pages($preview = false): Collection
    {
        if (! $this->processed) {
            $this->processPages();
        }

        return ($preview) ? $this->combined : $this->published;
    }

    public function directories($uri = '/', $recursive = false): array
    {
        $uri = self::normalizeQuery($uri, $this->rootPath);
        $dir = $this->rootPath.$uri;
        $paths = [];

        if (! is_dir($dir)) {
            return null;
        }

        if ($recursive) {
            $rdi = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $iter = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
            
            foreach ($iter as $path => $file) {
                if ($file->isDir()) {
                    $paths[] = '/'.rel_path($path, $this->rootPath);
                }
            }
        } else {
            $iter = new DirectoryIterator($dir);

            foreach ($iter as $file) {
                if ($file->isDir() && ! $file->isDot()) {
                    $paths[] = '/'.rel_path($file->getPathname(), $this->rootPath);
                }
            }
        }

        return $paths;
    }

    public function getDirectoryTemplate($uri): ?Page
    {
        $uri = self::normalizeQuery($uri, $this->rootPath).'default.md';

        if (file_exists($path = $this->rootPath.$uri)) {
            return new Page($path);
        }

        return null;
    }

    public function createDirectoryTemplate($uri)
    {
        $uri = self::normalizeQuery($uri, $this->rootPath).'default.md';

        if (! file_exists($path = $this->rootPath.$uri)) {
            return Page::create($path);
        }

        return false;
    }

    public function createFile($uri, $content): bool
    {
        $uri = self::normalizeQuery($uri, $this->rootPath);

        if (! file_exists($path = $this->rootPath.$uri)) {
            ensure_dir_exists(dirname($path));

            if (! touch($path)) {
                throw new ErrorException('Failed to create file: '.$path);
            }
            if (! file_put_contents($path, $content)) {
                throw new ErrorException('Failed to write to file: '.$path);
            }

            return true;
        }

        return false;
    }

    public function getPath($uri)
    {
        $uri = self::normalizeQuery($uri, $this->rootPath);

        if (file_exists($path = $this->rootPath.$uri)) {
            return $path;
        }

        return false;
    }

    public function getFile($uri)
    {
        if ($path = $this->getPath($uri)) {
            return file_get_contents($path);
        }

        return false;
    }

    public function scan($uri, $recursive = false): array
    {
        $uri = self::normalizeQuery($uri, $this->rootPath);
        $dir = $this->rootPath.$uri;
        $paths = [];

        if (! is_dir($dir)) {
            return null;
        }

        if ($recursive) {
            $rdi = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $iter = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);

            foreach ($iter as $path => $file) {
                $paths[] = '/'.rel_path($path, $this->rootPath);
            }
        } else {
            $iter = new DirectoryIterator($dir);

            foreach ($iter as $file) {
                if (! $file->isDot()) {
                    $paths[] = '/'.rel_path($file->getPathname(), $this->rootPath);
                }
            }
        }

        return $paths;
    }

    public function files($uri = '/', $recursive = false): array
    {
        $uri = self::normalizeQuery($uri, $this->rootPath);
        $dir = $this->rootPath.$uri;
        $paths = [];

        if (! is_dir($dir)) {
            return null;
        }

        if ($recursive) {
            $rdi = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $iter = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);

            foreach ($iter as $path => $file) {
                if (! $file->isDir()) {
                    $paths[] = '/'.rel_path($path, $this->rootPath);
                }
            }
        } else {
            $iter = new DirectoryIterator($dir);

            foreach ($iter as $file) {
                if (! $file->isDot() && ! $file->isDir()) {
                    $paths[] = '/'.rel_path($file->getPathname(), $this->rootPath);
                }
            }
        }

        return $paths;
    }

    public static function normalizeRoute($uri, $rootPath): string
    {
        // Add root
        if (! Str::startsWith($uri, '/')) {
            $uri = '/'.$uri;
        }
        // Remove draft prefix
        if (Str::startsWith($basename = basename($uri), '_')) {
            $uri = dirname($uri).'/'.ltrim($basename, '_');
        }
        // Remove markdown filetype
        if (Str::endsWith($uri, '.md')) {
            $uri = substr($uri, 0, -3);
        }
        // Remove "index"
        if (Str::endsWith($uri, 'index')) {
            $uri = substr($uri, 0, -5);
        }
        // Append "/" if uri references a directory
        elseif (is_dir($rootPath.$uri)) {
            if (! Str::endsWith($uri, '/')) {
                $uri .= '/';
            } 
        }
        // Remove trailing "/" if accidentally appended
        elseif (Str::endsWith($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        return canonicalize_path(urldecode(remove_double_slashes($uri)));
    }

    public static function normalizeQuery($uri, $rootPath): string
    {
        // Add root
        if (! Str::startsWith($uri, '/')) {
            $uri = '/'.$uri;
        }
        // Append "/" if uri references a directory
        if (is_dir($rootPath.$uri) && ! Str::endsWith($uri, '/')) {
            $uri .= '/';
        }

        return canonicalize_path(urldecode(remove_double_slashes($uri)));
    }

    public static function normalizePath($uri, $rootPath): string
    {
        // Add root
        if (! Str::startsWith($uri, '/')) {
            $uri = '/'.$uri;
        }
        // Remove draft prefix
        if (Str::startsWith($basename = basename($uri), '_')) {
            $uri = dirname($uri).'/'.ltrim($basename, '_');
        }
        // Add index if referencing a directory
        if (is_dir($rootPath.$uri)) {
            $uri .= rtrim($uri, '/').'/index.md';
        }
        // Remove trailing "/" if accidentally appended
        elseif (Str::endsWith($uri, '/')) {
            $uri = rtrim($uri, '/');
        }
        // Add markdown filetype
        if (! Str::endsWith($uri, '.md')) {
            $uri .= '.md';
        }

        return canonicalize_path(urldecode(remove_double_slashes($uri)));
    }

    public function get($id, $drafts = false, $draftsOnly = false): ?Page
    {
        // Route is the common identifier between both drafts and published pages
        $route = self::normalizeRoute($id, $this->rootPath);

        // If we already have processed the entire folder we simply check if the file exists or not
        if ($this->processed) {
            return ($drafts) ? ($this->combined[$route] ?? null) : ($this->published[$route] ?? null);
        }
        // If the page has been located previously we return the same instance
        elseif ($draftsOnly && isset($this->drafts[$route])) {
            return $this->drafts[$route];
        }
        elseif ($drafts && isset($this->combined[$route])) {
            return $this->combined[$route];
        }
        elseif (! $drafts && isset($this->published[$route])) {
            return $this->published[$route];
        }

        $uri = self::normalizePath($id, $this->rootPath);
        
        // Skip if in a hidden folder or if named "default.md"
        if (Str::contains(dirname($uri), '/_') || basename($uri) == 'default.md') {
            return null;
        }

        // Find draft
        if ($drafts && file_exists($draftPath = $this->rootPath.dirname($uri).'/_'.basename($uri))) {
            $page = new Page($draftPath);

            $this->drafts->put($page->route, $page);
            $this->combined->put($page->route, $page);

            return $page;
        }
        // Find page without draft prefix (can still be unpublished and considered a draft)
        if (file_exists($path = $this->rootPath.$uri)) {
            $page = new Page($path);

            if ($page->isDraft()) {
                $this->drafts->put($page->route, $page);
                $this->combined->put($page->route, $page);

                return ($drafts) ? $page : null;
            } else {
                $this->published->put($page->route, $page);

                // Only map route if not already populated from a draft
                if (! isset($this->combined[$page->route])) {
                    $this->combined->put($page->route, $page);
                }

                return (! $draftsOnly) ? $page : null;
            }
        }

        return null;
    }

    public function query($uri, $depth = 0, $drafts = false, $draftsOnly = false): Collection
    {
        $drafts = (config('content.drafts', false)) ? $drafts : false;  
        $uri = self::normalizeQuery($uri, $this->rootPath);

        $routes = ($draftsOnly) ? $this->drafts() : $this->pages($drafts);

        // Find all routes that begin with query
        if ($depth >= 0 && Str::endsWith($uri, '/')) {
            $dir = $uri;
            $startDepth = substr_count(rtrim($uri, '/'), '/');

            return $routes->filter(function($page) use ($dir, $uri, $startDepth, $depth) {
                if (Str::startsWith($page->route, $dir) && $page->route != $uri) {
                    if ($depth > 0) {
                        $currentDepth = substr_count($page->route, '/');
                        $layer = $currentDepth - $startDepth;

                        if ($layer > $depth) {
                            return false;
                        }
                    }
                    return true;
                }
                return false;
            });
        }

        // Return single route or empty Collection
        return new Collection([$routes->firstWhere('route', $uri)]);
    }
}