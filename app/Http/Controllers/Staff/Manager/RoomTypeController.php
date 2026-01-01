<?php

namespace App\Http\Controllers\Staff\Manager;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use App\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    private int $perPage = 10;

    public function index(Request $request): View
    {
        $manager = $this->manager($request);

        $search = (string) $request->string('search');

        $query = RoomType::query()
            ->where('hotel_id', $manager->hotel_id)
            ->withCount('rooms');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        $roomTypes = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view('staff.manager.room-types.index', [
            'roomTypes' => $roomTypes,
            'filters' => ['search' => $search],
            'hotelName' => $manager->hotel?->name,
        ]);
    }

    public function create(Request $request): View
    {
        $manager = $this->manager($request);

        return view('staff.manager.room-types.create', [
            'hotelName' => $manager->hotel?->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = $this->manager($request);

        $roomType = RoomType::create(array_merge(
            ['hotel_id' => $manager->hotel_id],
            $this->validatedData($request)
        ));

        $page = $this->pageForRoomType($manager, $roomType);

        return redirect()
            ->route('staff.manager.room-types.index', ['page' => $page])
            ->with('status', 'Room type created.');
    }

    public function edit(Request $request, RoomType $roomType): View
    {
        $manager = $this->manager($request);
        $this->ensureRoomTypeForManager($manager, $roomType);

        return view('staff.manager.room-types.edit', [
            'roomType' => $roomType,
            'hotelName' => $manager->hotel?->name,
        ]);
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $manager = $this->manager($request);
        $this->ensureRoomTypeForManager($manager, $roomType);

        $roomType->update($this->validatedData($request));
        $page = $this->pageForRoomType($manager, $roomType);

        return redirect()
            ->route('staff.manager.room-types.index', ['page' => $page])
            ->with('status', 'Room type updated.');
    }

    private function manager(Request $request): StaffUser
    {
        $manager = $request->user('staff');

        abort_unless($manager && $manager->role === 'manager', 403);

        return $manager;
    }

    private function ensureRoomTypeForManager(StaffUser $manager, RoomType $roomType): void
    {
        if ($roomType->hotel_id !== $manager->hotel_id) {
            abort(404);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:40'],
            'max_children' => ['required', 'integer', 'min:0', 'max:20'],
            'base_occupancy' => ['required', 'integer', 'min:1', 'max:40'],
            'price_off_peak' => ['required', 'numeric', 'min:0'],
            'price_peak' => ['required', 'numeric', 'min:0'],
            'active_rate' => ['required', 'string', Rule::in(['off_peak', 'peak'])],
        ]);

        $capacity = (int) $data['max_adults'] + (int) $data['max_children'];

        if ($data['base_occupancy'] > $capacity) {
            throw ValidationException::withMessages([
                'base_occupancy' => 'Base occupancy cannot exceed total capacity.',
            ]);
        }

        return $data;
    }

    private function pageForRoomType(StaffUser $manager, RoomType $roomType): int
    {
        $base = RoomType::where('hotel_id', $manager->hotel_id);

        $beforeCount = (clone $base)
            ->where('name', '<', $roomType->name)
            ->count();

        $tieBreakerCount = (clone $base)
            ->where('name', $roomType->name)
            ->where('id', '<', $roomType->id)
            ->count();

        $position = $beforeCount + $tieBreakerCount;

        return (int) floor($position / $this->perPage) + 1;
    }
}
