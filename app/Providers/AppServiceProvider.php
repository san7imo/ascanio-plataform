<?php

namespace App\Providers;

use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Policies\TenantPolicy;
use App\Modules\IdentityAccess\TenantContext;
use App\Modules\Parties\Models\TenantParty;
use App\Modules\Parties\Policies\TenantPartyPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        // Resolve on every event: daemon workers replace scoped bindings between
        // jobs. Capturing one context instance at boot would retain stale state.
        $clearContext = static fn () => app(TenantContext::class)->clear();
        Queue::before($clearContext);
        Queue::after($clearContext);
        Queue::exceptionOccurred($clearContext);
        Queue::failing($clearContext);
        Queue::looping($clearContext);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(TenantParty::class, TenantPartyPolicy::class);
        RateLimiter::for('workspace-governance', fn (Request $request) => Limit::perMinute(10)->by('governance:'.$request->user()->id));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
