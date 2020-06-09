<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use DOMDocument;
use Soma\Store;
use Illuminate\Support\Str;
use Papyrus\Content\Filter;

class AnchorFilter extends Filter
{
    public function after(string $html, Store &$meta, ?SplFileInfo $file)
    {
        if (is_null($file)) {
            return $html;
        }

        $finder = app('content.filesystem');
        $contentRoot = $finder->getRootPath();

        $dir = $file->getPath();
        $path = $file->getPathname();
        $dom = new DOMDocument();
        @ $dom->loadHTML('<div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $anchors = $dom->getElementsByTagName('a');
        $cachePathBase = get_path('cache.resources');
        $cacheUrlBase = get_url('cache.resources');

        if (! empty($anchors)) {
            foreach ($anchors as $a) {
                $href = $a->getAttribute('href');
                $resource = realpath($dir.'/'.$href);

                if (! is_url($href) && ! Str::startsWith($href, '#')) {
                    // URI to page
                    if ($page = $finder->get($href)) {
                        $a->setAttribute('href', $page->url);
                    }
                    // Relative URI to page
                    elseif ($page = $finder->get(rel_path(canonicalize_path($contentRoot.'/'.$resource), $contentRoot))) {
                        $a->setAttribute('href', $page->url);
                    }
                    // Relative URI to file
                    elseif (! file_exists($resource)) {
                        $filename = basename($resource);
                        
                        $relCacheDir = md5($resource);
                        $relCachePath = $relCacheDir.'/'.$filename;
                        $cacheDir = ensure_dir_exists($cachePathBase.'/'.$relCacheDir);
                        $cachePath = $cacheDir.'/'.$filename;
                        $cacheUrl = $cacheUrlBase.'/'.$relCachePath;

                        copy($resource, $cachePath);
                        $a->setAttribute('href', $cacheUrl);
                    }
                }
            }
        }

        // Remove wrapper div
        $html = @ $dom->saveHTML();
        $html = substr($html, 5);
        $html = substr($html, 0, -7);

        return $html;
    }
}