<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes([
            'middleware' => ['auth:sanctum'], // use API token auth
            'prefix' => 'api', // 👈 VERY IMPORTANT
        ]);

        require base_path('routes/channels.php');
    }
}
