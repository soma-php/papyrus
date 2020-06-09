<?php namespace Papyrus;

use Soma\Manifest;
use Nsrosenqvist\Blade;

class Theme
{
    protected $name;
    protected $manifest;
    protected $parent;
    protected $path;
    protected $meta;

    public function __construct($name, $path, $manifest)
    {
        $this->name = $name;
        $this->manifest = $manifest;
        $this->path = $path;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getPath() : string
    {
        return $this->path;
    }

    public function getCompilerEngine() : string
    {
        return $this->getMeta('engine') ?: 'standard';
    }

    public function getBaseDirs() : array
    {
        $theme = $this;
        $baseDirs = [$theme->getName() => $theme->getPath()];

        while ($theme->hasParent()) {
            $theme = $theme->getParent();
            $name = $theme->getName();
            $baseDirs[$name] = $theme->getPath();
        }

        return $baseDirs;
    }

    public function getPublicDirs() : array
    {
        $theme = $this;
        $publicDirs = [$theme->getName() => $theme->getPublicPath()];

        while ($theme->hasParent()) {
            $theme = $theme->getParent();
            $name = $theme->getName();
            $publicDirs[$name] = $theme->getPublicPath();
        }

        return array_filter($publicDirs);
    }

    public function getTemplateDirs() : array
    {
        $theme = $this;
        $templateDirs = [$theme->getName() => $theme->getTemplatePath()];

        while ($theme->hasParent()) {
            $theme = $theme->getParent();
            $name = $theme->getName();
            $templateDirs[$name] = $theme->getTemplatePath();
        }

        return $templateDirs;
    }

    public function getPublicPath() : ?string
    {
        if ($publicPath = $this->getMeta('public')) {
            $publicPath = ltrim($publicPath, './');

            return $this->path.'/'.$publicPath;
        }

        return null;
    }

    public function getTemplatePath() : string
    {
        if ($templatePath = $this->getMeta('templates')) {
            $templatePath = ltrim($templatePath, './');

            return $this->path.'/'.$templatePath;
        }

        return $this->path;
    }

    public function getMeta(string $key = null, $default = null)
    {
        $meta = $this->meta ?? $this->meta = new Manifest($this->manifest, true);

        if (! is_null($key)) {
            return $meta->get($key, $default);
        }
        
        return $meta;
    }

    public function hasParent() : bool
    {
        return boolval($this->getParent());
    }

    public function getParent() : ?Theme
    {
        return $this->parent;
    }

    public function setParent(Theme $parent) : Theme
    {
        $this->parent = $parent;

        return $this;
    }
}