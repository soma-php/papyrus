# Commands

Most commands available by default are provided by SOMA so please refer to its [documentation](https://soma-php.github.io/) for more information.

## content:compile

```text
php appctrl content:compile {page?} {--force}
```

A page can be specified using a content directory relative path or if none are provided then all files in the content directory will be cache validated and recompiled if required.

The *force* flag forces a recompilation.

## content:routes

```text
php appctrl content:routes
```

The command presents a formatted list of all publicy available route end-points. Route handles that are reserved by an external handler are marked with an appended ellipsis.