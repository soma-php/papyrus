<?php

namespace Papyrus\Compilers;

use Exception;
use Papyrus\Theme;
use Papyrus\TemplateCompiler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder;
use NSRosenqvist\Blade\Compiler as Blade;

class BladeCompiler implements TemplateCompiler
{
    protected $blade;

    public function __construct($cacheDir, $baseDirs)
    {
        $finder = new FileViewFinder(new Filesystem, $baseDirs);

        $this->blade = new Blade($cacheDir, $finder);
    }

    public function locate(string $template, Theme $theme) : ?string
    {
        try {
            return $this->blade->find($template);
        } catch (Exception $e) {
            return null;
        }
    }

    public function render(string $template, array $data = [], Theme $theme) : string
    {
        try {
            return $this->blade->render($template, $data);
        } catch (Exception $e) {
            if (is_debug()) {
                throw $e;
            } else {
                return "";
            }
        }
        
    }
}