<?php namespace Papyrus\Content;

use Papyrus\Content\File;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Intervention\Image\Image as ImageInstance;
use Intervention\Image\ImageManagerStatic as ImageManager;

class Image extends File
{
    public $data = [];
    public $src;
    public $sizes;
    public $srcset;
    public $alt;
    public $title;
    public $class;
    public $width;
    public $height;
    public $loading;
    public $id;
    public $rel;

    protected static $filters = [];

    protected $instance;
    protected $hashid = null;
    protected $remote = false;
    protected $resizes = null;
    protected $rules = null;

    public static $defaultSizes = [];
    public static $defaultRules = [];

    public static $cacheDir;
    public static $cacheUrl;
    public static $quality = 90;
    public static $process = true;

    public function __construct($resource, $attributes = [])
    {
        foreach ($attributes as $attr => $val) {
            if (property_exists($this, $attr)) {
                $this->{$attr} = $val;
            }
        }

        if (is_url($resource)) {
            $this->remote = true;
            $this->src = $resource;
        }
        else {
            parent::__construct($resource);
        }
    }

    public function process() : Image
    {
        // Perform no processing if it's not a local file
        if ($this->remote) {
            return $this;
        }

        if (self::$process) {
            $hashid = $this->getHashId();

            // Get details of original image
            list($width, $height) = getimagesize($this->path);
            $this->width = $width;
            $this->height = $height;

            $real = [
                'width' => $this->width,
                'height' => $this->height
            ];

            $resizes = $this->resizes ?: self::$defaultSizes;
            $rules = $this->rules ?: self::$defaultRules;

            // Create resized versions
            if (! empty($resizes)) {
                $created = [];
                $srcset = "";
                $sizes = "";
                
                foreach ($resizes as $size) {
                    if (Str::contains($size, 'x')) {
                        list($width, $height) = explode('x', $size);
                        $new = [
                            'width' => $width,
                            'height' => $height
                        ];
                    }
                    else {
                        $new = [
                            'width' => $size,
                            'height' => null
                        ];
                    }

                    // Make sure we don't upscale but only make smaller versions
                    if ($new['width'] < $real['width']) {
                        $cacheRelPath = '/'.$hashid.'_'.$size.'.'.$this->extension;
                        $cachePath = self::$cacheDir.$cacheRelPath;

                        if (! file_exists($cachePath)) {
                            // After each resize we make sure we reload the
                            // original in order to preserve quality
                            $image = $this->getImageInstance(true);
                            $image->resize($new['width'], $new['height'], function ($constraint) {
                                $constraint->aspectRatio();
                            });
            
                            $image->save($cachePath, self::$quality);
                        }

                        $created[$new['width']] = $cacheRelPath;
                    }
                }

                // Create srcset and sizes attribute
                foreach ($created as $width => $cacheRelPath) {
                    $srcset .= self::$cacheUrl.$cacheRelPath.' '.$width.'w, ';
                    $sizes .= '(max-width: '.$width.'px) '.$width.'px, ';
                }

                // Move original image to public directory
                if (! file_exists($cachePath = self::$cacheDir.($cacheRelPath = '/'.$hashid.'.'.$this->extension))) {
                    $image = $this->getImageInstance(true);
                    $image->save($cachePath, self::$quality);
                }
                
                $srcset .= self::$cacheUrl.$cacheRelPath.' '.$real['width'].'w';
                $sizes .= $real['width'].'px';

                // Modify attributes
                if ($rules) {
                    $sizes = implode(', ', $rules);
                }

                $this->src = self::$cacheUrl.$cacheRelPath;
                $this->srcset = $srcset;
                $this->sizes = $sizes;
                $this->data['size'] = $real['width'].'x'.$real['height'];
            }
        }
        else {
            // Even if image processing is turned off we still need to move the original image to a public directory
            if (! file_exists($cachePath = self::$cacheDir.($cacheRelPath = '/'.$hashid.'.'.$this->extension))) {
                copy($this->path, $cachePath);
            }

            $this->src = self::$cacheUrl.$cacheRelPath;
        }

        return $this;
    }

    public function getImageInstance(bool $reload = false) : ?ImageInstance
    {
        if ($this->isRemote()) {
            return null;
        }
        if ($reload) {
            return $this->instance = ImageManager::make($this->path);
        }
        if (isset($this->instance)) {
            return $this->instance;
        }
        
        return $this->instance = ImageManager::make($this->path);
    }

    public function getHashId() : ?string
    {
        if ($this->hashid) {
            return $this->hashid;
        }
        if ($this->path) {
            return $this->hashid = md5($this->path);
        }
        if ($this->src) {
            return $this->hashid = md5($this->path);
        }
        
        return null;
    }

    public function isRemote() : bool
    {
        return $this->remote;
    }

    public function isLocal() : bool
    {
        return ! $this->remote;
    }

    public function display(array $overrides = []) : string
    {
        $this->process();

        // Merge default attributes with overrides
        $attr = $this->getAttributes(false);
        $attr = array_merge($attr, $overrides);

        // Filter
        $image = clone $this;

        foreach ($attr as $key => $val) {
            $image->{$key} = $val;
        }

        $image = self::filter($image);
        $attr = $image->getAttributes();

        // Construct tag
        $tag = '<img '.parse_attributes($attr).'>';

        return $tag;
    }

    public function getAttributes(bool $collapseData = true)
    {
        $attr = Arr::only(get_object_vars($this), [
            'src', 'alt', 'title', 'class', 'id', 'rel', 'data'
        ]);

        if ($this->srcset) {
            $attr['srcset'] = $this->srcset;
            $attr['sizes'] = $this->sizes ?: false;
        }
        elseif ($this->width || $this->height) {
            $attr['width'] = $this->width ?: false;
            $attr['height'] = $this->height ?: false;
        }

        // Convert the data array to individual attributes
        if ($collapseData) {
            foreach ($attr['data'] as $key => $val) {
                $attr['data-'.$key] = $attr['data'][$key];
            }

            unset($attr['data']);
        }

        return array_filter($attr);
    }

    public function processSize($size) : Image
    {
        $path = $this->getSizePath($size);

        if (! file_exists($path)) {
            if (Str::contains($size, 'x')) {
                list($width, $height) = explode('x', $size);
            }
            else {
                $width = $size;
                $height = null;
            }

            $image = $this->getImageInstance(true);

            $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            });

            $image->save($path, self::$quality);
        }

        return $this;
    }

    public function getPath() : string
    {
        return self::$cacheDir.'/'.$this->getHashId().'.'.$this->extension;
    }

    public function getUrl() : string
    {
        return self::$cacheUrl.'/'.$this->getHashId().'.'.$this->extension;
    }
    
    public function getSizePath($size) : string
    {
        return self::$cacheDir.'/'.$this->getHashId().'_'.$size.'.'.$this->extension;
    }

    public function getSizeUrl($size) : string
    {
        return self::$cacheUrl.'/'.$this->getHashId().'_'.$size.'.'.$this->extension;
    }

    public function getSize($size) : ?string
    {
        if ($this->isRemote()) {
            return null;
        }

        if (file_exists($this->getSizePath($size))) {
            return $this->getSizeUrl($size);;
        }
        else {
            return $this->processSize($size)->getSizeUrl($size);
        }
    }

    public static function filter(Image $image) : Image
    {
        foreach (self::$filters as $filter) {
            $image = call_user_func($filter, $image);
        }

        return $image;
    }

    public function __toString()
    {
        return $this->display();
    }

    public function resizes(array $sizes, array $rules = []) : Image
    {
        $this->resizes = $sizes;
        $this->rules = $rules;

        return $this;
    }

    public static function addFilter($name, ?callable $filter = null) : void
    {
        if (is_callable($name)) {
            $filter = $name;
            $name = 'filter-'.(count(self::$filters) + 1);
        }

        self::$filters[$name] = $filter;
    }

    public static function removeFilter($name) : void
    {
        if (isset(self::$filter[$name])) {
            unset(self::$filter[$name]);
        }
    }
}
