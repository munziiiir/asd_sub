<x-admin.layout title="Staff Users">
    @php
        $statusMeta = [
            'active' => ['label' => 'Active', 'class' => 'badge-success'],
            'inactive' => ['label' => 'Inactive', 'class' => 'badge-ghost'],
            'on_leave' => ['label' => 'On leave', 'class' => 'badge-warning'],
        ];
    @endphp
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-sm uppercase tracking-widest text-primary font-semibold">Staff</p>
            <h1 class="text-3xl font-bold">Staff Users</h1>
            <p class="text-base-content/70 mt-1">Manage staff across hotels.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.staffusers.create') }}" class="btn btn-primary">Add staff</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.staffusers.index') }}" class="mb-4 bg-base-200 shadow rounded-box p-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:max-w-2xl">
            <input
                type="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by name, email, hotel, role, or status"
                class="input input-bordered w-full"
            />
        </div>
        <div class="flex flex-wrap md:flex-nowrap items-center gap-2 md:gap-3 md:justify-end">
            <select name="role" class="select select-sm select-bordered md:w-36">
                <option value="">Role</option>
                @foreach ($roles as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['role'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="select select-sm select-bordered md:w-40">
                <option value="">Employment</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            @if (($filters['search'] ?? '') || ($filters['role'] ?? null) || ($filters['status'] ?? null))
                <a href="{{ route('admin.staffusers.index') }}" class="btn btn-sm btn-ghost">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto bg-base-200 shadow rounded-box">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Hotel</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staffUsers as $staff)
                    <tr data-highlight-id="{{ $staff->id }}" class="transition-colors duration-700">
                        <td class="font-semibold">{{ $staff->name }}</td>
                        <td>{{ $staff->email }}</td>
                        <td>{{ $staff->hotel?->name ?? '—' }}</td>
                        <td>{{ $roles[$staff->role] ?? ($staff->role ? ucfirst($staff->role) : '—') }}</td>
                        <td>
                            @php
                                $status = $staff->employment_status;
                                $badge = $status ? ($statusMeta[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'class' => 'badge-ghost']) : null;
                            @endphp
                            @if ($badge)
                                <span class="badge badge-sm {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="flex gap-2">
                            <a href="{{ route('admin.staffusers.edit', $staff) }}" class="btn btn-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.staffusers.destroy', $staff) }}" onsubmit="return confirm('Delete this staff user?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-6">No staff users yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $staffUsers->links() }}
    </div>
</x-admin.layout>
