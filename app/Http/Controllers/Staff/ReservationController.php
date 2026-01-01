<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Support\ReservationFolioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user('staff');

        $reservations = Reservation::with(['customer'])
            ->where('hotel_id', $staff->hotel_id)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
            )
            ->when(
                $request->filled('code'),
                fn ($query) => $query->where('code', 'like', '%'.$request->string('code').'%')
            )
            ->orderByDesc('check_in_date')
            ->paginate(15)
            ->withQueryString();

        $statuses = $this->statusOptions();

        return view('staff.reservations.index', compact('reservations', 'statuses'));
    }


    public function create(Request $request): View
    {
        $hotelName = $request->user('staff')?->hotel?->name ?? 'Hotel';

        return view('staff.reservations.create', compact('hotelName'));
    }

    public function show(Request $request, Reservation $reservation): View
    {
        $staff = $request->user('staff');
        $this->authorizeReservation($reservation, $staff);

        $reservation->load(['customer', 'rooms.roomType', 'occupants']);
        $statuses = $this->statusOptions();
        $filterPayload = $this->rawFilters($request);
        $backQuery = $this->sanitizeFilters($filterPayload);

        return view('staff.reservations.show', [
            'reservation' => $reservation,
            'statuses' => $statuses,
            'backQuery' => $backQuery,
            'filterPayload' => $filterPayload,
        ]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $staff = $request->user('staff');
        $this->authorizeReservation($reservation, $staff);

        $status = $reservation->status;

        if ($status === 'CheckedOut') {
            return redirect()
                ->route('staff.reservations.show', array_merge([$reservation], $this->backQuery($request)))
                ->withErrors(['status' => 'Checked-out reservations cannot be modified.']);
        }

        $data = $this->validatedData($request, $reservation);

        $immutableFields = [];

        if ($status === 'CheckedIn') {
            $immutableFields = ['check_in_date', 'adults', 'children'];
        } elseif (now()->greaterThanOrEqualTo($reservation->check_in_date)) {
            $immutableFields = ['check_in_date', 'adults', 'children'];
        }

        foreach ($immutableFields as $field) {
            unset($data[$field]);
        }

        $reservation->update($data);

        $folioService = app(ReservationFolioService::class);
        $folioService->syncRoomCharges($reservation, 'staff edit');
        $folio = $folioService->ensureOpenFolio($reservation);
        $folioService->normalizeOverpayment($folio, 'staff edit refund');
        $folioService->enforceDepositStatus($reservation);

        return redirect()
            ->route('staff.reservations.show', array_merge([$reservation], $this->backQuery($request)))
            ->with('status', "Reservation {$reservation->code} updated.");
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $staff = $request->user('staff');
        $this->authorizeReservation($reservation, $staff);

        if (! in_array($reservation->status, ['Cancelled', 'CheckedOut'], true)) {
            app(ReservationFolioService::class)->applyCancellationPolicy($reservation, 'staff');
        }

        return $this->redirectBackToIndex($request, "Reservation {$reservation->code} cancelled.");
    }

    protected function validatedData(Request $request, Reservation $reservation): array
    {
        $rules = [
            'check_out_date' => ['required', 'date', 'after:' . optional($reservation->check_in_date)->toDateString()],
        ];

        if (now()->lessThan($reservation->check_in_date) && $reservation->status !== 'CheckedIn') {
            $rules['check_in_date'] = ['required', 'date', 'after_or_equal:today', 'before_or_equal:check_out_date'];
            $rules['adults'] = ['required', 'integer', 'min:0', 'max:20'];
            $rules['children'] = ['required', 'integer', 'min:0', 'max:20'];
        }

        return $request->validate($rules);
    }

    protected function statusOptions(): array
    {
        return [
            'Pending',
            'Confirmed',
            'CheckedIn',
            'CheckedOut',
            'Cancelled',
            'NoShow',
        ];
    }

    protected function statusOptionsFor(string $currentStatus): array
    {
        $statuses = $this->statusOptions();
        $state = strtolower($currentStatus);

        return match ($state) {
            'checkedin' => array_values(array_intersect($statuses, ['CheckedIn', 'CheckedOut'])),
            'checkedout' => ['CheckedOut'],
            default => $statuses,
        };
    }

    protected function backQuery(Request $request): array
    {
        return $this->sanitizeFilters($this->rawFilters($request));
    }

    protected function rawFilters(Request $request): array
    {
        $filters = $request->input('filters');

        if (! is_array($filters)) {
            $filters = $request->input('__filter');
        }

        if (! is_array($filters)) {
            $filters = $request->only(['status', 'code', 'page']);
            if (! $request->has('status')) {
                $filters['status'] = '__ALL__';
            }
        }

        return $filters;
    }

    protected function sanitizeFilters(array $filters): array
    {
        if (($filters['status'] ?? null) === '__ALL__') {
            unset($filters['status']);
        }

        return array_filter($filters, fn ($value) => filled($value));
    }

    protected function redirectBackToIndex(Request $request, string $message): RedirectResponse
    {
        return redirect()
            ->route('staff.reservations.index', $this->backQuery($request))
            ->with('status', $message);
    }

    protected function authorizeReservation(Reservation $reservation, $staff): void
    {
        abort_unless($staff && $reservation->hotel_id === $staff->hotel_id, 403);
    }
}
