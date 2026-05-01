<?php

namespace App\Providers;

use App\Contracts\PaymentNotificationClient;
use App\Services\FakePaymentNotificationClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentNotificationClient::class, FakePaymentNotificationClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
