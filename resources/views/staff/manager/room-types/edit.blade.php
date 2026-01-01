<x-layouts.app.base :title="'Edit Room Type'">
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.manager.room-types.index') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to list
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-4xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <a href="{{ route('staff.manager.room-types.index') }}" class="link">Rates & availability</a>
            <span class="text-base-content/50">→</span>
            <span>Edit</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-4xl px-4 space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Edit room type</h1>
                    <p class="text-sm text-base-content/70">Update {{ $roomType->name }} for {{ $hotelName ?? 'this hotel' }}.</p>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <h2 class="card-title">Room type details</h2>
                    <p class="text-sm text-base-content/70">Keep capacity and pricing accurate. Switch the active rate when moving between seasons.</p>

                    <form method="POST" action="{{ route('staff.manager.room-types.update', $roomType) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="label mb-1" for="name">
                                    <span class="label-text font-semibold">Name</span>
                                </label>
                                <input id="name" name="name" type="text" value="{{ old('name', $roomType->name) }}" class="input input-bordered w-full" required>
                                @error('name')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="label mb-1" for="base_occupancy">
                                    <span class="label-text font-semibold">Base occupancy</span>
                                </label>
                                <input id="base_occupancy" name="base_occupancy" type="number" min="1" max="40" value="{{ old('base_occupancy', $roomType->base_occupancy) }}" class="input input-bordered w-full" required>
                                @error('base_occupancy')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="label mb-1" for="max_adults">
                                    <span class="label-text font-semibold">Max adults</span>
                                </label>
                                <input id="max_adults" name="max_adults" type="number" min="1" max="40" value="{{ old('max_adults', $roomType->max_adults) }}" class="input input-bordered w-full" required>
                                @error('max_adults')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="label mb-1" for="max_children">
                                    <span class="label-text font-semibold">Max children</span>
                                </label>
                                <input id="max_children" name="max_children" type="number" min="0" max="20" value="{{ old('max_children', $roomType->max_children) }}" class="input input-bordered w-full" required>
                                @error('max_children')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="label mb-1" for="active_rate">
                                    <span class="label-text font-semibold">Active rate</span>
                                </label>
                                <select id="active_rate" name="active_rate" class="select select-bordered w-full" required>
                                    <option value="off_peak" @selected(old('active_rate', $roomType->active_rate) === 'off_peak')>Off-peak price active</option>
                                    <option value="peak" @selected(old('active_rate', $roomType->active_rate) === 'peak')>Peak price active</option>
                                </select>
                                @error('active_rate')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="label mb-1" for="price_off_peak">
                                    <span class="label-text font-semibold">Off-peak price (per night)</span>
                                </label>
                                <div class="join w-full">
                                    <span class="join-item btn btn-ghost">£</span>
                                    <input id="price_off_peak" name="price_off_peak" type="number" step="0.01" min="0" value="{{ old('price_off_peak', $roomType->price_off_peak) }}" class="join-item input input-bordered w-full" required>
                                </div>
                                @error('price_off_peak')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="label mb-1" for="price_peak">
                                    <span class="label-text font-semibold">Peak price (per night)</span>
                                </label>
                                <div class="join w-full">
                                    <span class="join-item btn btn-ghost">£</span>
                                    <input id="price_peak" name="price_peak" type="number" step="0.01" min="0" value="{{ old('price_peak', $roomType->price_peak) }}" class="join-item input input-bordered w-full" required>
                                </div>
                                @error('price_peak')
                                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <a href="{{ route('staff.manager.room-types.index') }}" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
