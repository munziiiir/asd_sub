<div class="relative w-full" style="max-width: {{ $maxWidth }};" wire:click.away="closeDropdown">
    @if($inputName)
        <input type="hidden" name="{{ $inputName }}" value="{{ $value }}">
    @endif
    <label class="relative {{ $inputClasses }}">
        {{-- <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-base-content/60"> --}}
            <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g stroke-linejoin="round"stroke-linecap="round"stroke-width="2.5"fill="none"stroke="currentColor"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></g></svg>
        {{-- </span> --}}

        <input
            type="search"
            placeholder="{{ $placeholder }}"
            wire:model.live.debounce.300ms="search"
            wire:focus="openDropdown"
            autocomplete="off"
        />
    </label>

    @if($dropdownOpen)
        <div class="absolute left-0 right-0 z-20 mt-2">
            <div class="{{ $dropdownClasses }} max-h-[250px] overflow-y-auto">
                @if(empty($results))
                    <div class="px-4 py-3 text-sm text-base-content/70">
                        No matches found.
                    </div>
                @else
                    <ul class="menu w-full">
                        @foreach ($results as $result)
                            <li>
                                <button
                                    type="button"
                                    class="flex w-full flex-col items-start gap-0 rounded-none px-4 py-2 text-left hover:bg-base-200"
                                    wire:click="select('{{ $result['id'] }}')"
                                >
                                    <span class="font-semibold text-base-content">{{ $result['label'] }}</span>
                                    @if(! empty($result['subLabel']))
                                        <span class="text-sm text-base-content/70">
                                            {{ $result['subLabel'] }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
