<x-admin.layout title="Admin Users">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-sm uppercase tracking-widest text-primary font-semibold">Access</p>
            <h1 class="text-3xl font-bold">Admin Users</h1>
            <p class="text-base-content/70 mt-1">Manage who can access the admin console.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add admin</a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 bg-base-200 shadow rounded-box p-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:max-w-xl">
            <input
                type="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by name or username"
                class="input input-bordered w-full"
            />
        </div>
        <div class="flex flex-wrap items-center gap-2 md:justify-end">
            <select name="status" class="select select-sm select-bordered md:w-32">
                <option value="">Status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            @if (($filters['search'] ?? '') || ($filters['status'] ?? null))
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-ghost">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto bg-base-200 shadow rounded-box">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($admins as $admin)
                    <tr data-highlight-id="{{ $admin->id }}" class="transition-colors duration-700">
                        <td class="font-semibold">{{ $admin->name }}</td>
                        <td>{{ $admin->username }}</td>
                        <td>
                            @if ($admin->is_active)
                                <span class="badge badge-success badge-sm">Active</span>
                            @else
                                <span class="badge badge-ghost badge-sm">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $admin->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="flex gap-2">
                            <a href="{{ route('admin.users.edit', $admin) }}" class="btn btn-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.users.destroy', $admin) }}" onsubmit="return confirm('Delete this admin?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-6">No admin accounts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $admins->links() }}
    </div>
</x-admin.layout>
