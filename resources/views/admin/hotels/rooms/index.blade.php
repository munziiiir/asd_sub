<x-admin.layout :title="'Rooms — ' . $hotel->name">
    @php
        $statusMeta = [
            'available' => ['label' => 'Available', 'class' => 'badge-success'],
            'occupied' => ['label' => 'Occupied', 'class' => 'badge-error'],
            'cleaning' => ['label' => 'Cleaning', 'class' => 'badge-warning'],
            'oos' => ['label' => 'Out of service', 'class' => 'badge-ghost'],
        ];
    @endphp

    <div class="breadcrumbs mb-4 text-sm">
        <ul>
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.hotels.index') }}">Hotels</a></li>
            <li>Rooms</li>
        </ul>
    </div>

    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-sm uppercase tracking-widest text-primary font-semibold">{{ $hotel->code }}</p>
            <h1 class="text-3xl font-bold">Rooms — {{ $hotel->name }}</h1>
            <p class="text-base-content/70 mt-1">Manage rooms for this hotel.</p>
        </div>
        <a href="{{ route('admin.hotels.rooms.create', $hotel) }}" class="btn btn-primary">Add room</a>
    </div>

    <form method="GET" action="{{ route('admin.hotels.rooms.index', $hotel) }}" class="mb-4 bg-base-200 shadow rounded-box p-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:max-w-2xl">
            <input
                type="search"
                name="search"
                value="{{ $search ?? '' }}"
                placeholder="Search rooms by number, floor, type, or status"
                class="input input-bordered w-full"
            />
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            @if ($search)
                <a href="{{ route('admin.hotels.rooms.index', $hotel) }}" class="btn btn-sm btn-ghost">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto bg-base-200 shadow rounded-box">
        <table class="table">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Type</th>
                    <th>Floor</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rooms as $room)
                    <tr data-highlight-id="{{ $room->id }}" class="transition-colors duration-700">
                        <td class="font-semibold">{{ $room->number }}</td>
                        <td>{{ $room->roomType?->name ?? '—' }}</td>
                        <td>{{ $room->floor ?? '—' }}</td>
                        <td>
                            @php $badge = $statusMeta[$room->status] ?? ['label' => ucfirst($room->status), 'class' => 'badge-ghost']; @endphp
                            <span class="badge badge-sm {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        </td>
                        <td class="flex gap-2">
                            <a href="{{ route('admin.hotels.rooms.edit', [$hotel, $room]) }}" class="btn btn-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.hotels.rooms.destroy', [$hotel, $room]) }}" onsubmit="return confirm('Delete this room?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-6">No rooms added yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rooms->links() }}
    </div>
</x-admin.layout>
