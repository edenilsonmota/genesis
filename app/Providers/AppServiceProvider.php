<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Church;
use App\Models\User;
use App\Policies\AreaPolicy;
use App\Policies\ChurchPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::define(
            'global-administrator',
            fn (User $user): bool => $user->isGlobalAdministrator(),
        );

        Gate::policy(Area::class, AreaPolicy::class);
        Gate::policy(Church::class, ChurchPolicy::class);
    }
}
