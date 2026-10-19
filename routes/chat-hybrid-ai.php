<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Siberfx\ChatHybridAi\Http\Controllers\AdminController;
use Siberfx\ChatHybridAi\Http\Controllers\WidgetController;
use Siberfx\ChatHybridAi\Http\Middleware\VerifyConversationToken;

$widget = config('chat-hybrid-ai.routes.widget');

Route::prefix($widget['prefix'])
    ->middleware([...$widget['middleware'], SubstituteBindings::class])
    ->name('chat-hybrid-ai.widget.')
    ->group(function () {
        Route::get('widget.js', [WidgetController::class, 'script'])->name('script');
        Route::get('config', [WidgetController::class, 'config'])->name('config');
        Route::post('conversations', [WidgetController::class, 'start'])->middleware('throttle:chat-hybrid-ai-start')->name('start');

        Route::prefix('conversations/{conversation}')
            ->middleware(VerifyConversationToken::class)
            ->group(function () {
                Route::get('/', [WidgetController::class, 'show'])->name('show');
                Route::patch('/', [WidgetController::class, 'update'])->name('update');
                Route::get('messages', [WidgetController::class, 'messages'])->name('messages');
                Route::post('messages', [WidgetController::class, 'send'])->name('send');
                Route::post('read', [WidgetController::class, 'read'])->name('read');
                Route::post('human', [WidgetController::class, 'human'])->name('human');
                Route::post('close', [WidgetController::class, 'close'])->name('close');
                Route::post('transcript', [WidgetController::class, 'transcript'])->middleware('throttle:chat-hybrid-ai-transcript')->name('transcript');
            });
    });

$adminApi = function () {
    Route::get('config', [AdminController::class, 'config'])->name('config');
    Route::get('stats', [AdminController::class, 'stats'])->name('stats');
    Route::get('conversations', [AdminController::class, 'conversations'])->name('conversations');
    Route::get('conversations/{conversation}', [AdminController::class, 'show'])->name('show');
    Route::delete('conversations/{conversation}', [AdminController::class, 'destroy'])->name('destroy');
    Route::get('conversations/{conversation}/messages', [AdminController::class, 'messages'])->name('messages');
    Route::post('conversations/{conversation}/messages', [AdminController::class, 'send'])->name('send');
    Route::post('conversations/{conversation}/take-over', [AdminController::class, 'takeOver'])->name('take-over');
    Route::post('conversations/{conversation}/return-to-ai', [AdminController::class, 'returnToAi'])->name('return-to-ai');
    Route::post('conversations/{conversation}/close', [AdminController::class, 'close'])->name('close');
    Route::post('conversations/{conversation}/reopen', [AdminController::class, 'reopen'])->name('reopen');
    Route::post('conversations/{conversation}/transcript', [AdminController::class, 'transcript'])->name('transcript');
};

// Admin inbox page and the session-authenticated API it uses.
$admin = config('chat-hybrid-ai.routes.admin');

Route::prefix($admin['prefix'])
    ->middleware([...$admin['middleware'], 'can:viewHybridChat'])
    ->name('chat-hybrid-ai.admin.')
    ->group(function () use ($adminApi) {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::prefix('api')->group($adminApi);
    });

// The same admin API for token clients (SPAs, mobile apps), e.g. Sanctum.
$token = config('chat-hybrid-ai.routes.admin_api');

if ($token['enabled'] ?? false) {
    Route::prefix($token['prefix'])
        ->middleware([...$token['middleware'], SubstituteBindings::class, 'can:viewHybridChat'])
        ->name('chat-hybrid-ai.admin-api.')
        ->group($adminApi);
}
