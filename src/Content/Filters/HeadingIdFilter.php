<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use Soma\Store;
use Illuminate\Support\Str;
use Papyrus\Content\Filter;

class HeadingIdFilter extends Filter
{
    public function after(string $html, Store &$meta, ?SplFileInfo $file)
    {
        $pattern = '#(?P<full_tag><(?P<tag_name>h\d)(?P<tag_extra>[^>]*)>(?P<tag_contents>[^<]*)</h\d>)#i';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            $find = [];
            $replace = [];

            foreach ($matches as $match) {
                if (strlen($match['tag_extra']) && false !== stripos($match['tag_extra'], 'id=')) {
                    continue;
                }

                $find[]    = $match['full_tag'];
                $id        = Str::slug($match['tag_contents']);
                $id_attr   = sprintf(' id="%s"', $id);
                $replace[] = sprintf('<%1$s%2$s%3$s>%4$s</%1$s>', $match['tag_name'], $match['tag_extra'], $id_attr, $match['tag_contents']);
            }

            $html = str_replace($find, $replace, $html);
        }

        return $html;
    }
}