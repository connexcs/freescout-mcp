<?php

namespace Modules\McpServer\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\McpServer\Services\McpServerFactory;

class McpServerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $autoload = dirname(__DIR__).'/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new \RuntimeException(
                'MCP Server dependencies are missing. Install the release package or run composer install in Modules/McpServer.'
            );
        }

        require_once $autoload;

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/config.php', 'mcpserver');

        $this->app->singleton(McpServerFactory::class, function ($app) {
            return new McpServerFactory($app['config']->get('mcpserver', []));
        });
    }

    public function boot()
    {
        $this->loadRoutesFrom(dirname(__DIR__).'/Http/routes.php');
    }
}
