<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRegistrationRequests
{
    public function __construct(private readonly ThrottleRequests $throttleRequests) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register.store')) {
            return $next($request);
        }

        return $this->throttleRequests->handle(
            $request,
            $next,
            (string) config('fortify.limiters.register', 'register'),
        );
    }
}
