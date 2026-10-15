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
                // The active gateway is chosen in the admin panel (system_configs
                // "payment_gateway" key) and defaults to EFI. The env/config value
                // is only used as the fallback default when no admin setting exists.
                $default = config('services.payment.gateway', 'efi');
                $selected = $this->resolveSelectedGateway($app, $default);

                return match ($selected) {
                    'inter' => $this->makeInterGateway() ?? new \App\Service\Payment\NullPaymentGateway(),
                    'null' => new \App\Service\Payment\NullPaymentGateway(),
                    default => $this->makeEfiGateway() ?? new \App\Service\Payment\NullPaymentGateway(),
                };
            }
        );
    }

    /**
     * Resolves the active payment gateway identifier from the admin-managed
     * system config, falling back to the provided default. Reading the DB is
     * wrapped defensively so the binding keeps working during console commands
     * executed before the database/migrations are available.
     */
    private function resolveSelectedGateway($app, string $default): string
    {
        try {
            return (string) $app->make(\App\Service\SystemConfigService::class)
                ->getPaymentGateway($default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Builds the EFI gateway when its credentials are fully configured.
     */
    private function makeEfiGateway(): ?\App\Service\Payment\EfiPaymentGateway
    {
        $efi = config('services.efi');

        $ready = !empty($efi['client_id'])
            && !empty($efi['client_secret'])
            && !empty($efi['pix_key'])
            && !empty($efi['certificate_path']);

        if (!$ready) {
            return null;
        }

        return new \App\Service\Payment\EfiPaymentGateway(
            pixBaseUrl: $efi['pix_base_url'],
            cobrancasBaseUrl: $efi['cobrancas_base_url'],
            clientId: $efi['client_id'],
            clientSecret: $efi['client_secret'],
            pixKey: $efi['pix_key'],
            certificatePath: $efi['certificate_path'],
            privateKeyPath: $efi['private_key_path'] ?? null,
            cobExpiration: $efi['cob_expiration'] ?? 3600,
            boletoDueDays: $efi['boleto_due_days'] ?? 3,
        );
    }

    /**
     * Builds the Banco Inter gateway when its credentials are fully configured.
     */
    private function makeInterGateway(): ?\App\Service\Payment\InterPaymentGateway
    {
        $inter = config('services.inter');

        $ready = !empty($inter['client_id'])
            && !empty($inter['client_secret'])
            && !empty($inter['pix_key'])
            && !empty($inter['certificate_path'])
            && !empty($inter['private_key_path']);

        if (!$ready) {
            return null;
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
