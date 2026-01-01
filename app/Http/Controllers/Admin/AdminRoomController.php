<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\HandlesRowHighlighting;
use App\Http\Controllers\Admin\Concerns\HandlesSearch;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRoomController extends Controller
{
    use HandlesRowHighlighting;
    use HandlesSearch;

    private int $perPage = 10;

    /** @var array<string,string> */
    private array $statusOptions = [
        'available' => 'Available',
        'occupied' => 'Occupied',
        'cleaning' => 'Cleaning',
        'oos' => 'Out of service',
    ];

    public function index(Request $request, Hotel $hotel): View
    {
        $search = (string) $request->string('search');

        $query = Room::query()
            ->select('rooms.*')
            ->with('roomType')
            ->where('rooms.hotel_id', $hotel->id)
            ->leftJoin('room_types', 'room_types.id', '=', 'rooms.room_type_id');

        if ($search) {
            $this->applyFuzzySearch($query, $search, [
                'rooms.number',
                'rooms.floor',
                'rooms.status',
                'room_types.name',
            ]);
        }

        $rooms = $query
            ->orderBy('rooms.number')
            ->orderBy('rooms.id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view('admin.hotels.rooms.index', [
            'hotel' => $hotel,
            'rooms' => $rooms,
            'statusOptions' => $this->statusOptions,
            'search' => $search,
        ]);
    }

    public function create(Hotel $hotel): View
    {
        return view('admin.hotels.rooms.create', [
            'hotel' => $hotel,
            'roomTypes' => $this->roomTypes($hotel),
            'statusOptions' => $this->statusOptions,
        ]);
    }

    public function store(Request $request, Hotel $hotel): RedirectResponse
    {
        $room = Room::create($this->validatedData($request, $hotel));
        $page = $this->pageForRoom($hotel, $room);

        return $this->redirectWithHighlight(
            'admin.hotels.rooms.index',
            ['hotel' => $hotel, 'page' => $page],
            $room,
            'created',
            'Room created.'
        );
    }

    public function edit(Hotel $hotel, Room $room): View
    {
        $this->ensureRoomInHotel($room, $hotel);

        return view('admin.hotels.rooms.edit', [
            'hotel' => $hotel,
            'room' => $room,
            'roomTypes' => $this->roomTypes($hotel),
            'statusOptions' => $this->statusOptions,
        ]);
    }

    public function update(Request $request, Hotel $hotel, Room $room): RedirectResponse
    {
        $this->ensureRoomInHotel($room, $hotel);

        $room->update($this->validatedData($request, $hotel, $room));
        $page = $this->pageForRoom($hotel, $room);

        return $this->redirectWithHighlight(
            'admin.hotels.rooms.index',
            ['hotel' => $hotel, 'page' => $page],
            $room,
            'updated',
            'Room updated.'
        );
    }

    public function destroy(Hotel $hotel, Room $room): RedirectResponse
    {
        $this->ensureRoomInHotel($room, $hotel);

        $room->delete();

        return redirect()
            ->route('admin.hotels.rooms.index', $hotel)
            ->with('status', 'Room removed.');
    }

    /**
     * @return array<string,mixed>
     */
    protected function validatedData(Request $request, Hotel $hotel, ?Room $room = null): array
    {
        $data = $request->validate([
            'room_type_id' => [
                'required',
                Rule::exists('room_types', 'id')->where(fn ($query) => $query->where('hotel_id', $hotel->id)),
            ],
            'number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('rooms', 'number')
                    ->ignore($room?->id)
                    ->where(fn ($query) => $query->where('hotel_id', $hotel->id)),
            ],
            'floor' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'string', 'max:32', Rule::in(array_keys($this->statusOptions))],
        ]);

        return array_merge($data, ['hotel_id' => $hotel->id]);
    }

    /**
     * @return \Illuminate\Support\Collection<int,\App\Models\RoomType>
     */
    protected function roomTypes(Hotel $hotel)
    {
        return RoomType::where('hotel_id', $hotel->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function ensureRoomInHotel(Room $room, Hotel $hotel): void
    {
        if ($room->hotel_id !== $hotel->id) {
            abort(404);
        }
    }

    private function pageForRoom(Hotel $hotel, Room $room): int
    {
        $beforeCount = Room::where('hotel_id', $hotel->id)
            ->where('number', '<', $room->number)
            ->count();

        $tieBreakerCount = Room::where('hotel_id', $hotel->id)
            ->where('number', $room->number)
            ->where('id', '<', $room->id)
            ->count();

        $position = $beforeCount + $tieBreakerCount;

        return (int) floor($position / $this->perPage) + 1;
    }
}
