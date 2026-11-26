<?php

namespace App\Providers;

use App\Services\Advisor\AiProvider;
use App\Services\Advisor\RuleBasedAdvisor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProvider::class, function ($app) {
            $engines = (array) config('advisor.engines', ['rule_based' => RuleBasedAdvisor::class]);
            $engine = (string) config('advisor.engine', 'rule_based');
            $class = $engines[$engine] ?? RuleBasedAdvisor::class;

            return $app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
