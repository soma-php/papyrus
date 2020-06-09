<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use Soma\Store;
use Papyrus\Content\Filter;
use Illuminate\Support\Str;

class HeadingOffsetFilter extends Filter
{
    public function before(string $markdown, Store &$meta, ?SplFileInfo $file)
    {
        $offset = app('themes')->getActiveTheme()->getMeta('heading-offset') ?: null;
        $offset = $offset ?? config('content.heading-offset', 0);

        if ($offset) {
            $markdown = str_replace("\r\n", "\n", $markdown);
            $line = strtok($markdown, "\n");
            $processed = [];
            $offset = min($offset, 6);

            while ($line !== false) {
                if (Str::startsWith($line, '#')) {
                    $processed[] = str_repeat('#', $offset).$line;
                }
                else {
                    $processed[] = $line;
                }

                $line = strtok("\n");
            }

            $markdown = implode("\n", $processed);
        }

        return $markdown;
    }
}