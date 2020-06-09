<?php

namespace Papyrus\Content\Filters;

use SplFileInfo;
use DomDocument;
use Soma\Store;
use Papyrus\Content\Image;
use Papyrus\Content\Filter;

use Exception;

class ImageFilter extends Filter
{
    public function after(string $html, Store &$meta, ?SplFileInfo $file)
    {
        $dir = $file->getPath();
        $dom = new DomDocument();
        @ $dom->loadHTML('<div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $imgs = $dom->getElementsByTagName('img');

        if (! empty($imgs)) {
            foreach ($imgs as $img) {
                $src = $img->getAttribute('src');
                $alt = $img->getAttribute('alt');

                if (! is_url($src)) {
                    $src = realpath($dir.'/'.$src);
                }

                try {
                    $image = Image::filter((new Image($src, ['alt' => $alt]))->process());

                    if ($img->hasAttribute('src')) {
                        $img->removeAttribute('src');
                    }

                    foreach ($image->getAttributes() as $key => $val) {
                        $img->setAttribute($key, $val);
                    }
                }
                catch (Exception $e) {}

                    // try {
                    //     $image = Image::filter((new Image($path, ['src' => $src, 'alt' => $alt]))->process());
                        
                        
                        
                    //     $img->setAttribute('srcset', $image->srcset);
                    //     $img->setAttribute('sizes', $image->sizes);
                    //     $img->setAttribute('src', $image->src);
                    //     $img->setAttribute('alt', $image->alt);
                    //     $img->setAttribute('data-size', $image->data['size'] ?? '');
                    // }
                    // catch (Exception $e) {}
                // }
            }
        }

        // Remove wrapper div
        $html = @ $dom->saveHTML();
        $html = substr($html, 5);
        $html = substr($html, 0, -7);

        return $html;
    }
}