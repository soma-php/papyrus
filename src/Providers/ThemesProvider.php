<?php namespace Papyrus\Providers;

use Soma\Store;
use Soma\ServiceProvider;
use Psr\Container\ContainerInterface;

use Papyrus\ThemeManager;
use Papyrus\Compilers\StandardCompiler;
use Papyrus\Compilers\BladeCompiler;

class ThemesProvider extends ServiceProvider
{
    public function install(ContainerInterface $c)
    {
        // Make sure directories exist
        ensure_dir_exists(get_path('themes.public'));
        ensure_dir_exists(get_path('cache.themes'));

        // Link theme's public dir and all parents'
        $publicDir = get_path('themes.public');
        $theme = $c->get('themes')->getActiveTheme();

        foreach ($theme->getPublicDirs() as $name => $path) {
            $linkPath = $publicDir.'/'.$name;

            if (! file_exists($linkPath)) {
                symlink($path, $linkPath);
            }
        }
    }

    public function uninstall(ContainerInterface $c)
    {
        // Remove symlinks from public dir
        $publicDir = get_path('themes.public');

        if (is_dir($publicDir)) {
            foreach (array_diff(scandir($publicDir), ['..', '.']) as $themeLink) {
                if (is_link($linkPath = $publicDir.'/'.$themeLink)) {
                    unlink($linkPath);
                }
            }
        }

        // Delete directories
        runlink(get_path('themes.public'));
        runlink(get_path('cache.themes'));
    }

    public function refresh(ContainerInterface $c)
    {
        $this->uninstall($c);
        $this->install($c);
    }

    public function getExtensions() : array
    {
        return [
            'paths' => function(Store $paths, ContainerInterface $c) {
                $paths['themes'] = env('APP_THEMES', null);
                $paths['themes.public'] = $paths['root'].'/themes';
                $paths['cache.themes'] = $paths['cache'].'/themes';

                return $paths;
            },
            'urls' => function(Store $urls, ContainerInterface $c) {
                $urls['themes'] = $urls['root'].'/themes';

                return $urls;
            },
        ];
    }

    public function getFactories() : array
    {
        return [
            'themes' => function(ContainerInterface $c) {
                $themesDir = $c->get('paths')->get('themes');
                $themesUrl = $c->get('urls')->get('themes');
                $activeTheme = $c->get('config')->get('themes.active');

                $themes = new ThemeManager($themesDir, $themesUrl, $activeTheme);

                // PHP engine
                $compiler = new StandardCompiler();
                $themes->registerCompiler('standard', $compiler);

                // Blade
                $theme = $themes->getActiveTheme();
                $cachePath = $c->get('paths')->get('cache.themes');
                $baseDirs = is_null($theme) ? [] : $theme->getTemplateDirs();
                
                $compiler = new BladeCompiler($cachePath, $baseDirs);
                $themes->registerCompiler('blade', $compiler);

                return $themes;
            },
        ];
    }
}
