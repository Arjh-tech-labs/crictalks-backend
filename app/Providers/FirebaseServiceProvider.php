<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('firebase.auth', function ($app) {
            $factory = (new Factory)
                ->withServiceAccount(storage_path('app/firebase/sportsvaniapp-01-firebase-adminsdk.json'));
            
            return $factory->createAuth();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

