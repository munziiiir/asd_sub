<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsAndHsts
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only enforce in production-like environments to avoid local dev disruption.
        if (app()->environment('production')) {
            if (! $request->isSecure()) {
                return redirect()->secure($request->getRequestUri());
            }

            // Add HSTS header (6 months) when on HTTPS.
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains; preload');
        }

        return $response;
    }
}
