<?php

namespace App\Providers;

use App\Contracts\Messaging\WhatsAppDelivery;
use App\Services\Messaging\AnaWhatsAppGatewayAdapter;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(
            TenantContext::class,
            fn () => new TenantContext()
        );

        $this->app->bind(
            WhatsAppDelivery::class,
            AnaWhatsAppGatewayAdapter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'auth.password.forgot',
            function (Request $request): array {
                $identifier = strtolower(
                    trim(
                        (string) $request->input(
                            'identifier',
                            ''
                        )
                    )
                );

                return [
                    Limit::perMinute(5)
                        ->by(
                            'forgot-ip:'
                            . $request->ip()
                        ),

                    Limit::perMinute(3)
                        ->by(
                            'forgot-identifier:'
                            . $this->rateLimitHash(
                                $identifier
                            )
                        ),
                ];
            }
        );

        RateLimiter::for(
            'auth.password.verify',
            function (Request $request): array {
                $challengeId = trim(
                    (string) $request->input(
                        'challenge_id',
                        ''
                    )
                );

                return [
                    Limit::perMinute(10)
                        ->by(
                            'verify-ip:'
                            . $request->ip()
                        ),

                    Limit::perMinute(5)
                        ->by(
                            'verify-challenge:'
                            . $this->rateLimitHash(
                                $challengeId
                            )
                        ),
                ];
            }
        );

        RateLimiter::for(
            'auth.password.reset',
            function (Request $request): array {
                $resetProof = trim(
                    (string) $request->input(
                        'reset_proof',
                        ''
                    )
                );

                return [
                    Limit::perMinute(5)
                        ->by(
                            'reset-ip:'
                            . $request->ip()
                        ),

                    Limit::perMinute(3)
                        ->by(
                            'reset-proof:'
                            . $this->rateLimitHash(
                                $resetProof
                            )
                        ),
                ];
            }
        );
    }

    private function rateLimitHash(
        string $value
    ): string {
        return hash_hmac(
            'sha256',
            $value,
            (string) config(
                'app.key'
            )
        );
    }
}
