# Advanced

## Services

You can utilize the full functionality of SOMA and the rest of the PHP ecosystem to customize your installation. Look for other pre-made services that integrate new functionality into Papyrus or make your own. Refer to the [SOMA documentation](https://soma-php.github.io/) for more information.

## Shortcodes

Shortcodes are integrated using [maiorano84/shortcodes](https://github.com/maiorano84/shortcodes) and is a simple way to create custom tags for your markdown files. Define the logic in a service provider that you register:

```php
namespace MyTheme;

use Soma\ServiceProvider;
use Psr\Container\ContainerInterface;

use Papyrus\ShortcodeManager;
use Papyrus\Content\Shortcode;
use Papyrus\Content\Page;

class MyThemeProvider extends ServiceProvider
{
    public function getExtensions() : array
    {
        return [
            'content.shortcodes' => function(ShortcodeManager $manager, ContainerInterface $c) {
                $manager->register(new Shortcode('gallery', ['type'=>'masonry'], function($content = null, array $atts = []) {
                    // The attribute markdown="1" enables
                    // markdown processing inside a HTML tag
                    return "## My ".$atts['type']." Gallery \n\n".$content;
                }));

                return $manager;
            },
        ];
    }
}
```

This shortcode will then be available to use in your page files:

```md
[gallery type=slideshow]
![Me at the beach]('me-at-the-beach.jpg')
![My dog at the beach]('dog-at-the-beach.jpg')
[/gallery]
```

## Routing

The built-in router supports custom route handlers that can either be defined in `config/content.php` or added programmatically in a *service provider*. They only need to callable and match the expected defintion. Make sure to return a boolean indicating if the request was handled by your code or not. If you want to create a class handling requests you can utilize the `__invoke` [magic method](https://www.php.net/manual/en/language.oop5.magic.php#object.invoke).

```php
return [
    'title' => 'My Site',
    'language' => 'en_US',
    'router' => [
        'disable' => false,
        'handlers' => [
            '/custom' => function (string $uri, \Symfony\Component\HttpFoundation\Request $request) {

                // ...

                return true;
            },
        ],
    ],
];
```

## Adding Page features

Any customization of the `Page` class should preferable be done as an extension of the `Filesystem` class so that everything is applied before any actual *page* processing takes place. One can register custom filters automatically if a theme or service depends on it and the properties and methods are added via macros. All of the default macros and filters can be overloaded.

### Pre-loaded and lazy-loaded properties 

Properties and methods can be added and made available on the *Page* class using macros. There are three types of macros, each with their own definition. The first is simply a method that can be called on the *Page* instance (e.g. `$page->greetPerson('John')`) and may have any number of parameters it may require:

```php
Page::macro('greetPerson', function($person) {
    return 'Hello '.$person;
});
```

The second type are *pre-loaded properties*. These are static in relation to the content and are saved as meta properties but requires some sort of processing of said content to be set. To indicate that the property is pre-loaded the method name should be prefixed with an underscore and the function will be passed `$html` and `$meta` since most properties aren't defined yet and trying to access them would cause a compilation recursion. One example is the property `$page->excerpt` that is included by default that either extracts the first content paragraph or the original meta property if it has been set:

```php
Page::macro('_excerpt', function($html, $meta) {
    if ($excerpt = $meta->get('excerpt')) {
        $excerpt = Parsedown::instance()->text($excerpt);
    }
    else {
        $first = strpos($html, '<p');
        $excerpt = substr($html, $first, strpos($html, '</p>') - $first);
        $excerpt = substr($excerpt, strpos($excerpt, '>') + 1);
    }

    return $excerpt;
});
```

The third type are *lazy-loaded properties*. These two are static in relation to the content since they will be cached for the duration of the request upon access but may return any type of instanced object since it won't be serialized and saved to file. To indicate that the property is lazy-loaded the method name should be suffixed with an underscore. This is the definition of `$page->images` that retrieves all the image files from the content body. Note how here it's safe to access the HTML using `$this->html`.

```php
Page::macro('images_', function() {
    $dom = new DOMDocument();
    @ $dom->loadHTML('<div>'.$this->html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $imgs = $dom->getElementsByTagName('img');
    $images = [];

    if (! empty($imgs)) {
        foreach ($imgs as $key => $img) {
            try {
                $src = $img->getAttribute('src');
                $images[] = new Image($img->getAttribute('src'), [
                    'alt' => $img->getAttribute('alt'),
                    'sizes' => $img->getAttribute('sizes'),
                    'srcset' => $img->getAttribute('srcset'),
                    'class' => $img->getAttribute('class'),
                    'id' => $img->getAttribute('id'),
                    'data' => ['size' => $img->getAttribute('data-size')],
                ]);
            }
            catch (Exception $e) {}
        }
    }

    return $images;
});
```

### Mixins

Mixins are classes that will be processed using reflection to register macros. This makes it easier to register a bunch of related functionality in one go and keeps your code more neatly organized. Since the scope can't be changed from one class to another in PHP, and `$this` should point to the *Page* instance just like an internal method, one must return a function from the method that will be registered with the method name:

```php
namespace MyTheme;

class MyMixin
{
    public function _preloadedProperty()
    {
        return function($html, $meta) {

            // ...

            return $preloaded;
        };
    }

    public function lazyLoadedProperty_()
    {
        return function() {

            // ...

            return $lazyLoaded;
        };
    }

    public function normalClassMethod()
    {
        return function ($param) {
            
            // ...

            return $result;
        };
    }
}

```

### Custom content filters

Upon page compilation the markdown and then the html gets passed through all of the configured filters. Since files only are compiled upon updates and won't run on each request these can do more performance intensive operations without impacting live site peformance.

A filter can change the markdown prior to rendering, add or modify meta properties, and also process the resulting html after rendering. It must extend the `Papyrus\Content\Filter` abstract class.

```php
namespace MyTheme;

use SplFileInfo;
use Soma\Store;
use Papyrus\Content\Filter;

class MyFilter extends Filter
{
    public function before(string $markdown, Store &$meta, ?SplFileInfo $file)
    {
        // ..

        return $markdown;
    }

    public function after(string $html, Store &$meta, ?SplFileInfo $file)
    {
        // ...

        return $html;
    }
}
```

Filters can also be run on strings if required, much like the Wordpress `do_shortcode` function call:

```php
$html = filter_content('Random **markdown** formatted string with a [shortcode][/shortcode]');
```

### Registering these using a Service Provider

To ensure that all modifications are applied to *Page* before any content processing happens you should do so as an extension of the *Filesystem* class.

```php
namespace MyTheme;

use Soma\ServiceProvider;
use Psr\Container\ContainerInterface;

use Papyrus\Content\Page;
use Papyrus\Content\Filesystem;

use MyTheme\MyMixin;
use MyTheme\MyFilter;

class MyThemeProvider extends ServiceProvider
{
    public function getExtensions() : array
    {
        return [
            'content.filesystem' => function(Filesystem $files, ContainerInterface $c) {
                // Register macros using a mixin
                Page::mixin(new MyMixin());

                // Register a filter
                Page::addFilter('my-filter', new MyFilter());
                
                return $files;
            },
        ];
    }
}
```

## Performance

The system is designed to only load the pages absolutely necessary to present the current request. If no other pages are presented (not presenting a directory index), then only a single page will be cache validated and loaded into memory, and pages are only ever queried once. So getting the same pages in multiple locations doesn't impact filesystem or memory usage.

In special cases, like when using the *Search* class to perform a full-text search, since only a subset of the total number of pages are what actually will be presented, the pages that won't be a part of the final result set gets unloaded.

In addition to this, page meta and body are cached separately and loaded on demand. So iterating through pages and only displaying their titles will never load the HTML into memory. Since some properties need to process the file content to be set and returns a dynamic object Papyrus implements lazy-loading of such properties that are static in relation to file content but accessed using an instanced class.

By default every page file that is new or updated will be recompiled on a request. That means that if the cache is cleared someone may experience a great performance hit on the next request, but since the cache always gets validated there shouldn't be a need to ever clear the cache. One way to mitigate this would be clearing the cache from a terminal command and then at the same time recompile everything in one go.

The cache behavior can be adjusted as well. The config key `content.cache.ignore-mtime` disables checking the modification date of the file and modifies the system to only compile a page if the cache file is missing completely. To have the website still reflect content changes one would have to schedule a scan to periodically validate the cache and recompile a file if invalid. A command is provided to support this:

```sh
php appctrl content:compile
```

The config key `content.cache.validate-visited` modifies that behavior to still validate the page being requested, so files other than the specific one requested, like in an archive, won't be recompiled until they are directly visited. Using these two keys in combination is the default and recommended configuration:

```php
return [
    // ...
    'cache' => [
        'ignore-mtime' => true,
        'validate-visited' => true,
    ],
    // ...
];
```

You can also configure an included `full-page` cache or set the correct headers for an HTTP `gateway` caching mechanism. So if you notice specific pages receiving more traffic you can choose to cache these specifically. When using this type of system one need to be careful to not cache content that is dynamic and dependent on a user session. Refer to the configuration documentation for more information.

Another way to cache content on a presentation level is by installing a cache system in your theme (such as `nsrosenqvist/soma-cache`) and cache search results and archives so that it's not reloaded on each pagination.