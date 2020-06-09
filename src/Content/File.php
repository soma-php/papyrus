<?php namespace Papyrus\Content;

use InvalidArgumentException;
use SplFileInfo;

class File
{
    protected $resource;

    public $filename;
    public $basename;
    public $path;
    public $extension;
    public $directory;

    public function __construct($resource)
    {
        if (is_string($resource)) {
            $resource = new SplFileInfo($resource);
        }
        if (! $resource instanceof SplFileInfo) {
            throw new InvalidArgumentException(self::class.' must be created from a file path or SplFileInfo');
        }

        $this->path = remove_double_slashes($resource->getPath().'/'.$resource->getBasename());
        $this->basename = $resource->getBasename();
        $this->extension = $resource->getExtension();
        $this->filename = $resource->getBasename('.'.$this->extension);
        $this->directory = $resource->getPath();

        if (! file_exists($this->path)) {
            throw new InvalidArgumentException("File doesn't exist");
        }
    }
}
