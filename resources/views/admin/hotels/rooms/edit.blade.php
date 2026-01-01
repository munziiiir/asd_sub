<x-admin.layout :title="'Edit Room — ' . $hotel->name">
    <div class="breadcrumbs mb-4 text-sm">
        <ul>
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.hotels.index') }}">Hotels</a></li>
            <li><a href="{{ route('admin.hotels.rooms.index', $hotel) }}">Rooms</a></li>
            <li>Edit</li>
        </ul>
    </div>

    <div class="card bg-base-200 shadow">
        <div class="card-body space-y-4">
            <h1 class="card-title">Edit room — {{ $hotel->name }}</h1>
            <form method="POST" action="{{ route('admin.hotels.rooms.update', [$hotel, $room]) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-1" for="number">
                            <span class="label-text font-semibold">Room number</span>
                        </label>
                        <input id="number" name="number" type="text" value="{{ old('number', $room->number) }}" required class="input input-bordered w-full">
                        @error('number')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label mb-1" for="room_type_id">
                            <span class="label-text font-semibold">Room type</span>
                        </label>
                        <select id="room_type_id" name="room_type_id" class="select select-bordered w-full" required>
                            <option value="">Select type</option>
                            @foreach ($roomTypes as $roomType)
                                <option value="{{ $roomType->id }}" @selected(old('room_type_id', $room->room_type_id) == $roomType->id)>{{ $roomType->name }}</option>
                            @endforeach
                        </select>
                        @error('room_type_id')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-1" for="floor">
                            <span class="label-text font-semibold">Floor</span>
                        </label>
                        <input id="floor" name="floor" type="text" value="{{ old('floor', $room->floor) }}" class="input input-bordered w-full">
                        @error('floor')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label mb-1" for="status">
                            <span class="label-text font-semibold">Status</span>
                        </label>
                        <select id="status" name="status" class="select select-bordered w-full" required>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $room->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('admin.hotels.rooms.index', $hotel) }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
