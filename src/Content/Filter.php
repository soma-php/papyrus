<?php

namespace Papyrus\Content;

use SplFileInfo;
use Soma\Store;

abstract class Filter
{
    public function before(string $markdown, Store &$meta, ?SplFileInfo $file)
    {
        return $markdown;
    }

    public function after(string $html, Store &$meta, ?SplFileInfo $file)
    {
        return $html;
    }
}