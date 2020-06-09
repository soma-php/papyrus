<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use Papyrus\Content\Filter;
use Soma\Store;

class ShortcodeFilter extends Filter
{
    public function before(string $markdown, Store &$meta, ?SplFileInfo $file)
    {
        $shortcodes = app('content.shortcodes');

        if (! empty($shortcodes->getRegistered())) {
            return app('content.shortcodes')->doShortcode($markdown, null, true);
        }

        return $markdown;
    }
}