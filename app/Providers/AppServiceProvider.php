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
        $this->app->bind(
            \App\Services\Backup\Replication\BackupReplicationInterface::class,
            \App\Services\Backup\Replication\LocalSecondaryReplicationProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Document::class, \App\Policies\DocumentPolicy::class);
    }
}
