<?php namespace Papyrus\Commands;

use Exception;
use Soma\Command;
use Papyrus\Content\Page;

class ContentCompile extends Command
{
    protected $signature = 'content:compile {page?} {--force}';
    protected $description = 'Compile all Papyrus pages';

    public function handle()
    {
        $finder = app('content.filesystem');
        $force = $this->option('force', false);
        $term = $this->argument('page', false);

        if ($term) {
            // Single page
            if ($page = $finder->get($term, true)) {
                $this->info('Compiling '.$page->id);
                $page->compile();
                $this->info('Done!');
            }
            else {
                $this->error('Couldn\'t find "'.$term.'"');
                return 1;
            }
        }
        else {
            // All pages       
            $pages = $finder->scanPages();
            $compiled = 0;
            $skipped = 0;
            $this->info('Processing '.count($pages).' page(s)...');
            
            foreach ($pages as $path) {
                $page = new Page($path);

                if ($force || ! $page->validateCache(false)) {
                    $this->line('Compiling '.$page->id);
                    $page->compile();
                    $compiled++;
                }
                else {
                    $skipped++;
                }
            }

            $this->info('Compiled '.$compiled.' page(s)');
            $this->info('Skipped '.$skipped.' page(s)');
        }

        return 0;
    }
}