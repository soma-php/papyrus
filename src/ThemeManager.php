<?php namespace Papyrus;

use Exception;
use Symfony\Component\Finder\Finder;
use Nsrosenqvist\Blade;
use Papyrus\Theme;

use Papyrus\TemplateCompiler;

class ThemeManager
{
    protected $themesDir;
    protected $themesUrl;
    protected $data;
    protected $active;

    protected $compilers = [];

    public $themes = [];

    public function __construct($themesDir, $themesUrl, $activeTheme)
    {
        $this->themesDir = $themesDir;
        $this->themesUrl = $themesUrl;
        $this->active = $activeTheme;

        // Load themes
        $files = (new Finder)->in($this->themesDir)
            ->name('/theme\.(php|json|yml|ini)$/')
            ->depth('== 1')
            ->followLinks()
            ->files();

        foreach ($files as $file) {
            $manifest = $file->getPathname();
            $path = $file->getPath();
            $name = basename($path);

            $this->themes[$name] = new Theme($name, $path, $manifest);
        }

        // Map parents
        foreach ($this->themes as $name => $theme) {
            $parent = $theme->getMeta('inherit');

            if (! is_null($parent) && isset($this->themes[$parent])) {
                $theme->setParent($this->themes[$parent]);
            }
        }
    }

    public function registerCompiler(string $name, TemplateCompiler $compiler) : ThemeManager
    {
        $this->compilers[$name] = $compiler;

        return $this;
    }

    public function getCompiler($name = null) : ?TemplateCompiler
    {
        if (is_null($name)) {
            $theme = $this->getActiveTheme();
            $name = $theme->getCompilerEngine();
        }

        return $this->compilers[$name] ?? null;
    }

    public function url($file, $theme = null) : string
    {
        if (is_null($theme)) {
            $theme = $this->getActiveTheme();
        } else {
            $theme = $this->getTheme($theme);
        }

        foreach ($theme->getPublicDirs() as $name => $dir) {
            if (file_exists($path = $dir.'/'.$file)) {
                return $this->themesUrl.'/'.$name.'/'.$file;
            }
        }

        return '';
    }

    public function path($file, $theme = null) : string
    {
        if (is_null($theme)) {
            $theme = $this->getActiveTheme();
        } else {
            $theme = $this->getTheme($theme);
        }

        foreach ($theme->getPublicDirs() as $name => $dir) {
            if (file_exists($path = $dir.'/'.$file)) {
                return $this->themesDir.'/'.$name.'/'.$file;
            }
        }

        return '';
    }

    public function exists($theme) : bool
    {
        return isset($this->themes[$theme]);
    }

    public function getTheme($theme = null) : ?Theme
    {
        if ($theme instanceof Theme) {
            return $theme;
        } elseif (is_null($theme)) {
            return $this->getActiveTheme();
        } else {
            return $this->themes[$theme] ?? null;
        }
    }

    public function locate($template, Theme $theme = null) : ?string
    {
        return $this->getCompiler()->locate($template, $theme ?? $this->getActiveTheme());
    }

    public function render($template, $data = []) : string
    {
        if (empty($compiler = $this->getCompiler())) {
            throw new Exception("No compiler has been set");
        }

        $theme = $this->getActiveTheme();
        
        return $compiler->render($template, $data, $theme);
    }

    public function getActiveTheme() : ?Theme
    {
        return $this->themes[$this->active] ?? null;
    }
}