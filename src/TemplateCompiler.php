<?php

namespace Papyrus;

use Papyrus\Theme;

interface TemplateCompiler
{
    public function locate(string $template, Theme $theme) : ?string;

    public function render(string $template, array $data = [], Theme $theme) : string;
}