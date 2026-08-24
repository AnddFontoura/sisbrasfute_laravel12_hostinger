<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\PaymentGatewayContract::class,
            \App\Service\Payment\NullPaymentGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addDays(15));
        Passport::loadKeysFrom(storage_path());

        $paths = collect(glob(database_path('migrations/*')))
            ->filter(fn ($path) => is_dir($path))
            ->toArray();

        $this->loadMigrationsFrom($paths);
    }
}
