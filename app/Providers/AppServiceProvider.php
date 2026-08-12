<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\HotLeadNotifierInterface;
use App\Contracts\OutboundTransport;
use App\Services\DatabaseHotLeadNotifier;
use App\Services\MockAIProvider;
use App\Services\SymfonySmtpTransport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OutboundTransport::class, SymfonySmtpTransport::class);
        $this->app->bind(AIProviderInterface::class, MockAIProvider::class);
        $this->app->bind(HotLeadNotifierInterface::class, DatabaseHotLeadNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
