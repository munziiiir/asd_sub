<x-admin.layout title="Edit Staff User">
    <div class="breadcrumbs mb-4 text-sm">
        <ul>
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.staffusers.index') }}">Staff Users</a></li>
            <li>Edit</li>
        </ul>
    </div>

    <div class="card bg-base-200 shadow">
        <div class="card-body space-y-4">
            <h1 class="card-title">Edit staff user</h1>
            <form method="POST" action="{{ route('admin.staffusers.update', $staffUser) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="label mb-1">
                            <span class="label-text font-semibold">Hotel</span>
                        </label>
                        <livewire:inputs.searchable-select
                            wire:key="hotel-select-edit-{{ $staffUser->id }}"
                            input-name="hotel_id"
                            :value="old('hotel_id', $staffUser->hotel_id)"
                            model="App\\Models\\Hotel"
                            value-field="id"
                            :search-fields="['name','code']"
                            :display-fields="['name','code']"
                            :show-all-when-empty="true"
                            placeholder="Search hotel"
                            :max-results="12"
                            input-classes="input input-bordered w-full"
                            dropdown-classes="bg-base-100 border border-base-200 rounded-xl shadow-lg w-full"
                            :clearable="false"
                        />
                        @error('hotel_id')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label mb-1" for="name">
                            <span class="label-text font-semibold">Name</span>
                        </label>
                        <input id="name" name="name" type="text" value="{{ old('name', $staffUser->name) }}" required class="input input-bordered w-full">
                        @error('name')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-1" for="email">
                            <span class="label-text font-semibold">Email</span>
                        </label>
                        <input id="email" name="email" type="email" value="{{ old('email', $staffUser->email) }}" required class="input input-bordered w-full">
                        @error('email')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label mb-1" for="role">
                            <span class="label-text font-semibold">Role</span>
                        </label>
                        <select id="role" name="role" class="select select-bordered w-full" required>
                            <option value="">Select role</option>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $staffUser->role) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-1" for="employment_status">
                            <span class="label-text font-semibold">Employment status</span>
                        </label>
                        <select id="employment_status" name="employment_status" class="select select-bordered w-full" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('employment_status', $staffUser->employment_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('employment_status')
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

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('admin.staffusers.index') }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
