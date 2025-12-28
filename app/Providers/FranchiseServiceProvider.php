<?php

namespace App\Providers;

use App\Services\Franchise\FranchiseServiceInterface;
use App\Services\Franchise\PizzaHutService;
use App\Services\Franchise\BaristaService;
use App\Services\Franchise\DefaultService;
use Illuminate\Support\ServiceProvider;

class FranchiseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(FranchiseServiceInterface::class, function ($app) {
            $user = auth()->user();
            $franchise = $user?->franchise;
            
            if (!$franchise) {
                return new DefaultService();
            }

            return match($franchise->slug) {
                'pizzahut' => new PizzaHutService(),
                'barista' => new BaristaService(),
                default => new DefaultService(),
            };
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
