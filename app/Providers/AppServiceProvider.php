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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Laravel\Passport\Passport::useUserModel(\App\Models\User::class);

        if (request()->is('api/*')) {
            \Log::info('GLOBAL API Debug:', [
                'url' => request()->fullUrl(),
                'has_header' => request()->hasHeader('Authorization'),
                'header' => request()->header('Authorization'),
            ]);
        }
    }
}
