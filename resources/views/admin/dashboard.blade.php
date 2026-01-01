<x-admin.layout title="Admin Dashboard">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm uppercase tracking-widest text-primary font-semibold">Admin</p>
            <h1 class="text-3xl font-bold">Dashboard</h1>
            <p class="text-base-content/70 mt-1">Manage admin accounts and hotel settings.</p>
        </div>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        <a href="{{ route('admin.users.index') }}" class="stat bg-base-200 shadow transition hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-box">
            <div class="stat-title">Admins</div>
            <div class="stat-value text-primary">{{ $stats['admins'] }}</div>
            <div class="stat-desc">Active + inactive</div>
        </a>
        <a href="{{ route('admin.hotels.index') }}" class="stat bg-base-200 shadow transition hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-box">
            <div class="stat-title">Hotels</div>
            <div class="stat-value text-primary">{{ $stats['hotels'] }}</div>
            <div class="stat-desc">Configured locations</div>
        </a>
        <a href="{{ route('admin.staffusers.index') }}" class="stat bg-base-200 shadow transition hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-box">
            <div class="stat-title">Staff Users</div>
            <div class="stat-value text-primary">{{ $stats['staff'] }}</div>
            <div class="stat-desc">Across all hotels</div>
        </a>
    </div>

    <div class="card bg-base-200 shadow">
        <div class="card-body">
            <h2 class="card-title">Quick actions</h2>
            <div class="flex flex-wrap gap-3">
                <a class="btn btn-outline" href="{{ route('admin.users.create') }}">Add admin</a>
                <a class="btn btn-outline" href="{{ route('admin.users.index') }}">Manage admins</a>
                <a class="btn btn-outline" href="{{ route('admin.hotels.create') }}">Add hotel</a>
                <a class="btn btn-outline" href="{{ route('admin.hotels.index') }}">Manage hotels</a>
                <a class="btn btn-outline" href="{{ route('admin.staffusers.create') }}">Add Staff</a>
                <a class="btn btn-outline" href="{{ route('admin.staffusers.index') }}">Manage Staff</a>
            </div>
        </div>
    </div>
</x-admin.layout>
