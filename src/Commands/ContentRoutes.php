<?php namespace Papyrus\Commands;

use Exception;
use Soma\Command;

class ContentRoutes extends Command
{
    protected $signature = 'content:routes';
    protected $description = 'List all registered Papyrus web routes';

    public function handle()
    {
        $router = app('content.router');

        // Public routes
        $this->info("Public routes:");
        
        foreach ($router->all() as $route) {
            $this->line($route);
        }

        // Drafts
        $this->info("\nDraft routes:");

        foreach ($router->drafts() as $route) {
            $this->line($route);
        }

        return 0;
    }
}