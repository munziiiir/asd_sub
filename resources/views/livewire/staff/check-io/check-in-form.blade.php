<div class="card bg-base-100 shadow">
    <form class="card-body space-y-6" wire:submit.prevent="save">
        <div>
            <h2 class="card-title">Check a guest in</h2>
            <p class="text-sm text-base-content/70">
                Confirm ID and assign a room before you hand over keys.
            </p>
        </div>

        <div class="grid gap-4">
            <label class="flex flex-col gap-1 w-full">
                <span class="label-text font-semibold">Reservation</span>
                <select class="select select-bordered" wire:model="reservationId" required>
                    <option value="">Select reservation…</option>
                    @foreach ($arrivalOptions as $option)
                        <option value="{{ $option['id'] }}">
                            {{ $option['code'] }} — {{ $option['guest'] }}
                        </option>
                    @endforeach
                </select>
                @if (empty($arrivalOptions))
                    <span class="text-sm text-base-content/70">
                        No upcoming arrivals are waiting for check-in.
                    </span>
                @endif
                @error('reservationId')
                    <span class="text-sm text-error">{{ $message }}</span>
                @enderror
            </label>

            @php
                $selectedArrival = collect($arrivalOptions)->firstWhere('id', $reservationId);
            @endphp
            @if ($selectedArrival)
                <div class="rounded-lg border border-dashed border-base-300 bg-base-100 p-4 text-sm">
                    <p class="font-semibold text-base-content">Assigned room(s)</p>
                    <p class="text-base-content/70">
                        {{ $selectedArrival['room'] ?? 'Room assignment pending' }}
                    </p>
                    <p class="mt-1 text-xs text-base-content/60">
                        Update the reservation if the room assignment needs to change.
                    </p>
                </div>
            @endif
        </div>

        <div class="space-y-2 rounded-lg border border-base-200 p-4">
            <label class="flex items-center gap-3">
                <input type="checkbox" class="checkbox checkbox-primary" wire:model="identityVerified">
                <span class="font-semibold">Government ID verified</span>
            </label>
            <p class="text-sm text-base-content/70">
                Record which document you reviewed and add any discrepancies for the audit log.
            </p>
            @error('identityVerified')
                <span class="text-sm text-error">{{ $message }}</span>
            @enderror

            <label class="flex flex-col gap-1 w-full">
                <span class="label-text font-semibold">Document type</span>
                <select class="select select-bordered" wire:model="identityDocumentType">
                    <option value="">Select document…</option>
                    <option value="Passport">Passport</option>
                    <option value="National ID">National ID</option>
                    <option value="Work Visa">Work Visa</option>
                </select>
                @error('identityDocumentType')
                    <span class="text-sm text-error">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex flex-col gap-1 w-full">
                <span class="label-text font-semibold">Document number</span>
                <input
                    type="text"
                    class="input input-bordered"
                    wire:model.lazy="identityDocumentNumber"
                    maxlength="120"
                    placeholder="e.g. passport number"
                >
                @error('identityDocumentNumber')
                    <span class="text-sm text-error">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex flex-col gap-1 w-full">
                <span class="label-text font-semibold">Verification notes</span>
                <textarea
                    class="textarea textarea-bordered min-h-16"
                    wire:model.lazy="identityNotes"
                    maxlength="1000"
                    placeholder="Additional verification notes…"
                ></textarea>
                @error('identityNotes')
                    <span class="text-sm text-error">{{ $message }}</span>
                @enderror
            </label>
        </div>

        <label class="flex flex-col gap-1 w-full">
            <span class="label-text font-semibold">Front-desk notes</span>
            <textarea
                class="textarea textarea-bordered min-h-20"
                wire:model.lazy="remarks"
                maxlength="1000"
                placeholder="Late arrival info, VIP instructions, etc."
            ></textarea>
            @error('remarks')
                <span class="text-sm text-error">{{ $message }}</span>
            @enderror
        </label>

        <div class="card-actions justify-end">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Complete check-in</span>
                <span wire:loading wire:target="save">Processing…</span>
            </button>
        </div>
    </form>
</div>
