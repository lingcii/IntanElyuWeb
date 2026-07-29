<?php

namespace App\Providers;

use App\Mail\BrevoApiTransport;
use Illuminate\Support\Facades\Mail;
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
        // Register Brevo HTTP API mail driver.
        // Uses HTTPS (port 443) — works on Railway which blocks SMTP ports.
        Mail::extend('brevo-api', function (array $config = []) {
            return new BrevoApiTransport(
                $config['key'] ?? env('BREVO_API_KEY', '')
            );
        });
    }
}
