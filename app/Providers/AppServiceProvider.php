<?php

namespace App\Providers;

use App\Contracts\Gateways\IsnGatewayInterface;
use App\Contracts\Repositories\AccessTokenRepositoryInterface;
use App\Contracts\Repositories\OrderingRepositoryInterface;
use App\Contracts\Repositories\QuotePreviewRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Integrations\Isn\IsnGateway;
use App\Repositories\Eloquent\OrderingRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Redis\QuotePreviewRepository;
use App\Repositories\Sanctum\AccessTokenRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AccessTokenRepositoryInterface::class, AccessTokenRepository::class);
        $this->app->bind(QuotePreviewRepositoryInterface::class, QuotePreviewRepository::class);
        $this->app->bind(OrderingRepositoryInterface::class, OrderingRepository::class);
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
