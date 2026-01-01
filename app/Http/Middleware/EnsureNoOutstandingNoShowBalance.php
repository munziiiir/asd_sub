<?php

namespace App\Http\Middleware;

use App\Models\CustomerUser;
use App\Support\ReservationFolioService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNoOutstandingNoShowBalance
{
    /**
     * Block starting a new booking if the customer has an unpaid no-show balance.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $customer = CustomerUser::firstWhere('user_id', $user->id);
        if (! $customer) {
            return $next($request);
        }

        $outstanding = app(ReservationFolioService::class)->outstandingNoShowForCustomer($customer->id);
        if (! $outstanding) {
            return $next($request);
        }

        return redirect()
            ->route('bookings.pay', $outstanding['reservation_id'])
            ->withErrors([
                'status' => 'You have an unpaid no-show balance. Please settle it before making new bookings.',
            ]);
    }
}

