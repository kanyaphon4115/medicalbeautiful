<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::defaultView('pagination.numbers');
        \Illuminate\Pagination\Paginator::defaultSimpleView('pagination.numbers');
        \Illuminate\Support\Facades\Gate::policy(\App\Models\ChatMessage::class, \App\Policies\ChatMessagePolicy::class);
    }
}
