<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Folio;
use App\Models\Reservation;
use App\Support\ChargeCatalog;
use App\Support\ReservationFolioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user('staff');
        abort_unless($staff, 403);

        $folioService = app(ReservationFolioService::class);

        $reservations = Reservation::with([
                'customer',
                'rooms',
                'checkOuts' => fn ($q) => $q->latest(),
                'folios.charges',
            ])
            ->where('hotel_id', $staff->hotel_id)
            ->whereIn('status', ['CheckedIn', 'CheckedOut', 'Confirmed'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(function (Reservation $reservation) use ($folioService) {
                $activeFolio = $this->activeFolio($reservation, createIfMissing: true);
                $nights = max(1, $reservation->check_in_date->diffInDays($reservation->check_out_date));
                $nightlyRate = $reservation->nightlyRateTotal();

                $folioChargesTotal = $reservation->folios?->flatMap->charges->sum('total_amount') ?? 0.0;
                $roomChargesTotal = $reservation->folios?->sum(fn ($folio) => $folioService->roomChargesTotal($folio)) ?? 0.0;
                $roomTotal = $roomChargesTotal > 0 ? $roomChargesTotal : ($nights * $nightlyRate);

                $checkout = $reservation->checkOuts->first();
                $checkoutExtras = (float) ($checkout?->extras_total ?? 0);
                $extrasTotal = ($folioChargesTotal - $roomChargesTotal) + $checkoutExtras;
                $grandTotal = $checkout?->grand_total ?? ($roomTotal + $extrasTotal);

                return [
                    'id' => $reservation->id,
                    'code' => $reservation->code,
                    'status' => $reservation->status,
                    'guest' => $reservation->customer?->name ?? 'Guest',
                    'room' => $reservation->roomNumberLabel(),
                    'check_in' => optional($reservation->check_in_date)->toDateString(),
                    'check_out' => optional($reservation->check_out_date)->toDateString(),
                    'folio_status' => $activeFolio?->status ?? 'Open',
                    'room_total' => round($roomTotal, 2),
                    'extras_total' => round($extrasTotal, 2),
                    'grand_total' => round($grandTotal, 2),
                ];
            });

        return view('staff.billing.index', [
            'reservations' => $reservations,
        ]);
    }

    public function show(Request $request, Reservation $reservation): View
    {
        $staff = $request->user('staff');
        abort_unless($staff && $reservation->hotel_id === $staff->hotel_id, 403);

        $reservation->loadMissing(['customer', 'rooms', 'folios.charges']);
        $folio = $this->activeFolio($reservation, createIfMissing: true);
        $chargeOptions = $this->chargeOptions();

        return view('staff.billing.show', [
            'reservation' => $reservation,
            'folio' => $folio->load('charges'),
            'chargeOptions' => $chargeOptions,
            'blockedCharges' => $this->blockedCharges($folio, $chargeOptions),
        ]);
    }

    public function storeCharge(Request $request, Reservation $reservation): RedirectResponse
    {
        $staff = $request->user('staff');
        abort_unless($staff && $reservation->hotel_id === $staff->hotel_id, 403);

        $data = $request->validate([
            'charge_code' => ['required', 'string', 'max:50'],
            'custom_name' => ['nullable', 'string', 'max:191'],
            'custom_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $folio = $this->activeFolio($reservation, createIfMissing: true);

        if ($folio->status === 'Closed') {
            return back()->withErrors(['description' => 'Folio is closed. Cannot add new charges.']);
        }

        $options = $this->chargeOptions();
        $code = $data['charge_code'];
        $selected = collect($options)->firstWhere('code', $code);

        if (! $selected) {
            return back()->withErrors(['charge_code' => 'Invalid charge selected.']);
        }

        $description = $selected['label'];
        $amount = (float) $selected['amount'];
        $quantity = max(1, (int) ($reservation->adults + $reservation->children));

        if ($code !== 'custom') {
            if ($this->hasExistingCharge($folio, $description)) {
                return back()->withErrors(['charge_code' => 'That charge has already been added (online or by staff).']);
            }
        }

        if ($code === 'custom') {
            $description = $data['custom_name'] ?? 'Custom charge';
            $amount = (float) ($data['custom_amount'] ?? 0);
            $quantity = 1;

            if ($description === '' || $amount <= 0) {
                return back()->withErrors(['custom_amount' => 'Provide a name and positive amount for custom charges.']);
            }
        }

        Charge::create([
            'folio_id' => $folio->id,
            'post_date' => now()->toDateString(),
            'description' => $description,
            'qty' => $quantity,
            'unit_price' => $amount,
            'tax_amount' => 0,
            'total_amount' => round($amount * $quantity, 2),
        ]);

        return redirect()
            ->route('staff.billing.show', $reservation)
            ->with('status', 'Charge added to folio.');
    }

    private function activeFolio(Reservation $reservation, bool $createIfMissing = false): Folio
    {
        $folio = $reservation->folios->firstWhere('status', 'Open')
            ?? $reservation->folios->first();

        if (! $folio && $createIfMissing) {
            $folioNo = sprintf('%s-F-%04d', $reservation->code, $reservation->incremental_no ?? $reservation->id);
            $folio = Folio::create([
                'reservation_id' => $reservation->id,
                'folio_no' => $folioNo,
                'status' => 'Open',
            ]);
        }

        return $folio ?? tap(new Folio())->forceFill(['status' => 'Closed']);
    }

    /**
    * @return array<int,array<string,mixed>>
    */
    private function chargeOptions(): array
    {
        return ChargeCatalog::options();
    }

    /**
     * @return array<string,bool>
     */
    private function blockedCharges(Folio $folio, array $options): array
    {
        $blocked = [];
        foreach ($options as $option) {
            if (($option['code'] ?? 'custom') === 'custom') {
                $blocked[$option['code']] = false;
                continue;
            }

            $blocked[$option['code']] = $this->hasExistingCharge($folio, $option['label']);
        }

        return $blocked;
    }

    private function hasExistingCharge(Folio $folio, string $label): bool
    {
        $needle = mb_strtolower($label);
        $onlineVariant = mb_strtolower('Online booking: ' . $label);

        return $folio->charges
            ->pluck('description')
            ->map(fn ($d) => mb_strtolower((string) $d))
            ->contains(fn ($desc) => $desc === $needle || $desc === $onlineVariant);
    }
}
