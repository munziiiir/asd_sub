<x-admin.layout title="Edit Admin User">
    <div class="breadcrumbs mb-4 text-sm">
        <ul>
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.users.index') }}">Admin Users</a></li>
            <li>Edit</li>
        </ul>
    </div>

    <div class="card bg-base-200 shadow">
        <div class="card-body space-y-4">
            <h1 class="card-title">Edit admin user</h1>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-1" for="name">
                            <span class="label-text font-semibold">Name</span>
                        </label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="input input-bordered w-full">
                        @error('name')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label mb-1" for="username">
                            <span class="label-text font-semibold">Username</span>
                        </label>
                        <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required class="input input-bordered w-full">
                        @error('username')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-1" for="password">
                            <span class="label-text font-semibold">Password (leave blank to keep)</span>
                        </label>
                        <input id="password" name="password" type="password" class="input input-bordered w-full">
                        @error('password')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label mb-1" for="password_confirmation">
                            <span class="label-text font-semibold">Confirm Password</span>
                        </label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input input-bordered w-full">
                    </div>
                </div>

                <label class="label cursor-pointer w-fit gap-3">
                    <span class="label-text">Active</span>
                    <input type="checkbox" name="is_active" value="1" class="toggle toggle-primary" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
