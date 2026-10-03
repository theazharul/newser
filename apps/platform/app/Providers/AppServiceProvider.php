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
        // Platform-controlled migrations live in database/migrations/platform/ (docs/backend/migrations.md).
        $this->loadMigrationsFrom(database_path('migrations/platform'));
    }
}
