<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('assistant', function (Request $request): Limit {
            $user = $request->user();
            $clinicScope = $user?->hasRole('super_admin')
                ? 'global'
                : (string) ($user?->activeClinicId() ?? 'none');
            $key = implode(':', [
                'assistant',
                (string) ($user?->id ?? 'guest'),
                $clinicScope,
                $request->ip(),
            ]);

            return Limit::perMinute((int) config('assistant.rate_limit_per_minute', 20))
                ->by($key)
                ->response(static fn (Request $request, array $headers) => response()->json([
                    'ok' => false,
                    'answer' => 'Has realizado demasiadas preguntas en poco tiempo. Espera un momento e inténtalo nuevamente.',
                    'code' => 'RATE_LIMITED',
                ], 429, $headers));
        });
    }
}
