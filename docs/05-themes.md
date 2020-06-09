# Themes

Perhaps controversially it's encouraged to include logic in the theme files and they can be installed, along with their dependencies, via composer. The theming engine is using a [Blade](https://laravel.com/docs/5.1/blade) compiler by default and thereby allowing for heavy use of logic in the templates. The target audience of this project is primarily developers so instead of having to create custom plugins for minor and/or major features, everything can easily baked into the theme so that there's only one project the developer needs to worry about. One might prefer separating out the logic into a separate project and that can be done easily by providing them through a service.

A typical theme would look something like this:

```text
MyTheme/
-- assets/
-- -- images/
-- -- -- logo.png
-- -- js/
-- -- -- main.js
-- -- scss/
-- -- -- main.scss
-- dist/
-- -- main.2ebbcecc.css
-- -- main.2f2121f2.js
-- -- logo.png
-- layouts/
-- -- bade.blade.php
-- lib/
-- -- MyThemeServiceProvider.php
-- 404.blade.php
-- contact.blade.php
-- index.blade.php
-- page.blade.php
-- post.blade.php
-- search.blade.php
-- tag.blade.php
-- composer.json
-- package.json
-- theme.yml
```

The only variables that gets set on a request and are available to the theme files are `$request`, an instance of `Symfony\Component\HttpFoundation\Request` for the current request, and `$page`, an instance of `Papyrus\Content\Page` for the current page. Everything else, all pages and menus, can be retrieved using simple helper functions or facades.

You can either create a theme from [the boilerplate](https://github.com/soma-php/papyrus-theme) or create everything from scratch. The `theme.yml` in the root of your theme defines where Papyrus can find the resources provided:

```yml
name: MyTheme
inherit: OtherTheme
engine: blade
public: ./dist
templates: ./
images:
  filters: ['\MyTheme\ImageFilter']
  sizes: ['576', '768', '992', '1200']
  rules: ['(max-width: 576px) 576px', '(max-width: 768px) 768px', '992px']
heading-offset: 2
```

The active theme is set by the config key `themes.active` and changing the theme requires running a `app:refresh` to update the filesystem links:

```sh
php appctrl app:refresh
```

## Properties

### inherit

The theme can inherit other themes and the parent themes will be searched recursively to find the requested template or asset. You can specify a specific theme's asset when including a file via the `theme_url` helper:

```php
$parentCss = theme_url('main.css', 'ParentTheme');
```

### engine

*engine* defines what compiler engine the theme is designed for. By default `blade` and `standard` are provided, the latter is simply including PHP or HTML files. You can easily add support for a custom template engine like [Twig](https://twig.symfony.com/) via a service provider.

### public

*public* will be linked to the public directory so that those files are made available. The active theme is set in `config/themes.php` and when it's changed you must run `php appctrl app:refresh`.

### templates

The key *templates* is where the compiler engine will look for the templates requested. The default template that is selected when no template can be found is `page` so make sure to always define a `page.blade.php` template in your theme.

### images

Papyrus automatically creates resized version of images you link to within your markdown files and generates responsive HTML for it. You can under *images* define either a custom rule that gets mapped to the attribute *sizes* or define an array of sizes that each image is resized to (*rules* => sizes, *sizes* => srcset). Callable *filters* with the following definition can also be set:

```php
function(\Papyrus\Content\Image $image) {

    // ...

    return $image;
}
```

### heading-offset

It's a key used by the *heading-offset* filter and sets what the topmost heading type will be in a page. So if you write your content using `h1` for your title and `heading-offset` is set to 2 then that heading will be converted into a `h3` and all subheadings will be converted respectively. This is so that themes that display content in different ways and hierarchy can still produce correct HTML.

## Starter theme

There's an MIT licensed Papyrus [starter theme](https://github.com/soma-php/papyrus-theme) that implements a simple Bootstrap 4 layout and showcasing most features on which you can build your own.