<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('requestId', bin2hex(random_bytes(16)));
        Context::add('requestId', $request->attributes->get('requestId'));
        $response = $next($request);
        $response->headers->set('X-Request-Id', $request->attributes->get('requestId'));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
