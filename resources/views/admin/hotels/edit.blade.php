<x-admin.layout title="Edit Hotel">
    <div class="breadcrumbs mb-4 text-sm">
        <ul>
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.hotels.index') }}">Hotels</a></li>
            <li>Edit</li>
        </ul>
    </div>

    <div class="card bg-base-200 shadow">
        <div class="card-body space-y-4">
            <h1 class="card-title">Edit hotel</h1>
            <form method="POST" action="{{ route('admin.hotels.update', $hotel) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-1" for="name">
                            <span class="label-text font-semibold">Name</span>
                        </label>
                        <input id="name" name="name" type="text" value="{{ old('name', $hotel->name) }}" required class="input input-bordered w-full">
                        @error('name')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label mb-1" for="code">
                            <span class="label-text font-semibold">Code</span>
                        </label>
                        <input id="code" name="code" type="text" value="{{ old('code', $hotel->code) }}" required class="input input-bordered w-full">
                        @error('code')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @php
                    $oldCountryCode = old('country_code', $countryCode);
                @endphp

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="label mb-1">
                            <span class="label-text font-semibold">Country</span>
                        </label>
                        <livewire:inputs.searchable-select
                            wire:key="country-select-edit-{{ $hotel->id }}"
                            input-name="country_code"
                            :value="$oldCountryCode"
                            model="App\\Models\\Country"
                            value-field="code"
                            :search-fields="['name','code']"
                            :display-fields="['name','code']"
                            :include-fields="['name']"
                            :show-all-when-empty="true"
                            placeholder="Search country"
                            :max-results="10"
                            input-classes="input input-bordered w-full"
                            dropdown-classes="bg-base-100 border border-base-200 rounded-xl shadow-lg w-full"
                        />
                        @error('country_code')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="label mb-1">
                            <span class="label-text font-semibold">Timezone</span>
                        </label>
                        <div data-role="timezone-select">
                            <livewire:inputs.searchable-select
                                wire:key="timezone-select-edit-{{ $hotel->id }}"
                                input-name="timezone_id"
                                :value="old('timezone_id', $hotel->timezone_id)"
                                model="App\\Models\\Timezone"
                                value-field="id"
                                :search-fields="['timezone']"
                                :display-fields="['timezone']"
                                :constraints="['country_code' => $oldCountryCode]"
                                :show-all-when-empty="true"
                                placeholder="Search timezone"
                                :max-results="12"
                                input-classes="input input-bordered w-full"
                                dropdown-classes="bg-base-100 border border-base-200 rounded-xl shadow-lg w-full"
                                :clearable="false"
                            />
                        </div>
                        @error('timezone')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('admin.hotels.index') }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>

<script>
    document.addEventListener('livewire:init', () => {
        let timezoneComponentId = null;
        const timezoneWrapper = document.querySelector('[data-role="timezone-select"]');

        const syncTimezoneConstraints = (countryCode) => {
            if (! timezoneComponentId) {
                timezoneComponentId = timezoneWrapper?.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            }
            if (timezoneComponentId) {
                Livewire.find(timezoneComponentId)?.call('setConstraints', { country_code: countryCode || null });
            }
        };

        Livewire.on('searchable-select-selected', (event) => {
            const { field, value, data } = event;
            if (field === 'country_code') {
                syncTimezoneConstraints(value);
            }
        });

        const initialCountry = "{{ $oldCountryCode }}";
        if (initialCountry) {
            syncTimezoneConstraints(initialCountry);
        }
    });
</script>
