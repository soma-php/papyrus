<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use DOMDocument;
use Soma\Store;
use Papyrus\Content\Filter;

use Michelf\SmartyPants;

class SmartyPantsFilter extends Filter
{
    public function before(string $markdown, Store &$meta, ?SplFileInfo $file)
    {
        return SmartyPants::defaultTransform($markdown);
    }
}