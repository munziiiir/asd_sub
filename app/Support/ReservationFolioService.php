<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\RoomStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationFolioService
{
    public function ensureOpenFolio(Reservation $reservation): Folio
    {
        $folio = Folio::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', 'Open')
            ->first()
            ?? Folio::query()
                ->where('reservation_id', $reservation->id)
                ->first();

        if ($folio) {
            return $folio;
        }

        $folioNo = sprintf('%s-F-%04d', $reservation->code, $reservation->incremental_no ?? $reservation->id);

        return Folio::create([
            'reservation_id' => $reservation->id,
            'folio_no' => $folioNo,
            'status' => 'Open',
        ]);
    }

    public function nights(Reservation $reservation): int
    {
        $checkIn = $reservation->check_in_date;
        $checkOut = $reservation->check_out_date;

        if (! $checkIn || ! $checkOut) {
            return 1;
        }

        return max(1, $checkIn->diffInDays($checkOut));
    }

    public function expectedRoomTotal(Reservation $reservation): float
    {
        return round($reservation->nightlyRateTotal() * $this->nights($reservation), 2);
    }

    public function requiredDeposit(Reservation $reservation): float
    {
        return max(0, round($reservation->nightlyRateTotal(), 2));
    }

    public function paymentsTotal(Folio $folio): float
    {
        return (float) Payment::query()
            ->where('folio_id', $folio->id)
            ->sum('amount');
    }

    public function chargesTotal(Folio $folio): float
    {
        return (float) Charge::query()
            ->where('folio_id', $folio->id)
            ->sum('total_amount');
    }

    public function systemChargesTotal(Folio $folio): float
    {
        return (float) Charge::query()
            ->where('folio_id', $folio->id)
            ->where(function ($q) {
                $q->where('description', 'like', 'Online booking:%')
                    ->orWhere('description', 'like', 'Reservation adjustment:%')
                    ->orWhere('description', 'like', 'Cancellation adjustment:%');
            })
            ->sum('total_amount');
    }

    public function roomChargesTotal(Folio $folio): float
    {
        return (float) Charge::query()
            ->where('folio_id', $folio->id)
            ->where(function ($q) {
                $q->where('description', 'like', 'Online booking: Room charges%')
                    ->orWhere('description', 'like', 'Reservation adjustment: Room charges%');
            })
            ->sum('total_amount');
    }

    public function syncRoomCharges(Reservation $reservation, string $reason = 'update'): float
    {
        $folio = $this->ensureOpenFolio($reservation);

        if ($folio->status === 'Closed') {
            return 0.0;
        }

        $expected = $this->expectedRoomTotal($reservation);
        $current = round($this->roomChargesTotal($folio), 2);
        $delta = round($expected - $current, 2);

        if (abs($delta) < 0.01) {
            return 0.0;
        }

        $nights = $this->nights($reservation);
        $today = now()->toDateString();

        if (abs($current) < 0.01 && $delta > 0) {
            Charge::create([
                'folio_id' => $folio->id,
                'post_date' => $today,
                'description' => 'Online booking: Room charges (' . $nights . ' night' . ($nights === 1 ? '' : 's') . ')',
                'qty' => 1,
                'unit_price' => $expected,
                'tax_amount' => 0,
                'total_amount' => $expected,
            ]);

            return $delta;
        }

        Charge::create([
            'folio_id' => $folio->id,
            'post_date' => $today,
            'description' => 'Reservation adjustment: Room charges (' . $reason . ')',
            'qty' => 1,
            'unit_price' => $delta,
            'tax_amount' => 0,
            'total_amount' => $delta,
        ]);

        return $delta;
    }

    /**
     * Apply cancellation/no-show policy:
     * - >14 days: free (refund paid deposit)
     * - 3-14 days: 50% of first night (refund remaining deposit)
     * - <72 hours: 100% of first night (keep deposit)
     * - No-show: 100% of entire booking value (no refunds; remaining balance due)
     *
     * @return array{policy:string,fee:float,refunded:float,remaining_due:float}
     */
    public function applyCancellationPolicy(Reservation $reservation, string $actor = 'guest', ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $roomStatusService = app(RoomStatusService::class);

        $this->syncRoomCharges($reservation, $actor . ' cancellation baseline');
        $folio = $this->ensureOpenFolio($reservation);
        $reservation->loadMissing('rooms');

        if ($folio->status === 'Closed') {
            return [
                'policy' => 'closed',
                'fee' => 0.0,
                'refunded' => 0.0,
                'remaining_due' => 0.0,
            ];
        }

        $checkIn = $reservation->check_in_date;
        $firstNight = $this->requiredDeposit($reservation);

        $hoursUntil = $checkIn ? $asOf->diffInHours($checkIn, false) : -1;
        $daysUntil = $checkIn ? $asOf->diffInDays($checkIn, false) : -1;

        $policy = 'no_show';
        $fee = 0.0;
        $isNoShow = true;

        if ($hoursUntil >= 0) {
            $isNoShow = false;

            if ($daysUntil > 14) {
                $policy = 'free';
                $fee = 0.0;
            } elseif ($hoursUntil < 72) {
                $policy = 'lt_72h';
                $fee = $firstNight;
            } else {
                $policy = '3_14_days';
                $fee = round($firstNight * 0.5, 2);
            }
        }

        if ($isNoShow) {
            $reservation->forceFill(['status' => 'NoShow'])->save();
            foreach ($reservation->rooms as $room) {
                $roomStatusService->syncToNextReservationOrFree($room, 'Available');
            }

            $chargesTotal = round($this->chargesTotal($folio), 2);
            $paymentsTotal = round($this->paymentsTotal($folio), 2);

            return [
                'policy' => 'no_show',
                'fee' => $chargesTotal,
                'refunded' => 0.0,
                'remaining_due' => max(0, round($chargesTotal - $paymentsTotal, 2)),
            ];
        }

        // Adjust only system-generated booking charges to match cancellation fee.
        $currentSystem = round($this->systemChargesTotal($folio), 2);
        $delta = round($fee - $currentSystem, 2);

        if (abs($delta) >= 0.01) {
            Charge::create([
                'folio_id' => $folio->id,
                'post_date' => $asOf->toDateString(),
                'description' => 'Cancellation adjustment: ' . $policy . ' (' . $actor . ')',
                'qty' => 1,
                'unit_price' => $delta,
                'tax_amount' => 0,
                'total_amount' => $delta,
            ]);
        }

        $reservation->forceFill(['status' => 'Cancelled'])->save();
        foreach ($reservation->rooms as $room) {
            $roomStatusService->syncToNextReservationOrFree($room, 'Available');
        }

        $refunded = $this->normalizeOverpayment($folio, $actor . ' cancellation refund');
        $chargesTotal = round($this->chargesTotal($folio), 2);
        $paymentsTotal = round($this->paymentsTotal($folio), 2);

        return [
            'policy' => $policy,
            'fee' => round($fee, 2),
            'refunded' => round($refunded, 2),
            'remaining_due' => max(0, round($chargesTotal - $paymentsTotal, 2)),
        ];
    }

    /**
     * Preview cancellation cost/refund without mutating data.
     *
     * @return array{policy:string,label:string,fee:float,paid:float,refundable:float,due:float}
     */
    public function previewCancellationOutcome(Reservation $reservation, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $folio = $this->ensureOpenFolio($reservation);

        $checkIn = $reservation->check_in_date;
        $firstNight = $this->requiredDeposit($reservation);
        $chargesTotal = max(round($this->chargesTotal($folio), 2), round($this->expectedRoomTotal($reservation), 2));
        $paid = round($this->paymentsTotal($folio), 2);

        $hoursUntil = $checkIn ? $asOf->diffInHours($checkIn, false) : -1;
        $daysUntil = $checkIn ? $asOf->diffInDays($checkIn, false) : -1;

        $policy = 'no_show';
        $label = 'No-show (100% of booking value)';
        $fee = $chargesTotal;

        if ($hoursUntil >= 0) {
            if ($daysUntil > 14) {
                $policy = 'free';
                $label = 'Free cancellation (>14 days before check-in)';
                $fee = 0.0;
            } elseif ($hoursUntil < 72) {
                $policy = 'lt_72h';
                $label = '100% of first night (<72 hours before check-in)';
                $fee = $firstNight;
            } else {
                $policy = '3_14_days';
                $label = '50% of first night (3–14 days before check-in)';
                $fee = round($firstNight * 0.5, 2);
            }
        }

        $refundable = max(0, round($paid - $fee, 2));
        $due = max(0, round($fee - $paid, 2));

        if ($policy === 'no_show') {
            $refundable = 0.0;
            $due = max(0, round($chargesTotal - $paid, 2));
        }

        return [
            'policy' => $policy,
            'label' => $label,
            'fee' => round($fee, 2),
            'paid' => $paid,
            'refundable' => $refundable,
            'due' => $due,
        ];
    }

    /**
     * Returns the first outstanding NoShow balance for a customer, if any.
     *
     * @return array{reservation_id:int,due:float}|null
     */
    public function outstandingNoShowForCustomer(int $customerId): ?array
    {
        $chargesSub = DB::table('folios as f')
            ->join('charges as c', 'c.folio_id', '=', 'f.id')
            ->selectRaw('f.reservation_id, SUM(c.total_amount) as charges_total')
            ->groupBy('f.reservation_id');

        $paymentsSub = DB::table('folios as f')
            ->join('payments as p', 'p.folio_id', '=', 'f.id')
            ->selectRaw('f.reservation_id, SUM(p.amount) as payments_total')
            ->groupBy('f.reservation_id');

        $row = DB::table('reservations as r')
            ->leftJoinSub($chargesSub, 'chg', fn ($j) => $j->on('chg.reservation_id', '=', 'r.id'))
            ->leftJoinSub($paymentsSub, 'pay', fn ($j) => $j->on('pay.reservation_id', '=', 'r.id'))
            ->where('r.customer_id', $customerId)
            ->where('r.status', 'NoShow')
            ->selectRaw('r.id as reservation_id, COALESCE(chg.charges_total, 0) - COALESCE(pay.payments_total, 0) as due')
            ->whereRaw('(COALESCE(chg.charges_total, 0) - COALESCE(pay.payments_total, 0)) > 0.01')
            ->orderBy('r.check_in_date')
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'reservation_id' => (int) $row->reservation_id,
            'due' => round((float) $row->due, 2),
        ];
    }

    /**
     * Sync optional online extras to the current guest count and selections.
     *
     * @param array<string,array{selected?:bool,qty?:int}> $selections
     */
    public function syncOnlineExtrasFromSelections(Reservation $reservation, array $selections, string $reason = 'extras update'): void
    {
        $folio = $this->ensureOpenFolio($reservation);

        if ($folio->status === 'Closed') {
            return;
        }

        $guestCount = max(1, (int) ($reservation->adults + $reservation->children));
        $today = now()->toDateString();

        foreach (ChargeCatalog::options() as $opt) {
            $code = (string) ($opt['code'] ?? 'custom');
            if ($code === 'custom') {
                continue;
            }

            $label = (string) ($opt['label'] ?? '');
            $unit = (float) ($opt['amount'] ?? 0);
            if ($label === '' || $unit <= 0) {
                continue;
            }

            $selected = (bool) ($selections[$code]['selected'] ?? false);
            $expected = $selected ? round($unit * $guestCount, 2) : 0.0;

            $baseline = 'Online booking: ' . $label;
            $adjustPrefix = 'Reservation adjustment: ' . $label;

            $current = (float) Charge::query()
                ->where('folio_id', $folio->id)
                ->where(function ($q) use ($baseline, $adjustPrefix) {
                    $q->where('description', $baseline)
                        ->orWhere('description', 'like', $adjustPrefix . '%');
                })
                ->sum('total_amount');

            $current = round($current, 2);
            $delta = round($expected - $current, 2);

            if (abs($delta) < 0.01) {
                continue;
            }

            if (abs($current) < 0.01 && $expected > 0) {
                Charge::create([
                    'folio_id' => $folio->id,
                    'post_date' => $today,
                    'description' => $baseline,
                    'qty' => $guestCount,
                    'unit_price' => $unit,
                    'tax_amount' => 0,
                    'total_amount' => $expected,
                ]);

                continue;
            }

            Charge::create([
                'folio_id' => $folio->id,
                'post_date' => $today,
                'description' => $adjustPrefix . ' (' . $reason . ')',
                'qty' => 1,
                'unit_price' => $delta,
                'tax_amount' => 0,
                'total_amount' => $delta,
            ]);
        }
    }

    public function normalizeOverpayment(Folio $folio, string $reason = 'auto refund'): float
    {
        if ($folio->status === 'Closed') {
            return 0.0;
        }

        $chargesTotal = round($this->chargesTotal($folio), 2);
        $paymentsTotal = round($this->paymentsTotal($folio), 2);

        if ($paymentsTotal - $chargesTotal <= 0.01) {
            return 0.0;
        }

        $refundAmount = round($paymentsTotal - $chargesTotal, 2);

        Payment::create([
            'folio_id' => $folio->id,
            'method' => 'Refund',
            'amount' => -1 * $refundAmount,
            'txn_ref' => 'RFND-' . strtoupper(Str::random(10)),
            'paid_at' => now(),
        ]);

        return $refundAmount;
    }

    /**
     * Enforce reservation status based on deposit required vs paid.
     *
     * @return array{required_deposit:float,paid:float,due:float,status_changed:bool,new_status:?string}
     */
    public function enforceDepositStatus(Reservation $reservation): array
    {
        $folio = $this->ensureOpenFolio($reservation);
        $roomStatusService = app(RoomStatusService::class);
        $reservation->loadMissing('rooms');

        $required = $this->requiredDeposit($reservation);
        $paid = round($this->paymentsTotal($folio), 2);
        $due = max(0, round($required - $paid, 2));

        $previous = $reservation->status;
        $new = null;

        $eligible = in_array($reservation->status, ['Pending', 'Confirmed'], true);

        if ($eligible && $reservation->status === 'Confirmed' && $due > 0.01) {
            $reservation->forceFill(['status' => 'Pending'])->save();
            foreach ($reservation->rooms as $room) {
                $roomStatusService->syncToNextReservationOrFree($room, 'Available');
            }
            $new = 'Pending';
        }

        if ($eligible && $reservation->status === 'Pending' && $due <= 0.01) {
            $reservation->forceFill(['status' => 'Confirmed'])->save();
            foreach ($reservation->rooms as $room) {
                $roomStatusService->syncToNextReservationOrFree($room, 'Reserved');
            }
            $new = 'Confirmed';
        }

        return [
            'required_deposit' => $required,
            'paid' => $paid,
            'due' => $due,
            'status_changed' => $new !== null && $new !== $previous,
            'new_status' => $new,
        ];
    }
}
