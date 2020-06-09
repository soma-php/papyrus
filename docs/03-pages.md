# Pages and routing

The body of the page files are formatted using [Markdown](https://www.markdownguide.org/) and the meta using a [YAML](https://learnxinyminutes.com/docs/yaml/) header section, other static CMS's usually uses a term borrowed from the publishing industry and call this the "front matter". The meta keys "keywords" and can be defined using a comma separated list and created as an array even though it's not a valid YAML array. This is purely for convenience sake and what keys are to be treated like this can be configured by the static array `\Papyrus\Content\Page::$metaCommaArrays` (also configurable with the config key `content.meta.comma-arrays`). The first character of each key is also made lowercase.

Here's an example of what a file could look like:

```text
---
Title: About
Keywords: Who I am, developer, photography
Author: John Doe
Template: page
Published: 2019-07-01
---
# About me

Commodi qui sed laudantium quaerat est. Fugit temporibus occaecati ut quos nesciunt in assumenda veritatis. Architecto blanditiis similique odit. Sequi repellat sunt aut. In mollitia et occaecati quod laboriosam occaecati.

![Photo of me](portrait.jpg)
```

The Markdown engine is [Parsedown](https://parsedown.org/) with the [Markdown Extra](https://github.com/erusev/parsedown-extra) extension. Links are automatically resolved and images are processed to create responsive tags for each so that content creation is as hassle free as possible.

## Meta

The front matter, or meta, is processed as YAML by default but Papyrus supports defining it as either JSON or INI as well. Other flat-file CMS's, such as [Pico](http://picocms.org/), have opted to define the meta section by using three repeating dashes (` --- `) which is how most static CMSs do it, so that's what's expected by default for compatibility's sake. However, you can just as well choose to define the section as a code block (` ``` `) which also allows you define a syntax highlighting (` ```yml `). This was added for convenience sake in order to enable YAML highlighting in popular text-editors.

There are also a couple of expected keys:
- **title**: This is not strictly required but is used as fallback in many cases, for example if "label" isn't defined on a menu item.
- **template**: Template configures what template from the theme the page should be loaded with. By default the compiler looks for one named "page".
- **published**: If missing the page is considered a draft and is hidden from feeds by default and content loaded via `ContentManager` and its helper functions.
- **language**: If missing the language will be set to what's defined in the content config.

## Page properties

The `Papyrus\Content\Page` class tries to only load data on-demand since a flat-file CMS application can otherwise get quite resource intensive if all pages are processed and kept in memory.

All properties and meta can be accessed using array access as well as tested with the methods `has()` and `is()`, which performs empty and boolean tests respectively. One can also call a method prepended with the test method names like `$page->hasTitle()` to check whether the meta property *title* is empty or not. Here's a list of what properties are set by default (refer to the advanced documentation for how to add custom properties):

- **relativePath** *(string)* - The file path relative from the content directory: `blog/index.md`
- **id** *(string)* - The unique identifier for the page: `/blog/index`
- **route** *(string)* - Id but without "index": `/blog/`
- **hashid** *(string)* - MD5 hash of the id: `7e1889003e5094afa723d24be7ce1357`
- **url** *(string)* - Absolute URL to the page: `http://example.com/blog/`
- **meta** *(Soma\Store)* - All meta properties can also be accessed directly from *Page* object
- **html** *(string)* - The rendered page contents
- **title** *(string)* - Either the meta property or the filename if it's missing
- **keywords** (array): Meta property or the top 10 keywords found from analyzing the content using [RakePlus](https://github.com/Donatello-za/rake-php-plus), unfortunately the library only supports the following language as of yet: en-US, es-AR, fr-FR, pl-PL, ru-RU
- **excerpt** *(string)* - Meta property or the first paragraph of the page text
- **author** *(string)* - Meta property or the config key `content.author`
- **updated** *(DateTime)* - Meta property or the file's modification time
- **modified** *(DateTime)* - The file's modification time
- **language** *(DateTime)* - Meta property or the config key `content.lang`
- **draft** *(bool)* - Whether the file is considered a draft or not, refer to the documentation further down for more information
- **image** *(Papyrus\Content\Image)* - Meta property or the first image found in the rendered contents
- **images** *(array of Papyrus\Content\Image)* - All images found in the rendered contents
- **raw** *(string)* - The unprocessed file contents
- **bare** *(string)* - The rendered page contents stripped from all HTML tags
- **body** *(string)* - The unprocessed body section of the file

The following are also inherited from `Papyrus\Content\File`:

- **filename** *(string)* - Just the basename without the file extension: `index`
- **basename** *(string)* - The basename: `index.md`
- **path** *(string)* - The full file path: `/path/to/content/blog/index.md`
- **extension** *(string)* - The file extension: `md`
- **directory** *(string)* - The directory containing the file: `/path/to/content/blog`

Since the *Page* class can be extended with additional features one might need to inspect an instance using the *tinker* command to get a more correct representation of the properties available.

```sh
php appctrl app:tinker
```

And then run `get_page('index');` &ndash; or alternatively export and dump it on a web request: `dd(get_page('index')->export())`.

## Filters

Some filters provide essential functionality while others merely add additional nice-to-haves. Custom ones can easily be created as well and the process is described in the advanced documentation.

### anchors

The anchor filter process links to other pages and static content and makes sure the URLs are set correctly.

### images

The filter processes all images and creates resized versions of each in order to be able to present them responsively to different devices.

### includes

The includes filter append or prepend the markdown or rendered html with user specified content. Both the *markdown* and *html* keys support both *before* and *after*:

```php
return [
    // ...
    'includes' => [
        'markdown' => [
            'after' => '/signature.md',
        ],
        'html' => [
            'before' => '/ads/advertisement.html'
        ],
    ],
    // ...
];
```

### hashtags

The hashtags filter searches the *page* contents for hashtags (e.g. #yolo) and wraps with an anchor the tag ("yolo") prepended with a user defined route:

```php
return [
    // ...
    'tag-route' => '/tag?t=',
    // ...
];
```

### heading-id

Processes all the headings found in the page content and adds slugified ids.

### heading-offset

Processes all the page contents and sets what the topmost heading type should be in a page. So if you write your content using `h1` for your title and `heading-offset` is set to 2 then that heading will be converted into a `h3` and all subheadings will be converted respectively. This is so that themes that display content in different ways and hierarchy can still produce correct HTML. The configuration key should be set by the theme in its manifest:

```yml
heading-offset: 2
```

### shortcodes

Enable the processing of shortcodes in the page markdown.

### smartypants

[SmartyPants](https://daringfireball.net/projects/smartypants/) is a library that translates plain ASCII punctuation characters into "smart" typographic punctuation HTML entities. It makes the typography of the rendered page contents more professional. There are edge-cases where the library doesn't work as it should, so if you find you're having trouble with how the pages render you may want to try turning this one off.

One important thing to keep in mind is that a triple dash (`---`) is normally interpreted as an `<hr>` but SmartyPants interprets double dash as an en-dash (&ndash;), therefore it's better to write hr's with a triple underscore (`___`) if using this filter.

## Routing

There is no need to define any routes for Papyrus since it uses the filesystem to create the site hierarchy. A file named `contact.md` at the content root will be presented at `/contact` and a file in `writings/` called `my-first-essay.md` will be presented at `/writings/my-first-essay`. Any directory containing a file called `index.md` will be presented at the root of that directory (`/`). So if you want to create different content types you can simply create a folder called "blog", create your blog posts as sub-resources and an index file as an archive.

```text
-- blog/
-- -- 201907/
-- -- -- image.jpg
-- -- -- my-first-post.md
-- -- index.md
-- index.md
-- about.md
-- contact.md
```

A file missing the *published* meta key will be treated as a draft and is not viewable unless drafts are enabled and `?preview=true` is added to the URL. If you prepend the file name with an underscore it will also be treated as a draft, e.g. `_my-first-post.md`, or if you set the meta property `draft: true`. If you have an underscored file with otherwise the same name as another file in the same folder it will be the unpublished draft of the live file. This is helpful since it allows you to work on a new revision of a file, and preview it, without accidentally publishing unfinished work. The draft is only presented if `?preview=true` otherwise the original is presented:

```text
-- blog/
-- -- 201907/
-- -- -- image.jpg
-- -- -- _my-first-post.md
-- -- -- my-first-post.md
-- -- index.md
```

If *published* is set to a future date it won't be considered published until the point in time has passed. You can also prepend folder names with an underscore to hide all content within. This is however not previewable:

```text
-- blog/
-- -- _unpublished-drafts/
-- -- -- not-previewable.md
```

The file name "default.md" is reserved by the system as it's used as a template when programmatically creating new pages in the same directory.

## Images

Papyrus provides a system to automatically process all images and linked files within a page's content. Absolute URLs are kept as is but local images within the content directory gets resized and processed to save bandwidth and enable presenting the most suitable size for each device. Links to other pages gets their proper absolute URL and PDF:s and other static gets copied to a public directory so that they can be downloaded.

`Papyrus\Content\Image` can load images from either remotely or from a theme or content directory and they will be processed and moved to a public cache directory. On string conversion it turns into a HTML *img* tag but one can also change attributes by rendering it via the *display* method.

```php
image('my-header.jpg')->display(['alt' => 'My Header']);
```

## Feed

You can easily define what content should be available in the site's main RSS feed using the *include* and *exclude* config keys. One can also define specific sub-feeds by setting the *feed* meta key: `feed: Pottery`. This will be available as a sub-resource: `http://www.example.com/feed/pottery`.

The system has been designed with podcasting in mind, to be able to attach media resources to feed items, but as of now it's not yet implemented. Please express if you have a need for it and the feature will prioritized.