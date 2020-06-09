# Configuration

## Setup
All essential configuration is set in the `.env` file in the root of your project:

```sh
APP_URL="http://localhost:8000"
APP_STAGE="development"
APP_TIMEZONE="Europe/Stockholm"
APP_DEBUG=true
APP_OPTIMIZE=true
APP_CONFIG="/absolute/path/to/your/config/folder"
APP_STORAGE="/absolute/path/to/your/storage/folder"
APP_CONTENT="/absolute/path/to/your/content/folder"
APP_THEMES="/absolute/path/to/your/themes/folder"
```

*The storage directory needs to be writable by the application.*

*APP_DEBUG* should only be set if you're actively debugging the application since it will slow down the website's performance noticeably. *APP_OPTIMIZE* should always be enabled when running in a live production environment, otherwise no files will be cached and content processing will likely tank your servers performance.

There's no need for the project to be all together in the same directory, *public* is the web root and the `.env` should preferable be in the parent directory. The *content* can be a git repository or simply a Dropbox folder synced to your server and the content will be compiled and cached in *storage*. The following however is a recommended project structure:

```text
Project/
-- config/
-- -- app.php
-- content/
-- -- index.md
-- -- config.yml
-- -- menus.yml
-- public/
-- -- index.php
-- storage/
-- themes/
-- .env
-- appctrl
-- composer.json
```

The environment variables are primarily used internally by SOMA or Papyrus but can also be accessed using the included helper functions.

## Configuration keys

The *config* folder contains all the configuration files for your project. However, any keys you set in `config.yml` in the root of your content folder will override keys in the `content` config namespace. This is the recommended place to put variables that changes the rendering of your content and behavior of your site. Theme specific settings are recommended to be embedded into the theme manifest (refer to the theme documentation).

The full list of keys supported by default are as follows and these can just as well be set via `content/config.yml`:

```php
return [
    'title' => 'My Site',
    'language' => 'en_US',
    'description' => 'My personal blog',

    // Default author for published contents, this can be overriden for each page
    'author' => 'John Doe',

    // 'timezone' should be one of the PHP supported: https://www.php.net/manual/en/timezones.php
    'timezone' => 'Europe/Stockholm',

    // 'date-format' sets the default format used by the format_date helper. 
    // If false or missing then the default format is the ISO-8601 standard: YYYY-MM-DD
    'date-format' => false,

    // Adds the option to type-cast specific meta keys
    'meta' => [
        'dates' => ['published', 'birthday'],
        'booleans' => ['featured'],
        'integers' => ['order'],
    ],

    // If you enable drafts you can view the draft in the web browser
    // by appending "?preview=true" to the URL.
    'drafts' => true,

    // Sets the default number of posts per page if you call paginate
    // via ContentManager or the paginate helper.
    'pagination' => 10,

    // Sets the page that will be shown if no page can be found
    '404' => '/404.md',

    // Settings for the built-in content search functionality
    'search' => [
        'low_value' => ['is', 'a', 'the', 'in', 'he', 'she', 'it', 'its', 'and'],
        'exclude' => [
            'pages/sub'
        ],        
    ],

    // The router can be disabled if you want to implement you own
    // custom logic for all requests by setting this key to false
    'router' => [
        // Handlers are callables that take a string and a Symfony Request
        // as arguments and return a boolean indicating whether
        // they handled the request or not. Check the advanced documentation
        // for more information.
        'handlers' => [],
    ],

    // Settings affecting the RSS feed. If 'feed' is set to false
    // then the built-in route handler for RSS feeds will
    // not be registered at all
    'feed' => [
        'route' => '/feed',
        'mode' => 'excerpt',
        // 'filter' will be run against the \Illuminate\Support\Collection instance
        // of pages so that custom logic can do more advanced filtering
        'filter' => function($val, $key) {
            return true;
        },
        'sort' => 'published',
        'order' => 'asc',
        'include' => [],
        'exclude' => [],
        // 'image' should be either an absolute URL or a relative path to a file
        // within the content directory.
        'image' => 'my-feed-logo.jpg',
    ],

    // If 'images' is set to false then all automatic image processing will be disabled
    'images' => [
        // This value changes what image processing driver will be used,
        // 'gd' and 'imagick' are supported.
        'driver' => 'gd',
        // Sets the image quality used in all automatic image processing
        'quality' => 80,
        'filters' => [
            // Filters need to be callable as with the content.filters
            // and can also be set via the theme manifest
            'example-filter' => \MyTheme\MyImageFilter::class,
        ],
    ],

    // Content filters (see explanation)
    'filters' => [
        'anchors' => \Papyrus\Content\Filters\AnchorFilter::class,
        'images' => \Papyrus\Content\Filters\ImageFilter::class,
        'includes' => \Papyrus\Content\Filters\ContentIncludesFilter::class,
        'hashtags' => \Papyrus\Content\Filters\HashtagFilter::class,
        'heading-id' => \Papyrus\Content\Filters\HeadingIdFilter::class,
        'heading-offset' => \Papyrus\Content\Filters\HeadingOffsetFilter::class,
        'shortcodes' => \Papyrus\Content\Filters\ShortcodeFilter::class,
        'smartypants' => \Papyrus\Content\Filters\SmartyPantsFilter::class,
    ],

    // Page mixins (see explanation)
    'mixins' => [
        \My\Custom\Mixin::class,
    ],

    // Filter settings (see explanation)
    'tag-route' => '/tag?t=',
    'includes' => [
        'markdown' => [
            'after' => '/signature.md',
        ],
        'html' => [
            'before' => '/ads/advertisement.html'
        ],
    ],

    // Cache settings (see explanation)
    'cache' => [
        'ignore-mtime' => true,
        'validate-visited' => true,
        'gateway' => false,
        'full-page' => true,
        'time' => 3600,
        'include' => false,
        'exclude' => [
            'feed'
        ],
    ],
];
```

Config is most easily retrieved by the config helper using dot notation, the first level being the config file name:

```php
$siteTitle = config('content.title', 'Default value');
```

### images.filters

Image filters need to be callables with the following definition:

```php
function(\Papyrus\Content\Image $image) {
  
    // ...

    return $image;
}
```

### filters

Content filters are pre and post processors for the markdown content. The default ones add support for links to other content or static resources, responsive image resizing, id attribute on headings, heading offset and shortcodes. Some filter settings that are related to presentation are set in the theme manifest. Shortcodes require further logic and should be registered by a *Service Provider* (see the advanced documentation). Further information regarding the filters and their definition can be found under the *Pages* documentation.

### mixins

Mixins are a way to extend the functionality of the *Page* class. Refer to the advanced documentation for more information.

### tag-route

Used by the *hashtags* filter and sets the route which will be prefixed onto the tag string for the generated anchor.

### includes

Enables automatic content insertion into every page. The content can either be markdown or pre-formatted html and the options are to either append or prepend it to the content. The value should be either a path relative to the *content* directory or an array of such paths.

### cache

Performance can be improved by adjusting the cache behavior. `ignore-mtime` disables checking the modification date of the file and only forces a recompile if the file is missing completely. If this is set one must manually perform a recompilation in order to reflect content updates which could be performed by a schedule server job. `validate-visited` modifies that behavior to just validate the page being requested, so that other files (like when showing an archive on an index page) won't be recompiled until they are directly visited and this is a reasonable performance optimization which is recommended to turn on. Refer to the advanced documentation for more on performance and how Papyrus loads content.

If you have a HTTP cache gateway configured you can set the correct headers on the response by setting `gateway` to true. Alternatively you can use the `full-page` cache that is a simple cache system that bypasses most of Papyrus' subsystems and returns a previously rendered response to an URI request if it exists and is not older than `time` in seconds.

You can also set what routes you want to be cacheable using the `include` and `exclude` lists. *Include* runs first and if the beginning of the route doesn't match the configured filter then it won't be cached. You can also *exclude* to filter out specific routes within the included set.