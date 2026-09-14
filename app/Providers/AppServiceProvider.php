<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Church;
use App\Models\Department;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Position;
use App\Models\User;
use App\PermissionLevel;
use App\Policies\AreaPolicy;
use App\Policies\ChurchPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\FinancialTransactionPolicy;
use App\Policies\MemberPolicy;
use App\Policies\PositionPolicy;
use App\Policies\UserPolicy;
use App\Services\PermissionService;
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
        Gate::define(
            'view-dashboard',
            fn (User $user): bool => $this->app->make(PermissionService::class)->can($user, 'dashboard', PermissionLevel::Read),
        );

        Gate::policy(Area::class, AreaPolicy::class);
        Gate::policy(Church::class, ChurchPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(FinancialTransaction::class, FinancialTransactionPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::define(
            'view-localities',
            fn (User $user): bool => $user->can('viewAny', Church::class)
                || $user->can('viewAny', Member::class),
        );
    }
}
