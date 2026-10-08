<?php

namespace App\Providers;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\User;
use App\Services\Auth\PermissionEvaluator;
use App\Services\Auth\ScopeContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ScopeContext::class);
        $this->app->singleton(PermissionEvaluator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Single catch-all Gate delegating to PermissionEvaluator.
        Gate::define('perform', function (
            User $user,
            ResourceType $resourceType,
            PermissionAction $action,
            Channel $channel,
            ?int $projectId = null,
            ?int $geographyNodeId = null,
        ) {
            return app(PermissionEvaluator::class)->can(
                $user, $resourceType, $action, $channel, $projectId, $geographyNodeId
            );
        });
    }
}
