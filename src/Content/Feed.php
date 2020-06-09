<?php namespace Papyrus\Content;

use Papyrus\TemplateCompiler;
use Papyrus\Theme;

class Feed
{
    protected $name;
    protected $pages;
    protected $url;
    protected $copyright;
    protected $category;
    protected $language;
    protected $image;

    public function __construct($pages, $name, $description, $url = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->pages = collect($pages);
        $this->url = $url;
    }

    public function name()
    {
        return $name;
    }

    public function url()
    {
        return $this->url;
    }

    public function isEmpty()
    {
        return $this->pages->isEmpty();
    }

    public function setImage($url, $title, $link)
    {
        $this->image = compact('url', 'title', 'link');
    }

    public function setLanguage($lang)
    {
        $this->language = $lang;
    }

    public function setCopyright($copy)
    {
        $this->copyright = $copy;
    }

    public function setCategory($category)
    {
        $this->category = $category;
    }

    public function content(TemplateCompiler $compiler = null, Theme $theme = null)
    {
        $mode = strtolower(config('content.feed.mode', 'excerpt'));

        $rss = '<?xml version="1.0" encoding="utf-8"?>';
        $rss .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
        $rss .= '<channel>';
        $rss .= '<atom:link href="'.$this->url.'" rel="self" type="application/rss+xml" />';
        $rss .= '<title>'.$this->name.'</title>';
        $rss .= '<description>'.$this->description.'</description>';
        
        if ($this->language) {
            $rss .= '<language>'.$this->language.'</language>';
        }
        if ($this->copyright) {
            $rss .= '<copyright>'.$this->copyright.'</copyright>';
        }
        if ($this->category) {
            $rss .= '<category>'.$this->category.'</category>';
        }
        if ($this->image) {
            $rss .= '<image>';
            $rss .= (isset($this->image['url'])) ? '<url>'.$this->image['url'].'</url>' : '';
            $rss .= (isset($this->image['title'])) ? '<title>'.$this->image['title'].'</title>' : '';
            $rss .= (isset($this->image['link'])) ? '<link>'.$this->image['link'].'</link>' : '';
            $rss .= '</image>';
        }

        foreach ($this->pages as $page) {
            if ($compiler && $theme && $template = $compiler->locate('feed', $theme)) {
                $body = $compiler->render($template, compact('mode', 'page'));
            }
            else {
                $body = ($mode == 'full') ? $page->html : $page->excerpt;
            }

            $rss .= '<item>';
            $rss .= '<title>'.$page->title.'</title>';
            $rss .= '<description><![CDATA['.htmlspecialchars($body).']]></description>';
            $rss .= '<link>'.$page->url.'</link>';
            $rss .= '<pubDate>'.format_date($page->published, 'r').'</pubDate>';
            $rss .= '<guid>'.$page->hashid.'</guid>';

            if ($page->hasEnclosure()) {
                $rss .= '<enclosure '.parse_attributes($page->enclosure).'/>';
            }

            $rss .= '</item>';
        }

        $rss .= '</channel>';
        $rss .= '</rss>';

        return $rss;
    }
}
