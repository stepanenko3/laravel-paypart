<?php

namespace Stepanenko3\PlanPay;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PlanPayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerMigrations();
        $this->registerPublishing();

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->configure();
    }

    /**
     * Setup the configuration for PlanPay.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/paypart.php', 'planpay'
        );
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        if (PlanPay::$registersRoutes) {
            Route::group([
                'prefix' => config('planpay.path'),
                'namespace' => 'Stepanenko3\PlanPay\Http\Controllers',
                'as' => 'planpay.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }
    }

    /**
     * Register the package resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'planpay');
    }

    /**
     * Register the package migrations.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        if (PlanPay::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../resources/lang' => resource_path('lang')], 'planpay-langs');
            $this->publishes([__DIR__.'/../config/planpay.php' => $this->app->configPath('planpay.php')], 'planpay-config');
            $this->publishes([__DIR__.'/../database/migrations' => $this->app->databasePath('migrations')], 'planpay-migrations');
            $this->publishes([__DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/planpay')], 'planpay-views');
        }
    }
}
