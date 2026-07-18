<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Client;
use Laravel\Mcp\Facades\Mcp;

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
        // Registered once, by name, instead of connecting from inside the
        // agent every time it needs a tool. In production this would be
        // Client::web('https://...'), reaching a server this application
        // does not control; the local, stdio-based server it points to
        // here (see routes/ai.php) is a stand-in kept for this book's
        // example, so it runs the same way for every reader.
        Mcp::registerClient('exchange-rates', fn () => Client::local('php', ['artisan', 'mcp:start', 'exchange-rates']));
    }
}
