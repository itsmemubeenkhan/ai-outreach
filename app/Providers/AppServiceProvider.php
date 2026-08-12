<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\HotLeadNotifierInterface;
use App\Contracts\OutboundTransport;
use App\Services\DatabaseHotLeadNotifier;
use App\Services\MockAIProvider;
use App\Services\SymfonySmtpTransport;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Task;

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
        View::composer('layouts.app', function ($view) {
            $dueFollowUps = collect();
            if (auth()->check()) {
                $dueFollowUps = Task::with('lead')->where('status', 'open')->where('due_at', '<=', now())
                    ->where(fn ($query) => $query->where('user_id', auth()->id())->when(auth()->user()->isAdmin(), fn ($q) => $q->orWhereNull('user_id')))
                    ->orderBy('due_at')->limit(5)->get();
            }
            $view->with('dueFollowUps', $dueFollowUps);
        });
    }
}
