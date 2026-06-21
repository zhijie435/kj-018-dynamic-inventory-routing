<?php

namespace App\Providers;

use App\Models\Channel;
use App\Models\InventorySource;
use App\Policies\ChannelPolicy;
use App\Policies\InventorySourcePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(InventorySource::class, InventorySourcePolicy::class);
    }
}
