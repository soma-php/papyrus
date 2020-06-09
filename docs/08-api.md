# API

The API is still largely undocumented, it's a work in progress. However, Papyrus is a small project so it's quite easy to just refer to the [project source](https://github.com/soma-php/papyrus) if you need to look up how to use any of the classes.

## Systems

Papyrus is organized into two subsystems, themes and content. When you register `Papyrus\Providers\PapyrusProvider` it also adds `Papyrus\Providers\ContentProvider` and `Papyrus\Providers\ThemesProvider` as well as the default SOMA event system. The *PapyrusProvider* and the `Papyrus\Papyrus` class simply connects the two independent systems and resolves the current request.

## Managers

The *ShortcodeManager* is just a wrapper around [maiorano84/shortcodes](https://github.com/maiorano84/shortcodes) and all its features can be utilized by *ContentManager*. Almost everything is represented by a class in Papyrus and by using the *ContentManager* to load a *menu* or a *page* rather than simply creating an instance on your own you will have all system configuration and caching applied and handled for you automatically.

The *helpers* are the recommended way for theme developers to interface with the *ContentManager* since as a developer you won't have to reference the fully namespaced class name or require the user to alias the class.

The *ThemeManager* is utilized by Papyrus to render the response for the web request and would rarely, or if ever, need to be used by anyone manually. Instead there are helpers available and changing the active theme is done using a config file.