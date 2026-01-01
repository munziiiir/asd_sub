<x-layouts.app.base :title="'Edit Front Desk Staff'">
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.manager.frontdesk-staff.index') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to list
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-3xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <a href="{{ route('staff.manager.frontdesk-staff.index') }}" class="link">Front desk staff</a>
            <span class="text-base-content/50">→</span>
            <span>Edit</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-3xl px-4 space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Edit front desk staff</h1>
                    <p class="text-sm text-base-content/70">Update access for {{ $staffUser->name }} at {{ $hotelName ?? 'this hotel' }}.</p>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <h2 class="card-title">Account details</h2>
                    <p class="text-sm text-base-content/70">This account remains scoped to your hotel and the front desk role.</p>

                    <form method="POST" action="{{ route('staff.manager.frontdesk-staff.update', $staffUser) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="label mb-1" for="name">
                                    <span class="label-text font-semibold">Full name</span>
                                </label>
                                <input id="name" name="name" type="text" value="{{ old('name', $staffUser->name) }}" class="input input-bordered w-full" required>
                                @error('name')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="label mb-1" for="email">
                                    <span class="label-text font-semibold">Email</span>
                                </label>
                                <input id="email" name="email" type="email" value="{{ old('email', $staffUser->email) }}" class="input input-bordered w-full" required>
                                @error('email')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="label mb-1" for="employment_status">
                                    <span class="label-text font-semibold">Employment status</span>
                                </label>
                                <select id="employment_status" name="employment_status" class="select select-bordered w-full" required>
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('employment_status', $staffUser->employment_status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('employment_status')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="label mb-1" for="password">
                                    <span class="label-text font-semibold">Password (optional)</span>
                                </label>
                                <input id="password" name="password" type="password" class="input input-bordered w-full" placeholder="Leave blank to keep current password">
                                @error('password')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="label mb-1" for="password_confirmation">
                                    <span class="label-text font-semibold">Confirm password</span>
                                </label>
                                <input id="password_confirmation" name="password_confirmation" type="password" class="input input-bordered w-full" placeholder="Match the password above">
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <a href="{{ route('staff.manager.frontdesk-staff.index') }}" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
