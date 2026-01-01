<x-admin.layout title="Hotels">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-sm uppercase tracking-widest text-primary font-semibold">System settings</p>
            <h1 class="text-3xl font-bold">Hotels</h1>
            <p class="text-base-content/70 mt-1">Manage hotel locations and timezones.</p>
        </div>
        <a href="{{ route('admin.hotels.create') }}" class="btn btn-primary">Add hotel</a>
    </div>

    <form method="GET" action="{{ route('admin.hotels.index') }}" class="mb-4 bg-base-200 shadow rounded-box p-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:max-w-2xl">
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search hotels by name, code, country, or timezone"
                class="input input-bordered w-full"
            />
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            @if (request('search'))
                <a href="{{ route('admin.hotels.index') }}" class="btn btn-sm btn-ghost">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto bg-base-200 shadow rounded-box">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Country</th>
                    <th>Timezone</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hotels as $hotel)
                    <tr data-highlight-id="{{ $hotel->id }}" class="transition-colors duration-700">
                        <td class="font-semibold">{{ $hotel->name }}</td>
                        <td>{{ $hotel->code }}</td>
                        <td>{{ $hotel->country?->name ?? '—' }}</td>
                        <td>{{ $hotel->timezone?->timezone ?? '—' }}</td>
                        <td class="flex gap-2">
                            <a href="{{ route('admin.hotels.rooms.index', $hotel) }}" class="btn btn-xs">Rooms</a>
                            <a href="{{ route('admin.hotels.edit', $hotel) }}" class="btn btn-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.hotels.destroy', $hotel) }}" onsubmit="return confirm('Delete this hotel?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-6">No hotels configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $hotels->links() }}
    </div>
</x-admin.layout>
