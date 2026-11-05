<?php

namespace App\Providers;

use App\Services\IsnGateway;
use App\Services\IsnGatewayInterface;
use App\Services\OrderingService;
use App\Services\OrderingServiceInterface;
use App\Services\QuotePreviewServiceInterface;
use App\Services\QuoteService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QuotePreviewServiceInterface::class, QuoteService::class);
        $this->app->bind(OrderingServiceInterface::class, OrderingService::class);
        $this->app->bind(IsnGatewayInterface::class, IsnGateway::class);
    }

    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(15)->by(hash('sha256', $request->ip().'|auth')));
        RateLimiter::for('portal', fn (Request $request): Limit => Limit::perMinute($request->isMethod('GET') ? 120 : 30)->by(hash('sha256', $request->ip().'|'.$request->method())));
        RateLimiter::for('quotes', fn (Request $request): Limit => Limit::perMinute(30)->by(hash('sha256', $request->ip().'|POST')));
        RateLimiter::for('orders', fn (Request $request): Limit => Limit::perMinute(30)->by(hash('sha256', $request->ip().'|POST')));
    }
}
