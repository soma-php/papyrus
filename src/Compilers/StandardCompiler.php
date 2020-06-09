<?php

namespace Papyrus\Compilers;

use Exception;
use Symfony\Component\Finder\Finder;
use Papyrus\Theme;
use Papyrus\TemplateCompiler;

class StandardCompiler implements TemplateCompiler
{
    public function locate(string $template, Theme $theme) : ?string
    {
        if ($template == realpath($template)) {
            return $template;
        }

        $baseDirs = $theme->getTemplateDirs();
        $files = (new Finder)->in($baseDirs)
            ->name($template.'.php')
            ->files();

        if ($files->hasResults()) {
            return Arr::first($files);
        }

        return null;
    }

    public function render(string $template, array $data = [], Theme $theme) : string
    {
        $__path = $this->locate($template, $theme);

        try {
            if ($__path) {
                extract($data);
    
                ob_start();
                
                include $__path;
    
                $result = ob_get_contents();
                ob_end_clean();

                return $result;
            } else {
                throw new Exception('Template not found');
            }
        }
        catch (Exception $e) {
            if (is_debug()) {
                throw $e;
            }
        }
        
        return '';
    }
}