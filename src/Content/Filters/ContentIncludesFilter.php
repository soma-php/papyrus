<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use DomDocument;
use Soma\Store;
use Papyrus\Content\Filter;
use Illuminate\Support\Arr;

class ContentIncludesFilter extends Filter
{
    public function before(string $markdown, Store &$meta, ?SplFileInfo $file)
    {
        $fs = app('content.filesystem');

        if ($includes = config('content.includes.markdown.before', false)) {
            foreach (Arr::wrap($includes) as $file) {
                if (! file_exists($file)) {
                    $file = $fs->getPath($file);
                }
                if ($file) {
                    $content = file_get_contents($file);
                    $markdown = $content.$markdown;
                }
            }
        }

        if ($includes = config('content.includes.markdown.after', false)) {
            foreach (Arr::wrap($includes) as $file) {
                if (! file_exists($file)) {
                    $file = $fs->getPath($file);
                }
                if ($file) {
                    $content = file_get_contents($file);
                    $markdown .= $content;
                }
            }
        }

        return $markdown;
    }

    public function after(string $html, Store &$meta, ?SplFileInfo $file)
    {
        $fs = app('content.filesystem');
        
        if ($includes = config('content.includes.html.before', false)) {
            foreach (Arr::wrap($includes) as $file) {
                if (! file_exists($file)) {
                    $file = $fs->getPath($file);
                }
                if ($file) {
                    $content = file_get_contents($file);
                    $html = $content.$html;
                }
            }
        }

        if ($includes = config('content.includes.html.after', false)) {
            foreach (Arr::wrap($includes) as $file) {
                if (! file_exists($file)) {
                    $file = $fs->getPath($file);
                }
                if ($file) {
                    $content = file_get_contents($file);
                    $html .= $content;
                }
            }
        }
        
        return $html;
    }
}