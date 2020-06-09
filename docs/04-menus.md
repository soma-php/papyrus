# Menus

Menus are also created using YAML and resolved using dot-notation syntax. So if you want to load a menu at `menus/main.yml` you call `get_menu('menus.main')`. You can also create a master file in the content root called `menus.yml` and define multiple entries in this one file and access them as if each is a file in the content root:

```yml
main:
    # ...
sidebar:
    # ... 
```

The menu format is as follows. For each item you can either reference the file under *page* or manually specifying a *url*. Any unsupported key will be available for you as a key on each item, so you can easily define icons or any other property directly here in the file.

A *page* key will be resolved into the following: *url* to the page, the *route*, and the *label* will be set to the page title unless label is already defined on the item.

Any item can have the key *children* set which creates a hierarchy of items. Other ways to do so is by setting *list* and it will resolve a query for all pages under that route and populate *children*. When using *list* you can also set *sort* and *order*, as well as the *depth* that you want the query to process and if you want the result presented as a *flat* single level list rather than a hierarchy. One can also *limit* lists to only return a set number of items.

Here is an example of what a menu file could look like:

**main.yml**
```yml
- page: /
  label: Home
- page: /about.md
- page: /contact.md
- url: "http://github.com/john-doe"
  label: "My GitHub"
- list: /blog/
  depth: 2
  flat: true
  sort: published
  order: desc
  limit: 10
  label: "Recent blog posts"
- label: "My other writings"
    children:
      - list: /essays/
        label: Essays
      - url: "http://example.com"
        label: "My other site"
```