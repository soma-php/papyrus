<?php namespace Papyrus\Content;

use Soma\Store;
use Soma\Manifest;
use Papyrus\Content\File;
use Papyrus\Content\Filter;

use Illuminate\Support\Str;
use ParsedownExtra as Parsedown;

use SplFileInfo;
use Closure;
use DateTime;
use ArrayAccess;
use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;
use Exception;
use ErrorException;
use BadMethodCallException;

class Page extends File implements ArrayAccess
{
    protected static $macros = [];
    protected static $lazy = ['html', 'meta', 'raw', 'body', 'bare'];
    protected static $filters = [];

    protected $cacheValid;
    protected $cacheContent;
    protected $cacheMeta;

    public static $metaCommaArrays = ['keywords', 'tags'];
    public static $metaDates = ['published', 'updated'];
    public static $metaInts = [];
    public static $metaBools = [];
    public static $ignoreMTime = false;
    public static $rootUrl = '';
    public static $rootDir = '';
    public static $cacheDir = '';
    
    public $relativePath;
    public $id;
    public $hashid;
    public $route;
    public $url;

    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->relativePath = rel_path($this->path, self::$rootDir);
        $this->id = substr('/'.$this->relativePath, 0, -(strlen($this->extension) + 1));
        $this->hashid = md5($this->id);

        // Cache
        $this->cacheMeta = self::$cacheDir.'/'.$this->hashid.'.php';
        $this->cacheContent = self::$cacheDir.'/'.$this->hashid.'.html';

        // Route
        $this->route = $this->id;

        if (Str::startsWith($this->filename, '_')) {
            $clean = ltrim(basename($this->id), '_');
            $this->route = rtrim(dirname($this->id), '/').'/'.$clean;
        }

        $this->route = (Str::endsWith($this->route, 'index')) ? substr($this->route, 0, -5) : $this->route;

        // URL
        $this->url = self::$rootUrl.implode('/', array_map(function ($part) {
            return urlencode(trim($part));
        }, explode('/', $this->route)));
    }

    public static function addFilter(string $name, Filter $filter)
    {
        self::$filters[$name] = $filter;
    }

    public static function removeFilter(string $name)
    {
        if (isset(self::$filters[$name])) {
            unset(self::$filters[$name]);
        }
    }

    public static function hasFilter(string $name)
    {
        return isset(self::$filters[$name]);
    }

    public static function macro(string $name, callable $macro)
    {
        static::$macros[$name] = $macro;        
    }

    public static function mixin($mixin)
    {
        if (is_string($mixin) && class_exists($mixin)) {
            $mixin = new $mixin;
        }

        $methods = (new ReflectionClass($mixin))->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $method->setAccessible(true);

            static::macro($method->name, $method->invoke($mixin));

            if (Str::endsWith($method->name, '_') && ! in_array($name = trim($method->name, '_'), self::$lazy)) {
                self::$lazy[] = $name;
            }
        }
    }

    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    public function load(): Page
    {
        foreach (self::$lazy as $prop) {
            $val = $this->{$prop};
        }

        return $this;
    }

    public function unload() : Page
    {
        foreach (self::$lazy as $prop) {
            if (isset($this->{$prop})) {
                unset($this->{$prop});
            }
        }

        return $this;
    }

    public function export($internal = false)
    {
        $this->load();

        $props = get_object_vars($this);

        if (! $internal) {
            $reflect = new ReflectionClass($this);
            
            $protected = array_map(function ($prop) {
                return $prop->getName();
            }, $reflect->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE));

            $props = array_diff_key($props, array_flip($protected));
        }

        ksort($props);
        return $props;
    }

    public static function create($path, $body = '', $meta = [], $template = null)
    {
        if (file_exists($path = remove_double_slashes($path))) {
            throw new \ErrorException('File already exists: '.$path);
        }

        // Merge with template
        if ($template !== false && (! is_null($template) || file_exists($template = dirname($path).'/default.md'))) {
            $template = new self($template);
            $template->meta->replace($meta);
            $meta = $template->meta->all();

            if (empty($body)) {
                $body = $template->body;
            }

            $template->unload();
        }
        
        $meta = self::normalizeMeta($meta);
        $raw = '---'.PHP_EOL.Manifest::dumpYAML($meta).PHP_EOL.'---'.PHP_EOL.$body;
        
        // Create and save file
        ensure_dir_exists(dirname($path));

        if (! touch($path)) {
            throw new ErrorException('Failed to create file: '.$path);
        }
        if (! file_put_contents($path, $raw)) {
            throw new ErrorException('Failed to write to file: '.$path);
        }

        return new self($path);
    }

    public function save()
    {
        // Meta section
        $separator = $this->getMetaSeparator();
        $format = (! is_null($separator)) ? $this->getMetaFormat() : '';
        $format = (! is_null($separator) && ! in_array($format, ['json', 'ini', 'yml', 'yaml'])) ? 'yaml' : $format;
        $separator = $separator ?? '---';

        $meta = $this->meta->all();

        // OBS! We cannot filter out preloaded mixin meta unless we save the original
        // values from prior to the addition of the preloaded ones since they often depend
        // on values already set in the file with the same key. Filtering out these
        // would remove the keys altogether.

        // // Filter out all mixin meta since it's computed and shouldn't be saved
        // $mixins = array_filter(static::$macro, fn($key) => Str::startsWith($key, '_'), ARRAY_FILTER_USE_KEY);
        // $mixins = array_map(fn($item) => trim($item, '_'), array_keys($mixins));
        // $meta = array_diff_key($meta, array_combine($mixins, $mixins));

        // Export meta to string
        $meta = Manifest::dump($meta, $format);

        // File contents
        $raw = $separator.$format.PHP_EOL;
        $raw .= $meta;
        $raw .= PHP_EOL.$separator.PHP_EOL;
        $raw .= $this->body;

        if (! file_put_contents($this->path, $raw)) {
            throw new ErrorException('Failed to write to file: '.$path);
        }

        // This is required since some properties may be computed based on others
        $this->refreshCache();
    }

    public function delete()
    {
        unlink($this->path);
    }

    public static function filter($content, &$meta, $file)
    {
        if (is_array($meta)) {
            $meta = new Store($meta);
        }

        // Process content through the configured filters
        foreach (self::$filters as $filter) {
            $content = $filter->before($content, $meta, $file);
        }

        // Compile markdown to HTML
        try {
            $html = @Parsedown::instance()->text($content);
        }
        catch (Exception $e) {
            $html = '';
        }

        // Process content through the configured filters
        foreach (self::$filters as $filter) {
            $html = $filter->after($html, $meta, $file);
        }

        return html_entity_decode($html);
    }

    public function validateCache(?bool $ignoreMTime = null)
    {
        if (is_null($this->cacheValid) || ! is_null($ignoreMTime)) {
            if (is_null($ignoreMTime)) {
                $ignoreMTime = self::$ignoreMTime;
            }

            if (! file_exists($this->cacheContent) || ! file_exists($this->cacheMeta)) {
                $this->cacheValid = false;
            }
            elseif (! $ignoreMTime && filemtime($this->path) > filemtime($this->cacheContent)) {
                $this->cacheValid = false;
            }
            else {
                $this->cacheValid = true;
            }
        }

        return $this->cacheValid;
    }

    public function clearCache()
    {
        if (file_exists($this->cacheContent)) {
            unlink($this->cacheContent);
        }
        if (file_exists($this->cacheMeta)) {
            unlink($this->cacheMeta);
        }

        $this->unload();
    }

    public function refreshCache()
    {
        if (isset($this->cacheValid)) {
            $this->cacheValid = null;
        }

        $this->unload();
    }

    public function compile()
    {
        // Process meta and body sections
        $meta = $this->getParsedMeta();
        $body = $this->getBodySection();
        $html = self::filter($body, $meta, new SplFileInfo($this->path));

        // Process each mixin meta
        $mixins = array_filter(static::$macros, fn($key) => Str::startsWith($key, '_'), ARRAY_FILTER_USE_KEY);

        foreach ($mixins as $key => $macro) {
            if ($macro instanceof Closure) {
                $meta->set(trim($key, '_'), call_user_func_array($macro->bindTo($this, static::class), [$html, $meta]));
            }
            else {
                $meta->set(trim($key, '_'), call_user_func_array($macro, [$html, $meta]));
            }
        }

        // Save result
        if (should_optimize()) {
            Manifest::dumpFile($this->cacheMeta, $meta->all());

            if (! touch($this->cacheContent)) {
                throw new ErrorException('Failed to create file: '.$this->cacheContent);
            }
            if (! file_put_contents($this->cacheContent, $html ?: " ")) {
                throw new ErrorException('Failed to write to file: '.$this->cacheContent);
            }
        }

        $this->cacheValid = true;

        return [$html, $meta->all()];
    }

    public function getMetaSeparator()
    {
        if (Str::startsWith($this->raw, '```')) {
            return '```';
        }
        if (Str::startsWith($this->raw, '---')) {
            return '---';
        }

        return null;
    }

    public function getMetaFormat()
    {
        $separator = $this->getMetaSeparator();

        if ($separator == '```') {
            $raw = str_replace("\r\n", "\n", $this->raw);
            $line = strtok($this->raw, "\n");
            $type = strtolower(ltrim($line, '`')) ?: 'yml';
            return ($type == 'yaml') ? 'yml' : $type;
        }

        return 'yml';
    }

    public function getMetaSection()
    {
        if (is_null($separator = $this->getMetaSeparator())) {
            return '';
        }

        $meta = '';
        $type = $this->getMetaFormat();

        // fopen can deal with empty lines which strtok cannot
        $i = 0;
        $fp = fopen("php://memory", 'r+');
        fputs($fp, $this->raw);
        rewind($fp);

        while ($line = fgets($fp)){
            // Skip the first line since we know it's the opening line for the meta
            if (++$i == 1) {
                continue;
            }
            // Stop if we're at the end of the meta section
            if (Str::startsWith($line, $separator)) {
                break;
            }
            
            $meta .= $line;
        }

        fclose($fp);

        return trim($meta);
    }

    public static function normalizeMeta($meta)
    {
        if ($meta instanceof Store) {
            $meta = $meta->all();
        }

        // Ensure first character of each key is lowercase
        $meta = array_combine(
            array_map('lcfirst', array_keys($meta)), 
            array_values($meta)
        );

        // Correctly cast meta
        foreach (self::$metaCommaArrays as $key) {
            if (isset($meta[$key]) && is_string($meta[$key])) {
                $meta[$key] = array_map('trim', explode(',', $meta[$key]));
            }
        }
        foreach (self::$metaDates as $key) {
            if (isset($meta[$key]) && ! $meta[$key] instanceof DateTime) {
                $meta[$key] = make_datetime($meta[$key], config('content.date-format', DateTime::ISO8601));
            }
        }
        foreach (self::$metaInts as $key) {
            if (isset($meta[$key]) && ! is_int($meta[$key])) {
                $meta[$key] = intval($meta[$key]);
            }
        }
        foreach (self::$metaBools as $key) {
            if (isset($meta[$key]) && ! is_bool($meta[$key])) {
                $meta[$key] = make_bool($meta[$key]);
            }
        }

        return $meta;
    }

    protected function getParsedMeta()
    {
        $meta = [];
        $section = $this->getMetaSection();

        // Extract meta portion of file
        if ($section) {
            $meta = Manifest::parse($section, $this->getMetaFormat());
        }

        // Normalize
        $meta = self::normalizeMeta($meta);

        return $meta;
    }

    protected function meta_()
    {
        // Check if we can load from cache
        if (should_optimize() && $this->validateCache()) {
            return new Store(Manifest::parseFile($this->cacheMeta));
        }

        // If not, we compile the file
        list($html, $meta) = $this->compile();

        // Also set html so we don't compile twice on a request
        $this->html = $html;

        return new Store($meta);
    }

    public function getBodySection()
    {
        if (is_null($separator = $this->getMetaSeparator())) {
            $body = $this->raw;
        } else {
            $meta = true;
            $body = '';

            // fopen can deal with empty lines which strtok cannot
            $i = 0;
            $fp = fopen("php://memory", 'r+');
            fputs($fp, $this->raw);
            rewind($fp);

            while ($line = fgets($fp)){
                // Skip the first line since we know it's the opening line for the meta
                if (++$i == 1) {
                    continue;
                }
                // Skip if still processing meta
                if ($meta) {
                    if (Str::startsWith($line, $separator)) {
                        $meta = false;
                    }

                    continue;
                }

                $body .= $line;
            }

            fclose($fp);
        }

        return ltrim($body);
    }

    protected function html_()
    {
        // Check if we can load from cache
        if (should_optimize() && $this->validateCache()) {
            return file_get_contents($this->cacheContent);
        }

        // If not, we compile the file
        list($html, $meta) = $this->compile();

        // Also set meta so we don't compile twice on a request
        $this->meta = $meta;

        return $html;
    }

    protected function raw_()
    {
        return trim(file_get_contents($this->path));
    }

    protected function bare_()
    {
        return strip_bare($this->html);
    }

    protected function body_()
    {
        return $this->getBodySection();
    }

    public function is($key) : bool
    {
        if (! is_null($val = $this->{$key})) {
            return boolval($val);
        }

        return false;
    }

    public function has($key) : bool
    {
        if (! is_null($val = $this->{$key})) {
            return ! empty($val);
        }

        return false;
    }

    public function __get($property)
    {
        // Support lazy loading of html and meta
        if (in_array($property, self::$lazy)) {
            return $this->{$property} = $this->{$property.'_'}();
        }
        else {
            return $this->meta->get($property, null);
        }
    }

    public function __call(string $method, array $args)
    {
        // Calls macro if defined and callable
        if (($macro = static::$macros[$method] ?? false) && is_callable($macro)) {
            if ($macro instanceof Closure) {
                return call_user_func_array($macro->bindTo($this, static::class), $args);
            }

            return call_user_func_array($macro, $args);
        }
        // Checks if property is empty or not: hasTags()
        if (Str::startsWith($method, 'has')) {
            $property = lcfirst(substr($method, 3));

            return $this->has($property);
        }
        // Checks if property is truthy: isGallery()
        if (Str::startsWith($method, 'is')) {
            $property = lcfirst(substr($method, 2));

            return $this->is($property);
        }
        
        throw new BadMethodCallException(self::class." doesn't have a method called ".$method);
    }

    // ArrayAccess is not recommended but required for sorting via Collection
    public function offsetExists($key)
    {
        return ! is_null($this->{$key});
    }

    public function offsetGet($key)
    {
        return $this->{$key};
    }

    public function offsetSet($key, $value)
    {
        if (! $this->meta_->exists($key)) {
            $this->{$key} = $value;
        }
        else {
            $this->meta->set($key, $value);
        }
    }

    public function offsetUnset($key)
    {
        if (isset($this->{$key})) {
            unset($this->{$key});
        }
        elseif ($this->meta->exists($key)) {
            $this->meta->remove($key);
        }
    }
}