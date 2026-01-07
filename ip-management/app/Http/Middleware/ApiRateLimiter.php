<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->ip() . '|' . ($request->route()->getName() ?? 'api');

        if ($this->limiter->tooManyAttempts($key, 60)) {
            return response()->json([
                'error' => 'Too many requests',
                'message' => 'API rate limit exceeded',
                'retry_after' => $this->limiter->availableIn($key)
            ], 429);
        }

        $this->limiter->hit($key, 60);

        return $next($request);
    }
}
