<?php namespace Papyrus\Content;

use DateTime;
use DOMDocument;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use ParsedownExtra as Parsedown;
use DonatelloZa\RakePlus\RakePlus;

class Common
{
    const RAKEPLUS_LANG = ['en_US', 'es_AR', 'fr_FR', 'pl_PL', 'ru_RU'];

    public function _title()
    {
        return fn($html, $meta) => $meta->get('title', $this->filename);
    }

    public function _keywords()
    {
        return function($html, $meta) {
            $keywords = $meta->get('keywords', []);

            // Attempt using RakePlus to extract keywords
            if (empty($keywords)) {
                $lang = str_replace('-', '_', $meta->get('language', config('content.language', 'en_US')));

                if (in_array($lang, Common::RAKEPLUS_LANG)) {
                    $bare = strip_bare($html);
                    $keywords = RakePlus::create($bare, $lang, 3)->sortByScore('desc')->get();
                    $keywords = array_slice($keywords, 0, 10);
                }
            }

            return $keywords;
        };
    }

    public function _excerpt()
    {
        return function($html, $meta) {
            // Either return custom defined excerpt or extract from HTML
            if ($excerpt = $meta->get('excerpt')) {
                $excerpt = Parsedown::instance()->text($excerpt);
            }
            else {
                $first = strpos($html, '<p');
                $excerpt = substr($html, $first, strpos($html, '</p>') - $first);
                $excerpt = substr($excerpt, strpos($excerpt, '>') + 1);
            }

            return $excerpt;
        };
    }

    public function _author()
    {
        return fn($html, $meta) => $meta->get('author', config('content.author', null));
    }

    public function _updated()
    {
        return fn($html, $meta) => $meta->get('updated', null) ?? make_datetime(filemtime($this->path));
    }

    public function _modified()
    {
        return fn($html, $meta) => make_datetime(filemtime($this->path));
    }

    public function _language()
    {
        return fn($html, $meta) => $meta->get('language', config('content.language', 'en_US'));
    }

    public function _draft()
    {
        return function($html, $meta) {
            if (Str::startsWith($this->basename, '_')) {
                return true;
            }
            if (($published = $meta->get('published', false)) && $published > new DateTime()) {
                return true;
            }

            return false;
        };
    }

    public function image_()
    {
        return function() {
            $image = $this->meta->get('image') ?: Arr::first($this->images) ?: null;

            if (! $image instanceof Image && is_string($image)) {
                try {
                    if (! is_url($image) && ! file_exists($image)) {
                        $image = new Image(realpath($this->directory.'/'.$image));
                    } else {
                        $image = new Image($image);
                    }
                }
                catch (Exception $e) {
                    $image = null;
                }
            }

            return $image;
        };
    }

    public function images_()
    {
        return function() {
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
        };
    }  
}