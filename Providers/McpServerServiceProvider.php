<?php

namespace Modules\McpServer\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\McpServer\Contracts\TokenRepository;
use Modules\McpServer\Contracts\ReadRepository;
use Modules\McpServer\Http\Middleware\AuthenticateMcpToken;
use Modules\McpServer\Http\Middleware\ThrottleMcpRequests;
use Modules\McpServer\Repositories\EloquentTokenRepository;
use Modules\McpServer\Repositories\FreeScoutReadRepository;
use Modules\McpServer\Security\McpRequestContext;
use Modules\McpServer\Security\TokenCodec;
use Modules\McpServer\Security\TokenPolicy;
use Modules\McpServer\Services\McpServerFactory;
use Modules\McpServer\Tools\ReadToolCatalogue;

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

        $this->app->singleton(TokenCodec::class, function ($app) {
            return new TokenCodec((string) $app['config']->get('mcpserver.token_pepper', ''));
        });
        $this->app->singleton(TokenPolicy::class);
        $this->app->singleton(McpRequestContext::class);
        $this->app->singleton(TokenRepository::class, EloquentTokenRepository::class);
        $this->app->singleton(ReadRepository::class, FreeScoutReadRepository::class);

        $this->app->singleton(McpServerFactory::class, function ($app) {
            return new McpServerFactory(
                $app['config']->get('mcpserver', []),
                $app->make(ReadToolCatalogue::class)
            );
        });

        $this->app['router']->aliasMiddleware('mcpserver.auth', AuthenticateMcpToken::class);
        $this->app['router']->aliasMiddleware('mcpserver.throttle', ThrottleMcpRequests::class);
    }

    public function boot()
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/Database/Migrations');
        $this->loadViewsFrom(dirname(__DIR__).'/Resources/views', 'mcpserver');
        $this->loadRoutesFrom(dirname(__DIR__).'/Http/routes.php');

        $this->registerUserMenu();
        $this->registerSettings();
    }

    private function registerUserMenu(): void
    {
        \Eventy::addAction('user.profile.menu.after_profile', function ($user) {
            $currentUser = auth()->user();
            if (null === $currentUser || ($currentUser->id != $user->id && !$currentUser->isAdmin())) {
                return;
            }

            echo view('mcpserver::tokens.menu', ['user' => $user])->render();
        }, 20, 1);
    }

    private function registerSettings(): void
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections['mcpserver'] = [
                'title' => __('MCP Server'),
                'icon' => 'transfer',
                'order' => 350,
            ];

            return $sections;
        }, 20, 1);

        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ('mcpserver' !== $section) {
                return $settings;
            }

            return [
                'mcpserver.personal_tokens_enabled' => \App\Option::get('mcpserver.personal_tokens_enabled', true),
                'mcpserver.allow_non_admin_tokens' => \App\Option::get('mcpserver.allow_non_admin_tokens', true),
                'mcpserver.token_lifetime_days' => \App\Option::get('mcpserver.token_lifetime_days', 90),
            ];
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ('mcpserver' !== $section) {
                return $params;
            }

            return [
                'validator_rules' => [
                    'settings.mcpserver\\.token_lifetime_days' => 'required|integer|min:0|max:3650',
                ],
                'settings' => [
                    'mcpserver.personal_tokens_enabled' => ['default' => true],
                    'mcpserver.allow_non_admin_tokens' => ['default' => true],
                    'mcpserver.token_lifetime_days' => ['default' => 90],
                ],
            ];
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            return 'mcpserver' === $section ? 'mcpserver::settings' : $view;
        }, 20, 2);
    }
}
