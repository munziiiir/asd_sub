<?php

namespace App\Livewire\Staff\CheckIo;

use App\Events\ReservationCheckedIn;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CheckInForm extends Component
{
    protected $casts = [
        'reservationId' => 'integer',
        'identityVerified' => 'boolean',
    ];

    public ?int $reservationId = null;

    public bool $identityVerified = true;

    public string $identityDocumentType = '';

    public string $identityDocumentNumber = '';

    public string $identityNotes = '';

    public string $remarks = '';

    protected $listeners = ['viewer-timezone-detected' => 'updateViewerTimezone'];

    /** @var array<int,array<string,mixed>> */
    public array $arrivalOptions = [];

    protected ?int $hotelId = null;

    protected ?int $staffId = null;

    protected ?string $hotelTimezone = null;

    public ?string $viewerTimezone = null;

    public function mount(): void
    {
        $this->ensureContext();
        $this->identityVerified = true;

        $this->loadOptions();
    }

    public function render()
    {
        return view('livewire.staff.check-io.check-in-form');
    }

    public function save()
    {
        $data = $this->validate($this->rules());

        $this->ensureContext();

        $reservation = Reservation::where('hotel_id', $this->hotelId)
            ->where('id', $this->reservationId)
            ->firstOrFail();

        if (! in_array($reservation->status, ['Pending', 'Confirmed'], true)) {
            $this->addError('reservationId', 'Only pending or confirmed reservations can be checked in.');

            return;
        }

        $rooms = $reservation->rooms;
        $room = $rooms->first();

        if (! $room) {
            $this->addError('reservationId', 'Assign a room to this reservation before processing check-in.');

            return;
        }

        $checkIn = $reservation->checkIns()->create([
            'room_id' => $room->id,
            'handled_by' => $this->staffId,
            'checked_in_at' => now(),
            'identity_verified' => (bool) $data['identityVerified'],
            'identity_document_type' => $this->cleanText($data['identityDocumentType'] ?? null),
            'identity_document_number' => $this->cleanText($data['identityDocumentNumber'] ?? null),
            'identity_notes' => $this->cleanText($data['identityNotes'] ?? null, 1000),
            'remarks' => $this->cleanText($data['remarks'] ?? null, 1000),
        ]);

        $reservation->update(['status' => 'CheckedIn']);
        $reservation->rooms()->update(['status' => 'Occupied']);

        ReservationCheckedIn::dispatch($reservation->fresh(), $checkIn);

        session()->flash('status', "Reservation {$reservation->code} checked in.");

        \App\Support\AuditLogger::log('checkin.completed', [
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
        ], true, $reservation);

        return redirect()->route('staff.check-io.index');
    }

    protected function loadOptions(): void
    {
        $hotelToday = now($this->hotelTimezone ?? config('app.timezone'))->toDateString();

        $this->arrivalOptions = Reservation::with(['customer', 'rooms'])
            ->where('hotel_id', $this->hotelId)
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->whereDate('check_in_date', $hotelToday)
            ->orderBy('check_in_date')
            ->get()
            ->map(function (Reservation $reservation) {
                return [
                    'id' => $reservation->id,
                    'code' => $reservation->code,
                    'guest' => $reservation->customer->name ?? 'Guest profile missing',
                    'check_in' => $this->formatForViewer($reservation->check_in_date),
                    'room' => $reservation->roomNumberLabel(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    protected function rules(): array
    {
        $this->ensureContext();

        return [
            'reservationId' => [
                'required',
                Rule::exists('reservations', 'id')->where(fn ($query) => $query
                    ->where('hotel_id', $this->hotelId)
                    ->whereIn('status', ['Pending', 'Confirmed'])),
            ],
            'identityVerified' => ['accepted'],
            'identityDocumentType' => ['nullable', 'string', 'max:100'],
            'identityDocumentNumber' => ['nullable', 'string', 'max:120'],
            'identityNotes' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
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

    protected function cleanText($value, int $maxLength = 255): ?string
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
