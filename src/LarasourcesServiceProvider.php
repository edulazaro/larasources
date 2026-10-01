<?php

namespace EduLazaro\Larasources;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\Models\SourceArgument;

class LarasourcesServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Relation::morphMap([
            'source' => SourceRecord::class,
            'source_argument' => SourceArgument::class,
        ]);

        // Publish migrations
        $this->publishes([
            __DIR__.'/database/migrations/' => database_path('migrations'),
        ], 'larasources-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        // The package ships no configuration: whatever an origin needs is the
        // integration's own, resolved by its `config()`.
    }
}