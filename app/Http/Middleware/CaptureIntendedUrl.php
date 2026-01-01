<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CaptureIntendedUrl
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('get') && ! $request->session()->has('url.intended')) {
            $previous = url()->previous();
            $current = $request->fullUrl();

            if (
                $previous
                && $previous !== $current
                && parse_url($previous, PHP_URL_HOST) === $request->getHost()
                && ! Str::contains($previous, ['/login', '/register'])
            ) {
                $request->session()->put('url.intended', $previous);
            }
        }

        return $next($request);
    }
}
