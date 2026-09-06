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
            function ($app) {
                $inter = config('services.inter');
                $useInter = config('services.payment.gateway') === 'inter'
                    && !empty($inter['client_id'])
                    && !empty($inter['client_secret'])
                    && !empty($inter['pix_key'])
                    && !empty($inter['certificate_path'])
                    && !empty($inter['private_key_path']);

                if (!$useInter) {
                    return new \App\Service\Payment\NullPaymentGateway();
                }

                return new \App\Service\Payment\InterPaymentGateway(
                    baseUrl: $inter['base_url'],
                    clientId: $inter['client_id'],
                    clientSecret: $inter['client_secret'],
                    pixKey: $inter['pix_key'],
                    certificatePath: $inter['certificate_path'],
                    privateKeyPath: $inter['private_key_path'],
                    cobExpiration: $inter['cob_expiration'],
                    contaCorrente: $inter['conta_corrente'] ?? null,
                    boletoDueDays: $inter['boleto_due_days'] ?? 3,
                );
            }
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
