<?php

namespace Siberfx\ChatHybridAi;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Siberfx\ChatHybridAi\Ai\AiManager;
use Siberfx\ChatHybridAi\View\Components\Bubble;

class ChatHybridAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chat-hybrid-ai.php', 'chat-hybrid-ai');

        $this->app->singleton(AiManager::class, fn ($app) => new AiManager($app));
        $this->app->singleton(ChatManager::class, fn ($app) => new ChatManager($app->make(AiManager::class)));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'chat-hybrid-ai');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('chat-hybrid-ai.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/chat-hybrid-ai.php');
        }

        Blade::component('chat-hybrid-ai-bubble', Bubble::class);

        $this->defineGate();
        $this->defineRateLimiters();
        $this->defineChannels();
        $this->offerPublishing();
    }

    /**
     * Who may use the admin inbox. Like Horizon, only "local" by default:
     * define your own "viewHybridChat" gate in AppServiceProvider.
     */
    protected function defineGate(): void
    {
        if (! Gate::has('viewHybridChat')) {
            Gate::define('viewHybridChat', fn ($user = null) => $this->app->environment('local'));
        }
    }

    protected function defineRateLimiters(): void
    {
        RateLimiter::for('chat-hybrid-ai-start', fn (Request $request) => Limit::perMinute(10)->by('chat-start|'.$request->ip()));

        RateLimiter::for('chat-hybrid-ai-transcript', fn (Request $request) => Limit::perMinutes(10, 3)
            ->by('chat-transcript|'.$request->route()?->originalParameter('conversation')));
    }

    protected function defineChannels(): void
    {
        if (! config('chat-hybrid-ai.broadcasting.enabled')) {
            return;
        }

        Broadcast::channel('chat.admin', fn ($user) => Gate::forUser($user)->allows('viewHybridChat'));
    }

    protected function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/chat-hybrid-ai.php' => config_path('chat-hybrid-ai.php'),
        ], 'chat-hybrid-ai-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'chat-hybrid-ai-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/chat-hybrid-ai'),
        ], 'chat-hybrid-ai-views');

        $this->publishes([
            __DIR__.'/../resources/js/client' => resource_path('js/vendor/chat-hybrid-ai/client'),
            __DIR__.'/../resources/js/react' => resource_path('js/vendor/chat-hybrid-ai/react'),
            __DIR__.'/../resources/js/vue' => resource_path('js/vendor/chat-hybrid-ai/vue'),
        ], 'chat-hybrid-ai-js');
    }
}
