<?php

namespace App\Livewire;

use App\Models\Charge;
use App\Models\CustomerUser;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\ChargeCatalog;
use App\Support\ReservationFolioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BookingPayment extends Component
{
    public Reservation $reservation;
    public ?CustomerUser $customer = null;

    /** @var array<int,array<string,mixed>> */
    public array $chargeOptions = [];

    /**
     * @var array<string,array{selected:bool,qty:int}>
     */
    public array $extras = [];

    public string $cardOption = 'saved';
    public string $card_number = '';
    public string $expiry = '';
    public string $cvv = '';
    public string $card_brand = '';
    public string $card_last_four = '';
    public ?int $card_exp_month = null;
    public ?int $card_exp_year = null;

    public bool $processing = false;
    public bool $success = false;
    public ?string $errorMessage = null;
    public ?string $statusMessage = null;
    public ?string $redirectUrl = null;

    public function mount(Reservation $reservation): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $this->customer = CustomerUser::firstOrCreate(
            ['user_id' => $user->id],
            ['name' => $user->name, 'email' => $user->email]
        );

        abort_unless($reservation->customer_id === $this->customer->id, 403);

        $this->reservation = $reservation->loadMissing([
            'hotel.country',
            'reservationRooms.room.roomType',
            'occupants',
            'customer',
            'folios.charges',
            'folios.payments',
        ]);

        app(ReservationFolioService::class)->ensureOpenFolio($this->reservation);

        $this->card_brand = (string) ($this->customer->card_brand ?? '');
        $this->card_last_four = (string) ($this->customer->card_last_four ?? '');
        $this->card_exp_month = $this->customer->card_exp_month;
        $this->card_exp_year = $this->customer->card_exp_year;
        $this->cardOption = $this->hasSavedCard() ? 'saved' : 'new';

        $this->chargeOptions = ChargeCatalog::options();
        foreach ($this->chargeOptions as $option) {
            if ($option['code'] === 'custom') {
                continue;
            }
            $this->extras[$option['code']] = ['selected' => false, 'qty' => 1];
        }

        if ($this->reservation->status === 'Confirmed') {
            $this->success = true;
            $this->redirectUrl = route('bookings.show', $this->reservation);
            $this->statusMessage = 'This booking is already confirmed.';
        }
    }

    public function updatedCardNumber(): void
    {
        $digits = preg_replace('/\\D+/', '', $this->card_number);
        $this->card_number = $digits;
        $this->card_brand = $this->detectBrandFromNumber($digits);
    }

    public function updatedExtras($value = null, $key = null): void
    {
        foreach ($this->extras as $code => $meta) {
            $this->extras[$code]['qty'] = $this->totalGuests;
            $this->extras[$code]['selected'] = (bool) ($meta['selected'] ?? false);
        }
    }

    public function pay(): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        if ($this->success) {
            return;
        }

        $rules = [
            'cardOption' => ['required', Rule::in(['saved', 'new'])],
            'extras' => ['array'],
        ];

        if ($this->cardOption === 'new') {
            $rules = array_merge($rules, [
                'card_number' => ['required', 'string', 'regex:/^[0-9]{12,19}$/'],
                'expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\\/(\\d{2})$/'],
                'cvv' => ['required', 'string', 'regex:/^[0-9]{3,4}$/'],
            ]);
        }

        $this->validate($rules);

        if ($this->cardOption === 'saved' && ! $this->hasSavedCard()) {
            $this->addError('cardOption', 'Add a card to continue.');
            return;
        }

        $this->processing = true;

        try {
            DB::transaction(function () {
                $reservation = Reservation::lockForUpdate()->with(['rooms'])->find($this->reservation->id);
                if (! $reservation || $reservation->customer_id !== $this->customer?->id) {
                    throw new \RuntimeException('Reservation not found.');
                }

                if ($reservation->status === 'Cancelled') {
                    throw new \RuntimeException('This reservation was cancelled.');
                }

                if ($reservation->status === 'Confirmed') {
                    $this->success = true;
                    $this->redirectUrl = route('bookings.show', $reservation);
                    return;
                }

                if ($this->cardOption === 'new') {
                    [$month, $year] = $this->parseExpiry($this->expiry);
                    $brand = $this->card_brand ?: $this->detectBrandFromNumber($this->card_number);
                    $lastFour = substr($this->card_number, -4);

                    $this->customer?->forceFill([
                        'card_brand' => $brand,
                        'card_last_four' => $lastFour,
                        'card_exp_month' => $month,
                        'card_exp_year' => $year,
                        'card_number_hash' => Hash::make($this->card_number),
                        'card_cvv_hash' => Hash::make($this->cvv),
                    ])->save();

                    $this->card_brand = $brand;
                    $this->card_last_four = $lastFour;
                    $this->card_exp_month = $month;
                    $this->card_exp_year = $year;
                    $this->cardOption = 'saved';
                }

                $reservation->loadMissing(['folios.charges', 'folios.payments']);

                $folioService = app(ReservationFolioService::class);
                $folioService->syncRoomCharges($reservation, 'online payment');
                if ($reservation->status !== 'NoShow') {
                    $folioService->syncOnlineExtrasFromSelections($reservation, $this->extras, 'online payment');
                }

                $folio = $folioService->ensureOpenFolio($reservation);
                $folioService->normalizeOverpayment($folio, 'online payment refund');

                if ($reservation->status === 'NoShow') {
                    $chargesTotal = round($folioService->chargesTotal($folio), 2);
                    $paid = round($folioService->paymentsTotal($folio), 2);
                    $balanceDue = max(0, round($chargesTotal - $paid, 2));

                    if ($balanceDue <= 0.01) {
                        $this->success = true;
                        $this->redirectUrl = route('bookings.show', $reservation);
                        return;
                    }

                    Payment::create([
                        'folio_id' => $folio->id,
                        'method' => $this->paymentMethodLabel(),
                        'amount' => $balanceDue,
                        'txn_ref' => $this->fakePaymentReference(),
                        'paid_at' => now(),
                    ]);

                    $this->success = true;
                    $this->redirectUrl = route('bookings.show', $reservation);
                    return;
                }

                $requiredDeposit = $folioService->requiredDeposit($reservation);
                $paid = round($folioService->paymentsTotal($folio), 2);
                $depositDueNow = max(0, round($requiredDeposit - $paid, 2));

                if ($depositDueNow <= 0.01) {
                    $folioService->enforceDepositStatus($reservation);
                    $this->success = true;
                    $this->redirectUrl = route('bookings.show', $reservation);
                    return;
                }

                Payment::create([
                    'folio_id' => $folio->id,
                    'method' => $this->paymentMethodLabel(),
                    'amount' => $depositDueNow,
                    'txn_ref' => $this->fakePaymentReference(),
                    'paid_at' => now(),
                ]);

                $folioService->enforceDepositStatus($reservation);

                $this->reservation = $reservation->fresh([
                    'hotel.country',
                    'reservationRooms.room.roomType',
                    'occupants',
                    'customer',
                    'folios.charges',
                    'folios.payments',
                ]);
            });

            $this->success = true;
            $this->statusMessage = 'Payment successful! Redirecting you to your booking.';
            $this->redirectUrl = route('bookings.show', $this->reservation);
            $this->dispatch('payment-completed', url: $this->redirectUrl);
            \App\Support\AuditLogger::log('payment.processed', [
                'reservation_id' => $this->reservation->id,
                'amount' => $this->dueNow,
                'card_brand' => $this->card_brand ?: $this->customer?->card_brand,
            ], true, $this->reservation);
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'We could not process your payment. Please try again.';
            \App\Support\AuditLogger::log('payment.failed', [
                'reservation_id' => $this->reservation->id,
                'amount' => $this->dueNow,
            ], false, $this->reservation);
        } finally {
            $this->processing = false;
            $this->card_number = '';
            $this->cvv = '';
        }
    }

    public function getNightsProperty(): int
    {
        $checkIn = $this->reservation->check_in_date;
        $checkOut = $this->reservation->check_out_date;

        if (! $checkIn || ! $checkOut) {
            return 1;
        }

        return max(1, $checkIn->diffInDays($checkOut));
    }

    public function getRoomTotalProperty(): float
    {
        return round($this->reservation->nightlyRateTotal() * $this->nights, 2);
    }

    /**
     * Required deposit for online bookings.
     *
     * Business rule: only take the first night's room total (excluding add-ons).
     */
    public function getAmountDueNowProperty(): float
    {
        return max(0, round($this->reservation->nightlyRateTotal(), 2));
    }

    public function getPaymentsTotalProperty(): float
    {
        $folio = $this->activeFolio($this->reservation, createIfMissing: false);

        if (! $folio || ! $folio->exists) {
            return 0.0;
        }

        $folio->loadMissing('payments');

        return round((float) $folio->payments->sum('amount'), 2);
    }

    public function getDepositDueNowProperty(): float
    {
        return max(0, round($this->amountDueNow - $this->paymentsTotal, 2));
    }

    public function getDueNowProperty(): float
    {
        return $this->reservation->status === 'NoShow'
            ? $this->remainingBalance
            : $this->depositDueNow;
    }

    public function getExtrasTotalProperty(): float
    {
        return round(array_sum(array_map(function ($extra) {
            return (float) $extra['amount'] * (int) $extra['qty'];
        }, $this->selectedExtras())), 2);
    }

    public function getGrandTotalProperty(): float
    {
        return round($this->roomTotal + $this->extrasTotal, 2);
    }

    public function getRemainingBalanceProperty(): float
    {
        return max(0, round($this->grandTotal - $this->paymentsTotal, 2));
    }

    public function getTotalGuestsProperty(): int
    {
        return max(1, (int) ($this->reservation->adults ?? 0) + (int) ($this->reservation->children ?? 0));
    }

    public function render()
    {
        return view('livewire.booking-payment', [
            'extrasList' => $this->selectedExtras(),
        ]);
    }

    /**
     * @return array<int,array{code:string,label:string,amount:float,qty:int}>
     */
    private function selectedExtras(): array
    {
        return collect($this->chargeOptions)
            ->filter(fn ($opt) => $opt['code'] !== 'custom')
            ->filter(fn ($opt) => (bool) ($this->extras[$opt['code']]['selected'] ?? false))
            ->map(function ($opt) {
                $qty = $this->totalGuests;
                return [
                    'code' => $opt['code'],
                    'label' => $opt['label'],
                    'amount' => (float) $opt['amount'],
                    'qty' => $qty,
                ];
            })
            ->values()
            ->all();
    }

    private function hasSavedCard(): bool
    {
        return $this->card_last_four !== '';
    }

    private function paymentMethodLabel(): string
    {
        if ($this->cardOption === 'new') {
            $brand = $this->card_brand ?: $this->detectBrandFromNumber($this->card_number);
            $lastFour = $this->card_number ? ' ••••' . substr($this->card_number, -4) : '';

            return trim(($brand ?: 'Card') . $lastFour);
        }

        $brand = $this->card_brand ?: ($this->customer?->card_brand ?? 'Card');
        $lastFour = $this->card_last_four ?: ($this->customer?->card_last_four ?? '');
        $suffix = $lastFour ? ' ••••' . $lastFour : '';

        return trim($brand . $suffix);
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

    private function checkoutCharges(): array
    {
        $today = now()->toDateString();
        $charges = [];
        $nights = $this->nights;
        $roomTotal = $this->roomTotal;

        $charges[] = [
            'post_date' => $today,
            'description' => 'Online booking: Room charges (' . $nights . ' night' . ($nights === 1 ? '' : 's') . ')',
            'qty' => 1,
            'unit_price' => $roomTotal,
            'tax_amount' => 0,
            'total_amount' => $roomTotal,
        ];

        foreach ($this->selectedExtras() as $extra) {
            $charges[] = [
                'post_date' => $today,
                'description' => 'Online booking: ' . $extra['label'],
                'qty' => $extra['qty'],
                'unit_price' => $extra['amount'],
                'tax_amount' => 0,
                'total_amount' => round($extra['amount'] * $extra['qty'], 2),
            ];
        }

        return $charges;
    }

    private function clearCheckoutCharges(Folio $folio): void
    {
        Charge::where('folio_id', $folio->id)
            ->where('description', 'like', 'Online booking:%')
            ->delete();
    }

    private function fakePaymentReference(): string
    {
        return 'PAY-' . strtoupper(Str::random(10));
    }

    private function detectBrandFromNumber(?string $number): string
    {
        $num = (string) $number;

        $patterns = [
            'Visa' => '/^4[0-9]{6,}$/',
            'Mastercard' => '/^(5[1-5][0-9]{5,}|2[2-7][0-9]{5,})/',
            'American Express' => '/^3[47][0-9]{5,}/',
            'Discover' => '/^6(?:011|5[0-9]{2})[0-9]{3,}/',
            'JCB' => '/^(?:2131|1800|35\\d{3})\\d{11}$/',
            'Diners Club' => '/^3(?:0[0-5]|[68][0-9])[0-9]{4,}/',
        ];

        foreach ($patterns as $brand => $regex) {
            if (preg_match($regex, $num)) {
                return $brand;
            }
        }

        return 'Card';
    }

    private function parseExpiry(string $value): array
    {
        [$m, $y] = explode('/', $value);

        return [(int) $m, 2000 + (int) $y];
    }
}
