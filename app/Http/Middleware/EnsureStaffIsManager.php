<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIsManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if (! $user || $user->role !== 'manager') {
            abort(403, 'Only hotel managers can access this area.');
        }

        return $next($request);
    }
}
