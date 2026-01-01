<?php

namespace App\Livewire\Staff\CheckIo;

use App\Events\ReservationCheckedOut;
use App\Models\Charge;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\ChargeCatalog;
use App\Support\RoomStatusService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CheckOutForm extends Component
{
    protected $casts = [
        'reservationId' => 'integer',
        'extras' => 'array',
    ];

    public ?int $reservationId = null;

    public string $finalPaymentMethod = '';

    public string $notes = '';

    protected $listeners = ['viewer-timezone-detected' => 'updateViewerTimezone'];

    /** @var array<int,array<string,mixed>> */
    public array $departureOptions = [];

    /** @var array<string,array<string,mixed>> */
    public array $summaryLookup = [];

    /** @var array<int,array{code:string,custom_name:string,custom_amount:string|null}> */
    public array $extras = [];

    /** @var array<int,array<string,mixed>> */
    public array $chargeOptions = [];

    /** @var array<string,bool> */
    public array $blockedChargeCodes = [];

    public ?int $hotelId = null;

    public ?int $staffId = null;

    public ?string $viewerTimezone = null;

    public ?string $hotelTimezone = null;

    public function mount(): void
    {
        $this->ensureContext();
        $this->chargeOptions = ChargeCatalog::options();
        $this->extras = [];

        $this->loadOptions();
        $this->refreshBlockedChargeCodes();
    }

    public function render()
    {
        return view('livewire.staff.check-io.check-out-form', [
            'selectedSummary' => $this->selectedSummary,
            'extrasTotal' => $this->extrasTotal,
            'balanceSummary' => $this->balanceSummary,
        ]);
    }

    public function save()
    {
        $data = $this->validate($this->rules());

        $this->ensureContext();

        try {
            DB::transaction(function () use ($data) {
                $reservation = Reservation::where('hotel_id', $this->hotelId)
                    ->where('id', $this->reservationId)
                    ->lockForUpdate()
                    ->with(['rooms', 'customer', 'folios.charges', 'folios.payments'])
                    ->firstOrFail();

                if ($reservation->status !== 'CheckedIn') {
                    throw ValidationException::withMessages([
                        'reservationId' => 'Only checked-in guests can be checked out.',
                    ]);
                }

                $room = $reservation->rooms->first();

                if (! $room) {
                    throw ValidationException::withMessages([
                        'reservationId' => 'Assign a room to this reservation before processing check-out.',
                    ]);
                }

                $folio = $this->activeFolio($reservation, createIfMissing: true);
                $folio->loadMissing(['charges', 'payments']);

                if ($folio->status === 'Closed') {
                    throw ValidationException::withMessages([
                        'reservationId' => 'Folio is already closed. Reopen or adjust in billing before checkout.',
                    ]);
                }

                $plannedCharges = $this->extrasChargesForFolio($folio, $reservation);

                foreach ($plannedCharges as $charge) {
                    Charge::create(array_merge($charge, [
                        'folio_id' => $folio->id,
                        'post_date' => now()->toDateString(),
                    ]));
                }

                // Refresh ledger from DB to prevent client tampering.
                $chargesTotal = (float) $folio->charges()->sum('total_amount');
                $paymentsTotal = (float) $folio->payments()->sum('amount');
                $outstanding = round($chargesTotal - $paymentsTotal, 2);

                if ($outstanding < 0) {
                    throw ValidationException::withMessages([
                        'reservationId' => 'Folio has an overpayment. Adjust in billing before checkout.',
                    ]);
                }

                $postedPayment = null;

                if ($outstanding > 0) {
                    if ($data['finalPaymentMethod'] === '') {
                        throw ValidationException::withMessages([
                            'finalPaymentMethod' => 'Select a payment method to settle the remaining balance.',
                        ]);
                    }

                    $paymentMethodLabel = $this->paymentMethodLabel($reservation, $data['finalPaymentMethod']);

                    $postedPayment = Payment::create([
                        'folio_id' => $folio->id,
                        'method' => $paymentMethodLabel,
                        'amount' => $outstanding,
                        'txn_ref' => $this->generatePaymentReference(),
                        'paid_at' => now(),
                    ]);

                    $paymentsTotal += $outstanding;
                }

                $finalBalance = round($chargesTotal - $paymentsTotal, 2);

                if (abs($finalBalance) > 0.0001) {
                    throw ValidationException::withMessages([
                        'reservationId' => 'Folio cannot be closed until charges and payments are equal.',
                    ]);
                }

                $folio->forceFill(['status' => 'Closed'])->save();

                $summary = $this->staySummary($reservation);
                $extrasBreakdown = $this->extrasBreakdownFromCharges($plannedCharges);
                $extrasTotal = collect($extrasBreakdown)->sum('amount');

                $checkOut = $reservation->checkOuts()->create([
                    'room_id' => $room->id,
                    'handled_by' => $this->staffId,
                    'checked_out_at' => now(),
                    'room_charges_total' => $summary['room_total'] ?? 0,
                    'extras_breakdown' => $extrasBreakdown,
                    'extras_total' => $extrasTotal,
                    'grand_total' => $chargesTotal,
                    'final_payment_method' => $data['finalPaymentMethod'] ?: 'Already settled',
                    'final_payment_reference' => null,
                    'final_payment_status' => 'Captured',
                    'settled_at' => now(),
                    'notes' => $this->cleanText($data['notes'] ?? null),
                ]);

                $reservation->update(['status' => 'CheckedOut']);

                $roomStatusService = app(RoomStatusService::class);
                foreach ($reservation->rooms as $releasedRoom) {
                    $roomStatusService->syncToNextReservationOrFree($releasedRoom, 'Available');
                }

                ReservationCheckedOut::dispatch($reservation->fresh(), $checkOut);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('reservationId', 'Unable to complete check-out. Resolve folio issues in billing and try again.');
            \App\Support\AuditLogger::log('checkout.failed', [
                'reservation_id' => $this->reservationId,
            ], false);

            return;
        }

        \App\Support\AuditLogger::log('checkout.completed', [
            'reservation_id' => $this->reservationId,
        ], true);

        session()->flash('status', 'Reservation checked out and folio closed.');

        return redirect()->route('staff.check-io.index');
    }

    public function addExtra(): void
    {
        if (! $this->reservationId) {
            return;
        }

        $this->extras[] = ['code' => '', 'custom_name' => '', 'custom_amount' => null];
    }

    public function removeExtra(int $index): void
    {
        unset($this->extras[$index]);
        $this->extras = array_values($this->extras);
    }

    public function getSelectedSummaryProperty(): ?array
    {
        if (! $this->reservationId) {
            return null;
        }

        return $this->summaryLookup[$this->reservationId] ?? null;
    }

    public function getExtrasTotalProperty(): float
    {
        return collect($this->plannedExtrasCharges())->sum(fn ($charge) => (float) ($charge['total_amount'] ?? 0));
    }

    protected function loadOptions(): void
    {
        // Use hotel-local "today" to determine due departures, avoiding viewer timezone skew.
        $hotelCheckoutDate = now($this->hotelTimezone ?? config('app.timezone'))->toDateString();

        $departures = Reservation::with(['customer', 'rooms.roomType', 'folios.charges', 'folios.payments'])
            ->where('hotel_id', $this->hotelId)
            ->where('status', 'CheckedIn')
            ->whereDate('check_out_date', $hotelCheckoutDate)
            ->orderBy('check_out_date')
            ->get();

        $this->departureOptions = $departures->map(function (Reservation $reservation) {
                return [
                    'id' => $reservation->id,
                    'code' => $reservation->code,
                    'guest' => $reservation->customer->name ?? 'Guest profile missing',
                    'check_out' => $this->formatForViewer($reservation->check_out_date),
                    'room' => $reservation->roomNumberLabel(),
                ];
            })->values()->all();

        $this->summaryLookup = $departures->mapWithKeys(function (Reservation $reservation) {
            // Double-check hotel safety
            if ($reservation->hotel_id !== $this->hotelId) {
                return [];
            }
            return [$reservation->id => $this->staySummary($reservation)];
        })->all();

        $this->refreshBlockedChargeCodes();
    }

    /**
     * @return array<string,mixed>
     */
    protected function rules(): array
    {
        $this->ensureContext();

        $rules = [
            'reservationId' => [
                'required',
                Rule::exists('reservations', 'id')->where(fn ($query) => $query
                    ->where('hotel_id', $this->hotelId)
                    ->where('status', 'CheckedIn')),
            ],
            'extras' => ['array'],
            'extras.*.code' => ['nullable', 'string', 'max:50'],
            'extras.*.custom_name' => ['nullable', 'string', 'max:191'],
            'extras.*.custom_amount' => ['nullable', 'numeric', 'min:0'],
            'finalPaymentMethod' => ['nullable', Rule::in(['Cash', 'Card', 'Wire'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        // Add custom validation for finalPaymentMethod when balance is due
        $balanceSummary = $this->balanceSummary;
        if ($balanceSummary && $balanceSummary['needs_payment']) {
            $rules['finalPaymentMethod'] = ['required', Rule::in(['Cash', 'Card', 'Wire'])];
        }

        return $rules;
    }

    public function updatedReservationId($value = null): void
    {
        $this->extras = [];
        $this->refreshSummaryForSelection($value !== null ? (int) $value : null);
    }

    public function getBalanceSummaryProperty(): ?array
    {
        $summary = $this->selectedSummary;

        if (! $summary) {
            return null;
        }

        $folio = $summary['folio'] ?? [];
        $existingCharges = (float) ($folio['charges_total'] ?? 0);
        $existingPayments = (float) ($folio['payments_total'] ?? 0);
        $plannedCharges = collect($this->plannedExtrasCharges())->sum(fn ($charge) => (float) ($charge['total_amount'] ?? 0));
        $balanceAfterCharges = round(($existingCharges + $plannedCharges) - $existingPayments, 2);

        return [
            'charges' => round($existingCharges, 2),
            'payments' => round($existingPayments, 2),
            'planned_charges' => round($plannedCharges, 2),
            'balance_after_charges' => $balanceAfterCharges,
            'needs_payment' => $balanceAfterCharges > 0,
            'overpaid' => $balanceAfterCharges < 0,
            'settle_amount' => $balanceAfterCharges > 0 ? $balanceAfterCharges : 0.00,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function plannedExtrasCharges(): array
    {
        $options = collect($this->chargeOptions)->keyBy('code');
        $summary = $this->selectedSummary;
        $guestCount = max(1, (int) ($summary['guest_count'] ?? 1));
        $blocked = $this->blockedChargeCodes;
        $charges = [];
        $seen = [];

        foreach ($this->extras as $extra) {
            $code = trim((string) ($extra['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            if ($code !== 'custom') {
                if (isset($seen[$code])) {
                    continue;
                }

                if (($blocked[$code] ?? false) || ! $options->has($code)) {
                    continue;
                }

                $seen[$code] = true;
                $option = $options[$code];
                $unit = (float) ($option['amount'] ?? 0);
                $total = round($unit * $guestCount, 2);

                if ($unit <= 0 || $total <= 0) {
                    continue;
                }

                $charges[] = [
                    'description' => $option['label'],
                    'qty' => $guestCount,
                    'unit_price' => $unit,
                    'tax_amount' => 0,
                    'total_amount' => $total,
                ];

                continue;
            }

            $name = trim((string) ($extra['custom_name'] ?? ''));
            $amount = (float) ($extra['custom_amount'] ?? 0);

            if ($name === '' || $amount <= 0) {
                continue;
            }

            $charges[] = [
                'description' => $name,
                'qty' => 1,
                'unit_price' => round($amount, 2),
                'tax_amount' => 0,
                'total_amount' => round($amount, 2),
            ];
        }

        return $charges;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function extrasChargesForFolio(Folio $folio, Reservation $reservation): array
    {
        $options = collect($this->chargeOptions)->keyBy('code');
        $blocked = $this->blockedCharges($folio, $this->chargeOptions);
        $guestCount = max(1, (int) ($reservation->adults + $reservation->children));
        $charges = [];
        $seen = [];

        foreach ($this->extras as $index => $extra) {
            $code = trim((string) ($extra['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            if ($code !== 'custom') {
                if (isset($seen[$code])) {
                    throw ValidationException::withMessages([
                        "extras.$index.code" => 'This add-on is already selected.',
                    ]);
                }

                $seen[$code] = true;

                if (! $options->has($code)) {
                    throw ValidationException::withMessages([
                        "extras.$index.code" => 'Invalid add-on selected.',
                    ]);
                }

                if (($blocked[$code] ?? false) === true) {
                    throw ValidationException::withMessages([
                        "extras.$index.code" => 'This add-on is already on the folio.',
                    ]);
                }

                $option = $options[$code];
                $unit = (float) ($option['amount'] ?? 0);
                $total = round($unit * $guestCount, 2);

                if ($unit <= 0 || $total <= 0) {
                    continue;
                }

                $charges[] = [
                    'description' => $option['label'],
                    'qty' => $guestCount,
                    'unit_price' => $unit,
                    'tax_amount' => 0,
                    'total_amount' => $total,
                ];

                continue;
            }

            $name = trim((string) ($extra['custom_name'] ?? ''));
            $amount = (float) ($extra['custom_amount'] ?? 0);

            if ($name === '' || $amount <= 0) {
                throw ValidationException::withMessages([
                    "extras.$index.custom_amount" => 'Provide a name and positive amount for custom charges.',
                ]);
            }

            $charges[] = [
                'description' => $name,
                'qty' => 1,
                'unit_price' => round($amount, 2),
                'tax_amount' => 0,
                'total_amount' => round($amount, 2),
            ];
        }

        return $charges;
    }

    /**
     * @return array<int,array{description:string,amount:float}>
     */
    protected function extrasBreakdownFromCharges(array $charges): array
    {
        return collect($charges)
            ->map(fn ($charge) => [
                'description' => (string) ($charge['description'] ?? ''),
                'amount' => (float) ($charge['total_amount'] ?? 0),
            ])
            ->filter(fn ($item) => $item['description'] !== '' || $item['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * Resolve payment method label for ledger based on selection and stored card.
     */
    protected function paymentMethodLabel(Reservation $reservation, string $selection): string
    {
        $selection = trim($selection);

        if (in_array($selection, ['Card', 'Wire'], true)) {
            $brand = $reservation->customer?->card_brand;
            $lastFour = $reservation->customer?->card_last_four;

            if ($brand || $lastFour) {
                $suffix = $lastFour ? ' ••••' . $lastFour : '';
                return trim(($brand ?: 'Card') . $suffix);
            }

            return $selection === 'Wire' ? 'Wire (card on file)' : 'Card on file';
        }

        return 'Cash';
    }

    protected function generatePaymentReference(): string
    {
        return 'PAY-' . strtoupper(Str::random(10));
    }

    /**
     * @return array<string,mixed>
     */
    protected function staySummary(Reservation $reservation): array
    {
        $nights = max(1, $reservation->check_in_date->diffInDays($reservation->check_out_date));
        $nightlyRate = (float) $reservation->nightlyRateTotal();
        $folio = $this->activeFolio($reservation, createIfMissing: true);
        $folio->loadMissing(['charges', 'payments']);
        $chargesTotal = (float) $folio->charges->sum('total_amount');
        $paymentsTotal = (float) $folio->payments->sum('amount');

        return [
            'code' => $reservation->code,
            'guest' => $reservation->customer?->name ?? 'Guest profile missing',
            'nights' => $nights,
            'nightly_rate' => $nightlyRate,
            'room_total' => round($nights * $nightlyRate, 2),
            'guest_count' => max(1, (int) ($reservation->adults + $reservation->children)),
            'folio' => [
                'id' => $folio->id,
                'status' => $folio->status ?? 'Open',
                'charges_total' => round($chargesTotal, 2),
                'payments_total' => round($paymentsTotal, 2),
                'blocked_codes' => $this->blockedCharges($folio, $this->chargeOptions),
            ],
        ];
    }

    protected function activeFolio(Reservation $reservation, bool $createIfMissing = false): Folio
    {
        $reservation->loadMissing('folios');

        $folio = $reservation->folios->firstWhere('status', 'Open')
            ?? $reservation->folios->first();

        if (! $folio && $createIfMissing) {
            $folioNo = sprintf('%s-F-%04d', $reservation->code, $reservation->incremental_no ?? $reservation->id);
            $folio = Folio::create([
                'reservation_id' => $reservation->id,
                'folio_no' => $folioNo,
                'status' => 'Open',
            ]);
            $reservation->load('folios');
        }

        return $folio ?? tap(new Folio())->forceFill(['status' => 'Closed']);
    }

    protected function refreshSummaryForSelection(?int $reservationId = null): void
    {
        $this->ensureContext();

        $reservationId = $reservationId ?? $this->reservationId;

        if (! $reservationId) {
            $this->refreshBlockedChargeCodes();
            return;
        }

        $reservation = Reservation::where('hotel_id', $this->hotelId)
            ->with(['customer', 'rooms.roomType', 'folios.charges', 'folios.payments'])
            ->find($reservationId);

        if (! $reservation) {
            $this->refreshBlockedChargeCodes();
            return;
        }

        $this->summaryLookup[$reservation->id] = $this->staySummary($reservation);
        $this->summaryLookup = $this->summaryLookup;
        $this->refreshBlockedChargeCodes();
    }

    /**
     * @param array<int,array<string,mixed>> $options
     * @return array<string,bool>
     */
    protected function blockedCharges(Folio $folio, array $options): array
    {
        $blocked = [];

        foreach ($options as $option) {
            $code = $option['code'] ?? 'custom';

            if ($code === 'custom') {
                $blocked[$code] = false;
                continue;
            }

            $blocked[$code] = $this->hasExistingCharge($folio, $option['label'] ?? '');
        }

        return $blocked;
    }

    protected function hasExistingCharge(Folio $folio, string $label): bool
    {
        if (! $label) {
            return false;
        }

        $folio->loadMissing('charges');

        $needle = mb_strtolower($label);
        $onlineVariant = mb_strtolower('Online booking: ' . $label);

        return $folio->charges
            ->pluck('description')
            ->map(fn ($d) => mb_strtolower((string) $d))
            ->contains(fn ($desc) => $desc === $needle || $desc === $onlineVariant);
    }

    protected function refreshBlockedChargeCodes(): void
    {
        $summary = $this->selectedSummary;
        $this->blockedChargeCodes = $summary['folio']['blocked_codes'] ?? [];
    }

    protected function ensureContext(): void
    {
        if ($this->hotelId && $this->staffId && $this->hotelTimezone && $this->viewerTimezone) {
            return;
        }

        $staff = auth('staff')->user();
        abort_unless($staff, 403);

        $this->hotelId = $staff->hotel_id;
        $this->staffId = $staff->id;
        $this->hotelTimezone = $this->normalizeTimezone($staff->hotel->timezone ?? null) ?? config('app.timezone');

        $cookieTz = request()->cookie('viewer_timezone');
        $decodedTz = $cookieTz ? urldecode($cookieTz) : null;
        $cookieTimezone = $this->sanitizeTimezone($decodedTz ?: $cookieTz);

        $this->viewerTimezone = $this->normalizeTimezone($cookieTimezone ?? $this->hotelTimezone) ?? config('app.timezone');
    }

    protected function normalizeTimezone($tz): ?string
    {
        if ($tz instanceof \App\Models\Timezone) {
            return $this->sanitizeTimezone($tz->timezone);
        }

        return $this->sanitizeTimezone($tz);
    }

    protected function sanitizeTimezone($tz): ?string
    {
        if (! is_string($tz)) {
            return null;
        }

        $tz = trim($tz);

        if ($tz === '' || $tz === 'null' || $tz === 'undefined') {
            return null;
        }

        if (! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return null;
        }

        return $tz;
    }

    protected function dateForViewer($date): ?string
    {
        if (! $date) {
            return null;
        }

        return Carbon::parse($date, $this->hotelTimezone ?? config('app.timezone'))
            ->setTimezone($this->viewerTimezone ?? config('app.timezone'))
            ->toDateString();
    }

    protected function formatForViewer($date): ?string
    {
        $converted = $this->dateForViewer($date);

        return $converted ? Carbon::parse($converted)->format('M d, Y') : null;
    }

    protected function viewerTodayDate(): string
    {
        return now($this->viewerTimezone ?? config('app.timezone'))->toDateString();
    }

    protected function hotelDateForViewerDate(string $viewerDate): string
    {
        $viewerTimezone = $this->viewerTimezone ?? config('app.timezone');
        $hotelTimezone = $this->hotelTimezone ?? config('app.timezone');

        return Carbon::parse($viewerDate, $viewerTimezone)
            ->setTimezone($hotelTimezone)
            ->toDateString();
    }

    public function updateViewerTimezone($payload = null): void
    {
        $tz = is_array($payload) ? ($payload['timezone'] ?? null) : $payload;

        $normalized = $this->normalizeTimezone($tz);

        if (! $normalized || $normalized === $this->viewerTimezone) {
            return;
        }

        $this->viewerTimezone = $normalized;
        $this->loadOptions();
    }

    protected function cleanText($value, int $maxLength = 1000): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }
}
