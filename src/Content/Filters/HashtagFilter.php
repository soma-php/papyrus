<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use Papyrus\Content\Filter;
use Soma\Store;

class HashtagFilter extends Filter
{
    public function after(string $html, Store &$meta, ?SplFileInfo $file)
    {
        $route = ltrim(config('content.tag-route', ''), '/');
        $html = preg_replace('/(?:^|\s)#([a-zA-Z0-9_-]+)/', ' <a href="'.app_url($route).'$1">#$1</a>', $html);

        return $html;
    }
}