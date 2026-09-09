<?php

namespace App\Providers;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\MockAiProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProvider::class, function () {
            $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
            $model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest'));
            if (!empty($apiKey)) {
                return new GeminiProvider($apiKey, $model);
            }

            return new MockAiProvider();
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
