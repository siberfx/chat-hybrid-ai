<?php

namespace Siberfx\ChatHybridAi\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\ChatHybridAi\ChatHybridAiServiceProvider;
use Siberfx\ChatHybridAi\Facades\HybridChat;
use Siberfx\ChatHybridAi\Tests\Fixtures\FakeAgent;
use Siberfx\ChatHybridAi\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected FakeAgent $agent;

    protected function getPackageProviders($app): array
    {
        return [ChatHybridAiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('chat-hybrid-ai.ai.driver', 'fake');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAgent(new FakeAgent);

        Gate::define('viewHybridChat', fn (User $user) => $user->is_admin);
    }

    protected function useAgent(FakeAgent $agent): FakeAgent
    {
        $this->agent = $agent;
        HybridChat::ai()->forgetDrivers();
        HybridChat::extendAi('fake', static fn () => $agent);

        return $agent;
    }

    protected function admin(): User
    {
        return User::create(['name' => 'Ada Admin', 'email' => 'ada@example.com', 'is_admin' => true]);
    }
}
